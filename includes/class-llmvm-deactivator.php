<?php
/**
 * Deactivation tasks for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation class for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */
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


