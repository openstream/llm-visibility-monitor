<?php
/**
 * Plugin Name: LLM Visibility Monitor
 * Description: Monitor LLM responses on a schedule and store/export results.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 8.0
 * Author: Openstream Internet Solutions
 * Author URI: https://www.openstream.ch
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: llm-visibility-monitor
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants.
define( 'LLMVM_VERSION', '0.2.0' );
define( 'LLMVM_PLUGIN_FILE', __FILE__ );

// Define plugin paths safely.
function llmvm_define_paths() {
    if ( ! defined( 'LLMVM_PLUGIN_DIR' ) ) {
        // Use a more conservative approach to prevent any issues.
        $plugin_dir_path = '';
        $plugin_dir_url = '';
        
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

// Load includes safely.
function llmvm_load_includes() {
    llmvm_define_paths();
    
    // Ensure we have a valid plugin directory.
    if ( empty( LLMVM_PLUGIN_DIR ) || ! is_string( LLMVM_PLUGIN_DIR ) ) {
        return;
    }
    
    $includes_dir = LLMVM_PLUGIN_DIR . 'includes/';
    if ( is_dir( $includes_dir ) && is_string( $includes_dir ) && ! empty( $includes_dir ) ) {
        $files = [
            'class-llmvm-activator.php',
            'class-llmvm-deactivator.php',
            'class-llmvm-logger.php',
            'class-llmvm-database.php',
            'class-llmvm-openrouter-client.php',
            'class-llmvm-cron.php',
            'class-llmvm-admin.php',
            'class-llmvm-exporter.php',
            'class-llmvm-email-reporter.php'
        ];
        
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
 * Load plugin translations (only for WordPress versions < 4.6).
 */
function llmvm_load_textdomain() {
    // WordPress 4.6+ automatically loads plugin translations
    if ( version_compare( get_bloginfo( 'version' ), '4.6', '<' ) ) {
        $plugin_dir = dirname( plugin_basename( __FILE__ ) ) ?: '';
        if ( ! empty( $plugin_dir ) && is_string( $plugin_dir ) && '' !== $plugin_dir ) {
            $languages_path = $plugin_dir . '/languages';
            if ( is_string( $languages_path ) && ! empty( $languages_path ) ) {
                load_plugin_textdomain( 'llm-visibility-monitor', false, $languages_path );
            }
        }
    }
}

/**
 * Initialize plugin.
 */
function llmvm_init() {
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
        ( new LLMVM_Admin() )->hooks();
    }
    
    if ( is_admin() && class_exists( 'LLMVM_Exporter' ) ) {
        ( new LLMVM_Exporter() )->hooks();
    }

    if ( class_exists( 'LLMVM_Cron' ) ) {
        ( new LLMVM_Cron() )->hooks();
    }
    
    if ( class_exists( 'LLMVM_Email_Reporter' ) ) {
        ( new LLMVM_Email_Reporter() )->hooks();
    }
}
add_action( 'plugins_loaded', 'llmvm_init' );

// Hook translations loading to init (for older WordPress versions)
add_action( 'init', 'llmvm_load_textdomain' );


