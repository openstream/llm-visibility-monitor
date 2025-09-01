<?php
/**
 * Simple plugin logger writing to error_log and uploads directory.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Logger {

    /**
     * Write a log line.
     *
     * @param string $message Message to log.
     * @param array  $context Extra context.
     */
    public static function log( string $message, array $context = [] ): void {
        $options   = get_option( 'llmvm_options', [] );
        // Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $options ) ) {
            $options = [];
        }
        $debug_log = (bool) ( $options['debug_logging'] ?? false );
        if ( ! $debug_log ) {
            return;
        }

        $timestamp = gmdate( 'c' );
        $ctx_pairs = [];
        // Ensure context is a proper array to prevent PHP 8.1 deprecation warnings.
        if ( ! is_array( $context ) ) {
            $context = [];
        }
        foreach ( $context as $k => $v ) {
            // Ensure key is a string to prevent PHP 8.1 deprecation warnings.
            $k = is_string( $k ) ? $k : (string) $k;
            
            if ( is_scalar( $v ) ) {
                $v_str = (string) $v;
                // Skip empty or whitespace-only values
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
        $ctx_str  = $ctx_pairs ? ' ' . implode( ' ', $ctx_pairs ) : '';
        // Ensure ctx_str is a string to prevent PHP 8.1 deprecation warnings.
        $ctx_str = is_string( $ctx_str ) ? $ctx_str : '';
        $line      = sprintf( '[LLMVM %s] %s%s', $timestamp, $message, $ctx_str );
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging for plugin functionality.
        error_log( $line );

        $upload_dir = wp_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/llm-visibility-monitor';
        $log_file   = $log_dir . '/llmvm.log';
        
        // Ensure the log directory exists with proper permissions
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
            // Always use read-then-write method for reliability
            $existing_content = '';
            if ( $wp_filesystem->exists( $log_file ) ) {
                $existing_content = $wp_filesystem->get_contents( $log_file );
                if ( ! is_string( $existing_content ) ) {
                    $existing_content = '';
                }
            }
            
            // Append new line to existing content
            $new_content = $existing_content . $line . PHP_EOL;
            
            // Write the complete content back
            $wp_filesystem->put_contents( $log_file, $new_content, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 );
        }
    }
}


