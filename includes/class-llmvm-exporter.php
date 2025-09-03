<?php
/**
 * CSV Exporter for results.
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV Exporter class for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */
class LLMVM_Exporter {

	/**
	 * Set up WordPress hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_llmvm_export_csv', array( $this, 'handle_export' ) );
	}

	/**
	 * Handle CSV export request.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'llmvm_view_dashboard' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
		}

		// Verify nonce with proper sanitization.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'llmvm_export_csv' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
		}

		// Get current user ID and check if admin.
		$current_user_id = get_current_user_id();
		$is_admin        = current_user_can( 'llmvm_manage_settings' );

		// Filter results by user (unless admin).
		$user_filter = $is_admin ? 0 : $current_user_id;
		$results     = LLMVM_Database::get_latest_results( 1000, 'created_at', 'DESC', 0, $user_filter );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=llm-visibility-results.csv' );

		// Use WordPress filesystem API instead of direct PHP functions.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Create CSV content in memory.
		$csv_content = '';

		// Add headers - include User column for admins.
		if ( $is_admin ) {
			$csv_content .= "Date,Prompt,Model,Answer,User ID,User Name\n";
		} else {
			$csv_content .= "Date,Prompt,Model,Answer\n";
		}

		// Get user names for admin export.
		$user_names = array();
		if ( $is_admin ) {
			$users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
			foreach ( $users as $user ) {
				$user_names[ $user->ID ] = $user->display_name;
			}
		}

		foreach ( $results as $row ) {
			// Properly escape CSV values using WordPress functions where appropriate
			$created_at = wp_strip_all_tags( (string) ( $row['created_at'] ?? '' ) );
			$prompt = wp_strip_all_tags( (string) ( $row['prompt'] ?? '' ) );
			$model = wp_strip_all_tags( (string) ( $row['model'] ?? '' ) );
			$answer = wp_strip_all_tags( (string) ( $row['answer'] ?? '' ) );
			$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;

			// Escape double quotes for CSV format (CSV standard)
			$created_at = str_replace( '"', '""', $created_at );
			$prompt = str_replace( '"', '""', $prompt );
			$model = str_replace( '"', '""', $model );
			$answer = str_replace( '"', '""', $answer );

			if ( $is_admin ) {
				$user_name = isset( $user_names[ $user_id ] ) ? $user_names[ $user_id ] : 'Unknown User';
				$user_name = str_replace( '"', '""', $user_name );

				$csv_content .= sprintf(
					'"%s","%s","%s","%s","%d","%s"' . "\n",
					$created_at,
					$prompt,
					$model,
					$answer,
					$user_id,
					$user_name
				);
			} else {
				$csv_content .= sprintf(
					'"%s","%s","%s","%s"' . "\n",
					$created_at,
					$prompt,
					$model,
					$answer
				);
			}
		}

		// Output CSV content - this is raw CSV data, not HTML
		echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV export, not HTML
		exit;
	}
}


