<?php
/**
 * Manual cron endpoint for production environments with DISABLE_WP_CRON=1
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manual cron handler for production environments.
 */
class LLMVM_Manual_Cron {

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		// Add manual cron endpoint
		add_action( 'init', array( $this, 'add_manual_cron_endpoint' ) );
		
		// Handle manual cron requests
		add_action( 'template_redirect', array( $this, 'handle_manual_cron' ) );
	}

	/**
	 * Add manual cron endpoint.
	 */
	public function add_manual_cron_endpoint(): void {
		add_rewrite_rule( '^llmvm-cron/?$', 'index.php?llmvm_manual_cron=1', 'top' );
		add_rewrite_tag( '%llmvm_manual_cron%', '([^&]+)' );
	}

	/**
	 * Handle manual cron requests.
	 */
	public function handle_manual_cron(): void {
		if ( ! get_query_var( 'llmvm_manual_cron' ) ) {
			return;
		}

		// Security: Require secret key for production use
		$secret_key = get_option( 'llmvm_cron_secret', '' );
		$provided_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		
		if ( empty( $secret_key ) || $secret_key !== $provided_key ) {
			wp_die( 'Unauthorized', 'Unauthorized', array( 'response' => 401 ) );
		}

		// Force process queue even when DISABLE_WP_CRON is set
		do_action( 'llmvm_process_queue' );
		
		// Process any scheduled prompt crons
		$this->process_scheduled_prompts();
		
		// Log the manual cron execution
		LLMVM_Logger::log( 'Manual cron executed', array(
			'timestamp' => gmdate( 'Y-m-d H:i:s' ),
			'disable_wp_cron' => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 1 : 0
		) );
		
		wp_die( 'Cron processed successfully', 'Success', array( 'response' => 200 ) );
	}

	/**
	 * Process scheduled prompts manually.
	 */
	private function process_scheduled_prompts(): void {
		global $wpdb;
		
		// Get all scheduled prompts
		$prompts = get_option( 'llmvm_prompts', array() );
		
		foreach ( $prompts as $prompt ) {
			$hook = 'llmvm_run_prompt_' . $prompt['id'];
			$next_run = wp_next_scheduled( $hook );
			
			// If the next run is due or overdue, trigger it
			if ( $next_run && $next_run <= time() ) {
				do_action( $hook );
				LLMVM_Logger::log( 'Triggered scheduled prompt', array(
					'prompt_id' => $prompt['id'],
					'next_run' => gmdate( 'Y-m-d H:i:s', $next_run )
				) );
			}
		}
	}

	/**
	 * Generate and store secret key for manual cron.
	 */
	public static function generate_secret_key(): string {
		$secret_key = wp_generate_password( 32, false );
		update_option( 'llmvm_cron_secret', $secret_key );
		return $secret_key;
	}

	/**
	 * Get manual cron URL.
	 */
	public static function get_cron_url(): string {
		$secret_key = get_option( 'llmvm_cron_secret', '' );
		if ( empty( $secret_key ) ) {
			$secret_key = self::generate_secret_key();
		}
		
		return home_url( '/llmvm-cron/?key=' . $secret_key );
	}
}
