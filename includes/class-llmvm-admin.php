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
    }

    /**
     * Register settings and fields.
     */
    public function register_settings(): void {
        register_setting( 'llmvm_settings', 'llmvm_options', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_options' ],
            'default'           => [ 'api_key' => '', 'cron_frequency' => 'daily' ],
            'show_in_rest'      => false,
        ] );

        add_settings_section( 'llmvm_section_main', __( 'General Settings', 'llm-visibility-monitor' ), '__return_false', 'llmvm-settings' );

        add_settings_field( 'llmvm_api_key', __( 'OpenRouter API Key', 'llm-visibility-monitor' ), [ $this, 'field_api_key' ], 'llmvm-settings', 'llmvm_section_main' );

        add_settings_field( 'llmvm_cron_frequency', __( 'Cron Frequency', 'llm-visibility-monitor' ), [ $this, 'field_cron_frequency' ], 'llmvm-settings', 'llmvm_section_main' );
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
            $api_key = (string) $input['api_key'];
            $api_key = trim( $api_key );
            if ( '' !== $api_key && ! str_starts_with( $api_key, '::' ) ) {
                $api_key = LLMVM_Cron::encrypt_api_key( $api_key );
            }
            $new['api_key'] = $api_key;
        } else {
            $new['api_key'] = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
        }

        $freq = isset( $input['cron_frequency'] ) ? sanitize_text_field( (string) $input['cron_frequency'] ) : ( $options['cron_frequency'] ?? 'daily' );
        $freq = in_array( $freq, [ 'daily', 'weekly' ], true ) ? $freq : 'daily';
        $new['cron_frequency'] = $freq;

        if ( ( $options['cron_frequency'] ?? '' ) !== $freq ) {
            ( new LLMVM_Cron() )->reschedule( $freq );
        }

        return $new;
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


