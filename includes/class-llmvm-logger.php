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

		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/llm-visibility-monitor';
		$log_file   = $log_dir . '/llmvm.log';

		// Ensure the log directory exists with proper permissions.
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
        
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
        
        // Rotate log file if it's too large (over 1MB)
        if ( $wp_filesystem && $wp_filesystem->exists( $log_file ) && $wp_filesystem->size( $log_file ) > 1024 * 1024 ) {
            $backup_file = $log_dir . '/llmvm-' . gmdate( 'Y-m-d-H-i-s' ) . '.log';
            $wp_filesystem->move( $log_file, $backup_file );
        }
        
        // Write to log file using WordPress filesystem API
        if ( $wp_filesystem && $wp_filesystem->is_writable( $log_dir ) ) {
            // Use a simple append approach with error suppression
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
        }
    }
}


