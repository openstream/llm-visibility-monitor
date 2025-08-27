<?php
/**
 * Simple plugin logger writing to error_log and a plugin file.
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
        $debug_log = (bool) ( $options['debug_logging'] ?? false );
        if ( ! $debug_log ) {
            return;
        }

        $timestamp = gmdate( 'c' );
        $ctx_pairs = [];
        foreach ( $context as $k => $v ) {
            if ( is_scalar( $v ) ) {
                $v_str = (string) $v;
                // Skip empty or whitespace-only values
                if ( '' !== trim( $v_str ) ) {
                    $ctx_pairs[] = $k . '=' . $v_str;
                }
            } else {
                $json_str = wp_json_encode( $v );
                if ( $json_str && '""' !== $json_str && '[]' !== $json_str && '{}' !== $json_str ) {
                    $ctx_pairs[] = $k . '=' . substr( $json_str, 0, 200 );
                }
            }
        }
        $ctx_str  = $ctx_pairs ? ' ' . implode( ' ', $ctx_pairs ) : '';
        $line      = sprintf( '[LLMVM %s] %s%s', $timestamp, $message, $ctx_str );
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging for plugin functionality.
        error_log( $line );

        $log_dir  = WP_CONTENT_DIR . '/uploads/llmvm-logs';
        $log_file = $log_dir . '/llmvm.log';
        if ( ! is_dir( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions
        file_put_contents( $log_file, $line . PHP_EOL, FILE_APPEND | LOCK_EX );
    }
}


