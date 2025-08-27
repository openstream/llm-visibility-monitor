<?php
/**
 * Deactivation tasks for LLM Visibility Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        $timestamp = wp_next_scheduled( 'llmvm_run_checks' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'llmvm_run_checks' );
        }
    }
}


