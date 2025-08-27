<?php
/**
 * Admin UI for settings, prompts CRUD, and dashboard page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Admin {

    /**
     * Register admin hooks.
     */
    public function hooks(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );

        // Form handlers for prompts CRUD.
        add_action( 'admin_post_llmvm_add_prompt', [ $this, 'handle_add_prompt' ] );
        add_action( 'admin_post_llmvm_edit_prompt', [ $this, 'handle_edit_prompt' ] );
        add_action( 'admin_post_llmvm_delete_prompt', [ $this, 'handle_delete_prompt' ] );
    }

    /**
     * Add menu pages.
     */
    public function register_menus(): void {
        add_options_page(
            __( 'LLM Visibility Monitor', 'llm-visibility-monitor' ),
            __( 'LLM Visibility Monitor', 'llm-visibility-monitor' ),
            'manage_options',
            'llmvm-settings',
            [ $this, 'render_settings_page' ]
        );

        add_management_page(
            __( 'LLM Visibility Dashboard', 'llm-visibility-monitor' ),
            __( 'LLM Visibility Dashboard', 'llm-visibility-monitor' ),
            'manage_options',
            'llmvm-dashboard',
            [ $this, 'render_dashboard_page' ]
        );

        add_submenu_page(
            null,
            __( 'LLM Visibility Result', 'llm-visibility-monitor' ),
            __( 'LLM Visibility Result', 'llm-visibility-monitor' ),
            'manage_options',
            'llmvm-result',
            [ $this, 'render_result_page' ]
        );
    }

    /**
     * Register settings and fields.
     */
    public function register_settings(): void {
        register_setting( 'llmvm_settings', 'llmvm_options', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_options' ],
            'default'           => [ 'api_key' => '', 'cron_frequency' => 'daily', 'model' => 'openrouter/stub-model-v1', 'debug_logging' => false ],
            'show_in_rest'      => false,
        ] );

        add_settings_section( 'llmvm_section_main', __( 'General Settings', 'llm-visibility-monitor' ), '__return_false', 'llmvm-settings' );

        add_settings_field( 'llmvm_api_key', __( 'OpenRouter API Key', 'llm-visibility-monitor' ), [ $this, 'field_api_key' ], 'llmvm-settings', 'llmvm_section_main' );

        add_settings_field( 'llmvm_cron_frequency', __( 'Cron Frequency', 'llm-visibility-monitor' ), [ $this, 'field_cron_frequency' ], 'llmvm-settings', 'llmvm_section_main' );
        add_settings_field( 'llmvm_model', __( 'Model', 'llm-visibility-monitor' ), [ $this, 'field_model' ], 'llmvm-settings', 'llmvm_section_main' );
        add_settings_field( 'llmvm_debug_logging', __( 'Debug Logging', 'llm-visibility-monitor' ), [ $this, 'field_debug_logging' ], 'llmvm-settings', 'llmvm_section_main' );
    }

    /**
     * Sanitize options and reschedule cron when frequency changes.
     *
     * @param array $input Raw input.
     * @return array
     */
    public function sanitize_options( array $input ): array {
        $options = get_option( 'llmvm_options', [] );

        $new = [];
        if ( isset( $input['api_key'] ) ) {
            $api_key = trim( (string) $input['api_key'] );
            // If the field shows the masked placeholder or is empty, keep the existing key.
            if ( '' === $api_key || '********' === $api_key ) {
                $new['api_key'] = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
            } else {
                $encrypted = LLMVM_Cron::encrypt_api_key( $api_key );
                // Verify round-trip decryption to catch env/salt issues immediately.
                $decrypted = LLMVM_Cron::decrypt_api_key( $encrypted );
                if ( $decrypted !== $api_key ) {
                    set_transient( 'llmvm_notice', [ 'type' => 'error', 'msg' => __( 'Could not securely store API key. Please try again.', 'llm-visibility-monitor' ) ], 60 );
                    LLMVM_Logger::log( 'API key save failed round-trip check' );
                } else {
                    set_transient( 'llmvm_notice', [ 'type' => 'success', 'msg' => __( 'API key saved securely.', 'llm-visibility-monitor' ) ], 60 );
                }
                $new['api_key'] = $encrypted;
            }
        } else {
            $new['api_key'] = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
        }

        $freq = isset( $input['cron_frequency'] ) ? sanitize_text_field( (string) $input['cron_frequency'] ) : ( $options['cron_frequency'] ?? 'daily' );
        $freq = in_array( $freq, [ 'daily', 'weekly' ], true ) ? $freq : 'daily';
        $new['cron_frequency'] = $freq;

        if ( ( $options['cron_frequency'] ?? '' ) !== $freq ) {
            ( new LLMVM_Cron() )->reschedule( $freq );
        }

        $model           = isset( $input['model'] ) ? sanitize_text_field( (string) $input['model'] ) : ( $options['model'] ?? 'openrouter/stub-model-v1' );
        $new['model']    = $model;
        $debug_logging   = ! empty( $input['debug_logging'] );
        $new['debug_logging'] = $debug_logging;

        return $new;
    }

    /** Display one-time admin notices */
    public function admin_notices(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $notice = get_transient( 'llmvm_notice' );
        if ( ! empty( $notice ) && is_array( $notice ) ) {
            delete_transient( 'llmvm_notice' );
            $class = ( 'error' === ( $notice['type'] ?? '' ) ) ? 'notice notice-error' : 'notice notice-success';
            echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( (string) ( $notice['msg'] ?? '' ) ) . '</p></div>';
        }
    }

    /** Render API key field */
    public function field_api_key(): void {
        $options = get_option( 'llmvm_options', [] );
        $value   = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
        // Do not display plaintext; show placeholder masked value when set.
        $display = '' !== $value ? '********' : '';
        echo '<input type="password" name="llmvm_options[api_key]" value="' . esc_attr( $display ) . '" autocomplete="new-password" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Stored encrypted. Re-enter to change.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render cron frequency field */
    public function field_cron_frequency(): void {
        $options = get_option( 'llmvm_options', [] );
        $value   = isset( $options['cron_frequency'] ) ? (string) $options['cron_frequency'] : 'daily';
        echo '<select name="llmvm_options[cron_frequency]">';
        echo '<option value="daily"' . selected( $value, 'daily', false ) . '>' . esc_html__( 'Daily', 'llm-visibility-monitor' ) . '</option>';
        echo '<option value="weekly"' . selected( $value, 'weekly', false ) . '>' . esc_html__( 'Weekly', 'llm-visibility-monitor' ) . '</option>';
        echo '</select>';
    }

    /** Render model field */
    public function field_model(): void {
        $options = get_option( 'llmvm_options', [] );
        $value   = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
        echo '<input type="text" name="llmvm_options[model]" value="' . esc_attr( $value ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'OpenRouter model id, e.g. openai/gpt-4o-mini or openai/gpt-5 when available. Use openrouter/stub-model-v1 for testing.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render debug logging field */
    public function field_debug_logging(): void {
        $options = get_option( 'llmvm_options', [] );
        $value   = ! empty( $options['debug_logging'] );
        echo '<label><input type="checkbox" name="llmvm_options[debug_logging]" value="1"' . checked( $value, true, false ) . ' /> ' . esc_html__( 'Enable debug logging to error_log and uploads/llmvm-logs/llmvm.log', 'llm-visibility-monitor' ) . '</label>';
    }

    /** Render settings page */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $prompts = get_option( 'llmvm_prompts', [] );
        $prompts = is_array( $prompts ) ? $prompts : [];
        include LLMVM_PLUGIN_DIR . 'includes/views/settings-page.php';
    }

    /** Render dashboard */
    public function render_dashboard_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $results = LLMVM_Database::get_latest_results( 50 );
        include LLMVM_PLUGIN_DIR . 'includes/views/dashboard-page.php';
    }

    /** Render single result page */
    public function render_result_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
        $row = $id ? LLMVM_Database::get_result_by_id( $id ) : null;
        include LLMVM_PLUGIN_DIR . 'includes/views/result-page.php';
    }

    /** Handle Add Prompt */
    public function handle_add_prompt(): void {
        $this->verify_permissions_and_nonce( 'llmvm_add_prompt' );

        $text = isset( $_POST['prompt_text'] ) ? wp_unslash( (string) $_POST['prompt_text'] ) : '';
        $text = sanitize_textarea_field( $text );
        if ( '' !== trim( $text ) ) {
            $prompts   = get_option( 'llmvm_prompts', [] );
            $prompts   = is_array( $prompts ) ? $prompts : [];
            $prompts[] = [ 'id' => uniqid( 'p_', true ), 'text' => $text ];
            update_option( 'llmvm_prompts', $prompts, false );
        }

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'options-general.php?page=llmvm-settings' ) );
        exit;
    }

    /** Handle Edit Prompt */
    public function handle_edit_prompt(): void {
        $this->verify_permissions_and_nonce( 'llmvm_edit_prompt' );

        $id   = isset( $_POST['prompt_id'] ) ? sanitize_text_field( (string) $_POST['prompt_id'] ) : '';
        $text = isset( $_POST['prompt_text'] ) ? wp_unslash( (string) $_POST['prompt_text'] ) : '';
        $text = sanitize_textarea_field( $text );

        $prompts = get_option( 'llmvm_prompts', [] );
        $prompts = is_array( $prompts ) ? $prompts : [];
        foreach ( $prompts as &$prompt ) {
            if ( isset( $prompt['id'] ) && $prompt['id'] === $id ) {
                $prompt['text'] = $text;
                break;
            }
        }
        unset( $prompt );
        update_option( 'llmvm_prompts', $prompts, false );

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'options-general.php?page=llmvm-settings' ) );
        exit;
    }

    /** Handle Delete Prompt */
    public function handle_delete_prompt(): void {
        $this->verify_permissions_and_nonce( 'llmvm_delete_prompt' );

        $id      = isset( $_POST['prompt_id'] ) ? sanitize_text_field( (string) $_POST['prompt_id'] ) : '';
        $prompts = get_option( 'llmvm_prompts', [] );
        $prompts = is_array( $prompts ) ? $prompts : [];
        $prompts = array_values( array_filter( $prompts, static function ( $p ) use ( $id ) {
            return isset( $p['id'] ) && $p['id'] !== $id;
        } ) );
        update_option( 'llmvm_prompts', $prompts, false );

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'options-general.php?page=llmvm-settings' ) );
        exit;
    }

    private function verify_permissions_and_nonce( string $action ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }
        $nonce = isset( $_POST['_wpnonce'] ) ? (string) $_POST['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }
    }
}


