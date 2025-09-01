<?php
/**
 * Cron scheduler and runner for LLM Visibility Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Cron {

    /** Hook name for scheduled event */
    public const HOOK = 'llmvm_run_checks';

    /**
     * Register hooks.
     */
    public function hooks(): void {
        // Re-enable cron with proper action
        add_action( self::HOOK, [ $this, 'run' ] );

        // Add weekly schedule if not present.
        add_filter( 'cron_schedules', [ $this, 'register_schedules' ] );

        // Admin-triggered run-now endpoint.
        add_action( 'admin_post_llmvm_run_now', [ $this, 'handle_run_now' ] );
    }

    /**
     * Provide custom schedules (weekly).
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function register_schedules( array $schedules ): array {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = [
                'interval' => DAY_IN_SECONDS * 7,
                'display'  => __( 'Once Weekly', 'llm-visibility-monitor' ),
            ];
        }
        return $schedules;
    }

    /**
     * Clear any existing cron jobs for this hook.
     */
    public function clear_cron(): void {
        // Clear all scheduled events for this hook
        wp_clear_scheduled_hook( self::HOOK );
        LLMVM_Logger::log( 'Cleared all cron jobs for hook', [ 'hook' => self::HOOK ] );
    }

    /**
     * Reschedule the event to a new frequency.
     *
     * @param string $frequency 'daily' or 'weekly'.
     */
    public function reschedule( string $frequency ): void {
        $frequency = in_array( $frequency, [ 'daily', 'weekly' ], true ) ? $frequency : 'daily';

        // Check if cron is already scheduled with the same frequency
        $next_scheduled = wp_next_scheduled( self::HOOK );
        if ( $next_scheduled ) {
            LLMVM_Logger::log( 'Cron already scheduled', [ 'next_run' => gmdate( 'Y-m-d H:i:s', $next_scheduled ) ] );
            return;
        }

        // Calculate next run time based on frequency
        $next_run = $this->calculate_next_run_time( $frequency );
        
        wp_schedule_event( $next_run, $frequency, self::HOOK );
        LLMVM_Logger::log( 'Scheduled new cron job', [ 'frequency' => $frequency, 'next_run' => gmdate( 'Y-m-d H:i:s', $next_run ) ] );
    }

    /**
     * Calculate the next run time for the given frequency.
     *
     * @param string $frequency 'daily' or 'weekly'.
     * @return int Unix timestamp for next run.
     */
    private function calculate_next_run_time( string $frequency ): int {
        $now = time();
        
        if ( 'daily' === $frequency ) {
            // Next run at 9:00 AM tomorrow
            $next_run = strtotime( 'tomorrow 9:00 AM', $now );
        } else {
            // Next run at 9:00 AM next Monday
            $next_run = strtotime( 'next monday 9:00 AM', $now );
        }
        
        return $next_run;
    }

    /**
     * Run scheduled job: send prompts to OpenRouter and store results.
     */
    public function run(): void {
        $options   = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $raw_key   = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
        $api_key   = $raw_key !== '' ? self::decrypt_api_key( $raw_key ) : '';
        $model   = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
        $prompts = get_option( 'llmvm_prompts', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $prompts ) ) {
            $prompts = [];
        }

        LLMVM_Logger::log( 'Run start', [ 'prompts' => count( $prompts ), 'model' => $model, 'prompt_texts' => array_column( $prompts, 'text' ) ] );



        if ( empty( $prompts ) ) {
            LLMVM_Logger::log( 'Run abort: no prompts configured' );
            return;
        }
        if ( 'openrouter/stub-model-v1' !== $model && empty( $api_key ) ) {
            $reason = 'empty';
            if ( '' !== $raw_key ) {
                $reason = 'cannot decrypt; please re-enter in Settings';
                // Clear the corrupted API key to allow re-entry
                $options['api_key'] = '';
                update_option( 'llmvm_options', $options );
                LLMVM_Logger::log( 'Cleared corrupted API key from options' );
            }
            LLMVM_Logger::log( 'Run abort: missing API key for real model', [ 'reason' => $reason ] );
            return;
        }

        $client = new LLMVM_OpenRouter_Client();
        foreach ( $prompts as $prompt_item ) {
            $prompt_text = isset( $prompt_item['text'] ) ? (string) $prompt_item['text'] : '';
            if ( '' === trim( $prompt_text ) ) {
                continue;
            }

            LLMVM_Logger::log( 'Sending prompt', [ 'model' => $model, 'prompt_text' => $prompt_text ] );
            $response   = $client->query( $api_key, $prompt_text, $model );
            $resp_model = isset( $response['model'] ) ? (string) $response['model'] : 'unknown';
            $answer     = isset( $response['answer'] ) ? (string) $response['answer'] : '';
            $status     = isset( $response['status'] ) ? (int) $response['status'] : 0;
            $error      = isset( $response['error'] ) ? (string) $response['error'] : '';

            LLMVM_Logger::log( 'Inserting result', [ 'prompt_text' => $prompt_text, 'resp_model' => $resp_model, 'answer_length' => strlen( $answer ) ] );
            LLMVM_Database::insert_result( $prompt_text, $resp_model, $answer );
            if ( $status && $status >= 400 ) {
                LLMVM_Logger::log( 'OpenRouter error stored', [ 'status' => $status, 'error' => $error ] );
            }
        }
        LLMVM_Logger::log( 'Run completed' );
        
        // Fire action hook for email reporter and other extensions
        do_action( 'llmvm_run_completed' );
    }

    /**
     * Handle manual run from admin.
     */
    public function handle_run_now(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }
        // Verify nonce with proper sanitization.
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'llmvm_run_now' ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }
        LLMVM_Logger::log( 'Run Now triggered by admin' );
        $this->run();
        wp_safe_redirect( wp_nonce_url( admin_url( 'tools.php?page=llmvm-dashboard&llmvm_ran=1' ), 'llmvm_run_completed' ) ?: '' );
        exit;
    }

    /**
     * Decrypt API key stored in options.
     */
    public static function decrypt_api_key( string $ciphertext ): string {
        $ciphertext = wp_unslash( $ciphertext );
        
        // If it's already plaintext (no colon separator), return as-is
        if ( strpos( $ciphertext, ':' ) === false ) {
            return $ciphertext;
        }
        
        $parts = explode( ':', $ciphertext );
        if ( count( $parts ) !== 2 ) {
            return $ciphertext; // legacy/plaintext.
        }
        
        [ $iv_b64, $payload_b64 ] = $parts;
        $iv      = base64_decode( $iv_b64 );
        $payload = base64_decode( $payload_b64 );
        if ( ! $iv || ! $payload ) {
            LLMVM_Logger::log( 'API key decryption failed: invalid base64 data' );
            return '';
        }
        
        // Try new method first (without AUTH_KEY)
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $out = openssl_decrypt( $payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        
        if ( false !== $out ) {
            return is_string( $out ) ? $out : '';
        }
        
        // Try old method (with AUTH_KEY) if new method failed
        if ( defined( 'AUTH_KEY' ) ) {
            $key = hash( 'sha256', wp_salt( 'auth' ) . AUTH_KEY, true );
            $out = openssl_decrypt( $payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
            
            if ( false !== $out ) {
                return is_string( $out ) ? $out : '';
            }
        }
        
        LLMVM_Logger::log( 'API key decryption failed: both methods failed' );
        return '';
    }

    /**
     * Encrypt API key for storage.
     */
    public static function encrypt_api_key( string $plaintext ): string {
        $iv  = random_bytes( 16 );
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $ct  = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        if ( false === $ct ) {
            LLMVM_Logger::log( 'API key encryption failed: openssl_encrypt returned false, storing as plaintext' );
            return $plaintext; // fallback to plaintext if encryption fails.
        }
        return base64_encode( $iv ) . ':' . base64_encode( $ct );
    }
}


