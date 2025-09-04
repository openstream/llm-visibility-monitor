<?php
/**
 * Admin UI for settings, prompts CRUD, and dashboard page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Admin {

    /**
     * Constructor.
     */
    public function __construct() {
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
        add_action( 'admin_post_llmvm_bulk_delete_results', [ $this, 'handle_bulk_delete' ] );
    }

    /**
     * Add menu pages.
     */
    public function register_menus(): void {
        // Ensure all parameters are properly typed to prevent PHP 8.1 deprecation warnings
        
        // Settings page - only for administrators
        $page_title = __( 'LLM Visibility Monitor', 'llm-visibility-monitor' );
        $menu_title = __( 'LLM Visibility Monitor', 'llm-visibility-monitor' );
        $capability = 'llmvm_manage_settings';
        $menu_slug = 'llmvm-settings';
        $callback = [ $this, 'render_settings_page' ];

        add_options_page(
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $callback
        );

        // Prompts management page - for users who can manage prompts
        $prompts_title = __( 'LLM Prompts', 'llm-visibility-monitor' );
        $prompts_slug = 'llmvm-prompts';
        $prompts_callback = [ $this, 'render_prompts_page' ];

        add_management_page(
            $prompts_title,
            $prompts_title,
            'llmvm_manage_prompts',
            $prompts_slug,
            $prompts_callback
        );

        // Dashboard page - for users with dashboard access
        $dashboard_title = __( 'LLM Visibility Dashboard', 'llm-visibility-monitor' );
        $dashboard_slug = 'llmvm-dashboard';
        $dashboard_callback = [ $this, 'render_dashboard_page' ];

        add_management_page(
            $dashboard_title,
            $dashboard_title,
            'llmvm_view_dashboard',
            $dashboard_slug,
            $dashboard_callback
        );

        // Result detail page - for users with results access
        $result_title = __( 'LLM Visibility Result', 'llm-visibility-monitor' );
        $result_slug = 'llmvm-result';
        // Use a proper parent slug instead of null to prevent PHP 8.1 deprecation warnings
        // This creates a hidden submenu page that can be accessed directly via URL
        $result_callback = [ $this, 'render_result_page' ];

        add_submenu_page(
            'tools.php', // Use tools.php as parent instead of null
            $result_title,
            $result_title,
            'llmvm_view_results',
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
                    // Clear cached models when API key is updated
                    delete_transient( 'llmvm_openrouter_models' );
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
        if ( ! current_user_can( 'llmvm_view_dashboard' ) ) {
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
        // Only render on settings page
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter is safe for page detection.
        if ( ! isset( $_GET['page'] ) || 'llmvm-settings' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
            return;
        }
        
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
        
        // Get available models (will fallback to common models if API key decryption fails)
        $models = self::get_openrouter_models();
        
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

    /** Render prompts management page */
    public function render_prompts_page(): void {
        if ( ! current_user_can( 'llmvm_manage_prompts' ) ) {
            return;
        }

        $prompts = get_option( 'llmvm_prompts', [] );
        $prompts = is_array( $prompts ) ? $prompts : [];
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( false === $prompts ) {
            $prompts = [];
        }
        
        // Get current user info for filtering
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'llmvm_manage_settings' );
        
        // Filter prompts based on user role
        $user_prompts = [];
        $all_prompts = [];
        
        foreach ( $prompts as $prompt ) {
            $prompt_user_id = isset( $prompt['user_id'] ) ? (int) $prompt['user_id'] : 1;
            
            // Always add to user_prompts if it belongs to current user
            if ( $prompt_user_id === $current_user_id ) {
                $user_prompts[] = $prompt;
            }
            
            // Add to all_prompts if admin (for viewing all prompts)
            if ( $is_admin ) {
                $all_prompts[] = $prompt;
            }
        }
        
        if ( ! defined( 'LLMVM_PLUGIN_DIR' ) || empty( LLMVM_PLUGIN_DIR ) ) {
            return;
        }
        $prompts_file = LLMVM_PLUGIN_DIR . 'includes/views/prompts-page.php';
        if ( is_file( $prompts_file ) && is_string( $prompts_file ) ) {
            include $prompts_file;
        }
    }

    /** Render settings page */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'llmvm_manage_settings' ) ) {
            return;
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
        if ( ! current_user_can( 'llmvm_view_dashboard' ) ) {
            return;
        }

        // Verify nonce for form submissions
        if ( isset( $_POST['action'] ) && 'llmvm_bulk_delete' === $_POST['action'] ) {
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'llmvm_bulk_delete_results' ) ) {
                wp_die( esc_html__( 'Security check failed', 'llm-visibility-monitor' ) );
            }
        }

        // Get current user ID for filtering
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'llmvm_manage_settings' );

        // Get sorting parameters
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
        $order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
        
        // Validate orderby parameter
        $allowed_columns = [ 'id', 'created_at', 'prompt', 'model', 'user_id' ];
        if ( ! in_array( $orderby, $allowed_columns, true ) ) {
            $orderby = 'created_at';
        }
        
        // Validate order parameter
        $order = strtoupper( $order );
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        // Get results filtered by user (unless admin)
        $user_filter = $is_admin ? 0 : $current_user_id;
        $results = LLMVM_Database::get_latest_results( 50, $orderby, $order, 0, $user_filter );
        $total_results = LLMVM_Database::get_total_results( $user_filter );
        
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
        if ( ! current_user_can( 'llmvm_view_results' ) ) {
            return;
        }
        
        // Verify nonce for security
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'llmvm_view_result' ) ) {
            wp_die( esc_html__( 'Security check failed', 'llm-visibility-monitor' ) );
        }
        
        // Sanitize the ID parameter
        $id = isset( $_GET['id'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['id'] ) ) : 0;
        
        // Get current user ID for ownership verification
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'llmvm_manage_settings' );
        
        // Get result with user filtering (unless admin)
        $user_filter = $is_admin ? 0 : $current_user_id;
        $row = $id ? LLMVM_Database::get_result_by_id( $id, $user_filter ) : null;
        
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
        if ( ! current_user_can( 'llmvm_manage_prompts' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }
        
        $this->verify_permissions_and_nonce( 'llmvm_add_prompt' );

        // Sanitize the prompt text and models inputs.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce().
        $text = isset( $_POST['prompt_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt_text'] ) ) : '';
        
        // Handle both prompt_models[] (array format) and prompt_models (single format)
        $models_input = '';
        if ( isset( $_POST['prompt_models'] ) ) {
            $models_input = wp_unslash( $_POST['prompt_models'] );
        }
        
        // Debug: Log what we received
        LLMVM_Logger::log('Models input received', ['models_input' => $models_input]);
        
        // Handle both single model (backward compatibility) and multiple models
        $models = array();
        if ( is_array( $models_input ) ) {
            // Multiple models selected
            foreach ( $models_input as $model ) {
                $model = sanitize_text_field( $model );
                if ( ! empty( $model ) ) {
                    $models[] = $model;
                }
            }
        } else {
            // Single model (backward compatibility)
            $model = sanitize_text_field( $models_input );
            if ( ! empty( $model ) ) {
                $models[] = $model;
            }
        }
        
        // Debug: Log processed models
        LLMVM_Logger::log('Processed models', ['models' => $models]);
        
        if ( '' !== trim( $text ) ) {
            $prompts   = get_option( 'llmvm_prompts', [] );
            $prompts   = is_array( $prompts ) ? $prompts : [];
            // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
            if ( false === $prompts ) {
                $prompts = [];
            }
            
            // Get current user ID
            $current_user_id = get_current_user_id();
            
            // Check for duplicate prompts (same text, models, and user)
            $is_duplicate = false;
            foreach ( $prompts as $existing_prompt ) {
                if ( isset( $existing_prompt['text'] ) && 
                     trim( $existing_prompt['text'] ) === trim( $text ) &&
                     isset( $existing_prompt['user_id'] ) && 
                     $existing_prompt['user_id'] === $current_user_id ) {
                    
                    // Get existing models (handle both old 'model' and new 'models' format)
                    $existing_models = array();
                    if ( isset( $existing_prompt['models'] ) && is_array( $existing_prompt['models'] ) ) {
                        $existing_models = $existing_prompt['models'];
                    } elseif ( isset( $existing_prompt['model'] ) ) {
                        $existing_models = array( $existing_prompt['model'] );
                    }
                    
                    // Check if models are the same
                    if ( $existing_models === $models ) {
                        $is_duplicate = true;
                        break;
                    }
                }
            }
            
            if ( ! $is_duplicate ) {
                // Use specified models or fall back to default
                if ( empty( $models ) ) {
                    $options = get_option( 'llmvm_options', [] );
                    $default_model = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
                    $models = array( $default_model );
                }
                
                $prompts[] = [ 
                    'id' => uniqid( 'p_', true ), 
                    'text' => $text,
                    'models' => $models,
                    'user_id' => $current_user_id
                ];
                update_option( 'llmvm_prompts', $prompts, false );
                set_transient( 'llmvm_notice', [ 'type' => 'success', 'msg' => __( 'Prompt added successfully.', 'llm-visibility-monitor' ) ], 60 );
            } else {
                set_transient( 'llmvm_notice', [ 'type' => 'warning', 'msg' => __( 'This prompt with the same models already exists.', 'llm-visibility-monitor' ) ], 60 );
            }
        }

        // Redirect back to the prompts page
        wp_safe_redirect( admin_url( 'tools.php?page=llmvm-prompts' ) );
        exit;
    }

    /** Handle Edit Prompt */
    public function handle_edit_prompt(): void {
        if ( ! current_user_can( 'llmvm_manage_prompts' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }
        
        $this->verify_permissions_and_nonce( 'llmvm_edit_prompt' );

        // Sanitize the prompt ID, text, and models inputs.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce().
        $id   = isset( $_POST['prompt_id'] ) ? sanitize_text_field( wp_unslash( $_POST['prompt_id'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce().
        $text = isset( $_POST['prompt_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt_text'] ) ) : '';
        
        // Handle both prompt_models[] (array format) and prompt_models (single format)
        $models_input = '';
        if ( isset( $_POST['prompt_models'] ) ) {
            $models_input = wp_unslash( $_POST['prompt_models'] );
        }
        
        // Debug: Log what we received
        LLMVM_Logger::log('Edit prompt - Models input received', ['models_input' => $models_input]);
        
        // Handle both single model (backward compatibility) and multiple models
        $models = array();
        if ( is_array( $models_input ) ) {
            // Multiple models selected
            foreach ( $models_input as $model ) {
                $model = sanitize_text_field( $model );
                if ( ! empty( $model ) ) {
                    $models[] = $model;
                }
            }
        } else {
            // Single model (backward compatibility)
            $model = sanitize_text_field( $models_input );
            if ( ! empty( $model ) ) {
                $models[] = $model;
            }
        }
        
        // Debug: Log processed models
        LLMVM_Logger::log('Edit prompt - Processed models', ['models' => $models]);

        $prompts = get_option( 'llmvm_prompts', [] );
        $prompts = is_array( $prompts ) ? $prompts : [];
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( false === $prompts ) {
            $prompts = [];
        }
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'llmvm_manage_settings' );
        
        $prompt_updated = false;
        foreach ( $prompts as &$prompt ) {
            if ( isset( $prompt['id'] ) && $prompt['id'] === $id ) {
                // Check if user can edit this prompt (owner or admin)
                if ( ! $is_admin && ( ! isset( $prompt['user_id'] ) || $prompt['user_id'] !== $current_user_id ) ) {
                    wp_die( esc_html__( 'You can only edit your own prompts.', 'llm-visibility-monitor' ) );
                }
                
                // Check if this would create a duplicate (same text, models, and user)
                $is_duplicate = false;
                foreach ( $prompts as $other_prompt ) {
                    if ( $other_prompt['id'] !== $id && 
                         isset( $other_prompt['text'] ) && 
                         trim( $other_prompt['text'] ) === trim( $text ) &&
                         isset( $other_prompt['user_id'] ) && 
                         $other_prompt['user_id'] === $current_user_id ) {
                        
                        // Get existing models (handle both old 'model' and new 'models' format)
                        $existing_models = array();
                        if ( isset( $other_prompt['models'] ) && is_array( $other_prompt['models'] ) ) {
                            $existing_models = $other_prompt['models'];
                        } elseif ( isset( $other_prompt['model'] ) ) {
                            $existing_models = array( $other_prompt['model'] );
                        }
                        
                        // Check if models are the same
                        if ( $existing_models === $models ) {
                            $is_duplicate = true;
                            break;
                        }
                    }
                }
                
                if ( ! $is_duplicate ) {
                    $prompt['text'] = $text;
                    if ( ! empty( $models ) ) {
                        $prompt['models'] = $models;
                        // Remove old 'model' field if it exists
                        unset( $prompt['model'] );
                    }
                    $prompt_updated = true;
                    set_transient( 'llmvm_notice', [ 'type' => 'success', 'msg' => __( 'Prompt updated successfully.', 'llm-visibility-monitor' ) ], 60 );
                } else {
                    set_transient( 'llmvm_notice', [ 'type' => 'warning', 'msg' => __( 'This prompt with the same models already exists.', 'llm-visibility-monitor' ) ], 60 );
                }
                break;
            }
        }
        unset( $prompt );
        
        if ( $prompt_updated ) {
            update_option( 'llmvm_prompts', $prompts, false );
        }

        // Redirect back to the prompts page
        wp_safe_redirect( admin_url( 'tools.php?page=llmvm-prompts' ) );
        exit;
    }

    /** Handle Delete Prompt */
    public function handle_delete_prompt(): void {
        if ( ! current_user_can( 'llmvm_manage_prompts' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }
        
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
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'llmvm_manage_settings' );
        
        // Filter prompts to only allow deletion of user's own prompts (unless admin)
        $filtered_prompts = [];
        foreach ( $prompts as $prompt ) {
            if ( isset( $prompt['id'] ) && $prompt['id'] !== $id ) {
                $filtered_prompts[] = $prompt;
            } elseif ( isset( $prompt['id'] ) && $prompt['id'] === $id ) {
                // Check if user can delete this prompt (owner or admin)
                if ( $is_admin || ( isset( $prompt['user_id'] ) && $prompt['user_id'] === $current_user_id ) ) {
                    // Allow deletion, don't add to filtered array
                    set_transient( 'llmvm_notice', [ 'type' => 'success', 'msg' => __( 'Prompt deleted successfully.', 'llm-visibility-monitor' ) ], 60 );
                } else {
                    // Don't allow deletion, keep the prompt
                    $filtered_prompts[] = $prompt;
                    set_transient( 'llmvm_notice', [ 'type' => 'error', 'msg' => __( 'You can only delete your own prompts.', 'llm-visibility-monitor' ) ], 60 );
                }
            }
        }
        
        update_option( 'llmvm_prompts', $filtered_prompts, false );

        // Redirect back to the prompts page
        wp_safe_redirect( admin_url( 'tools.php?page=llmvm-prompts' ) );
        exit;
    }

    private function verify_permissions_and_nonce( string $action ): void {
        // Verify nonce with proper sanitization.
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }
    }

    /** Handle Delete Result */
    public function handle_delete_result(): void {
        if ( ! current_user_can( 'llmvm_view_results' ) ) {
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
            $deleted = LLMVM_Database::delete_results_by_ids( [ $id ] );
            
            if ( $deleted ) {
                set_transient( 'llmvm_notice', [ 'type' => 'success', 'msg' => __( 'Result deleted successfully.', 'llm-visibility-monitor' ) ], 60 );
                LLMVM_Logger::log( 'Result deleted by admin', [ 'id' => $id ] );
            } else {
                set_transient( 'llmvm_notice', [ 'type' => 'error', 'msg' => __( 'Failed to delete result.', 'llm-visibility-monitor' ) ], 60 );
            }
        }
        
        // Always redirect to a clean dashboard URL to prevent accidental Run Now triggers
        wp_safe_redirect( admin_url( 'tools.php?page=llmvm-dashboard' ) ?: '' );
        exit;
    }

    /** Handle Bulk Delete Results */
    public function handle_bulk_delete(): void {
        if ( ! current_user_can( 'llmvm_view_dashboard' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'llmvm_bulk_delete_results' ) ) {
            wp_die( esc_html__( 'Security check failed', 'llm-visibility-monitor' ) );
        }

        // Get bulk action and IDs
        $bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $result_ids = isset( $_POST['result_ids'] ) ? array_map( 'intval', (array) $_POST['result_ids'] ) : [];

        if ( 'delete' === $bulk_action && ! empty( $result_ids ) ) {
            $current_user_id = get_current_user_id();
            $is_admin = current_user_can( 'llmvm_manage_settings' );
            
            // Delete results with user filtering (unless admin)
            $user_filter = $is_admin ? 0 : $current_user_id;
            $deleted = LLMVM_Database::delete_results_by_ids( $result_ids, $user_filter );
            
            if ( $deleted > 0 ) {
                /* translators: %d: number of results deleted */
                set_transient( 'llmvm_notice', [ 'type' => 'success', 'msg' => sprintf( __( '%d results deleted successfully.', 'llm-visibility-monitor' ), $deleted ) ], 60 );
            } else {
                set_transient( 'llmvm_notice', [ 'type' => 'warning', 'msg' => __( 'No results were deleted. You can only delete your own results.', 'llm-visibility-monitor' ) ], 60 );
            }
        }

        // Redirect back to dashboard
        wp_safe_redirect( admin_url( 'tools.php?page=llmvm-dashboard' ) );
        exit;
    }


    /**
     * Fetch available models from OpenRouter API.
     */
    public static function get_openrouter_models(): array {
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

        // Check for cached models first
        $cached_models = get_transient( 'llmvm_openrouter_models' );
        if ( false !== $cached_models && is_array( $cached_models ) ) {
            return $cached_models;
        }

        LLMVM_Logger::log( 'Fetching OpenRouter models', [ 'key_length' => strlen( $decrypted_key ) ] );
        
        $response = wp_remote_get( 'https://openrouter.ai/api/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $decrypted_key,
                'HTTP-Referer'  => home_url(),
                'X-Title'       => 'LLM Visibility Monitor',
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            LLMVM_Logger::log( 'Failed to fetch OpenRouter models', [ 'error' => $response->get_error_message() ] );
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
            LLMVM_Logger::log( 'OpenRouter models API error', [ 'status' => $status_code ] );
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
            LLMVM_Logger::log( 'Invalid response from OpenRouter models API' );
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

        // Cache the models for 1 hour to reduce API calls
        set_transient( 'llmvm_openrouter_models', $models, HOUR_IN_SECONDS );

        return $models;
    }

    /**
     * Enqueue admin assets (CSS and JS).
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Only load on our plugin pages
        if ( ! in_array( $hook, [ 'settings_page_llmvm-settings', 'tools_page_llmvm-dashboard', 'tools_page_llmvm-result', 'tools_page_llmvm-prompts' ], true ) ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'llmvm-admin',
            LLMVM_PLUGIN_URL . 'assets/css/llmvm-admin.css',
            [],
            LLMVM_VERSION
        );

        // Enqueue jQuery UI for multi-select functionality on prompts page
        if ( 'tools_page_llmvm-prompts' === $hook ) {
            wp_enqueue_script( 'jquery-ui-core' );
            wp_enqueue_script( 'jquery-ui-widget' );
            wp_enqueue_script( 'jquery-ui-position' );
            wp_enqueue_script( 'jquery-ui-autocomplete' );
            wp_enqueue_style( 'jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css' );
        }

        // Enqueue JavaScript
        $dependencies = [ 'jquery' ];
        if ( 'tools_page_llmvm-prompts' === $hook ) {
            $dependencies[] = 'jquery-ui-autocomplete';
        }
        
        wp_enqueue_script(
            'llmvm-admin',
            LLMVM_PLUGIN_URL . 'assets/js/llmvm-admin.js',
            $dependencies,
            LLMVM_VERSION,
            true
        );
    }
}


