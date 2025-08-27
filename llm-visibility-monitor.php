<?php
/**
 * Plugin Name:       LLM Visibility Monitor
 * Description:       Monitor LLM responses on a schedule and store/export results.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Openstream Internet Solutions
 * Author URI:        https://www.openstream.ch
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       llm-visibility-monitor
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants.
define( 'LLMVM_VERSION', '0.1.0' );
define( 'LLMVM_PLUGIN_FILE', __FILE__ );
define( 'LLMVM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLMVM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Includes.
require_once LLMVM_PLUGIN_DIR . 'includes/class-llmvm-activator.php';
require_once LLMVM_PLUGIN_DIR . 'includes/class-llmvm-deactivator.php';
require_once LLMVM_PLUGIN_DIR . 'includes/class-llmvm-database.php';
require_once LLMVM_PLUGIN_DIR . 'includes/class-llmvm-openrouter-client.php';
require_once LLMVM_PLUGIN_DIR . 'includes/class-llmvm-cron.php';
require_once LLMVM_PLUGIN_DIR . 'includes/class-llmvm-admin.php';
require_once LLMVM_PLUGIN_DIR . 'includes/class-llmvm-exporter.php';

/**
 * Activation hook: create DB tables and schedule cron if needed.
 */
function llmvm_activate() {
    LLMVM_Activator::activate();
}
register_activation_hook( __FILE__, 'llmvm_activate' );

/**
 * Deactivation hook: clear scheduled events.
 */
function llmvm_deactivate() {
    LLMVM_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'llmvm_deactivate' );

/**
 * Initialize plugin.
 */
function llmvm_init() {
    // Load translations.
    load_plugin_textdomain( 'llm-visibility-monitor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    // Ensure database is ready (safe to call on every load; guarded internally).
    LLMVM_Database::maybe_upgrade();

    // Initialize admin interfaces only in admin.
    if ( is_admin() ) {
        ( new LLMVM_Admin() )->hooks();
        ( new LLMVM_Exporter() )->hooks();
    }

    // Cron runner hooks (front and admin).
    ( new LLMVM_Cron() )->hooks();
}
add_action( 'plugins_loaded', 'llmvm_init' );


