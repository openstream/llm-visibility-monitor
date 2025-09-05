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

		// Create LLM Manager roles.
		self::create_llm_manager_roles();

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
	 * Create LLM Manager roles with appropriate capabilities.
	 */
	private static function create_llm_manager_roles(): void {
		// Migrate existing users from old llm_manager role to llm_manager_free
		$users_with_old_role = get_users( array( 'role' => 'llm_manager' ) );
		foreach ( $users_with_old_role as $user ) {
			$user->remove_role( 'llm_manager' );
			$user->add_role( 'llm_manager_free' );
		}

		// Remove existing roles if they exist (for updates).
		remove_role( 'llm_manager' );
		remove_role( 'llm_manager_free' );
		remove_role( 'llm_manager_pro' );
		remove_role( 'sc_customer' );

		// Create LLM Manager Free role (renamed from original LLM Manager).
		add_role( 'llm_manager_free', __( 'LLM Manager Free', 'llm-visibility-monitor' ), array(
			'read'                    => true,
			'level_1'                 => true, // Required for basic admin access
			'llmvm_manage_prompts'    => true,
			'llmvm_view_dashboard'    => true,
			'llmvm_view_results'      => true,
			'llmvm_free_plan'         => true,
		) );

		// Create LLM Manager Pro role.
		add_role( 'llm_manager_pro', __( 'LLM Manager Pro', 'llm-visibility-monitor' ), array(
			'read'                    => true,
			'level_1'                 => true, // Required for basic admin access
			'llmvm_manage_prompts'    => true,
			'llmvm_view_dashboard'    => true,
			'llmvm_view_results'      => true,
			'llmvm_pro_plan'          => true,
		) );

		// Create SC Customer role with limited admin access.
		add_role( 'sc_customer', __( 'SC Customer', 'llm-visibility-monitor' ), array(
			'read'                    => true,
			'level_1'                 => true, // Required for basic admin access
			'edit_posts'             => true, // Required to bypass SureCart admin restrictions
			'llmvm_manage_prompts'    => true,
			'llmvm_view_dashboard'    => true,
			'llmvm_view_results'      => true,
			'llmvm_free_plan'         => true,
		) );

		// Add LLM capabilities to administrator role.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'llmvm_manage_prompts' );
			$admin_role->add_cap( 'llmvm_view_dashboard' );
			$admin_role->add_cap( 'llmvm_view_results' );
			$admin_role->add_cap( 'llmvm_manage_settings' );
			$admin_role->add_cap( 'llmvm_unlimited_plan' );
		}

		// Ensure all LLM Manager roles have necessary capabilities for admin access
		$llm_roles = [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ];
		foreach ( $llm_roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				// Ensure they have level_1 capability for basic admin access
				$role->add_cap( 'level_1' );
				// Ensure they have edit_posts capability to bypass SureCart restrictions
				$role->add_cap( 'edit_posts' );
			}
		}
	}
}


