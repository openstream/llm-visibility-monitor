<?php
/**
 * Diagnostics for LLM Visibility Monitor automatic processing issues.
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostics class for troubleshooting automatic processing.
 */
class LLMVM_Diagnostics {

	/**
	 * Run comprehensive diagnostics.
	 */
	public static function run_diagnostics(): array {
		$results = array(
			'cron_status' => self::check_cron_status(),
			'scheduled_prompts' => self::check_scheduled_prompts(),
			'queue_status' => self::check_queue_status(),
			'email_settings' => self::check_email_settings(),
			'api_settings' => self::check_api_settings(),
			'recommendations' => array()
		);

		// Generate recommendations based on findings
		$results['recommendations'] = self::generate_recommendations( $results );

		return $results;
	}

	/**
	 * Check WordPress cron status.
	 */
	private static function check_cron_status(): array {
		$status = array(
			'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'doing_cron' => defined( 'DOING_CRON' ) && DOING_CRON,
			'next_queue_run' => wp_next_scheduled( 'llmvm_process_queue' ),
			'queue_scheduled' => wp_next_scheduled( 'llmvm_process_queue' ) !== false,
		);

		$status['next_queue_run_formatted'] = $status['next_queue_run'] ? 
			gmdate( 'Y-m-d H:i:s', $status['next_queue_run'] ) : 'Not scheduled';

		return $status;
	}

	/**
	 * Check scheduled prompts.
	 */
	private static function check_scheduled_prompts(): array {
		$prompts = get_option( 'llmvm_prompts', array() );
		$scheduled_prompts = array();

		foreach ( $prompts as $prompt ) {
			$hook = 'llmvm_run_prompt_' . $prompt['id'];
			$next_run = wp_next_scheduled( $hook );
			
			$scheduled_prompts[] = array(
				'prompt_id' => $prompt['id'],
				'text' => substr( $prompt['text'], 0, 50 ) . '...',
				'frequency' => $prompt['cron_frequency'] ?? 'daily',
				'next_run' => $next_run,
				'next_run_formatted' => $next_run ? gmdate( 'Y-m-d H:i:s', $next_run ) : 'Not scheduled',
				'is_due' => $next_run && $next_run <= time(),
				'is_overdue' => $next_run && $next_run < ( time() - 3600 ), // Overdue by 1 hour
			);
		}

		return $scheduled_prompts;
	}

	/**
	 * Check queue status.
	 */
	private static function check_queue_status(): array {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'llmvm_queue';
		
		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
		
		if ( ! $table_exists ) {
			return array( 'error' => 'Queue table does not exist' );
		}

		$status_counts = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
			ARRAY_A
		);

		$status = array(
			'table_exists' => true,
			'status_counts' => $status_counts,
			'total_jobs' => array_sum( array_column( $status_counts, 'count' ) ),
		);

		// Get recent jobs
		$recent_jobs = $wpdb->get_results(
			"SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10",
			ARRAY_A
		);

		$status['recent_jobs'] = $recent_jobs;

		return $status;
	}

	/**
	 * Check email settings.
	 */
	private static function check_email_settings(): array {
		$options = get_option( 'llmvm_options', array() );
		
		return array(
			'email_enabled' => isset( $options['email_reports'] ) && $options['email_reports'],
			'admin_email' => get_option( 'admin_email' ),
			'from_address' => $options['email_from_address'] ?? '',
		);
	}

	/**
	 * Check API settings.
	 */
	private static function check_api_settings(): array {
		$options = get_option( 'llmvm_options', array() );
		
		return array(
			'api_key_set' => ! empty( $options['api_key'] ),
			'model' => $options['model'] ?? 'openrouter/stub-model-v1',
			'is_stub_model' => ( $options['model'] ?? '' ) === 'openrouter/stub-model-v1',
		);
	}

	/**
	 * Generate recommendations based on diagnostic results.
	 */
	private static function generate_recommendations( array $results ): array {
		$recommendations = array();

		// Check for DISABLE_WP_CRON issues
		if ( $results['cron_status']['wp_cron_disabled'] ) {
			$recommendations[] = array(
				'type' => 'warning',
				'message' => 'WordPress cron is disabled (DISABLE_WP_CRON=1). Scheduled prompts may not run automatically.',
				'solution' => 'Set up a system cron job to trigger wp-cron.php every minute.'
			);
		}

		// Check for unscheduled prompts
		$unscheduled_prompts = array_filter( $results['scheduled_prompts'], function( $prompt ) {
			return ! $prompt['next_run'];
		});

		if ( ! empty( $unscheduled_prompts ) ) {
			$recommendations[] = array(
				'type' => 'error',
				'message' => count( $unscheduled_prompts ) . ' prompts are not scheduled to run automatically.',
				'solution' => 'Use the "Reschedule Crons" button in Settings to fix scheduling.'
			);
		}

		// Check for overdue prompts
		$overdue_prompts = array_filter( $results['scheduled_prompts'], function( $prompt ) {
			return $prompt['is_overdue'];
		});

		if ( ! empty( $overdue_prompts ) ) {
			$recommendations[] = array(
				'type' => 'warning',
				'message' => count( $overdue_prompts ) . ' prompts are overdue for execution.',
				'solution' => 'Check if WordPress cron is working or use manual triggers.'
			);
		}

		// Check for email issues
		if ( ! $results['email_settings']['email_enabled'] ) {
			$recommendations[] = array(
				'type' => 'info',
				'message' => 'Email reports are disabled.',
				'solution' => 'Enable email reports in Settings if you want to receive notifications.'
			);
		}

		// Check for API issues
		if ( $results['api_settings']['is_stub_model'] ) {
			$recommendations[] = array(
				'type' => 'info',
				'message' => 'Using stub model for testing. No real API calls will be made.',
				'solution' => 'Configure a real OpenRouter model in Settings for production use.'
			);
		}

		return $recommendations;
	}

	/**
	 * Get diagnostic summary for admin display.
	 */
	public static function get_diagnostic_summary(): string {
		$results = self::run_diagnostics();
		
		$summary = '<div class="llmvm-diagnostics">';
		$summary .= '<h3>🔍 Automatic Processing Diagnostics</h3>';
		
		// Cron status
		$cron_status = $results['cron_status'];
		$summary .= '<h4>WordPress Cron Status</h4>';
		$summary .= '<ul>';
		$summary .= '<li>WP Cron Disabled: ' . ( $cron_status['wp_cron_disabled'] ? '❌ Yes' : '✅ No' ) . '</li>';
		$summary .= '<li>Queue Scheduled: ' . ( $cron_status['queue_scheduled'] ? '✅ Yes' : '❌ No' ) . '</li>';
		$summary .= '<li>Next Queue Run: ' . esc_html( $cron_status['next_queue_run_formatted'] ) . '</li>';
		$summary .= '</ul>';

		// Scheduled prompts
		$summary .= '<h4>Scheduled Prompts (' . count( $results['scheduled_prompts'] ) . ')</h4>';
		if ( empty( $results['scheduled_prompts'] ) ) {
			$summary .= '<p>No prompts configured.</p>';
		} else {
			$summary .= '<ul>';
			foreach ( $results['scheduled_prompts'] as $prompt ) {
				$status_icon = $prompt['is_overdue'] ? '🔴' : ( $prompt['is_due'] ? '🟡' : '🟢' );
				$summary .= '<li>' . $status_icon . ' ' . esc_html( $prompt['text'] ) . ' (' . $prompt['frequency'] . ') - ' . esc_html( $prompt['next_run_formatted'] ) . '</li>';
			}
			$summary .= '</ul>';
		}

		// Recommendations
		if ( ! empty( $results['recommendations'] ) ) {
			$summary .= '<h4>⚠️ Recommendations</h4>';
			$summary .= '<ul>';
			foreach ( $results['recommendations'] as $rec ) {
				$icon = $rec['type'] === 'error' ? '🔴' : ( $rec['type'] === 'warning' ? '🟡' : 'ℹ️' );
				$summary .= '<li>' . $icon . ' <strong>' . esc_html( $rec['message'] ) . '</strong><br>';
				$summary .= '<em>Solution: ' . esc_html( $rec['solution'] ) . '</em></li>';
			}
			$summary .= '</ul>';
		}

		$summary .= '</div>';
		
		return $summary;
	}
}
