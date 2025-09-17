<?php
/**
 * Simple plugin logger writing to error_log and uploads directory.
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */
class LLMVM_Logger {

	/**
	 * Track recent log entries to prevent duplicates.
	 *
	 * @var array
	 */
	private static $recent_logs = array();
	
	/**
	 * Maximum number of recent logs to track.
	 *
	 * @var int
	 */
	private static $max_recent_logs = 100;

	/**
	 * Write a log line.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Extra context.
	 */
	public static function log( string $message, array $context = array() ): void {
		$options = get_option( 'llmvm_options', array() );
		// Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$debug_log = (bool) ( $options['debug_logging'] ?? false );
		if ( ! $debug_log ) {
			return;
		}

		$timestamp = gmdate( 'c' );
		$ctx_pairs = array();
		// Ensure context is a proper array to prevent PHP 8.1 deprecation warnings.
		if ( ! is_array( $context ) ) {
			$context = array();
		}
		foreach ( $context as $k => $v ) {
			// Ensure key is a string to prevent PHP 8.1 deprecation warnings.
			$k = is_string( $k ) ? $k : (string) $k;

			if ( is_scalar( $v ) ) {
				$v_str = (string) $v;
				// Skip empty or whitespace-only values.
				if ( '' !== trim( $v_str ) ) {
					$ctx_pairs[] = $k . '=' . $v_str;
				}
			} else {
				$json_str = wp_json_encode( $v );
				if ( $json_str && '""' !== $json_str && '[]' !== $json_str && '{}' !== $json_str ) {
					$ctx_pairs[] = $k . '=' . substr( (string) $json_str, 0, 200 ) ?: '';
				}
			}
		}
		$ctx_str = $ctx_pairs ? ' ' . implode( ' ', $ctx_pairs ) : '';
		// Ensure ctx_str is a string to prevent PHP 8.1 deprecation warnings.
		$ctx_str = is_string( $ctx_str ) ? $ctx_str : '';
		$line    = sprintf( '[LLMVM %s] %s%s', $timestamp, $message, $ctx_str );

		// Check for duplicate log entries (within last 5 seconds).
		$log_key      = $message . '|' . md5( serialize( $context ) );
		$current_time = time();

		// Clean old entries (older than 5 seconds).
		self::$recent_logs = array_filter( self::$recent_logs, function( $entry ) use ( $current_time ) {
			return ( $current_time - $entry['time'] ) < 5;
		} );

		// Check if this exact log entry was already written recently.
		foreach ( self::$recent_logs as $entry ) {
			if ( $entry['key'] === $log_key ) {
				return; // Skip duplicate.
			}
		}

		// Add to recent logs.
		self::$recent_logs[] = array(
			'key'  => $log_key,
			'time' => $current_time,
		);

		// Limit array size.
		if ( count( self::$recent_logs ) > self::$max_recent_logs ) {
			self::$recent_logs = array_slice( self::$recent_logs, -self::$max_recent_logs );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging for plugin functionality.
		error_log( $line );

		// Write to plugin root directory for easier access
		$log_file = LLMVM_PLUGIN_DIR . 'llmvm-master.log';
		$current_run_file = LLMVM_PLUGIN_DIR . 'llmvm-current-run.log';
        
        // Use WordPress filesystem API for file operations
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Ensure filesystem is properly initialized
        if ( ! $wp_filesystem || ! method_exists( $wp_filesystem, 'put_contents' ) ) {
            return;
        }
        
        // Rotate master log file if it's too large (over 5MB)
        if ( $wp_filesystem && $wp_filesystem->exists( $log_file ) && $wp_filesystem->size( $log_file ) > 5 * 1024 * 1024 ) {
            $backup_file = LLMVM_PLUGIN_DIR . 'llmvm-master-' . gmdate( 'Y-m-d-H-i-s' ) . '.log';
            $wp_filesystem->move( $log_file, $backup_file );
        }
        
        // Write to master log file
        $result = @file_put_contents( $log_file, $line . PHP_EOL, LOCK_EX | FILE_APPEND );
        if ( false === $result ) {
            // Fallback to WordPress filesystem if direct write fails
            $existing_content = '';
            if ( $wp_filesystem->exists( $log_file ) ) {
                $existing_content = $wp_filesystem->get_contents( $log_file );
            }
            $new_content = $existing_content . $line . PHP_EOL;
            $wp_filesystem->put_contents( $log_file, $new_content, FS_CHMOD_FILE );
        }

        // Write to current run log file for specific events
        $current_run_keywords = array(
            'New run detected',
            'Same run, keeping existing results',
            'Added result to current run results',
            'Using current run results for email',
            'Firing email action for completed queue jobs',
            'Email reporter called',
            'Using results from global variable',
            'Email report: sending',
            'Email report: sent successfully'
        );
        
        $is_current_run_log = false;
        foreach ( $current_run_keywords as $keyword ) {
            if ( strpos( $message, $keyword ) !== false ) {
                $is_current_run_log = true;
                break;
            }
        }
        
        if ( $is_current_run_log ) {
            // Clear current run log file on new run detection
            if ( strpos( $message, 'New run detected' ) !== false ) {
                @file_put_contents( $current_run_file, '', LOCK_EX );
            }
            
            // Write to current run log file
            @file_put_contents( $current_run_file, $line . PHP_EOL, LOCK_EX | FILE_APPEND );
        }
    }
}


