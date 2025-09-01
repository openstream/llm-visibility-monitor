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
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Form handlers for prompts CRUD.
        add_action( 'admin_post_llmvm_add_prompt', [ $this, 'handle_add_prompt' ] );
        add_action( 'admin_post_llmvm_edit_prompt', [ $this, 'handle_edit_prompt' ] );
        add_action( 'admin_post_llmvm_delete_prompt', [ $this, 'handle_delete_prompt' ] );
        
        // Form handler for result deletion.
        add_action( 'admin_post_llmvm_delete_result', [ $this, 'handle_delete_result' ] );
        

    }

    /**
     * Add menu pages.
     */
    public function register_menus(): void {
        // Ensure all parameters are properly typed to prevent PHP 8.1 deprecation warnings
        $page_title = 'LLM Visibility Monitor';
        $menu_title = 'LLM Visibility Monitor';
        $capability = 'manage_options';
        $menu_slug = 'llmvm-settings';
        $callback = [ $this, 'render_settings_page' ];

        add_options_page(
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $callback
        );

        $dashboard_title = 'LLM Visibility Dashboard';
        $dashboard_slug = 'llmvm-dashboard';
        $dashboard_callback = [ $this, 'render_dashboard_page' ];

        add_management_page(
            $dashboard_title,
            $dashboard_title,
            $capability,
            $dashboard_slug,
            $dashboard_callback
        );

        $result_title = 'LLM Visibility Result';
        $result_slug = 'llmvm-result';
        // Use a proper parent slug instead of null to prevent PHP 8.1 deprecation warnings
        // This creates a hidden submenu page that can be accessed directly via URL
        $result_callback = [ $this, 'render_result_page' ];

        add_submenu_page(
            'tools.php', // Use tools.php as parent instead of null
            $result_title,
            $result_title,
            $capability,
            $result_slug,
            $result_callback
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
        add_settings_field( 'llmvm_email_reports', __( 'Email Reports', 'llm-visibility-monitor' ), [ $this, 'field_email_reports' ], 'llmvm-settings', 'llmvm_section_main' );
    }

    /**
     * Sanitize options and reschedule cron when frequency changes.
     *
     * @param array $input Raw input.
     * @return array
     */
    public function sanitize_options( array $input ): array {
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }

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
        
        $email_reports   = ! empty( $input['email_reports'] );
        $new['email_reports'] = $email_reports;

        return $new;
    }

    /** Display one-time admin notices */
    public function admin_notices(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $notice = get_transient( 'llmvm_notice' );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $notice ) || false === $notice ) {
            return;
        }
        if ( ! empty( $notice ) ) {
            delete_transient( 'llmvm_notice' );
            $class = ( 'error' === ( $notice['type'] ?? '' ) ) ? 'notice notice-error' : 'notice notice-success';
            echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( (string) ( $notice['msg'] ?? '' ) ) . '</p></div>';
        }
    }

    /** Render API key field */
    public function field_api_key(): void {
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value   = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
        // Do not display plaintext; show placeholder masked value when set.
        $display = '' !== $value ? '********' : '';
        echo '<input type="password" name="llmvm_options[api_key]" value="' . esc_attr( $display ) . '" autocomplete="new-password" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Stored encrypted. Re-enter to change.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render cron frequency field */
    public function field_cron_frequency(): void {
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value   = isset( $options['cron_frequency'] ) ? (string) $options['cron_frequency'] : 'daily';
        echo '<select name="llmvm_options[cron_frequency]">';
        echo '<option value="daily"' . selected( $value, 'daily', false ) . '>' . esc_html__( 'Daily', 'llm-visibility-monitor' ) . '</option>';
        echo '<option value="weekly"' . selected( $value, 'weekly', false ) . '>' . esc_html__( 'Weekly', 'llm-visibility-monitor' ) . '</option>';
        echo '</select>';
    }

    /** Render model field */
    public function field_model(): void {
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
        
        // Get available models (will fallback to common models if API key decryption fails)
        $models = $this->get_openrouter_models();
        
        echo '<select name="llmvm_options[model]" id="llmvm-model-select" class="llmvm-model-select">';
        echo '<option value="">' . esc_html__( 'Select a model...', 'llm-visibility-monitor' ) . '</option>';
        
        foreach ( $models as $model ) {
            $selected = ( $model['id'] === $value ) ? ' selected="selected"' : '';
            echo '<option value="' . esc_attr( $model['id'] ) . '"' . esc_attr( $selected ) . '>';
            echo esc_html( $model['name'] . ' (' . $model['id'] . ')' );
            echo '</option>';
        }
        
        echo '</select>';
        
        echo '<p class="description">' . esc_html__( 'Select an OpenRouter model. Common models are shown above. For more models, enter the model ID manually.', 'llm-visibility-monitor' ) . '</p>';
        
        // Add a manual input field for custom models
        echo '<p><label for="llmvm-model-custom">' . esc_html__( 'Or enter custom model ID:', 'llm-visibility-monitor' ) . '</label></p>';
        echo '<input type="text" id="llmvm-model-custom" value="' . esc_attr( $value ) . '" class="regular-text llmvm-model-custom" placeholder="e.g., openai/gpt-4o-mini" />';
        

    }

    /** Render debug logging field */
    public function field_debug_logging(): void {
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value   = ! empty( $options['debug_logging'] );
        echo '<label><input type="checkbox" name="llmvm_options[debug_logging]" value="1"' . checked( $value, true, false ) . ' /> ' . esc_html__( 'Enable debug logging to error_log and uploads/llm-visibility-monitor/llmvm.log', 'llm-visibility-monitor' ) . '</label>';
    }

    /** Render email reports field */
    public function field_email_reports(): void {
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = ! empty( $options['email_reports'] );
        echo '<label><input type="checkbox" name="llmvm_options[email_reports]" value="1"' . checked( $value, true, false ) . ' /> ' . esc_html__( 'Send email reports to admin after each cron run', 'llm-visibility-monitor' ) . '</label>';
        echo '<p class="description">' . esc_html__( 'Reports will be sent to the WordPress admin email address with a summary of the latest results.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render settings page */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $prompts = get_option( 'llmvm_prompts', [] );
        $prompts = is_array( $prompts ) ? $prompts : [];
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( false === $prompts ) {
            $prompts = [];
        }
        if ( ! defined( 'LLMVM_PLUGIN_DIR' ) || empty( LLMVM_PLUGIN_DIR ) ) {
            return;
        }
        $settings_file = LLMVM_PLUGIN_DIR . 'includes/views/settings-page.php';
        if ( is_file( $settings_file ) && is_string( $settings_file ) ) {
            include $settings_file;
        }
    }

    /** Render dashboard */
    public function render_dashboard_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $results = LLMVM_Database::get_latest_results( 50 );
        if ( ! defined( 'LLMVM_PLUGIN_DIR' ) || empty( LLMVM_PLUGIN_DIR ) ) {
            return;
        }
        $dashboard_file = LLMVM_PLUGIN_DIR . 'includes/views/dashboard-page.php';
        if ( is_file( $dashboard_file ) && is_string( $dashboard_file ) ) {
            include $dashboard_file;
        }
    }

    /** Render single result page */
    public function render_result_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Verify nonce for security
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'llmvm_view_result' ) ) {
            wp_die( esc_html__( 'Security check failed', 'llm-visibility-monitor' ) );
        }
        
        // Sanitize the ID parameter
        $id = isset( $_GET['id'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['id'] ) ) : 0;
        $row = $id ? LLMVM_Database::get_result_by_id( $id ) : null;
        // Ensure $row is always an array or null for the view.
        if ( ! is_array( $row ) ) {
            $row = null;
        }
        if ( ! defined( 'LLMVM_PLUGIN_DIR' ) || empty( LLMVM_PLUGIN_DIR ) ) {
            return;
        }
        $result_file = LLMVM_PLUGIN_DIR . 'includes/views/result-page.php';
        if ( is_file( $result_file ) && is_string( $result_file ) ) {
            include $result_file;
        }
    }

    /** Handle Add Prompt */
    public function handle_add_prompt(): void {
        $this->verify_permissions_and_nonce( 'llmvm_add_prompt' );

        // Sanitize the prompt text input.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce().
        $text = isset( $_POST['prompt_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt_text'] ) ) : '';
        if ( '' !== trim( $text ) ) {
            $prompts   = get_option( 'llmvm_prompts', [] );
            $prompts   = is_array( $prompts ) ? $prompts : [];
            // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
            if ( false === $prompts ) {
                $prompts = [];
            }
            $prompts[] = [ 'id' => uniqid( 'p_', true ), 'text' => $text ];
            update_option( 'llmvm_prompts', $prompts, false );
        }

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'options-general.php?page=llmvm-settings' ) ?: '' );
        exit;
    }

    /** Handle Edit Prompt */
    public function handle_edit_prompt(): void {
        $this->verify_permissions_and_nonce( 'llmvm_edit_prompt' );

        // Sanitize the prompt ID and text inputs.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce().
        $id   = isset( $_POST['prompt_id'] ) ? sanitize_text_field( wp_unslash( $_POST['prompt_id'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce().
        $text = isset( $_POST['prompt_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt_text'] ) ) : '';

        $prompts = get_option( 'llmvm_prompts', [] );
        $prompts = is_array( $prompts ) ? $prompts : [];
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( false === $prompts ) {
            $prompts = [];
        }
        foreach ( $prompts as &$prompt ) {
            if ( isset( $prompt['id'] ) && $prompt['id'] === $id ) {
                $prompt['text'] = $text;
                break;
            }
        }
        unset( $prompt );
        update_option( 'llmvm_prompts', $prompts, false );

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'options-general.php?page=llmvm-settings' ) ?: '' );
        exit;
    }

    /** Handle Delete Prompt */
    public function handle_delete_prompt(): void {
        $this->verify_permissions_and_nonce( 'llmvm_delete_prompt' );

        // Sanitize the prompt ID input.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce().
        $id      = isset( $_POST['prompt_id'] ) ? sanitize_text_field( wp_unslash( $_POST['prompt_id'] ) ) : '';
        $prompts = get_option( 'llmvm_prompts', [] );
        $prompts = is_array( $prompts ) ? $prompts : [];
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( false === $prompts ) {
            $prompts = [];
        }
        $prompts = array_values( array_filter( $prompts, static function ( $p ) use ( $id ) {
            return isset( $p['id'] ) && $p['id'] !== $id;
        } ) );
        update_option( 'llmvm_prompts', $prompts, false );

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'options-general.php?page=llmvm-settings' ) ?: '' );
        exit;
    }

    private function verify_permissions_and_nonce( string $action ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }
        // Verify nonce with proper sanitization.
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }
    }

    /** Handle Delete Result */
    public function handle_delete_result(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }
        
        // Verify nonce with proper sanitization.
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'llmvm_delete_result' ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }
        
        // Sanitize the result ID input.
        $id = isset( $_GET['id'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['id'] ) ) : 0;
        
        if ( $id > 0 ) {
            // Delete the result from the database.
            global $wpdb;
            $table_name = $wpdb->prefix . 'llm_visibility_results';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $deleted = $wpdb->delete( $table_name, [ 'id' => $id ], [ '%d' ] );
            
            if ( $deleted ) {
                set_transient( 'llmvm_notice', [ 'type' => 'success', 'msg' => __( 'Result deleted successfully.', 'llm-visibility-monitor' ) ], 60 );
                LLMVM_Logger::log( 'Result deleted by admin', [ 'id' => $id ] );
            } else {
                set_transient( 'llmvm_notice', [ 'type' => 'error', 'msg' => __( 'Failed to delete result.', 'llm-visibility-monitor' ) ], 60 );
            }
        }
        
        wp_safe_redirect( wp_get_referer() ?: admin_url( 'tools.php?page=llmvm-dashboard' ) ?: '' );
        exit;
    }



    /**
     * Fetch available models from OpenRouter API.
     */
    private function get_openrouter_models(): array {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        
        $api_key = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
        if ( empty( $api_key ) ) {
            return [
                [ 'id' => 'openrouter/stub-model-v1', 'name' => 'Stub Model (for testing)' ],
            ];
        }

        // Try to decrypt API key
        $decrypted_key = LLMVM_Cron::decrypt_api_key( $api_key );
        if ( empty( $decrypted_key ) ) {
            // If decryption failed, clear the corrupted API key and return common models
            $options['api_key'] = '';
            update_option( 'llmvm_options', $options );
            LLMVM_Logger::log( 'API key decryption failed, cleared corrupted key' );
            return [
                [ 'id' => 'openrouter/stub-model-v1', 'name' => 'Stub Model (for testing)' ],
                [ 'id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini' ],
                [ 'id' => 'openai/gpt-4o', 'name' => 'GPT-4o' ],
                [ 'id' => 'openai/gpt-5', 'name' => 'GPT-5' ],
                [ 'id' => 'anthropic/claude-3-5-sonnet', 'name' => 'Claude 3.5 Sonnet' ],
                [ 'id' => 'anthropic/claude-3-opus', 'name' => 'Claude 3 Opus' ],
                [ 'id' => 'google/gemini-pro', 'name' => 'Gemini Pro' ],
                [ 'id' => 'meta-llama/llama-3.1-8b-instruct', 'name' => 'Llama 3.1 8B Instruct' ],
                [ 'id' => 'meta-llama/llama-3.1-70b-instruct', 'name' => 'Llama 3.1 70B Instruct' ],
            ];
        }

        LLMVM_Logger::log( 'Fetching OpenRouter models with decrypted key length=' . strlen( $decrypted_key ) );
        
        $response = wp_remote_get( 'https://openrouter.ai/api/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $decrypted_key,
                'HTTP-Referer'  => home_url(),
                'X-Title'       => 'LLM Visibility Monitor',
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            LLMVM_Logger::log( 'Failed to fetch OpenRouter models error=' . $response->get_error_message() );
            return [
                [ 'id' => 'openrouter/stub-model-v1', 'name' => 'Stub Model (for testing)' ],
                [ 'id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini' ],
                [ 'id' => 'openai/gpt-4o', 'name' => 'GPT-4o' ],
                [ 'id' => 'openai/gpt-5', 'name' => 'GPT-5' ],
                [ 'id' => 'anthropic/claude-3-5-sonnet', 'name' => 'Claude 3.5 Sonnet' ],
                [ 'id' => 'anthropic/claude-3-opus', 'name' => 'Claude 3 Opus' ],
                [ 'id' => 'google/gemini-pro', 'name' => 'Gemini Pro' ],
                [ 'id' => 'meta-llama/llama-3.1-8b-instruct', 'name' => 'Llama 3.1 8B Instruct' ],
                [ 'id' => 'meta-llama/llama-3.1-70b-instruct', 'name' => 'Llama 3.1 70B Instruct' ],
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $status_code !== 200 ) {
            LLMVM_Logger::log( 'OpenRouter models API returned status=' . $status_code . ' body=' . substr( $body ?: '', 0, 200 ) );
            return [
                [ 'id' => 'openrouter/stub-model-v1', 'name' => 'Stub Model (for testing)' ],
                [ 'id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini' ],
                [ 'id' => 'openai/gpt-4o', 'name' => 'GPT-4o' ],
                [ 'id' => 'openai/gpt-5', 'name' => 'GPT-5' ],
                [ 'id' => 'anthropic/claude-3-5-sonnet', 'name' => 'Claude 3.5 Sonnet' ],
                [ 'id' => 'anthropic/claude-3-opus', 'name' => 'Claude 3 Opus' ],
                [ 'id' => 'google/gemini-pro', 'name' => 'Gemini Pro' ],
                [ 'id' => 'meta-llama/llama-3.1-8b-instruct', 'name' => 'Llama 3.1 8B Instruct' ],
                [ 'id' => 'meta-llama/llama-3.1-70b-instruct', 'name' => 'Llama 3.1 70B Instruct' ],
            ];
        }

        if ( ! is_array( $data ) || ! isset( $data['data'] ) ) {
            LLMVM_Logger::log( 'Invalid response from OpenRouter models API body=' . substr( $body ?: '', 0, 200 ) );
            return [
                [ 'id' => 'openrouter/stub-model-v1', 'name' => 'Stub Model (for testing)' ],
                [ 'id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini' ],
                [ 'id' => 'openai/gpt-4o', 'name' => 'GPT-4o' ],
                [ 'id' => 'openai/gpt-5', 'name' => 'GPT-5' ],
                [ 'id' => 'anthropic/claude-3-5-sonnet', 'name' => 'Claude 3.5 Sonnet' ],
                [ 'id' => 'anthropic/claude-3-opus', 'name' => 'Claude 3 Opus' ],
                [ 'id' => 'google/gemini-pro', 'name' => 'Gemini Pro' ],
                [ 'id' => 'meta-llama/llama-3.1-8b-instruct', 'name' => 'Llama 3.1 8B Instruct' ],
                [ 'id' => 'meta-llama/llama-3.1-70b-instruct', 'name' => 'Llama 3.1 70B Instruct' ],
            ];
        }

        $models = [];
        foreach ( $data['data'] as $model ) {
            if ( isset( $model['id'] ) && isset( $model['name'] ) ) {
                $models[] = [
                    'id'   => (string) $model['id'],
                    'name' => (string) $model['name'],
                ];
            }
        }

        // Sort models by name for better UX.
        usort( $models, function( $a, $b ) {
            return strcasecmp( $a['name'], $b['name'] );
        } );

        // Always include stub model at the top.
        array_unshift( $models, [ 'id' => 'openrouter/stub-model-v1', 'name' => 'Stub Model (for testing)' ] );

        return $models;
    }

    /**
     * Enqueue admin assets (CSS and JS).
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Only load on our plugin pages
        if ( ! in_array( $hook, [ 'settings_page_llmvm-settings', 'tools_page_llmvm-dashboard', 'tools_page_llmvm-result' ], true ) ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'llmvm-admin',
            LLMVM_PLUGIN_URL . 'assets/css/llmvm-admin.css',
            [],
            LLMVM_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'llmvm-admin',
            LLMVM_PLUGIN_URL . 'assets/js/llmvm-admin.js',
            [ 'jquery' ],
            LLMVM_VERSION,
            true
        );
    }
}


