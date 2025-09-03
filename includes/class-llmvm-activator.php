<?php
/**
 * Activation tasks for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation class for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */
class LLMVM_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		// Create or upgrade DB table.
		LLMVM_Database::maybe_upgrade();

		// Create LLM Manager role.
		self::create_llm_manager_role();

		// Schedule cron based on current setting.
		$options = get_option( 'llmvm_options', array() );
		// Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$cron_frequency = isset( $options['cron_frequency'] ) ? sanitize_text_field( (string) $options['cron_frequency'] ) : 'daily';

		$cron = new LLMVM_Cron();
		$cron->reschedule( $cron_frequency );
	}

	/**
	 * Create LLM Manager role with appropriate capabilities.
	 */
	private static function create_llm_manager_role(): void {
		// Remove existing role if it exists (for updates).
		remove_role( 'llm_manager' );

		// Create new LLM Manager role.
		add_role( 'llm_manager', __( 'LLM Manager', 'llm-visibility-monitor' ), array(
			'read'                    => true,
			'llmvm_manage_prompts'    => true,
			'llmvm_view_dashboard'    => true,
			'llmvm_view_results'      => true,
		) );

		// Add LLM capabilities to administrator role.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'llmvm_manage_prompts' );
			$admin_role->add_cap( 'llmvm_view_dashboard' );
			$admin_role->add_cap( 'llmvm_view_results' );
			$admin_role->add_cap( 'llmvm_manage_settings' );
		}
	}
}


