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
     * Reschedule the event to a new frequency.
     *
     * @param string $frequency 'daily' or 'weekly'.
     */
    public function reschedule( string $frequency ): void {
        $frequency = in_array( $frequency, [ 'daily', 'weekly' ], true ) ? $frequency : 'daily';

        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }

        wp_schedule_event( time() + MINUTE_IN_SECONDS, $frequency, self::HOOK );
    }

    /**
     * Run scheduled job: send prompts to OpenRouter and store results.
     */
    public function run(): void {
        $options   = get_option( 'llmvm_options', [] );
        $raw_key   = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
        $api_key   = $raw_key !== '' ? self::decrypt_api_key( $raw_key ) : '';
        $model   = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
        $prompts = get_option( 'llmvm_prompts', [] );
        $prompts = is_array( $prompts ) ? $prompts : [];

        LLMVM_Logger::log( 'Run start', [ 'prompts' => count( $prompts ), 'model' => $model ] );

        if ( empty( $prompts ) ) {
            LLMVM_Logger::log( 'Run abort: no prompts configured' );
            return;
        }
        if ( 'openrouter/stub-model-v1' !== $model && empty( $api_key ) ) {
            $reason = 'empty';
            if ( '' !== $raw_key ) {
                $reason = 'cannot decrypt; please re-enter in Settings';
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

            LLMVM_Logger::log( 'Sending prompt', [ 'model' => $model ] );
            $response   = $client->query( $api_key, $prompt_text, $model );
            $resp_model = isset( $response['model'] ) ? (string) $response['model'] : 'unknown';
            $answer     = isset( $response['answer'] ) ? (string) $response['answer'] : '';
            $status     = isset( $response['status'] ) ? (int) $response['status'] : 0;
            $error      = isset( $response['error'] ) ? (string) $response['error'] : '';

            LLMVM_Database::insert_result( $prompt_text, $resp_model, $answer );
            if ( $status && $status >= 400 ) {
                LLMVM_Logger::log( 'OpenRouter error stored', [ 'status' => $status, 'error' => $error ] );
            }
        }
        LLMVM_Logger::log( 'Run completed' );
    }

    /**
     * Handle manual run from admin.
     */
    public function handle_run_now(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }
        $nonce = isset( $_GET['_wpnonce'] ) ? (string) $_GET['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'llmvm_run_now' ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }
        LLMVM_Logger::log( 'Run Now triggered by admin' );
        $this->run();
        wp_safe_redirect( admin_url( 'tools.php?page=llmvm-dashboard&llmvm_ran=1' ) );
        exit;
    }

    /**
     * Decrypt API key stored in options.
     */
    public static function decrypt_api_key( string $ciphertext ): string {
        $ciphertext = wp_unslash( $ciphertext );
        $parts      = explode( ':', $ciphertext );
        if ( count( $parts ) !== 2 ) {
            return $ciphertext; // legacy/plaintext.
        }
        [ $iv_b64, $payload_b64 ] = $parts;
        $iv      = base64_decode( $iv_b64 );
        $payload = base64_decode( $payload_b64 );
        if ( ! $iv || ! $payload ) {
            return '';
        }
        $key = hash( 'sha256', wp_salt( 'auth' ) . AUTH_KEY, true );
        $out = openssl_decrypt( $payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return is_string( $out ) ? $out : '';
    }

    /**
     * Encrypt API key for storage.
     */
    public static function encrypt_api_key( string $plaintext ): string {
        $iv  = random_bytes( 16 );
        $key = hash( 'sha256', wp_salt( 'auth' ) . AUTH_KEY, true );
        $ct  = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        if ( false === $ct ) {
            return $plaintext; // fallback to plaintext if encryption fails.
        }
        return base64_encode( $iv ) . ':' . base64_encode( $ct );
    }
}


