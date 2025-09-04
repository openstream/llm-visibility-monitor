<?php
/**
 * Cron scheduler and runner for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron scheduler and runner class for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */
class LLMVM_Cron {

	/** Hook name for scheduled event */
	public const HOOK = 'llmvm_run_checks';

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		// Re-enable cron with proper action.
		add_action( self::HOOK, array( $this, 'run' ) );

		// Add weekly schedule if not present.
		add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );

		// Admin-triggered run-now endpoint.
		add_action( 'admin_post_llmvm_run_now', array( $this, 'handle_run_now' ) );

		// Admin-triggered single prompt run endpoint.
		add_action( 'admin_post_llmvm_run_single_prompt', array( $this, 'handle_run_single_prompt' ) );
	}

	/**
	 * Provide custom schedules (weekly).
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function register_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => DAY_IN_SECONDS * 7,
				'display'  => __( 'Once Weekly', 'llm-visibility-monitor' ),
			);
		}
		return $schedules;
	}

	/**
	 * Clear any existing cron jobs for this hook.
	 */
	public function clear_cron(): void {
		// Clear all scheduled events for this hook.
		wp_clear_scheduled_hook( self::HOOK );
		LLMVM_Logger::log( 'Cleared all cron jobs for hook', array( 'hook' => self::HOOK ) );
	}

	/**
	 * Reschedule the event to a new frequency.
	 *
	 * @param string $frequency 'daily' or 'weekly'.
	 */
	public function reschedule( string $frequency ): void {
		$frequency = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'daily';

		// Check if cron is already scheduled with the same frequency.
		$next_scheduled = wp_next_scheduled( self::HOOK );
		if ( $next_scheduled ) {
			LLMVM_Logger::log( 'Cron already scheduled', array( 'next_run' => gmdate( 'Y-m-d H:i:s', $next_scheduled ) ) );
			return;
		}

		// Calculate next run time based on frequency.
		$next_run = $this->calculate_next_run_time( $frequency );

		wp_schedule_event( $next_run, $frequency, self::HOOK );
		LLMVM_Logger::log( 'Scheduled new cron job', array( 'frequency' => $frequency, 'next_run' => gmdate( 'Y-m-d H:i:s', $next_run ) ) );
	}

	/**
	 * Calculate the next run time for the given frequency.
	 *
	 * @param string $frequency 'daily' or 'weekly'.
	 * @return int Unix timestamp for next run.
	 */
	private function calculate_next_run_time( string $frequency ): int {
		$now = time();

		if ( 'daily' === $frequency ) {
			// Next run at 9:00 AM tomorrow.
			$next_run = strtotime( 'tomorrow 9:00 AM', $now );
		} else {
			// Next run at 9:00 AM next Monday.
			$next_run = strtotime( 'next monday 9:00 AM', $now );
		}

		return $next_run;
	}

	/**
	 * Run scheduled job: send prompts to OpenRouter and store results.
	 */
	public function run(): void {
		$options   = get_option( 'llmvm_options', [] );
		// Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
		if ( ! is_array( $options ) ) {
			$options = [];
		}
		$raw_key   = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
		$api_key   = $raw_key !== '' ? self::decrypt_api_key( $raw_key ) : '';
		$model   = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
		$prompts = get_option( 'llmvm_prompts', [] );
		// Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
		if ( ! is_array( $prompts ) ) {
			$prompts = [];
		}

		// Force refresh the option to avoid cached values
		wp_cache_delete( 'llmvm_prompts', 'options' );
		$prompts = get_option( 'llmvm_prompts', [] );
		if ( ! is_array( $prompts ) ) {
			$prompts = [];
		}

		// Debug: Log the actual prompts being fetched
		LLMVM_Logger::log( 'Prompts fetched from options', [
			'count' => count( $prompts ),
			'prompt_ids' => array_column( $prompts, 'id' ),
			'prompt_texts' => array_column( $prompts, 'text' ),
			'user_ids' => array_column( $prompts, 'user_id' )
		] );

		LLMVM_Logger::log( 'Run start', [ 'prompts' => count( $prompts ), 'model' => $model, 'prompt_texts' => array_column( $prompts, 'text' ) ] );


		if ( empty( $prompts ) ) {
			LLMVM_Logger::log( 'Run abort: no prompts configured' );
			return;
		}
		if ( 'openrouter/stub-model-v1' !== $model && empty( $api_key ) ) {
			$reason = 'empty';
			if ( '' !== $raw_key ) {
				$reason = 'cannot decrypt; please re-enter in Settings';
				// Clear the corrupted API key to allow re-entry
				$options['api_key'] = '';
				update_option( 'llmvm_options', $options );
				LLMVM_Logger::log( 'Cleared corrupted API key from options' );
			}
			LLMVM_Logger::log( 'Run abort: missing API key for real model', [ 'reason' => $reason ] );
			return;
		}

		$client = new LLMVM_OpenRouter_Client();

		// Get the current user ID who is running the cron job
		$current_user_id = get_current_user_id();
		if ( $current_user_id === 0 ) {
			// If no current user (e.g., system cron), use the prompt's user_id
			$current_user_id = 1; // Default to admin
		}

		// Check usage limits for non-admin users
		if ( ! current_user_can( 'llmvm_manage_settings' ) ) {
			// Calculate total runs needed
			$total_runs = 0;
			foreach ( $prompts as $prompt_item ) {
				if ( isset( $prompt_item['models'] ) && is_array( $prompt_item['models'] ) ) {
					$total_runs += count( $prompt_item['models'] );
				} else {
					$total_runs += 1; // Fallback for single model
				}
			}
			
			// Check if user has enough runs
			if ( ! LLMVM_Usage_Manager::can_execute_runs( $current_user_id, $total_runs ) ) {
				LLMVM_Logger::log( 'Run aborted: insufficient runs remaining', [ 'user_id' => $current_user_id, 'runs_needed' => $total_runs ] );
				return;
			}
		}

		// Filter prompts to only include those belonging to the current user
		$user_prompts = [];
		foreach ( $prompts as $prompt_item ) {
			$prompt_user_id = isset( $prompt_item['user_id'] ) ? (int) $prompt_item['user_id'] : 1;
			if ( $prompt_user_id === $current_user_id ) {
				$user_prompts[] = $prompt_item;
			}
		}

		// Debug: Log the filtered prompts for the current user
		LLMVM_Logger::log( 'User prompts filtered', [
			'current_user_id' => $current_user_id,
			'total_prompts' => count( $prompts ),
			'user_prompts_count' => count( $user_prompts ),
			'user_prompt_ids' => array_column( $user_prompts, 'id' ),
			'user_prompt_texts' => array_column( $user_prompts, 'text' )
		] );

		if ( empty( $user_prompts ) ) {
			LLMVM_Logger::log( 'Run abort: no prompts found for current user', [ 'user_id' => $current_user_id ] );
			return;
		}

		foreach ( $user_prompts as $prompt_item ) {
			$prompt_text = isset( $prompt_item['text'] ) ? (string) $prompt_item['text'] : '';
			if ( '' === trim( $prompt_text ) ) {
				continue;
			}

			// Get models for this prompt (handle both old 'model' and new 'models' format)
			$prompt_models = array();
			if ( isset( $prompt_item['models'] ) && is_array( $prompt_item['models'] ) ) {
				$prompt_models = $prompt_item['models'];
			} elseif ( isset( $prompt_item['model'] ) && '' !== trim( $prompt_item['model'] ) ) {
				$prompt_models = array( $prompt_item['model'] );
			} else {
				$prompt_models = array( $model ); // Fall back to global default
			}

			// Use the current user ID who is running the job, not the prompt's stored user_id
			$user_id = $current_user_id;

			// Process each model for this prompt
			foreach ( $prompt_models as $prompt_model ) {
				LLMVM_Logger::log( 'Sending prompt', [ 'model' => $prompt_model, 'prompt_text' => $prompt_text, 'user_id' => $user_id ] );
				$response   = $client->query( $api_key, $prompt_text, $prompt_model );
				$resp_model = isset( $response['model'] ) ? (string) $response['model'] : 'unknown';
				$answer     = isset( $response['answer'] ) ? (string) $response['answer'] : '';
				$status     = isset( $response['status'] ) ? (int) $response['status'] : 0;
				$error      = isset( $response['error'] ) ? (string) $response['error'] : '';

				LLMVM_Logger::log( 'Inserting result', [ 'prompt_text' => $prompt_text, 'resp_model' => $resp_model, 'answer_length' => strlen( $answer ), 'user_id' => $user_id ] );
				LLMVM_Database::insert_result( $prompt_text, $resp_model, $answer, $user_id );
				if ( $status && $status >= 400 ) {
					LLMVM_Logger::log( 'OpenRouter error stored', [ 'status' => $status, 'error' => $error ] );
				}
			}
		}
		LLMVM_Logger::log( 'Run completed' );

		// Track usage for non-admin users
		if ( ! current_user_can( 'llmvm_manage_settings' ) ) {
			$total_runs = 0;
			foreach ( $prompts as $prompt_item ) {
				if ( isset( $prompt_item['models'] ) && is_array( $prompt_item['models'] ) ) {
					$total_runs += count( $prompt_item['models'] );
				} else {
					$total_runs += 1; // Fallback for single model
				}
			}
			LLMVM_Database::increment_usage( $current_user_id, 0, $total_runs );
			LLMVM_Logger::log( 'Usage tracked for run', [ 'user_id' => $current_user_id, 'runs' => $total_runs ] );
		}

		// Get the results that were just created for this user
		$user_results = LLMVM_Database::get_latest_results( 10, 'created_at', 'DESC', 0, $current_user_id );

		// Fire action hook for email reporter and other extensions with user context
		do_action( 'llmvm_run_completed', $current_user_id, $user_results );
	}

	/**
	 * Run a single prompt by ID.
	 */
	public function run_single_prompt( string $prompt_id ): void {
		$options   = get_option( 'llmvm_options', [] );
		// Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
		if ( ! is_array( $options ) ) {
			$options = [];
		}
		$raw_key   = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
		$api_key   = $raw_key !== '' ? self::decrypt_api_key( $raw_key ) : '';
		$model   = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
		$prompts = get_option( 'llmvm_prompts', [] );
		// Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
		if ( ! is_array( $prompts ) ) {
			$prompts = [];
		}

		// Find the specific prompt
		$target_prompt = null;
		foreach ( $prompts as $prompt ) {
			if ( isset( $prompt['id'] ) && $prompt['id'] === $prompt_id ) {
				$target_prompt = $prompt;
				break;
			}
		}

		if ( ! $target_prompt ) {
			LLMVM_Logger::log( 'Single prompt run failed: prompt not found', [ 'prompt_id' => $prompt_id ] );
			return;
		}

		// Check if current user can run this prompt (owner or admin)
		$current_user_id = get_current_user_id();
		$prompt_user_id = isset( $target_prompt['user_id'] ) ? (int) $target_prompt['user_id'] : 1;
		$is_admin = current_user_can( 'llmvm_manage_settings' );

		if ( ! $is_admin && $prompt_user_id !== $current_user_id ) {
			LLMVM_Logger::log( 'Single prompt run failed: user not authorized', [
				'prompt_id' => $prompt_id,
				'current_user_id' => $current_user_id,
				'prompt_user_id' => $prompt_user_id
			] );
			return;
		}

		// Check usage limits for non-admin users
		if ( ! $is_admin ) {
			$runs_needed = count( $prompt_models );
			if ( ! LLMVM_Usage_Manager::can_execute_runs( $current_user_id, $runs_needed ) ) {
				LLMVM_Logger::log( 'Single prompt run aborted: insufficient runs remaining', [
					'user_id' => $current_user_id,
					'runs_needed' => $runs_needed,
					'prompt_id' => $prompt_id
				] );
				return;
			}
		}

		LLMVM_Logger::log( 'Single prompt run start', [
			'prompt_id' => $prompt_id,
			'prompt_text' => $target_prompt['text'] ?? '',
			'current_user_id' => $current_user_id,
			'prompt_user_id' => $prompt_user_id
		] );

		$client = new LLMVM_OpenRouter_Client();

		$prompt_text = isset( $target_prompt['text'] ) ? (string) $target_prompt['text'] : '';
		if ( '' === trim( $prompt_text ) ) {
			LLMVM_Logger::log( 'Single prompt run failed: empty prompt text', [ 'prompt_id' => $prompt_id ] );
			return;
		}

		// Get models for this prompt (handle both old 'model' and new 'models' format)
		$prompt_models = array();
		if ( isset( $target_prompt['models'] ) && is_array( $target_prompt['models'] ) ) {
			$prompt_models = $target_prompt['models'];
		} elseif ( isset( $target_prompt['model'] ) && '' !== trim( $target_prompt['model'] ) ) {
			$prompt_models = array( $target_prompt['model'] );
		} else {
			$prompt_models = array( $model ); // Fall back to global default
		}

		// Use the current user ID who is running the job
		$user_id = $current_user_id;

		// Process each model for this prompt
		foreach ( $prompt_models as $prompt_model ) {
			LLMVM_Logger::log( 'Sending single prompt', [ 'model' => $prompt_model, 'prompt_text' => $prompt_text, 'user_id' => $user_id ] );
			$response   = $client->query( $api_key, $prompt_text, $prompt_model );
			$resp_model = isset( $response['model'] ) ? (string) $response['model'] : 'unknown';
			$answer     = isset( $response['answer'] ) ? (string) $response['answer'] : '';
			$status     = isset( $response['status'] ) ? (int) $response['status'] : 0;
			$error      = isset( $response['error'] ) ? (string) $response['error'] : '';

			LLMVM_Logger::log( 'Inserting single prompt result', [ 'prompt_text' => $prompt_text, 'resp_model' => $resp_model, 'answer_length' => strlen( $answer ), 'user_id' => $user_id ] );
			LLMVM_Database::insert_result( $prompt_text, $resp_model, $answer, $user_id );
			if ( $status && $status >= 400 ) {
				LLMVM_Logger::log( 'OpenRouter error stored for single prompt', [ 'status' => $status, 'error' => $error ] );
			}
		}

		LLMVM_Logger::log( 'Single prompt run completed', [ 'prompt_id' => $prompt_id ] );

		// Track usage for non-admin users
		if ( ! current_user_can( 'llmvm_manage_settings' ) ) {
			$runs_count = count( $prompt_models );
			LLMVM_Database::increment_usage( $current_user_id, 0, $runs_count );
			LLMVM_Logger::log( 'Usage tracked for single prompt run', [ 'user_id' => $current_user_id, 'runs' => $runs_count ] );
		}

		// Get the result that was just created
		$user_results = LLMVM_Database::get_latest_results( 1, 'created_at', 'DESC', 0, $current_user_id );

		// Fire action hook for email reporter and other extensions with user context
		do_action( 'llmvm_run_completed', $current_user_id, $user_results );
	}

	/**
	 * Handle manual run from admin.
	 */
	public function handle_run_now(): void {
		if ( ! current_user_can( 'llmvm_view_dashboard' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
		}
		// Verify nonce with proper sanitization.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'llmvm_run_now' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
		}
		LLMVM_Logger::log( 'Run Now triggered', [ 'user_id' => get_current_user_id(), 'user_roles' => implode( ', ', wp_get_current_user()->roles ) ] );
		$this->run();
		wp_safe_redirect( wp_nonce_url( admin_url( 'tools.php?page=llmvm-dashboard&llmvm_ran=1' ), 'llmvm_run_completed' ) ?: '' );
		exit;
	}

	/**
	 * Handle manual run of a single prompt.
	 */
	public function handle_run_single_prompt(): void {
		if ( ! current_user_can( 'llmvm_view_dashboard' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
		}

		// Verify nonce with proper sanitization.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'llmvm_run_single_prompt' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
		}

		// Get and sanitize the prompt ID
		$prompt_id = isset( $_GET['prompt_id'] ) ? sanitize_text_field( wp_unslash( $_GET['prompt_id'] ) ) : '';
		if ( empty( $prompt_id ) ) {
			wp_die( esc_html__( 'No prompt ID provided', 'llm-visibility-monitor' ) );
		}

		LLMVM_Logger::log( 'Single prompt run triggered', [
			'user_id' => get_current_user_id(),
			'user_roles' => implode( ', ', wp_get_current_user()->roles ),
			'prompt_id' => $prompt_id
		] );

		$this->run_single_prompt( $prompt_id );

		// Redirect back to prompts page with success message
		wp_safe_redirect( wp_nonce_url( admin_url( 'tools.php?page=llmvm-prompts&llmvm_ran=1' ), 'llmvm_run_completed' ) ?: '' );
		exit;
	}

	/**
	 * Decrypt API key stored in options.
	 */
	public static function decrypt_api_key( string $ciphertext ): string {
		// Try WordPress built-in decryption first (WordPress 6.4+)
		if ( function_exists( 'wp_decrypt' ) ) {
			$decrypted = wp_decrypt( $ciphertext );
			if ( false !== $decrypted ) {
				return $decrypted;
			}
		}

		// Fallback: assume it's plaintext (for backward compatibility)
		return $ciphertext;
	}

	/**
	 * Encrypt API key for storage.
	 */
	public static function encrypt_api_key( string $plaintext ): string {
		// Try WordPress built-in encryption first (WordPress 6.4+)
		if ( function_exists( 'wp_encrypt' ) ) {
			return wp_encrypt( $plaintext );
		}

		// Fallback: store as plaintext for older WordPress versions
		// This is not ideal for security, but ensures compatibility
		return $plaintext;
	}
}




