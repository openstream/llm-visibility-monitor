<?php
/**
 * Plugin Name: LLM Visibility Monitor
 * Description: Monitor LLM responses on a schedule and store/export results.
 * Version: 0.8.0
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 8.0
 * Author: Openstream
 * Author URI: https://openstream.ch
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: llm-visibility-monitor
 * Domain Path: /languages
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constants.
define( 'LLMVM_VERSION', '0.8.0' );
define( 'LLMVM_PLUGIN_FILE', __FILE__ );

/**
 * Define plugin paths safely.
 */
function llmvm_define_paths() {
	if ( ! defined( 'LLMVM_PLUGIN_DIR' ) ) {
		// Use a more conservative approach to prevent any issues.
		$plugin_dir_path = '';
		$plugin_dir_url  = '';

		if ( function_exists( 'plugin_dir_path' ) ) {
			$temp_path = plugin_dir_path( __FILE__ );
			$plugin_dir_path = ( is_string( $temp_path ) && ! empty( $temp_path ) ) ? $temp_path : '';
		}

		if ( function_exists( 'plugin_dir_url' ) ) {
			$temp_url = plugin_dir_url( __FILE__ );
			$plugin_dir_url = ( is_string( $temp_url ) && ! empty( $temp_url ) ) ? $temp_url : '';
		}

		// Final safety check to prevent any issues.
		if ( null === $plugin_dir_path || false === $plugin_dir_path ) {
			$plugin_dir_path = '';
		}
		if ( null === $plugin_dir_url || false === $plugin_dir_url ) {
			$plugin_dir_url = '';
		}

		define( 'LLMVM_PLUGIN_DIR', $plugin_dir_path );
		define( 'LLMVM_PLUGIN_URL', $plugin_dir_url );
	}
}

/**
 * Load includes safely.
 */
function llmvm_load_includes() {
	llmvm_define_paths();

	// Ensure we have a valid plugin directory.
	if ( empty( LLMVM_PLUGIN_DIR ) || ! is_string( LLMVM_PLUGIN_DIR ) ) {
		return;
	}

	$includes_dir = LLMVM_PLUGIN_DIR . 'includes/';
	if ( is_dir( $includes_dir ) && is_string( $includes_dir ) && ! empty( $includes_dir ) ) {
		$files = array(
			'class-llmvm-activator.php',
			'class-llmvm-deactivator.php',
			'class-llmvm-logger.php',
			'class-llmvm-database.php',
			'class-llmvm-usage-manager.php',
			'class-llmvm-openrouter-client.php',
			'class-llmvm-cron.php',
			'class-llmvm-admin.php',
			'class-llmvm-exporter.php',
			'class-llmvm-email-reporter.php',
		);

		foreach ( $files as $file ) {
			$file_path = $includes_dir . $file;
			if ( is_file( $file_path ) && is_readable( $file_path ) && is_string( $file_path ) ) {
				require_once $file_path;
			}
		}
	}
}

/**
 * Activation hook: create DB tables and schedule cron if needed.
 */
function llmvm_activate() {
	llmvm_load_includes();
	if ( class_exists( 'LLMVM_Activator' ) ) {
		LLMVM_Activator::activate();
	}
}
register_activation_hook( __FILE__, 'llmvm_activate' );

/**
 * Deactivation hook: clear scheduled events.
 */
function llmvm_deactivate() {
	llmvm_load_includes();
	if ( class_exists( 'LLMVM_Deactivator' ) ) {
		LLMVM_Deactivator::deactivate();
	}
}
register_deactivation_hook( __FILE__, 'llmvm_deactivate' );



/**
 * Initialize plugin.
 */
function llmvm_init() {
	static $initialized = false;

	// Prevent multiple initializations
	if ( $initialized ) {
		return;
	}
	$initialized = true;

	// Load text domain for translations (only for non-WordPress.org installations)
	// WordPress.org automatically loads translations, so this is only needed for external installations
	if ( ! defined( 'WP_PLUGIN_DIR' ) || strpos( plugin_dir_path( __FILE__ ), WP_PLUGIN_DIR ) === false ) {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Only loaded for non-WordPress.org installations.
		load_plugin_textdomain( 'llm-visibility-monitor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	// Define paths first.
	llmvm_define_paths();

	if ( empty( LLMVM_PLUGIN_DIR ) || ! is_string( LLMVM_PLUGIN_DIR ) ) {
		return;
	}

	llmvm_load_includes();

	// Ensure database is ready (safe to call on every load; guarded internally).
	if ( class_exists( 'LLMVM_Database' ) ) {
		LLMVM_Database::maybe_upgrade();
	}

	// Initialize admin classes
	if ( is_admin() && class_exists( 'LLMVM_Admin' ) ) {
		new LLMVM_Admin();
	}

	if ( is_admin() && class_exists( 'LLMVM_Exporter' ) ) {
		( new LLMVM_Exporter() )->hooks();
	}

	if ( class_exists( 'LLMVM_Cron' ) ) {
		$cron = new LLMVM_Cron();

		// Set up cron hooks
		$cron->hooks();

		// Schedule cron if not already scheduled
		$options   = get_option( 'llmvm_options', array() );
		$frequency = isset( $options['cron_frequency'] ) ? (string) $options['cron_frequency'] : 'daily';
		$cron->reschedule( $frequency );

		LLMVM_Logger::log( 'Plugin initialized', array( 'frequency' => $frequency ) );
	}

	if ( class_exists( 'LLMVM_Email_Reporter' ) ) {
		( new LLMVM_Email_Reporter() )->hooks();
	}

	// Ensure LLM Manager users can access admin pages
	llmvm_ensure_llm_manager_admin_access();
}
add_action( 'plugins_loaded', 'llmvm_init' );

// Run bypass even earlier to catch SureCart before it registers hooks
add_action( 'init', 'llmvm_bypass_surecart_restrictions', 1 );

/**
 * Bypass SureCart admin restrictions for sc_customer role.
 */
function llmvm_bypass_surecart_restrictions(): void {
	// Only apply to users with sc_customer role
	if ( ! current_user_can( 'sc_customer' ) ) {
		return;
	}

	// Add a higher priority hook to prevent SureCart redirects
	add_action( 'admin_init', 'llmvm_prevent_surecart_redirect', 5 );
}

/**
 * Prevent SureCart redirect by running before SureCart's hook.
 */
function llmvm_prevent_surecart_redirect(): void {
	// Only apply to users with sc_customer role
	if ( ! current_user_can( 'sc_customer' ) ) {
		return;
	}

	// Get current page
	$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	
	// Allow access to specific LLM pages
	$allowed_pages = array(
		'llmvm-prompts',
		'llmvm-dashboard',
		'llmvm-result'
	);

	// If accessing an allowed page, prevent redirect by doing nothing
	// This method runs at priority 5, before SureCart's priority 10
	if ( in_array( $current_page, $allowed_pages, true ) ) {
		// Do nothing - this prevents the redirect
		return;
	}
}

/**
 * Ensure LLM Manager users can access admin pages.
 */
function llmvm_ensure_llm_manager_admin_access(): void {
	// Ensure all LLM Manager roles have the necessary capabilities
	$llm_roles = [ 'llm_manager_free', 'llm_manager_pro', 'sc_customer' ];
	
	foreach ( $llm_roles as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			// Ensure they have level_1 capability for basic admin access
			if ( ! $role->has_cap( 'level_1' ) ) {
				$role->add_cap( 'level_1' );
			}
			
			// Ensure they have edit_posts capability to bypass SureCart restrictions
			if ( ! $role->has_cap( 'edit_posts' ) ) {
				$role->add_cap( 'edit_posts' );
			}
		}
	}
}




