<?php
/**
 * Admin UI for settings, prompts CRUD, and dashboard page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Admin {

    /**
     * Convert UTC date to user's preferred timezone.
     */
    public static function convert_utc_to_user_timezone( $utc_date, $user_id = null ) {
        if ( empty( $utc_date ) ) {
            return '';
        }
        
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        // Get user's preferred timezone
        $user_timezone = get_user_meta( $user_id, 'llmvm_timezone', true );
        
        // Fallback to site timezone if user hasn't set one
        if ( empty( $user_timezone ) ) {
            $user_timezone = get_option( 'timezone_string' );
            if ( empty( $user_timezone ) ) {
                // Fallback to UTC offset if timezone_string is not set
                $gmt_offset = get_option( 'gmt_offset' );
                if ( $gmt_offset !== false ) {
                    $user_timezone = sprintf( '%+03d:00', $gmt_offset );
                } else {
                    $user_timezone = 'UTC';
                }
            }
        }
        
        // Handle both timestamps and date strings
        if ( is_numeric( $utc_date ) ) {
            // It's a timestamp
            $utc_datetime = new DateTime( '@' . $utc_date, new DateTimeZone( 'UTC' ) );
        } else {
            // It's a date string
            $utc_datetime = new DateTime( $utc_date, new DateTimeZone( 'UTC' ) );
        }
        
        // Convert to user's timezone
        $user_timezone_obj = new DateTimeZone( $user_timezone );
        $utc_datetime->setTimezone( $user_timezone_obj );
        
        // Get user's locale for proper language formatting
        $user_locale = get_user_locale( $user_id );
        $original_locale = get_locale();
        
        // Temporarily switch to user's locale for date formatting
        if ( $user_locale !== $original_locale ) {
            switch_to_locale( $user_locale );
        }
        
        // Use WordPress's built-in date formatting that automatically adapts to language
        // This will use appropriate date/time formats for each language
        // Format: "September 21, 2025 at 11:00 AM" (English) or equivalent in other languages
        $formatted_date = wp_date( 'F j, Y \a\t g:i A', $utc_datetime->getTimestamp() );
        
        // Restore original locale
        if ( $user_locale !== $original_locale ) {
            restore_previous_locale();
        }
        
        return $formatted_date;
    }

    /**
     * Get the next cron execution time for display.
     */
    public static function get_next_cron_execution_time( string $frequency = 'daily', string $prompt_id = '' ): string {
        // If we have a prompt ID, get the actual scheduled time
        if ( ! empty( $prompt_id ) ) {
            $hook = 'llmvm_run_prompt_' . $prompt_id;
            $next_run = wp_next_scheduled( $hook );
            
            if ( $next_run ) {
                // Convert to user's timezone
                return self::convert_utc_to_user_timezone( $next_run );
            }
        }
        
        // Fallback: Calculate next execution time based on frequency (for new prompts)
        // This should use the same logic as the cron scheduler
        $cron = new LLMVM_Cron();
        $next_run = $cron->calculate_distributed_run_time( $frequency, 'fallback_prompt' );
        
        return self::convert_utc_to_user_timezone( $next_run );
    }

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Hide specific menu items for LLM Manager roles
        add_action( 'admin_menu', [ $this, 'hide_menu_items_for_llm_managers' ], 999 );

        // Add Subscription menu item
        add_action( 'admin_menu', [ $this, 'add_subscription_menu' ], 1000 );
        
        // Cleanup orphaned cron jobs on admin init
        add_action( 'admin_init', [ $this, 'cleanup_orphaned_cron_jobs' ] );
        
        // Add reschedule action for existing crons
        add_action( 'wp_ajax_llmvm_reschedule_crons', [ $this, 'handle_reschedule_crons' ] );
        
        // Add cleanup action for old results
        add_action( 'wp_ajax_llmvm_cleanup_old_results', [ $this, 'handle_cleanup_old_results' ] );

        // Ensure LLM Manager users can access admin pages
        add_action( 'init', [ $this, 'ensure_admin_access' ], 5 );
        
        // Customize Tools page to hide "Available Tools" section
        add_action( 'load-tools.php', [ $this, 'customize_tools_page' ] );
        
        // Hide "Available Tools" menu item from sidebar
        add_action( 'admin_menu', [ $this, 'hide_available_tools_menu' ], 999 );
        
        // Redirect non-admin users away from WordPress dashboard
        add_action( 'admin_init', [ $this, 'redirect_non_admin_from_dashboard' ] );
        
        // Customize dashboard for LLM Manager users
        add_action( 'wp_dashboard_setup', [ $this, 'customize_dashboard_for_llm_managers' ] );
        
        

        // Customize admin bar for LLM Manager roles
        add_action( 'wp_before_admin_bar_render', [ $this, 'customize_admin_bar_for_llm_managers' ] );
        
        // Hide WordPress logo from admin bar for LLM Manager users
        add_action( 'init', [ $this, 'hide_wordpress_logo_from_admin_bar' ] );
        add_action( 'wp_before_admin_bar_render', [ $this, 'hide_wordpress_logo_css' ] );
        
        // Try removing the WordPress logo action even earlier
        add_action( 'wp_loaded', [ $this, 'remove_wordpress_logo_early' ] );
        
        // Try removing it after admin bar is set up
        add_action( 'admin_bar_menu', [ $this, 'remove_wordpress_logo_node' ], 1 );
        


        // Form handlers for prompts CRUD.
        add_action( 'admin_post_llmvm_add_prompt', [ $this, 'handle_add_prompt' ] );
        add_action( 'admin_post_llmvm_edit_prompt', [ $this, 'handle_edit_prompt' ] );
        add_action( 'admin_post_llmvm_delete_prompt', [ $this, 'handle_delete_prompt' ] );
        
        // AJAX handlers for progress tracking
        add_action( 'wp_ajax_llmvm_get_progress', [ $this, 'ajax_get_progress' ] );
        
        // AJAX handlers for queue management
        add_action( 'wp_ajax_llmvm_get_queue_status', [ $this, 'ajax_get_queue_status' ] );
        add_action( 'wp_ajax_llmvm_clear_queue', [ $this, 'ajax_clear_queue' ] );
        
        // Form handler for result deletion.
        add_action( 'admin_post_llmvm_delete_result', [ $this, 'handle_delete_result' ] );
        add_action( 'admin_post_llmvm_bulk_delete_results', [ $this, 'handle_bulk_delete' ] );
        
        // Role management handlers.
        add_action( 'admin_post_llmvm_change_user_plan', [ $this, 'handle_change_user_plan' ] );
        add_action( 'admin_post_llmvm_remove_llm_access', [ $this, 'handle_remove_llm_access' ] );
        
        
        // Add timezone field to user profile.
        add_action( 'show_user_profile', [ $this, 'add_timezone_field' ] );
        add_action( 'edit_user_profile', [ $this, 'add_timezone_field' ] );
        add_action( 'personal_options_update', [ $this, 'save_timezone_field' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_timezone_field' ] );
        
        // Login page customization hooks are now registered in main plugin file
    }

    /**
     * Hide specific menu items for LLM Manager roles.
     */
    public function hide_menu_items_for_llm_managers(): void {
        // Only apply to LLM Manager roles
        if ( ! current_user_can( 'llmvm_manage_prompts' ) || current_user_can( 'llmvm_manage_settings' ) ) {
            return;
        }

        // Remove Dashboard menu
        remove_menu_page( 'index.php' );

        // Remove Posts menu
        remove_menu_page( 'edit.php' );

        // Remove Comments menu
        remove_menu_page( 'edit-comments.php' );
    }

    /**
     * Customize admin bar for LLM Manager roles.
     */
    public function customize_admin_bar_for_llm_managers(): void {
        // Only apply to LLM Manager roles (not admins)
        if ( ! current_user_can( 'llmvm_manage_prompts' ) || current_user_can( 'llmvm_manage_settings' ) ) {
            return;
        }

        global $wp_admin_bar;

        // Remove comments/notifications node
        $wp_admin_bar->remove_node( 'comments' );

        // Remove the "New" dropdown menu
        $wp_admin_bar->remove_node( 'new-content' );

        // Remove other unnecessary admin bar items for LLM Managers
        $wp_admin_bar->remove_node( 'updates' ); // Updates notification
        $wp_admin_bar->remove_node( 'search' ); // Search box
    }

    /**
     * Add Subscription menu item that links to customer dashboard.
     */
    public function add_subscription_menu(): void {
        // Only show for users with LLM capabilities (not regular subscribers)
        if ( ! current_user_can( 'llmvm_manage_prompts' ) && ! current_user_can( 'llmvm_view_dashboard' ) ) {
            return;
        }

        // Add the Subscription menu item after Tools
        add_menu_page(
            __( 'Subscription', 'llm-visibility-monitor' ),
            __( 'Subscription', 'llm-visibility-monitor' ),
            'read', // Basic read capability
            'subscription',
            [ $this, 'subscription_page_content' ],
            'dashicons-cart', // Shopping cart icon
            61 // Position after Tools (which is at 60)
        );
    }

    /**
     * Subscription page content with JavaScript redirect.
     */
    public function subscription_page_content(): void {
        $redirect_url = home_url( '/customer-dashboard/' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Subscription', 'llm-visibility-monitor' ); ?></h1>
            <p><?php esc_html_e( 'Redirecting to your subscription dashboard...', 'llm-visibility-monitor' ); ?></p>
            <p><a href="<?php echo esc_url( $redirect_url ); ?>" class="button button-primary"><?php esc_html_e( 'Go to Subscription Dashboard', 'llm-visibility-monitor' ); ?></a></p>
        </div>
        
        <script type="text/javascript">
            // Redirect after a short delay
            setTimeout(function() {
                window.location.href = '<?php echo esc_js( $redirect_url ); ?>';
            }, 1000);
        </script>
        <?php
    }

    /**
     * Ensure LLM Manager users can access admin pages.
     */
    public function ensure_admin_access(): void {
        // Only run for logged-in users
        if ( ! is_user_logged_in() ) {
            return;
        }

        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            return;
        }

        // Check if user has any LLM Manager role
        $has_llm_role = false;
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ], true ) ) {
                $has_llm_role = true;
                break;
            }
        }

        // If user has LLM role, ensure they have the necessary capabilities
        if ( $has_llm_role ) {
            // Ensure they have level_1 capability for basic admin access
            if ( ! $current_user->has_cap( 'level_1' ) ) {
                $current_user->add_cap( 'level_1' );
            }
            
            // Ensure they have edit_posts capability to bypass SureCart restrictions
            if ( ! $current_user->has_cap( 'edit_posts' ) ) {
                $current_user->add_cap( 'edit_posts' );
            }
        }
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
        // Use null as parent to create a hidden page that doesn't appear in menus
        // This page should only be accessed via direct links from the dashboard
        $result_callback = [ $this, 'render_result_page' ];

        add_submenu_page(
            'tools.php', // Use tools.php as parent to avoid null issues
            $result_title,
            $result_title,
            'llmvm_view_results',
            $result_slug,
            $result_callback
        );

        // Queue management page - for users with dashboard access
        $queue_title = __( 'LLM Queue Status', 'llm-visibility-monitor' );
        $queue_slug = 'llmvm-queue';
        $queue_callback = [ $this, 'render_queue_page' ];

        add_management_page(
            $queue_title,
            $queue_title,
            'llmvm_view_dashboard',
            $queue_slug,
            $queue_callback
        );
    }



    /**
     * Register settings and fields.
     */
    public function register_settings(): void {
        register_setting( 'llmvm_settings', 'llmvm_options', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_options' ],
            'default'           => [ 'api_key' => '', 'cron_frequency' => 'daily', 'model' => 'openrouter/stub-model-v1', 'debug_logging' => false, 'login_custom_text' => '', 'comparison_model' => 'openai/gpt-4o-mini' ],
            'show_in_rest'      => false,
        ] );

        add_settings_section( 'llmvm_section_main', __( 'General Settings', 'llm-visibility-monitor' ), '__return_false', 'llmvm-settings' );

        add_settings_field( 'llmvm_api_key', __( 'OpenRouter API Key', 'llm-visibility-monitor' ), [ $this, 'field_api_key' ], 'llmvm-settings', 'llmvm_section_main' );

        add_settings_field( 'llmvm_model', __( 'Model', 'llm-visibility-monitor' ), [ $this, 'field_model' ], 'llmvm-settings', 'llmvm_section_main' );
        add_settings_field( 'llmvm_comparison_model', __( 'Comparison Model', 'llm-visibility-monitor' ), [ $this, 'field_comparison_model' ], 'llmvm-settings', 'llmvm_section_main' );
        add_settings_field( 'llmvm_debug_logging', __( 'Debug Logging', 'llm-visibility-monitor' ), [ $this, 'field_debug_logging' ], 'llmvm-settings', 'llmvm_section_main' );
        add_settings_field( 'llmvm_email_reports', __( 'Email Reports', 'llm-visibility-monitor' ), [ $this, 'field_email_reports' ], 'llmvm-settings', 'llmvm_section_main' );
        add_settings_field( 'llmvm_email_bcc_admin', __( 'BCC Admin on All Reports', 'llm-visibility-monitor' ), [ $this, 'field_email_bcc_admin' ], 'llmvm-settings', 'llmvm_section_main' );
        add_settings_field( 'llmvm_email_from_address', __( 'Email From Address', 'llm-visibility-monitor' ), [ $this, 'field_email_from_address' ], 'llmvm-settings', 'llmvm_section_main' );
        add_settings_field( 'llmvm_queue_concurrency', __( 'Queue Concurrency Limit', 'llm-visibility-monitor' ), [ $this, 'field_queue_concurrency' ], 'llmvm-settings', 'llmvm_section_main' );

        // Usage Limits Section
        add_settings_section( 'llmvm_section_limits', __( 'Usage Limits', 'llm-visibility-monitor' ), [ $this, 'section_limits_description' ], 'llmvm-settings' );

        add_settings_field( 'llmvm_free_max_prompts', __( 'Free Plan - Max Prompts', 'llm-visibility-monitor' ), [ $this, 'field_free_max_prompts' ], 'llmvm-settings', 'llmvm_section_limits' );
        add_settings_field( 'llmvm_free_max_models', __( 'Free Plan - Max Models per Prompt', 'llm-visibility-monitor' ), [ $this, 'field_free_max_models' ], 'llmvm-settings', 'llmvm_section_limits' );
        add_settings_field( 'llmvm_free_max_runs', __( 'Free Plan - Max Runs per Month', 'llm-visibility-monitor' ), [ $this, 'field_free_max_runs' ], 'llmvm-settings', 'llmvm_section_limits' );

        add_settings_field( 'llmvm_pro_max_prompts', __( 'Pro Plan - Max Prompts', 'llm-visibility-monitor' ), [ $this, 'field_pro_max_prompts' ], 'llmvm-settings', 'llmvm_section_limits' );
        add_settings_field( 'llmvm_pro_max_models', __( 'Pro Plan - Max Models per Prompt', 'llm-visibility-monitor' ), [ $this, 'field_pro_max_models' ], 'llmvm-settings', 'llmvm_section_limits' );
        add_settings_field( 'llmvm_pro_max_runs', __( 'Pro Plan - Max Runs per Month', 'llm-visibility-monitor' ), [ $this, 'field_pro_max_runs' ], 'llmvm-settings', 'llmvm_section_limits' );

        // User Role Management Section
        add_settings_section( 'llmvm_section_roles', __( 'User Role Management', 'llm-visibility-monitor' ), [ $this, 'section_roles_description' ], 'llmvm-settings' );

        // Login Page Customization Section
        add_settings_section( 'llmvm_section_login', __( 'Login Page Customization', 'llm-visibility-monitor' ), [ $this, 'section_login_description' ], 'llmvm-settings' );
        add_settings_field( 'llmvm_login_custom_text', __( 'Login Page Custom Text', 'llm-visibility-monitor' ), [ $this, 'field_login_custom_text' ], 'llmvm-settings', 'llmvm_section_login' );
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


        $model           = isset( $input['model'] ) ? sanitize_text_field( (string) $input['model'] ) : ( $options['model'] ?? 'openrouter/stub-model-v1' );
        $new['model']    = $model;
        $debug_logging   = ! empty( $input['debug_logging'] );
        $new['debug_logging'] = $debug_logging;
        
        $email_reports   = ! empty( $input['email_reports'] );
        $new['email_reports'] = $email_reports;
        
        $email_bcc_admin = ! empty( $input['email_bcc_admin'] );
        $new['email_bcc_admin'] = $email_bcc_admin;
        
        $email_from_address = isset( $input['email_from_address'] ) ? sanitize_email( (string) $input['email_from_address'] ) : '';
        $new['email_from_address'] = $email_from_address;
        
        // Queue concurrency setting
        $queue_concurrency = isset( $input['queue_concurrency'] ) ? max( 1, min( 5, (int) $input['queue_concurrency'] ) ) : 1;
        $new['queue_concurrency'] = $queue_concurrency;
        
        // Queue system is now always enabled

        // Usage limits
        $new['free_max_prompts'] = isset( $input['free_max_prompts'] ) ? max( 1, (int) $input['free_max_prompts'] ) : 3;
        $new['free_max_models'] = isset( $input['free_max_models'] ) ? max( 1, (int) $input['free_max_models'] ) : 3;
        $new['free_max_runs'] = isset( $input['free_max_runs'] ) ? max( 1, (int) $input['free_max_runs'] ) : 30;
        
        // Force clear any cached options to ensure fresh values
        wp_cache_delete( 'llmvm_options', 'options' );
        
        $new['pro_max_prompts'] = isset( $input['pro_max_prompts'] ) ? max( 1, (int) $input['pro_max_prompts'] ) : 10;
        $new['pro_max_models'] = isset( $input['pro_max_models'] ) ? max( 1, (int) $input['pro_max_models'] ) : 6;
        $new['pro_max_runs'] = isset( $input['pro_max_runs'] ) ? max( 1, (int) $input['pro_max_runs'] ) : 300;
        
        // Login page customization
        $new['login_custom_text'] = isset( $input['login_custom_text'] ) ? wp_kses_post( (string) $input['login_custom_text'] ) : '';
        
        $comparison_model = isset( $input['comparison_model'] ) ? sanitize_text_field( (string) $input['comparison_model'] ) : ( $options['comparison_model'] ?? 'openai/gpt-4o-mini' );
        $new['comparison_model'] = $comparison_model;

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

    /**
     * Render comparison model field.
     */
    public function field_comparison_model(): void {
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
        $value = isset( $options['comparison_model'] ) ? (string) $options['comparison_model'] : 'openai/gpt-4o-mini';
        
        // Get available models (will fallback to common models if API key decryption fails)
        $models = self::get_openrouter_models();
        
        echo '<select name="llmvm_options[comparison_model]" id="llmvm-comparison-model-select" class="llmvm-model-select">';
        echo '<option value="">' . esc_html__( 'Select a comparison model...', 'llm-visibility-monitor' ) . '</option>';
        
        foreach ( $models as $model ) {
            $selected = ( $model['id'] === $value ) ? ' selected="selected"' : '';
            echo '<option value="' . esc_attr( $model['id'] ) . '"' . esc_attr( $selected ) . '>';
            echo esc_html( $model['name'] . ' (' . $model['id'] . ')' );
            echo '</option>';
        }
        
        echo '</select>';
        
        echo '<p class="description">' . esc_html__( 'Select the model to use for comparing responses with expected answers. This model will evaluate how well each response matches the expected answer on a scale of 1-10.', 'llm-visibility-monitor' ) . '</p>';
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

    /** Render email BCC admin field */
    public function field_email_bcc_admin(): void {
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = ! empty( $options['email_bcc_admin'] );
        $admin_email = get_option( 'admin_email' );
        echo '<label><input type="checkbox" name="llmvm_options[email_bcc_admin]" value="1"' . checked( $value, true, false ) . ' /> ' . esc_html__( 'BCC administrator on all email reports', 'llm-visibility-monitor' ) . '</label>';
        echo '<p class="description">' . esc_html__( 'When enabled, all email reports sent to users will also be BCC\'d to the administrator email address', 'llm-visibility-monitor' ) . ' (' . esc_html( $admin_email ) . ').</p>';
    }

    /** Render email from address field */
    public function field_email_from_address(): void {
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['email_from_address'] ) ? (string) $options['email_from_address'] : '';
        echo '<input type="email" name="llmvm_options[email_from_address]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" />';
        echo '<p class="description">' . esc_html__( 'Email address to use as the "From" address for all WordPress emails (plugin emails, password resets, user notifications, etc.). Leave empty to use the WordPress admin email address.', 'llm-visibility-monitor' ) . '</p>';
    }

    /**
     * Show queue status information.
     */
    public function show_queue_status(): void {
        if ( class_exists( 'LLMVM_Queue_Manager' ) ) {
            $queue_manager = new LLMVM_Queue_Manager();
            $queue_status = $queue_manager->get_queue_status();
            echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Queue System Status:', 'llm-visibility-monitor' ) . '</strong> ';
            echo esc_html( sprintf( 
                __( 'Pending: %d, Processing: %d, Completed: %d, Failed: %d', 'llm-visibility-monitor' ),
                $queue_status['pending'],
                $queue_status['processing'],
                $queue_status['completed'],
                $queue_status['failed']
            ) );
            echo '</p><p>' . esc_html__( 'All LLM requests are processed asynchronously through the queue system to prevent timeouts.', 'llm-visibility-monitor' ) . '</p></div>';
        }
    }

    /**
     * Show planned cron prompt executions for all users.
     */
    public function show_planned_cron_executions(): void {
        $prompts = get_option( 'llmvm_prompts', [] );
        
        if ( empty( $prompts ) ) {
            echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Planned Cron Executions:', 'llm-visibility-monitor' ) . '</strong> ';
            echo esc_html__( 'No prompts scheduled for execution.', 'llm-visibility-monitor' );
            echo '</p></div>';
            return;
        }

        // Get all scheduled cron jobs for prompts
        $scheduled_prompts = [];
        foreach ( $prompts as $prompt ) {
            $prompt_id = $prompt['id'];
            $hook = 'llmvm_run_prompt_' . $prompt_id;
            $next_run = wp_next_scheduled( $hook );
            
            if ( $next_run ) {
                $scheduled_prompts[] = [
                    'prompt' => $prompt,
                    'next_run' => $next_run,
                    'hook' => $hook
                ];
            }
        }

        if ( empty( $scheduled_prompts ) ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Planned Cron Executions:', 'llm-visibility-monitor' ) . '</strong> ';
            echo esc_html__( 'No cron jobs scheduled for any prompts.', 'llm-visibility-monitor' );
            echo '</p></div>';
            return;
        }

        // Sort by next run time
        usort( $scheduled_prompts, function( $a, $b ) {
            return $a['next_run'] - $b['next_run'];
        } );

        echo '<div class="notice notice-info">';
        echo '<p><strong>' . esc_html__( 'Planned Cron Executions:', 'llm-visibility-monitor' ) . '</strong></p>';
        echo '<table class="widefat" style="margin-top: 10px;">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__( 'Prompt', 'llm-visibility-monitor' ) . '</th>';
        echo '<th>' . esc_html__( 'User', 'llm-visibility-monitor' ) . '</th>';
        echo '<th>' . esc_html__( 'Frequency', 'llm-visibility-monitor' ) . '</th>';
        echo '<th>' . esc_html__( 'Next Execution', 'llm-visibility-monitor' ) . '</th>';
        echo '<th>' . esc_html__( 'Models', 'llm-visibility-monitor' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ( $scheduled_prompts as $scheduled ) {
            $prompt = $scheduled['prompt'];
            $next_run = $scheduled['next_run'];
            
            // Get user info
            $user = get_user_by( 'id', $prompt['user_id'] );
            $user_name = $user ? $user->display_name : sprintf( __( 'User ID: %d', 'llm-visibility-monitor' ), $prompt['user_id'] );
            
            // Format next execution time
            $formatted_time = self::convert_utc_to_user_timezone( gmdate( 'Y-m-d H:i:s', $next_run ), $prompt['user_id'] );
            $relative_time = human_time_diff( time(), $next_run );
            
            // Truncate prompt text if too long
            $prompt_text = $prompt['text'];
            if ( strlen( $prompt_text ) > 50 ) {
                $prompt_text = substr( $prompt_text, 0, 47 ) . '...';
            }
            
            // Format models
            $models = is_array( $prompt['models'] ) ? implode( ', ', $prompt['models'] ) : $prompt['models'];
            if ( strlen( $models ) > 30 ) {
                $models = substr( $models, 0, 27 ) . '...';
            }
            
            echo '<tr>';
            echo '<td>' . esc_html( $prompt_text ) . '</td>';
            echo '<td>' . esc_html( $user_name ) . '</td>';
            echo '<td>' . esc_html( ucfirst( $prompt['cron_frequency'] ) ) . '</td>';
            echo '<td>' . esc_html( $formatted_time ) . '<br><small>' . esc_html( sprintf( __( 'in %s', 'llm-visibility-monitor' ), $relative_time ) ) . '</small></td>';
            echo '<td>' . esc_html( $models ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '<p><small>' . esc_html__( 'All times are displayed in the respective user\'s timezone.', 'llm-visibility-monitor' ) . '</small></p>';
        
        // Add reschedule and cleanup buttons
        echo '<p style="margin-top: 10px;">';
        echo '<button type="button" id="reschedule-crons-btn" class="button button-secondary">';
        echo esc_html__( 'Reschedule All Crons with New Logic', 'llm-visibility-monitor' );
        echo '</button>';
        echo ' <button type="button" id="cleanup-old-results-btn" class="button button-secondary">';
        echo esc_html__( 'Clean Up Old Results', 'llm-visibility-monitor' );
        echo '</button>';
        echo ' <span id="reschedule-status" style="margin-left: 10px;"></span>';
        echo ' <span id="cleanup-status" style="margin-left: 10px;"></span>';
        echo '</p>';
        
        echo '</div>';
    }

    /**
     * Render the queue concurrency field.
     */
    public function field_queue_concurrency(): void {
        $options = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['queue_concurrency'] ) ? (int) $options['queue_concurrency'] : 1;
        echo '<input type="number" name="llmvm_options[queue_concurrency]" value="' . esc_attr( $value ) . '" min="1" max="5" step="1" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of jobs to process simultaneously. Recommended: 1 for shared hosting, 2-3 for VPS/dedicated servers.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render usage limits section description */
    public function section_limits_description(): void {
        echo '<p>' . esc_html__( 'Configure usage limits for Free and Pro user plans. These limits control how many prompts users can create, how many models they can select per prompt, and how many runs they can execute per month.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render free max prompts field */
    public function field_free_max_prompts(): void {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['free_max_prompts'] ) ? (int) $options['free_max_prompts'] : 3;
        echo '<input type="number" name="llmvm_options[free_max_prompts]" value="' . esc_attr( $value ) . '" min="1" class="small-text" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of prompts Free plan users can create.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render free max models field */
    public function field_free_max_models(): void {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['free_max_models'] ) ? (int) $options['free_max_models'] : 3;
        echo '<input type="number" name="llmvm_options[free_max_models]" value="' . esc_attr( $value ) . '" min="1" class="small-text" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of models Free plan users can select per prompt.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render free max runs field */
    public function field_free_max_runs(): void {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['free_max_runs'] ) ? (int) $options['free_max_runs'] : 30;
        
        // Force clear any cached options to ensure fresh values
        wp_cache_delete( 'llmvm_options', 'options' );
        $options = get_option( 'llmvm_options', [] );
        $value = isset( $options['free_max_runs'] ) ? (int) $options['free_max_runs'] : 30;
        
        echo '<input type="number" name="llmvm_options[free_max_runs]" value="' . esc_attr( $value ) . '" min="1" class="small-text" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of runs Free plan users can execute per month.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render pro max prompts field */
    public function field_pro_max_prompts(): void {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['pro_max_prompts'] ) ? (int) $options['pro_max_prompts'] : 10;
        echo '<input type="number" name="llmvm_options[pro_max_prompts]" value="' . esc_attr( $value ) . '" min="1" class="small-text" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of prompts Pro plan users can create.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render pro max models field */
    public function field_pro_max_models(): void {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['pro_max_models'] ) ? (int) $options['pro_max_models'] : 6;
        echo '<input type="number" name="llmvm_options[pro_max_models]" value="' . esc_attr( $value ) . '" min="1" class="small-text" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of models Pro plan users can select per prompt.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render pro max runs field */
    public function field_pro_max_runs(): void {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['pro_max_runs'] ) ? (int) $options['pro_max_runs'] : 300;
        echo '<input type="number" name="llmvm_options[pro_max_runs]" value="' . esc_attr( $value ) . '" min="1" class="small-text" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of runs Pro plan users can execute per month.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render user role management section description */
    public function section_roles_description(): void {
        echo '<p>' . esc_html__( 'Manage users with LLM Manager roles. You can assign users to Free or Pro plans, or remove their LLM Manager access.', 'llm-visibility-monitor' ) . '</p>';
        
        // Get users with LLM Manager roles
        $free_users = get_users( array( 'role' => 'llm_manager_free' ) );
        $pro_users = get_users( array( 'role' => 'llm_manager_pro' ) );
        
        if ( empty( $free_users ) && empty( $pro_users ) ) {
            echo '<p><em>' . esc_html__( 'No users currently have LLM Manager roles assigned.', 'llm-visibility-monitor' ) . '</em></p>';
            return;
        }
        
        echo '<div style="margin-top: 20px;">';
        
        if ( ! empty( $free_users ) ) {
            echo '<h4>' . esc_html__( 'LLM Manager Free Users', 'llm-visibility-monitor' ) . '</h4>';
            echo '<table class="widefat" style="margin-bottom: 20px;">';
            echo '<thead><tr><th>' . esc_html__( 'User', 'llm-visibility-monitor' ) . '</th><th>' . esc_html__( 'Email', 'llm-visibility-monitor' ) . '</th><th>' . esc_html__( 'Actions', 'llm-visibility-monitor' ) . '</th></tr></thead>';
            echo '<tbody>';
            foreach ( $free_users as $user ) {
                echo '<tr>';
                echo '<td>' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_login ) . ')</td>';
                echo '<td>' . esc_html( $user->user_email ) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $user->ID ) ) . '" class="button button-small">' . esc_html__( 'Edit User', 'llm-visibility-monitor' ) . '</a> ';
                echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_change_user_plan&user_id=' . $user->ID . '&plan=pro' ), 'llmvm_change_user_plan' ) ) . '" class="button button-small button-primary">' . esc_html__( 'Upgrade to Pro', 'llm-visibility-monitor' ) . '</a> ';
                echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_remove_llm_access&user_id=' . $user->ID ), 'llmvm_remove_llm_access' ) ) . '" class="button button-small" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to remove LLM Manager access from this user?', 'llm-visibility-monitor' ) ) . '\')">' . esc_html__( 'Remove Access', 'llm-visibility-monitor' ) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        
        if ( ! empty( $pro_users ) ) {
            echo '<h4>' . esc_html__( 'LLM Manager Pro Users', 'llm-visibility-monitor' ) . '</h4>';
            echo '<table class="widefat">';
            echo '<thead><tr><th>' . esc_html__( 'User', 'llm-visibility-monitor' ) . '</th><th>' . esc_html__( 'Email', 'llm-visibility-monitor' ) . '</th><th>' . esc_html__( 'Actions', 'llm-visibility-monitor' ) . '</th></tr></thead>';
            echo '<tbody>';
            foreach ( $pro_users as $user ) {
                echo '<tr>';
                echo '<td>' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_login ) . ')</td>';
                echo '<td>' . esc_html( $user->user_email ) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $user->ID ) ) . '" class="button button-small">' . esc_html__( 'Edit User', 'llm-visibility-monitor' ) . '</a> ';
                echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_change_user_plan&user_id=' . $user->ID . '&plan=free' ), 'llmvm_change_user_plan' ) ) . '" class="button button-small">' . esc_html__( 'Downgrade to Free', 'llm-visibility-monitor' ) . '</a> ';
                echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_remove_llm_access&user_id=' . $user->ID ), 'llmvm_remove_llm_access' ) ) . '" class="button button-small" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to remove LLM Manager access from this user?', 'llm-visibility-monitor' ) ) . '\')">' . esc_html__( 'Remove Access', 'llm-visibility-monitor' ) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }

    /** Render login page customization section description */
    public function section_login_description(): void {
        echo '<p>' . esc_html__( 'Customize the WordPress login page with your own branding. The login page will show "LLM Visibility Monitor" instead of the WordPress logo, and you can add custom text below it.', 'llm-visibility-monitor' ) . '</p>';
    }

    /** Render login custom text field */
    public function field_login_custom_text(): void {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $value = isset( $options['login_custom_text'] ) ? (string) $options['login_custom_text'] : '';
        
        echo '<textarea name="llmvm_options[login_custom_text]" id="llmvm_login_custom_text" rows="5" cols="50" class="large-text">' . esc_textarea( $value ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Enter custom text to display below the site name on the login page. You can use HTML tags like &lt;strong&gt;, &lt;em&gt;, and &lt;a&gt; for formatting.', 'llm-visibility-monitor' ) . '</p>';
        echo '<p class="description">' . esc_html__( 'Example: &lt;strong&gt;Welcome!&lt;/strong&gt; Please log in to access &lt;a href="https://docs.openstream.ch"&gt;documentation&lt;/a&gt;.', 'llm-visibility-monitor' ) . '</p>';
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
        
        if ( ! defined( 'LLMVM_PLUGIN_DIR' ) || empty( LLMVM_PLUGIN_DIR ) ) {
            return;
        }
        $prompts_file = LLMVM_PLUGIN_DIR . 'includes/views/prompts-page.php';
        if ( is_file( $prompts_file ) && is_string( $prompts_file ) ) {
            // Pass the prompts variable to the view
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

        // Set page title to prevent WordPress from using null values
        add_filter( 'admin_title', function( $title ) {
            $blog_name = get_bloginfo( 'name' );
            return 'LLM Visibility Dashboard - ' . ( $blog_name ?: 'WordPress' );
        } );
        
        // Ensure page title is never null
        add_filter( 'admin_title', function( $title ) {
            if ( $title === null || $title === '' ) {
                return 'LLM Visibility Dashboard - WordPress';
            }
            return $title;
        }, 1 );
        
        // Ensure WordPress constants are properly set
        if ( ! defined( 'WP_ADMIN' ) ) {
            define( 'WP_ADMIN', true );
        }
        
        // Ensure all required WordPress functions are available and not null
        if ( ! function_exists( 'get_bloginfo' ) ) {
            return;
        }
        
        // Ensure blog name is not null
        $blog_name = get_bloginfo( 'name' );
        if ( empty( $blog_name ) ) {
            $blog_name = 'WordPress';
        }

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

    /** Render queue management page */
    public function render_queue_page(): void {
        if ( ! current_user_can( 'llmvm_view_dashboard' ) ) {
            return;
        }

        // Get current user info for filtering
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'llmvm_manage_settings' );

        // Get queue manager
        $queue_manager = class_exists( 'LLMVM_Queue_Manager' ) ? new LLMVM_Queue_Manager() : null;
        
        if ( ! $queue_manager ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Queue Status', 'llm-visibility-monitor' ) . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Queue system is not available.', 'llm-visibility-monitor' ) . '</p></div></div>';
            return;
        }

        // Get user filter for queue data
        $user_filter = $is_admin ? null : $current_user_id;
        
        // Get queue status (filtered by user if not admin)
        $queue_status = $queue_manager->get_queue_status( $user_filter );
        
        // Get queue jobs (filtered by user if not admin)
        $queue_jobs = $queue_manager->get_queue_jobs( $user_filter, null, 100 );

        if ( ! defined( 'LLMVM_PLUGIN_DIR' ) || empty( LLMVM_PLUGIN_DIR ) ) {
            return;
        }
        
        // Include the queue page view
        $queue_file = LLMVM_PLUGIN_DIR . 'includes/views/queue-page.php';
        if ( is_file( $queue_file ) && is_string( $queue_file ) ) {
            include $queue_file;
        } else {
            // Fallback if view file doesn't exist
            $this->render_queue_page_fallback( $queue_status, $queue_jobs, $is_admin );
        }
    }

    /** Fallback queue page if view file doesn't exist */
    private function render_queue_page_fallback( array $queue_status, array $queue_jobs, bool $is_admin ): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'LLM Queue Status', 'llm-visibility-monitor' ) . '</h1>';
        
        // Queue status summary
        echo '<div class="llmvm-queue-status">';
        echo '<h2>' . esc_html__( 'Queue Status', 'llm-visibility-monitor' ) . '</h2>';
        echo '<div class="llmvm-status-cards">';
        echo '<div class="llmvm-status-card pending"><strong>' . esc_html__( 'Pending:', 'llm-visibility-monitor' ) . '</strong> ' . esc_html( $queue_status['pending'] ) . '</div>';
        echo '<div class="llmvm-status-card processing"><strong>' . esc_html__( 'Processing:', 'llm-visibility-monitor' ) . '</strong> ' . esc_html( $queue_status['processing'] ) . '</div>';
        echo '<div class="llmvm-status-card completed"><strong>' . esc_html__( 'Completed:', 'llm-visibility-monitor' ) . '</strong> ' . esc_html( $queue_status['completed'] ) . '</div>';
        echo '<div class="llmvm-status-card failed"><strong>' . esc_html__( 'Failed:', 'llm-visibility-monitor' ) . '</strong> ' . esc_html( $queue_status['failed'] ) . '</div>';
        echo '</div>';
        echo '</div>';

        // Queue jobs table
        echo '<h2>' . esc_html__( 'Recent Jobs', 'llm-visibility-monitor' ) . '</h2>';
        if ( empty( $queue_jobs ) ) {
            echo '<p>' . esc_html__( 'No jobs in queue.', 'llm-visibility-monitor' ) . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'ID', 'llm-visibility-monitor' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'llm-visibility-monitor' ) . '</th>';
            echo '<th>' . esc_html__( 'Model', 'llm-visibility-monitor' ) . '</th>';
            echo '<th>' . esc_html__( 'Response Time', 'llm-visibility-monitor' ) . '</th>';
            echo '<th>' . esc_html__( 'Execution Time', 'llm-visibility-monitor' ) . '</th>';
            echo '<th>' . esc_html__( 'Created', 'llm-visibility-monitor' ) . '</th>';
            if ( $is_admin ) {
                echo '<th>' . esc_html__( 'User', 'llm-visibility-monitor' ) . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ( $queue_jobs as $job ) {
                $job_data = $job['job_data'] ?? array();
                $model = $job_data['model'] ?? 'Unknown';
                $user_id = $job_data['user_id'] ?? 0;
                $user_name = $user_id ? get_user_by( 'id', $user_id )->display_name ?? 'User ' . $user_id : 'Unknown';
                
                // Calculate execution time
                $execution_time = '';
                if ( $job['status'] === 'completed' && ! empty( $job['completed_at'] ) ) {
                    $start_time = strtotime( $job['created_at'] );
                    $end_time = strtotime( $job['completed_at'] );
                    $execution_seconds = $end_time - $start_time;
                    $execution_time = $execution_seconds . 's';
                } elseif ( $job['status'] === 'processing' && ! empty( $job['started_at'] ) ) {
                    $start_time = strtotime( $job['started_at'] );
                    $current_time = time();
                    $execution_seconds = $current_time - $start_time;
                    $execution_time = $execution_seconds . 's (running)';
                } else {
                    $execution_time = '-';
                }
                
                // Get response time from job data
                $response_time = '';
                if ( isset( $job_data['response_time'] ) && is_numeric( $job_data['response_time'] ) ) {
                    $response_time = round( $job_data['response_time'] * 1000, 0 ) . 'ms';
                } else {
                    $response_time = '-';
                }
                
                echo '<tr>';
                echo '<td>' . esc_html( $job['id'] ) . '</td>';
                echo '<td><span class="llmvm-status-' . esc_attr( $job['status'] ) . '">' . esc_html( ucfirst( $job['status'] ) ) . '</span></td>';
                echo '<td>' . esc_html( $model ) . '</td>';
                echo '<td><span class="llmvm-metric-value">' . esc_html( $response_time ) . '</span></td>';
                echo '<td><span class="llmvm-metric-value">' . esc_html( $execution_time ) . '</span></td>';
                echo '<td>' . esc_html( $job['created_at'] ) . '</td>';
                if ( $is_admin ) {
                    echo '<td>' . esc_html( $user_name ) . '</td>';
                }
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }

    /** AJAX handler for getting queue status */
    public function ajax_get_queue_status(): void {
        $start_time = microtime( true );
        
        if ( ! current_user_can( 'llmvm_view_dashboard' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }

        if ( ! class_exists( 'LLMVM_Queue_Manager' ) ) {
            wp_send_json_error( array( 'message' => __( 'Queue system not available', 'llm-visibility-monitor' ) ) );
        }

        // Get current user info for filtering
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'llmvm_manage_settings' );
        
        // Get user filter for queue data
        $user_filter = $is_admin ? null : $current_user_id;
        
        $queue_manager = new LLMVM_Queue_Manager();
        $queue_status = $queue_manager->get_queue_status( $user_filter );
        
        // Get queue jobs (filtered by user if not admin) - limit to 20 for better performance
        $queue_jobs = $queue_manager->get_queue_jobs( $user_filter, null, 20 );

        // Calculate time ago for each job (using server time for accurate calculation)
        foreach ( $queue_jobs as &$job ) {
            // Use server time for accurate time difference calculation
            $created_time = strtotime( $job['created_at'] );
            $current_time = time(); // Use server time for accurate calculation
            
            $time_diff = $current_time - $created_time;
            
            if ( $time_diff < 60 ) {
                $job['time_ago'] = 'Just now';
            } elseif ( $time_diff < 3600 ) {
                $minutes = floor( $time_diff / 60 );
                $job['time_ago'] = $minutes . ' minute' . ( $minutes > 1 ? 's' : '' ) . ' ago';
            } elseif ( $time_diff < 86400 ) {
                $hours = floor( $time_diff / 3600 );
                $job['time_ago'] = $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ago';
            } else {
                $days = floor( $time_diff / 86400 );
                $job['time_ago'] = $days . ' day' . ( $days > 1 ? 's' : '' ) . ' ago';
            }
        }

        $total_time = microtime( true ) - $start_time;
        
        // Log performance for debugging
        LLMVM_Logger::log( 'Queue status AJAX response', array(
            'user_id' => $current_user_id,
            'is_admin' => $is_admin,
            'jobs_count' => count( $queue_jobs ),
            'response_time_ms' => round( $total_time * 1000, 2 ),
            'status' => $queue_status
        ) );

        wp_send_json_success( array(
            'status' => $queue_status,
            'jobs' => $queue_jobs
        ) );
    }

    /** AJAX handler for clearing queue */
    public function ajax_clear_queue(): void {
        if ( ! current_user_can( 'llmvm_manage_settings' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }

        if ( ! class_exists( 'LLMVM_Queue_Manager' ) ) {
            wp_send_json_error( array( 'message' => __( 'Queue system not available', 'llm-visibility-monitor' ) ) );
        }

        $queue_manager = new LLMVM_Queue_Manager();
        $queue_manager->clear_queue();

        wp_send_json_success( array( 'message' => __( 'Queue cleared successfully', 'llm-visibility-monitor' ) ) );
    }



    /**
     * Redirect non-admin users away from WordPress dashboard to LLM Prompts page.
     */
    public function redirect_non_admin_from_dashboard(): void {
        // Only run on admin pages
        if ( ! is_admin() ) {
            return;
        }
        
        // Get current screen
        $current_screen = get_current_screen();
        if ( ! $current_screen ) {
            return;
        }
        
        // Only redirect from the main dashboard (index.php)
        if ( $current_screen->id !== 'dashboard' ) {
            return;
        }
        
        // Check if user is administrator
        if ( current_user_can( 'manage_options' ) ) {
            return; // Administrators can access the dashboard
        }
        
        // Check if user has any LLM Manager role
        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            return;
        }
        
        $has_llm_role = false;
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ], true ) ) {
                $has_llm_role = true;
                break;
            }
        }
        
        // Only redirect LLM Manager users
        if ( ! $has_llm_role ) {
            return;
        }
        
        // Check if user can access prompts page
        if ( ! current_user_can( 'llmvm_manage_prompts' ) ) {
            // If they can't access prompts, redirect to dashboard page instead
            if ( current_user_can( 'llmvm_view_dashboard' ) ) {
                $redirect_url = admin_url( 'tools.php?page=llmvm-dashboard' );
            } else {
                // If they can't access anything, let them stay on dashboard
                return;
            }
        } else {
            // Redirect to prompts page
            $redirect_url = admin_url( 'tools.php?page=llmvm-prompts' );
        }
        
        // Perform the redirect
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Customize dashboard for LLM Manager users.
     */
    public function customize_dashboard_for_llm_managers(): void {
        // Only customize for LLM Manager users
        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            return;
        }
        
        // Check if user has any LLM Manager role
        $has_llm_role = false;
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ], true ) ) {
                $has_llm_role = true;
                break;
            }
        }
        
        // Only customize for LLM Manager users
        if ( ! $has_llm_role ) {
            return;
        }
        
        // Remove WordPress default widgets that aren't relevant
        remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
        remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );
        remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
        remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
        remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
        remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
        remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' ); // "At a Glance" widget
        
        // Add custom LLM Visibility Monitor widget
        wp_add_dashboard_widget(
            'llmvm_dashboard_widget',
            __( 'LLM Visibility Monitor', 'llm-visibility-monitor' ),
            [ $this, 'render_llmvm_dashboard_widget' ]
        );
        
        // Add CSS to clean up the dashboard
        add_action( 'admin_head', [ $this, 'add_dashboard_cleanup_css' ] );
    }
    
    /**
     * Render custom LLM Visibility Monitor dashboard widget.
     */
    public function render_llmvm_dashboard_widget(): void {
        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            return;
        }
        
        echo '<div class="llmvm-dashboard-widget">';
        echo '<h3>' . esc_html__( 'Welcome to LLM Visibility Monitor', 'llm-visibility-monitor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Manage your LLM prompts and monitor their visibility across different models.', 'llm-visibility-monitor' ) . '</p>';
        
        echo '<div class="llmvm-dashboard-actions">';
        
        // Show appropriate actions based on user capabilities
        if ( current_user_can( 'llmvm_manage_prompts' ) ) {
            echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=llmvm-prompts' ) ) . '" class="button button-primary">';
            echo esc_html__( 'Manage Prompts', 'llm-visibility-monitor' );
            echo '</a></p>';
        }
        
        if ( current_user_can( 'llmvm_view_dashboard' ) ) {
            echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=llmvm-dashboard' ) ) . '" class="button">';
            echo esc_html__( 'View Results', 'llm-visibility-monitor' );
            echo '</a></p>';
        }
        
        if ( current_user_can( 'llmvm_view_dashboard' ) ) {
            echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=llmvm-queue' ) ) . '" class="button">';
            echo esc_html__( 'Queue Status', 'llm-visibility-monitor' );
            echo '</a></p>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Add some basic styling
        echo '<style>
        .llmvm-dashboard-widget {
            padding: 10px 0;
        }
        .llmvm-dashboard-widget h3 {
            margin-top: 0;
        }
        .llmvm-dashboard-actions p {
            margin: 10px 0;
        }
        .llmvm-dashboard-actions .button {
            margin-right: 10px;
        }
        </style>';
    }

    /**
     * Hide WordPress logo from admin bar for LLM Manager users.
     */
    public function hide_wordpress_logo_from_admin_bar(): void {
        // Only hide for LLM Manager users
        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            return;
        }
        
        // Check if user has any LLM Manager role
        $has_llm_role = false;
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ], true ) ) {
                $has_llm_role = true;
                break;
            }
        }
        
        // Only hide for LLM Manager users
        if ( ! $has_llm_role ) {
            return;
        }
        
        // Debug: Log that we're trying to hide the logo
        error_log( 'LLMVM: Attempting to hide WordPress logo for user with roles: ' . implode( ', ', $current_user->roles ) );
        
        // Remove WordPress logo from admin bar (it's added with priority 10)
        $removed = remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu', 10 );
        error_log( 'LLMVM: WordPress logo removal result: ' . ( $removed ? 'success' : 'failed' ) );
        
        // Just hide the WordPress logo, don't add custom branding
    }
    
    /**
     * Remove WordPress logo action early for LLM Manager users.
     */
    public function remove_wordpress_logo_early(): void {
        // Only hide for LLM Manager users
        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            return;
        }
        
        // Check if user has any LLM Manager role
        $has_llm_role = false;
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ], true ) ) {
                $has_llm_role = true;
                break;
            }
        }
        
        // Only hide for LLM Manager users
        if ( ! $has_llm_role ) {
            return;
        }
        
        // Try removing the WordPress logo action with different priorities
        $removed1 = remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu', 10 );
        $removed2 = remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu', 0 );
        $removed3 = remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu', 999 );
        
        error_log( 'LLMVM: Early removal attempts - priority 10: ' . ( $removed1 ? 'success' : 'failed' ) . ', priority 0: ' . ( $removed2 ? 'success' : 'failed' ) . ', priority 999: ' . ( $removed3 ? 'success' : 'failed' ) );
    }
    
    /**
     * Remove WordPress logo node from admin bar directly.
     */
    public function remove_wordpress_logo_node( $wp_admin_bar ): void {
        // Only hide for LLM Manager users
        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            return;
        }
        
        // Check if user has any LLM Manager role
        $has_llm_role = false;
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ], true ) ) {
                $has_llm_role = true;
                break;
            }
        }
        
        // Only hide for LLM Manager users
        if ( ! $has_llm_role ) {
            return;
        }
        
        // Try to remove the WordPress logo node directly
        $wp_admin_bar->remove_node( 'wp-logo' );
        error_log( 'LLMVM: Attempted to remove wp-logo node directly from admin bar' );
    }
    
    /**
     * Hide WordPress logo using CSS for LLM Manager users.
     */
    public function hide_wordpress_logo_css(): void {
        // Debug: Always log this method is called
        error_log( 'LLMVM: hide_wordpress_logo_css() method called' );
        
        // Only hide for LLM Manager users
        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            error_log( 'LLMVM: No current user found' );
            return;
        }
        
        // Debug: Log user info
        error_log( 'LLMVM: Current user ID: ' . $current_user->ID . ', roles: ' . implode( ', ', $current_user->roles ) );
        
        // Check if user has any LLM Manager role
        $has_llm_role = false;
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ], true ) ) {
                $has_llm_role = true;
                break;
            }
        }
        
        // Debug: Log role check result
        error_log( 'LLMVM: Has LLM role: ' . ( $has_llm_role ? 'yes' : 'no' ) );
        
        // Only hide for LLM Manager users
        if ( ! $has_llm_role ) {
            error_log( 'LLMVM: User does not have LLM role, skipping logo hiding' );
            return;
        }
        
        // Add CSS and JavaScript directly using output buffering
        
        // Use output buffering to add CSS and JavaScript
        ob_start();
        ?>
        <style>
        /* Hide WordPress logo and its dropdown for LLM Manager users */
        #wp-admin-bar-wp-logo {
            display: none !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
        }
        /* Hide the logo item and icon */
        #wp-admin-bar-wp-logo .ab-item,
        #wp-admin-bar-wp-logo .ab-item:before,
        #wp-admin-bar-wp-logo .ab-icon {
            display: none !important;
            visibility: hidden !important;
        }
        /* Hide the dropdown menu */
        #wp-admin-bar-wp-logo .ab-sub-wrapper {
            display: none !important;
            visibility: hidden !important;
        }
        </style>
        <script>
        // JavaScript to hide WordPress logo immediately
        var logo = document.getElementById('wp-admin-bar-wp-logo');
        if (logo) {
            logo.style.display = 'none';
            logo.style.visibility = 'hidden';
            logo.style.width = '0';
            logo.style.height = '0';
            logo.style.overflow = 'hidden';
        }
        
        // Also try on DOMContentLoaded as fallback
        document.addEventListener('DOMContentLoaded', function() {
            var logo = document.getElementById('wp-admin-bar-wp-logo');
            if (logo) {
                logo.style.display = 'none';
                logo.style.visibility = 'hidden';
                logo.style.width = '0';
                logo.style.height = '0';
                logo.style.overflow = 'hidden';
            }
        });
        
        // Also try on window load as final fallback
        window.addEventListener('load', function() {
            var logo = document.getElementById('wp-admin-bar-wp-logo');
            if (logo) {
                logo.style.display = 'none';
                logo.style.visibility = 'hidden';
                logo.style.width = '0';
                logo.style.height = '0';
                logo.style.overflow = 'hidden';
            }
        });
        </script>
        <?php
        $output = ob_get_clean();
        
        // Add CSS to admin_head specifically
        add_action( 'admin_head', function() use ( $output ) {
            echo $output;
        } );
        
        // Add JavaScript to admin_footer (where it's working)
        add_action( 'admin_footer', function() use ( $output ) {
            // Extract only the JavaScript part
            if ( preg_match( '/<script>(.*?)<\/script>/s', $output, $matches ) ) {
                echo '<script>' . $matches[1] . '</script>';
            }
        } );
    }
    
    /**
     * Add custom branding to admin bar for LLM Manager users.
     */
    public function add_custom_admin_bar_branding( $wp_admin_bar ): void {
        // Only add for LLM Manager users
        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            return;
        }
        
        // Check if user has any LLM Manager role
        $has_llm_role = false;
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ], true ) ) {
                $has_llm_role = true;
                break;
            }
        }
        
        // Only add for LLM Manager users
        if ( ! $has_llm_role ) {
            return;
        }
        
        // Don't add custom branding - it's already in the sidebar
        
        // No custom branding needed - already in sidebar
    }

    /**
     * Add CSS to clean up the dashboard for LLM Manager users.
     */
    public function add_dashboard_cleanup_css(): void {
        // Only add CSS for LLM Manager users
        $current_user = wp_get_current_user();
        if ( ! $current_user ) {
            return;
        }
        
        // Check if user has any LLM Manager role
        $has_llm_role = false;
        foreach ( $current_user->roles as $role ) {
            if ( in_array( $role, [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ], true ) ) {
                $has_llm_role = true;
                break;
            }
        }
        
        // Only add CSS for LLM Manager users
        if ( ! $has_llm_role ) {
            return;
        }
        
        ?>
        <style>
        /* Hide the "Drag boxes here" areas */
        .postbox-container .empty-container {
            display: none !important;
        }
        
        /* Hide empty meta boxes */
        .meta-box-sortables .empty-container {
            display: none !important;
        }
        
        /* Hide the "Welcome to WordPress" panel if it exists */
        .welcome-panel {
            display: none !important;
        }
        
        /* Make the dashboard layout cleaner */
        .dashboard-widgets-wrap .postbox-container {
            width: 100% !important;
        }
        
        /* Hide any remaining empty containers */
        .postbox-container:empty {
            display: none !important;
        }
        
        /* Ensure our widget takes full width */
        #llmvm_dashboard_widget {
            width: 100% !important;
        }
        
        /* Hide the "Screen Options" and "Help" tabs for cleaner look */
        .screen-meta-toggle {
            display: none !important;
        }
        </style>
        <?php
    }

    /**
     * Hide "Available Tools" menu item from sidebar.
     */
    public function hide_available_tools_menu(): void {
        // Use CSS to hide the "Available Tools" menu item instead of removing it
        // This prevents access issues while still hiding the unwanted item
        add_action( 'admin_head', [ $this, 'hide_available_tools_menu_css' ] );
    }

    /**
     * Add CSS to hide "Available Tools" menu item.
     */
    public function hide_available_tools_menu_css(): void {
        ?>
        <style>
            /* Hide the "Available Tools" menu item from the Tools submenu */
            .wp-submenu a[href="tools.php"] {
                display: none !important;
            }
            /* Hide the "LLM Visibility Result" menu item from the Tools submenu */
            .wp-submenu a[href="tools.php?page=llmvm-result"] {
                display: none !important;
            }
            /* Also hide the parent Tools menu if it only contains Available Tools */
            .wp-submenu li:has(a[href="tools.php"]):only-child {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * Customize Tools page to hide "Available Tools" section.
     */
    public function customize_tools_page(): void {
        // Only customize if we're on the main Tools page (not a sub-page)
        if ( ! isset( $_GET['page'] ) ) {
            add_action( 'admin_footer', [ $this, 'hide_available_tools_section' ] );
        }
    }

    /**
     * Hide the "Available Tools" section via CSS.
     */
    public function hide_available_tools_section(): void {
        ?>
        <style>
            /* Hide the "Available Tools" section and related elements */
            .tools-php .wrap h1 + p,
            .tools-php .wrap h1 + .wp-list-table,
            .tools-php .wrap h1 + .wp-list-table + p,
            .tools-php .wrap h1 + .wp-list-table + p + p,
            .tools-php .wrap h1 + p + .wp-list-table,
            .tools-php .wrap h1 + p + .wp-list-table + p,
            .tools-php .wrap h1 + p + .wp-list-table + p + p {
                display: none !important;
            }
            .tools-php .wrap h1 {
                margin-bottom: 20px;
            }
            /* Also hide any paragraph that contains "Available Tools" text */
            .tools-php .wrap p:contains("Available Tools") {
                display: none !important;
            }
        </style>
        <script>
        // Additional JavaScript to hide "Available Tools" section
        document.addEventListener('DOMContentLoaded', function() {
            const wrap = document.querySelector('.tools-php .wrap');
            if (wrap) {
                const h1 = wrap.querySelector('h1');
                if (h1) {
                    let nextElement = h1.nextElementSibling;
                    while (nextElement) {
                        if (nextElement.tagName === 'P' || nextElement.classList.contains('wp-list-table')) {
                            if (nextElement.textContent.includes('Available Tools') || 
                                nextElement.classList.contains('wp-list-table')) {
                                nextElement.style.display = 'none';
                            }
                        }
                        nextElement = nextElement.nextElementSibling;
                    }
                }
            }
        });
        </script>
        <?php
    }

    /** Render single result page */
    public function render_result_page(): void {
        if ( ! current_user_can( 'llmvm_view_results' ) ) {
            return;
        }
        
        // Verify nonce for security (if provided)
        // Allow direct access without nonce for convenience, but verify user permissions
        if ( isset( $_GET['_wpnonce'] ) ) {
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'llmvm_view_result' ) ) {
                wp_die( esc_html__( 'Security check failed', 'llm-visibility-monitor' ) );
            }
        }
        
        // Sanitize the ID parameter
        $id = isset( $_GET['id'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['id'] ) ) : 0;
        
        // Validate ID parameter
        if ( $id <= 0 ) {
            wp_die( esc_html__( 'Invalid result ID', 'llm-visibility-monitor' ) );
        }
        
        // Get current user ID for ownership verification
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can( 'llmvm_manage_settings' );
        
        // Get result with user filtering (unless admin)
        $user_filter = $is_admin ? 0 : $current_user_id;
        $row = LLMVM_Database::get_result_by_id( $id, $user_filter );
        
        // Ensure $row is always an array or null for the view.
        if ( ! is_array( $row ) ) {
            $row = null;
        }
        
        // Set page title to prevent WordPress from using null values
        add_filter( 'admin_title', function( $title ) {
            $blog_name = get_bloginfo( 'name' );
            return 'LLM Visibility Result - ' . ( $blog_name ?: 'WordPress' );
        } );
        
        // Ensure page title is never null
        add_filter( 'admin_title', function( $title ) {
            if ( $title === null || $title === '' ) {
                return 'LLM Visibility Result - WordPress';
            }
            return $title;
        }, 1 );
        
        // Ensure WordPress constants are properly set
        if ( ! defined( 'WP_ADMIN' ) ) {
            define( 'WP_ADMIN', true );
        }
        
        // Ensure all required WordPress functions are available and not null
        if ( ! function_exists( 'get_bloginfo' ) ) {
            return;
        }
        
        // Ensure blog name is not null
        $blog_name = get_bloginfo( 'name' );
        if ( empty( $blog_name ) ) {
            $blog_name = 'WordPress';
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
        if ( isset( $_POST['prompt_models'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce()
            $raw_models = wp_unslash( $_POST['prompt_models'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verification handled in verify_permissions_and_nonce(), will be sanitized below based on type
            // Sanitize if it's an array
            if ( is_array( $raw_models ) ) {
                $models_input = array_map( 'sanitize_text_field', $raw_models );
            } else {
                $models_input = sanitize_text_field( $raw_models );
            }
        }
        
        
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
        
        // Handle web search option
        $web_search = isset( $_POST['web_search'] ) && '1' === $_POST['web_search']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce()
        
        // Handle cron frequency option
        $cron_frequency = isset( $_POST['cron_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['cron_frequency'] ) ) : 'daily'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce()
        $cron_frequency = in_array( $cron_frequency, [ 'daily', 'weekly', 'monthly' ], true ) ? $cron_frequency : 'daily';
        
        // Handle expected answer option
        $expected_answer = isset( $_POST['expected_answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expected_answer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce()
        
        if ( '' !== trim( $text ) ) {
            $prompts   = get_option( 'llmvm_prompts', [] );
            $prompts   = is_array( $prompts ) ? $prompts : [];
            // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
            if ( false === $prompts ) {
                $prompts = [];
            }
            
            // Get current user ID
            $current_user_id = get_current_user_id();
            
            // Check usage limits
            if ( ! LLMVM_Usage_Manager::can_add_prompt( $current_user_id ) ) {
                $limits = LLMVM_Usage_Manager::get_user_limits( $current_user_id );
                // translators: %d is the maximum number of prompts allowed
                set_transient( 'llmvm_notice', [ 'type' => 'error', 'msg' => sprintf( __( 'You have reached your prompt limit (%d prompts). Please delete some prompts or upgrade your plan.', 'llm-visibility-monitor' ), $limits['max_prompts'] ) ], 60 );
                wp_safe_redirect( admin_url( 'tools.php?page=llmvm-prompts' ) );
                exit;
            }
            
            // Check model limit for this prompt
            if ( ! LLMVM_Usage_Manager::can_add_models_to_prompt( $current_user_id, count( $models ) ) ) {
                $limits = LLMVM_Usage_Manager::get_user_limits( $current_user_id );
                // translators: %1$d is the number of models selected, %2$d is the maximum models allowed per prompt
                set_transient( 'llmvm_notice', [ 'type' => 'error', 'msg' => sprintf( __( 'You have selected too many models (%1$d). Your plan allows a maximum of %2$d models per prompt.', 'llm-visibility-monitor' ), count( $models ), $limits['max_models_per_prompt'] ) ], 60 );
                wp_safe_redirect( admin_url( 'tools.php?page=llmvm-prompts' ) );
                exit;
            }
            
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
                    'user_id' => $current_user_id,
                    'web_search' => $web_search,
                    'cron_frequency' => $cron_frequency,
                    'expected_answer' => $expected_answer
                ];
                update_option( 'llmvm_prompts', $prompts, false );
                
                // Schedule cron job for this prompt
                $cron = new LLMVM_Cron();
                $cron->schedule_prompt_cron( $prompts[ count( $prompts ) - 1 ]['id'], $cron_frequency );
                
                // Track usage (increment prompts count)
                LLMVM_Database::increment_usage( $current_user_id, 1, 0 );
                
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
        if ( isset( $_POST['prompt_models'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce()
            $raw_models = wp_unslash( $_POST['prompt_models'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verification handled in verify_permissions_and_nonce(), will be sanitized below based on type
            // Sanitize if it's an array
            if ( is_array( $raw_models ) ) {
                $models_input = array_map( 'sanitize_text_field', $raw_models );
            } else {
                $models_input = sanitize_text_field( $raw_models );
            }
        }
        
        
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
        
        // Handle web search option
        $web_search = isset( $_POST['web_search'] ) && is_array( $_POST['web_search'] ) && isset( $_POST['web_search'][ $id ] ) && '1' === $_POST['web_search'][ $id ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce()
        
        // Handle cron frequency option
        $cron_frequency = 'daily'; // Default value
        if ( isset( $_POST['cron_frequency'] ) && is_array( $_POST['cron_frequency'] ) && isset( $_POST['cron_frequency'][ $id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce()
            $cron_frequency = sanitize_text_field( wp_unslash( $_POST['cron_frequency'][ $id ] ) );
            $cron_frequency = in_array( $cron_frequency, [ 'daily', 'weekly', 'monthly' ], true ) ? $cron_frequency : 'daily';
        }
        
        // Handle expected answer option
        $expected_answer = '';
        if ( isset( $_POST['expected_answer'] ) && is_array( $_POST['expected_answer'] ) && isset( $_POST['expected_answer'][ $id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce()
            $expected_answer = sanitize_textarea_field( wp_unslash( $_POST['expected_answer'][ $id ] ) );
        } elseif ( isset( $_POST['expected_answer'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in verify_permissions_and_nonce()
            $expected_answer = sanitize_textarea_field( wp_unslash( $_POST['expected_answer'] ) );
        }

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
                    $prompt['web_search'] = $web_search;
                    $prompt['cron_frequency'] = $cron_frequency;
                    $prompt['expected_answer'] = $expected_answer;
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
            
            // Reschedule cron job for this prompt with new frequency
            $cron = new LLMVM_Cron();
            $cron->schedule_prompt_cron( $id, $cron_frequency );
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
        
        // Unschedule cron job for deleted prompt
        $cron = new LLMVM_Cron();
        $cron->unschedule_prompt_cron( $id );
        
        // Verify cron cleanup was successful and retry if needed
        $this->verify_and_cleanup_cron( $id );

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

    /**
     * Verify and cleanup cron jobs for deleted prompts.
     *
     * @param string $prompt_id The prompt ID to verify.
     */
    private function verify_and_cleanup_cron( string $prompt_id ): void {
        $hook = 'llmvm_run_prompt_' . $prompt_id;
        
        // Check if cron job still exists
        $next_scheduled = wp_next_scheduled( $hook );
        
        if ( $next_scheduled ) {
            LLMVM_Logger::log( 'Cron job still exists after deletion, attempting cleanup', [
                'prompt_id' => $prompt_id,
                'hook' => $hook,
                'next_scheduled' => gmdate( 'Y-m-d H:i:s', $next_scheduled )
            ] );
            
            // Force clear the cron job
            wp_clear_scheduled_hook( $hook );
            
            // Verify it's actually gone
            $verify_scheduled = wp_next_scheduled( $hook );
            if ( $verify_scheduled ) {
                LLMVM_Logger::log( 'Failed to remove cron job after retry', [
                    'prompt_id' => $prompt_id,
                    'hook' => $hook,
                    'next_scheduled' => gmdate( 'Y-m-d H:i:s', $verify_scheduled )
                ] );
            } else {
                LLMVM_Logger::log( 'Successfully removed orphaned cron job', [
                    'prompt_id' => $prompt_id,
                    'hook' => $hook
                ] );
            }
        }
    }

    /**
     * Cleanup orphaned cron jobs that don't have corresponding prompts.
     */
    public function cleanup_orphaned_cron_jobs(): void {
        // Only run this occasionally to avoid performance impact
        $last_cleanup = get_transient( 'llmvm_last_cron_cleanup' );
        if ( $last_cleanup && ( time() - $last_cleanup ) < 300 ) { // 5 minutes
            return;
        }
        
        set_transient( 'llmvm_last_cron_cleanup', time(), 300 );
        
        // Get all current prompts
        $prompts = get_option( 'llmvm_prompts', [] );
        $prompt_ids = array_column( $prompts, 'id' );
        
        // Get all LLM prompt cron jobs
        $cron = _get_cron_array();
        $orphaned_crons = [];
        
        foreach ( $cron as $timestamp => $hooks ) {
            foreach ( $hooks as $hook => $events ) {
                if ( strpos( $hook, 'llmvm_run_prompt_' ) === 0 ) {
                    $prompt_id = str_replace( 'llmvm_run_prompt_', '', $hook );
                    
                    // If this prompt ID doesn't exist in the prompts array, it's orphaned
                    if ( ! in_array( $prompt_id, $prompt_ids, true ) ) {
                        $orphaned_crons[] = $hook;
                    }
                }
            }
        }
        
        // Remove orphaned cron jobs
        if ( ! empty( $orphaned_crons ) ) {
            LLMVM_Logger::log( 'Found orphaned cron jobs, cleaning up', [
                'orphaned_crons' => $orphaned_crons,
                'total_prompts' => count( $prompt_ids )
            ] );
            
            foreach ( $orphaned_crons as $hook ) {
                wp_clear_scheduled_hook( $hook );
                LLMVM_Logger::log( 'Removed orphaned cron job', [ 'hook' => $hook ] );
            }
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
     * AJAX handler to get progress updates
     */
    public function ajax_get_progress(): void {
        // Check user permissions
        if ( ! current_user_can( 'llmvm_view_dashboard' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }
        
        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'llmvm_progress_nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed' ], 403 );
        }
        
        // Get run ID
        $run_id = isset( $_POST['run_id'] ) ? sanitize_text_field( wp_unslash( $_POST['run_id'] ) ) : '';
        
        if ( empty( $run_id ) ) {
            wp_send_json_error( [ 'message' => 'Run ID is required' ], 400 );
        }
        
        // Get progress from tracker
        $progress = LLMVM_Progress_Tracker::get_progress( $run_id );
        
        if ( 'not_found' === $progress['status'] ) {
            wp_send_json_error( [ 'message' => 'Progress not found' ], 404 );
        }
        
        wp_send_json_success( $progress );
    }


    /**
     * Fetch available models from OpenRouter API.
     */
    public static function get_openrouter_models(): array {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            // Handle case where options are stored as JSON string
            if ( is_string( $options ) ) {
                $decoded = json_decode( $options, true );
                $options = is_array( $decoded ) ? $decoded : [];
            } else {
                $options = [];
            }
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
        if ( ! in_array( $hook, [ 'settings_page_llmvm-settings', 'tools_page_llmvm-dashboard', 'tools_page_llmvm-result', 'tools_page_llmvm-prompts', 'tools_page_llmvm-queue' ], true ) ) {
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
            wp_enqueue_style( 'jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css', array(), '1.13.2' ); // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- jQuery UI theme is required for autocomplete functionality
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

        // Add reschedule crons JavaScript for settings page
        if ( 'settings_page_llmvm-settings' === $hook ) {
            wp_add_inline_script( 'llmvm-admin', '
                jQuery(document).ready(function($) {
                    $("#reschedule-crons-btn").on("click", function() {
                        var $btn = $(this);
                        var $status = $("#reschedule-status");
                        
                        $btn.prop("disabled", true).text("Rescheduling...");
                        $status.text("");
                        
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "llmvm_reschedule_crons",
                                nonce: "' . wp_create_nonce( 'llmvm_reschedule_crons' ) . '"
                            },
                            success: function(response) {
                                if (response.success) {
                                    $status.html("<span style=\"color: green;\">✓ " + response.data.message + "</span>");
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    $status.html("<span style=\"color: red;\">✗ " + response.data + "</span>");
                                }
                            },
                            error: function() {
                                $status.html("<span style=\"color: red;\">✗ Error rescheduling crons</span>");
                            },
                            complete: function() {
                                $btn.prop("disabled", false).text("Reschedule All Crons with New Logic");
                            }
                        });
                    });
                    
                    $("#cleanup-old-results-btn").on("click", function() {
                        var $btn = $(this);
                        var $status = $("#cleanup-status");
                        
                        $btn.prop("disabled", true).text("Cleaning up...");
                        $status.text("");
                        
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "llmvm_cleanup_old_results",
                                nonce: "' . wp_create_nonce( 'llmvm_cleanup_old_results' ) . '"
                            },
                            success: function(response) {
                                if (response.success) {
                                    $status.html("<span style=\"color: green;\">✓ " + response.data.message + "</span>");
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    $status.html("<span style=\"color: red;\">✗ " + response.data + "</span>");
                                }
                            },
                            error: function() {
                                $status.html("<span style=\"color: red;\">✗ Error cleaning up old results</span>");
                            },
                            complete: function() {
                                $btn.prop("disabled", false).text("Clean Up Old Results");
                            }
                        });
                    });
                });
            ' );
        }
    }

    /** Handle changing user plan */
    public function handle_change_user_plan(): void {
        if ( ! current_user_can( 'llmvm_manage_settings' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }

        // Verify nonce
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'llmvm_change_user_plan' ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }

        $user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
        $plan = isset( $_GET['plan'] ) ? sanitize_text_field( wp_unslash( $_GET['plan'] ) ) : '';

        if ( $user_id <= 0 || ! in_array( $plan, [ 'free', 'pro' ], true ) ) {
            wp_die( esc_html__( 'Invalid parameters', 'llm-visibility-monitor' ) );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            wp_die( esc_html__( 'User not found', 'llm-visibility-monitor' ) );
        }

        // Remove existing LLM Manager roles
        $user->remove_role( 'llm_manager_free' );
        $user->remove_role( 'llm_manager_pro' );

        // Add new role
        if ( $plan === 'free' ) {
            $user->add_role( 'llm_manager_free' );
            $message = __( 'User downgraded to Free plan successfully.', 'llm-visibility-monitor' );
        } else {
            $user->add_role( 'llm_manager_pro' );
            $message = __( 'User upgraded to Pro plan successfully.', 'llm-visibility-monitor' );
        }

        set_transient( 'llmvm_notice', [ 'type' => 'success', 'msg' => $message ], 60 );
        wp_safe_redirect( admin_url( 'options-general.php?page=llmvm-settings' ) );
        exit;
    }

    /** Handle removing LLM access from user */
    public function handle_remove_llm_access(): void {
        if ( ! current_user_can( 'llmvm_manage_settings' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }

        // Verify nonce
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'llmvm_remove_llm_access' ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }

        $user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;

        if ( $user_id <= 0 ) {
            wp_die( esc_html__( 'Invalid user ID', 'llm-visibility-monitor' ) );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            wp_die( esc_html__( 'User not found', 'llm-visibility-monitor' ) );
        }

        // Remove LLM Manager roles
        $user->remove_role( 'llm_manager_free' );
        $user->remove_role( 'llm_manager_pro' );

        set_transient( 'llmvm_notice', [ 'type' => 'success', 'msg' => __( 'LLM Manager access removed from user successfully.', 'llm-visibility-monitor' ) ], 60 );
        wp_safe_redirect( admin_url( 'options-general.php?page=llmvm-settings' ) );
        exit;
    }


    /** Add timezone field to user profile */
    public function add_timezone_field( $user ): void {
        $current_timezone = get_user_meta( $user->ID, 'llmvm_timezone', true );
        if ( empty( $current_timezone ) ) {
            $current_timezone = get_option( 'timezone_string' );
            if ( empty( $current_timezone ) ) {
                $gmt_offset = get_option( 'gmt_offset' );
                $current_timezone = $gmt_offset !== false ? sprintf( '%+03d:00', $gmt_offset ) : 'UTC';
            }
        }
        
        // Get list of timezones
        $timezones = timezone_identifiers_list();
        ?>
        <h3><?php echo esc_html__( 'LLM Visibility Monitor', 'llm-visibility-monitor' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="llmvm_timezone"><?php echo esc_html__( 'Timezone', 'llm-visibility-monitor' ); ?></label>
                </th>
                <td>
                    <select name="llmvm_timezone" id="llmvm_timezone" class="regular-text">
                        <option value=""><?php echo esc_html__( 'Use site default', 'llm-visibility-monitor' ); ?></option>
                        <?php foreach ( $timezones as $timezone ) : ?>
                            <option value="<?php echo esc_attr( $timezone ); ?>" <?php selected( $current_timezone, $timezone ); ?>>
                                <?php echo esc_html( $timezone ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php echo esc_html__( 'Choose your preferred timezone for displaying dates and times in LLM Visibility Monitor. If not set, the site default timezone will be used.', 'llm-visibility-monitor' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /** Save timezone field from user profile */
    public function save_timezone_field( $user_id ): void {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $timezone = isset( $_POST['llmvm_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['llmvm_timezone'] ) ) : '';
        
        // Validate timezone if provided
        if ( ! empty( $timezone ) && ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
            return;
        }

        // Save timezone preference
        if ( empty( $timezone ) ) {
            delete_user_meta( $user_id, 'llmvm_timezone' );
        } else {
            update_user_meta( $user_id, 'llmvm_timezone', $timezone );
        }
    }

    /**
     * Customize login page CSS and logo
     */
    public function customize_login_page(): void {
        ?>
        <style type="text/css">
            /* Hide WordPress logo completely */
            #login h1 a,
            .wp-login-logo,
            .login h1 a {
                background-image: none !important;
                background: none !important;
                width: auto !important;
                height: auto !important;
                text-indent: 0 !important;
                font-size: 24px !important;
                font-weight: 600 !important;
                color: #23282d !important;
                text-decoration: none !important;
                line-height: 1.3 !important;
                padding: 0 !important;
                margin-bottom: 25px !important;
                display: block !important;
                text-align: center !important;
                overflow: visible !important;
            }
            
            /* Remove any pseudo-elements */
            #login h1 a:before,
            #login h1 a:after,
            .wp-login-logo:before,
            .wp-login-logo:after {
                content: none !important;
                display: none !important;
            }
            
            #login h1 {
                padding-bottom: 0 !important;
            }
            
            .llmvm-login-custom-text {
                text-align: center;
                margin: 16px 0;
                padding: 16px;
                background: #f9f9f9;
                border: 1px solid #e5e5e5;
                border-radius: 4px;
                font-size: 14px;
                line-height: 1.5;
            }
            .llmvm-login-custom-text a {
                color: #0073aa;
                text-decoration: none;
            }
            .llmvm-login-custom-text a:hover {
                text-decoration: underline;
            }
        </style>
        
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Force replace WordPress logo with custom text
            var logoLink = document.querySelector('#login h1 a');
            if (logoLink) {
                logoLink.innerHTML = 'LLM Visibility Monitor';
                logoLink.style.backgroundImage = 'none';
                logoLink.style.background = 'none';
                logoLink.style.textIndent = '0';
                logoLink.style.fontSize = '24px';
                logoLink.style.fontWeight = '600';
                logoLink.style.color = '#23282d';
                logoLink.style.textDecoration = 'none';
                logoLink.style.lineHeight = '1.3';
                logoLink.style.padding = '0';
                logoLink.style.marginBottom = '25px';
                logoLink.style.display = 'block';
                logoLink.style.textAlign = 'center';
                logoLink.style.overflow = 'visible';
            }
        });
        </script>
        <?php
    }

    /**
     * Change login header URL to site URL
     */
    public function login_header_url(): string {
        return home_url();
    }

    /**
     * Change login header text to site name
     */
    public function login_header_text(): string {
        return 'LLM Visibility Monitor';
    }

    /**
     * Add custom text right after the login header
     */
    public function login_custom_text_after_header(): void {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $custom_text = isset( $options['login_custom_text'] ) ? (string) $options['login_custom_text'] : '';
        
        if ( ! empty( trim( $custom_text ) ) ) {
            echo '<div class="llmvm-login-custom-text" style="text-align: center; margin: 16px 0; padding: 16px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 4px; font-size: 14px; line-height: 1.5;">' . wp_kses_post( $custom_text ) . '</div>';
        }
    }

    /**
     * Add custom text below login form (kept for backward compatibility)
     */
    public function login_custom_text(): void {
        // This method is kept for backward compatibility but not used
        // Custom text is now displayed after the header instead
    }

    /**
     * Handle reschedule crons AJAX request
     */
    public function handle_reschedule_crons(): void {
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmvm_reschedule_crons' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        $cron = new LLMVM_Cron();
        $prompts = get_option( 'llmvm_prompts', [] );
        $rescheduled = 0;
        
        foreach ( $prompts as $prompt ) {
            $prompt_id = $prompt['id'];
            $frequency = $prompt['cron_frequency'] ?? 'daily';
            
            // Clear existing cron
            $hook = 'llmvm_run_prompt_' . $prompt_id;
            wp_clear_scheduled_hook( $hook );
            
            // Schedule with new logic
            $cron->schedule_prompt_cron( $prompt_id, $frequency );
            $rescheduled++;
        }
        
        wp_send_json_success( [
            'message' => sprintf( 'Rescheduled %d cron jobs with new logic', $rescheduled ),
            'count' => $rescheduled
        ] );
    }

    /**
     * Handle cleanup old results AJAX request
     */
    public function handle_cleanup_old_results(): void {
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'llmvm_cleanup_old_results' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'llmvm_current_run_results';
        
        // Delete results older than 5 minutes
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            date( 'Y-m-d H:i:s', time() - 300 ) // 5 minutes ago
        ) );
        
        wp_send_json_success( [
            'message' => sprintf( 'Cleaned up %d old results from database', $deleted ),
            'count' => $deleted
        ] );
    }

    /**
     * Show manual cron information for production environments.
     */
    public function show_manual_cron_info(): void {
        if ( ! class_exists( 'LLMVM_Manual_Cron' ) ) {
            return;
        }

        $cron_url = LLMVM_Manual_Cron::get_cron_url();
        
        echo '<div class="notice notice-info">';
        echo '<h3>🔧 Manual Cron for Production</h3>';
        echo '<p><strong>WordPress cron is disabled.</strong> Use this URL to trigger processing manually:</p>';
        echo '<p><code>' . esc_url( $cron_url ) . '</code></p>';
        echo '<p><strong>For automatic processing:</strong> Set up a system cron job to call this URL every minute:</p>';
        echo '<p><code>* * * * * curl -s "' . esc_url( $cron_url ) . '" > /dev/null 2>&1</code></p>';
        echo '</div>';
    }
}


