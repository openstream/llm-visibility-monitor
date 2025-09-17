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

		// Add custom schedules if not present.
		add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );

		// Admin-triggered run-now endpoint.
		add_action( 'admin_post_llmvm_run_now', array( $this, 'handle_run_now' ) );

		// Admin-triggered single prompt run endpoint.
		add_action( 'admin_post_llmvm_run_single_prompt', array( $this, 'handle_run_single_prompt' ) );
		
		// Schedule individual prompt cron jobs when prompts are added/edited
		add_action( 'llmvm_prompt_added', array( $this, 'schedule_prompt_cron' ), 10, 2 );
		add_action( 'llmvm_prompt_updated', array( $this, 'schedule_prompt_cron' ), 10, 2 );
		add_action( 'llmvm_prompt_deleted', array( $this, 'unschedule_prompt_cron' ), 10, 1 );
	}

	/**
	 * Provide custom schedules (weekly and monthly).
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
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => DAY_IN_SECONDS * 30,
				'display'  => __( 'Once Monthly', 'llm-visibility-monitor' ),
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
			// Cron is already scheduled, no need to log or reschedule
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
	 * @param string $frequency 'daily', 'weekly', or 'monthly'.
	 * @return int Unix timestamp for next run.
	 */
	private function calculate_next_run_time( string $frequency ): int {
		$now = time();

		switch ( $frequency ) {
			case 'daily':
				// Next run at 9:00 AM tomorrow.
				$next_run = strtotime( 'tomorrow 9:00 AM', $now );
				break;
			case 'weekly':
				// Next run at 9:00 AM next Monday.
				$next_run = strtotime( 'next monday 9:00 AM', $now );
				break;
			case 'monthly':
				// Next run at 9:00 AM first day of next month.
				$next_run = strtotime( 'first day of next month 9:00 AM', $now );
				break;
			default:
				$next_run = strtotime( 'tomorrow 9:00 AM', $now );
		}

		return $next_run;
	}

	/**
	 * Schedule cron job for a specific prompt.
	 *
	 * @param string $prompt_id The prompt ID.
	 * @param string $frequency The cron frequency.
	 */
	public function schedule_prompt_cron( string $prompt_id, string $frequency ): void {
		// Unschedule any existing cron for this prompt
		$this->unschedule_prompt_cron( $prompt_id );

		// Validate frequency
		$frequency = in_array( $frequency, [ 'daily', 'weekly', 'monthly' ], true ) ? $frequency : 'daily';

		// Calculate next run time
		$next_run = $this->calculate_next_run_time( $frequency );

		// Schedule the cron job with a unique hook for this prompt
		$hook = 'llmvm_run_prompt_' . $prompt_id;
		wp_schedule_event( $next_run, $frequency, $hook );

		// Add action hook for this specific prompt
		add_action( $hook, function() use ( $prompt_id ) {
			$this->run_single_prompt( $prompt_id );
		});

		LLMVM_Logger::log( 'Scheduled prompt cron job', [
			'prompt_id' => $prompt_id,
			'frequency' => $frequency,
			'next_run' => gmdate( 'Y-m-d H:i:s', $next_run )
		] );
	}

	/**
	 * Unschedule cron job for a specific prompt.
	 *
	 * @param string $prompt_id The prompt ID.
	 */
	public function unschedule_prompt_cron( string $prompt_id ): void {
		$hook = 'llmvm_run_prompt_' . $prompt_id;
		wp_clear_scheduled_hook( $hook );

		LLMVM_Logger::log( 'Unscheduled prompt cron job', [
			'prompt_id' => $prompt_id,
			'hook' => $hook
		] );
	}

	/**
	 * Run scheduled job: send prompts to OpenRouter and store results.
	 */
	public function run(): void {
		// Set execution time limit for long-running operations
		set_time_limit( 720 ); // 12 minutes
		ini_set( 'max_execution_time', 720 );
		
		$options   = get_option( 'llmvm_options', [] );
		// Handle case where options are stored as JSON string
		if ( is_string( $options ) ) {
			$options = json_decode( $options, true );
		}
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
			// If no current user (e.g., system cron), process all users
			$current_user_id = null; // Will process all users
		}

		// If running as system cron (no current user), process all users
		if ( $current_user_id === null ) {
			// Get all unique user IDs from prompts
			$user_ids = [];
			foreach ( $prompts as $prompt_item ) {
				$prompt_user_id = isset( $prompt_item['user_id'] ) ? (int) $prompt_item['user_id'] : 1;
				if ( ! in_array( $prompt_user_id, $user_ids, true ) ) {
					$user_ids[] = $prompt_user_id;
				}
			}
			
			LLMVM_Logger::log( 'System cron: processing all users', [ 'user_ids' => $user_ids ] );
			
			// Process each user individually
			foreach ( $user_ids as $user_id ) {
				// Check usage limits for each user before processing
				$user_prompts = array_filter( $prompts, function( $prompt ) use ( $user_id ) {
					return ( isset( $prompt['user_id'] ) ? (int) $prompt['user_id'] : 1 ) === $user_id;
				} );
				
				$total_runs = 0;
				foreach ( $user_prompts as $prompt_item ) {
					if ( isset( $prompt_item['models'] ) && is_array( $prompt_item['models'] ) ) {
						$total_runs += count( $prompt_item['models'] );
					} else {
						$total_runs += 1; // Fallback for single model
					}
				}
				
				// Check if user has enough runs (skip admin users)
				$user_obj = get_user_by( 'id', $user_id );
				if ( $user_obj && ! current_user_can( 'llmvm_manage_settings', $user_id ) ) {
					if ( ! LLMVM_Usage_Manager::can_run_prompts( $user_id, $total_runs ) ) {
						LLMVM_Logger::log( 'System cron: user limit reached, sending notification', [ 'user_id' => $user_id, 'runs_needed' => $total_runs ] );
						
						// Send limit notification email
						$email_reporter = new LLMVM_Email_Reporter();
						$email_reporter->send_limit_notification( $user_id );
						
						continue; // Skip processing this user
					}
				}
				
				$this->process_user_prompts( $user_id, $prompts, $client, $api_key, $model );
			}
			
			LLMVM_Logger::log( 'System cron: completed processing all users' );
			return;
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
			if ( ! LLMVM_Usage_Manager::can_run_prompts( $current_user_id, $total_runs ) ) {
				LLMVM_Logger::log( 'Run aborted: insufficient runs remaining', [ 'user_id' => $current_user_id, 'runs_needed' => $total_runs ] );
				
				// Send limit notification email
				$email_reporter = new LLMVM_Email_Reporter();
				$email_reporter->send_limit_notification( $current_user_id );
				
				return;
			}
		}

		// Process prompts for the current user
		$this->process_user_prompts( $current_user_id, $prompts, $client, $api_key, $model );
	}

	/**
	 * Add LLM request to queue for asynchronous processing.
	 *
	 * @param string $api_key API key.
	 * @param string $prompt Prompt text.
	 * @param string $model Model to use.
	 * @param int    $user_id User ID.
	 * @param string $prompt_id Prompt ID.
	 * @param int    $priority Job priority.
	 * @return int|false Job ID on success, false on failure.
	 */
	private function queue_llm_request( string $api_key, string $prompt, string $model, int $user_id, string $prompt_id = '', int $priority = 0, bool $is_batch_run = false, string $expected_answer = '', string $run_id = '' ) {
		if ( ! class_exists( 'LLMVM_Queue_Manager' ) ) {
			return false;
		}

		$queue_manager = new LLMVM_Queue_Manager();
		
		$job_data = array(
			'api_key'     => $api_key,
			'prompt'      => $prompt,
			'model'       => $model,
			'user_id'     => $user_id,
			'prompt_id'   => $prompt_id,
			'is_batch_run' => $is_batch_run,
			'expected_answer' => $expected_answer,
			'batch_run_id' => $run_id, // Use run_id as batch_run_id for filtering
		);

		return $queue_manager->add_job( 'llm_request', $job_data, $priority );
	}

	// Queue system is now always enabled - no need for decision logic

	/**
	 * Process a single LLM request synchronously.
	 *
	 * @param string $api_key API key.
	 * @param string $prompt_text Prompt text.
	 * @param string $model_to_use Model to use.
	 * @param int    $user_id User ID.
	 * @param array  $prompt_item Prompt item data.
	 * @param array  &$current_run_results Current run results array (passed by reference).
	 */
	private function process_single_llm_request( string $api_key, string $prompt_text, string $model_to_use, int $user_id, array $prompt_item, array &$current_run_results ): void {
		$client = new LLMVM_OpenRouter_Client();
		
		LLMVM_Logger::log( 'Sending prompt', [ 
			'model' => $model_to_use, 
			'original_model' => $prompt_item['model'] ?? $model_to_use, 
			'web_search' => ! empty( $prompt_item['web_search'] ), 
			'prompt_text' => $prompt_text, 
			'user_id' => $user_id 
		] );
		
		$response = $client->query( $api_key, $prompt_text, $model_to_use );
		$resp_model = isset( $response['model'] ) ? (string) $response['model'] : 'unknown';
		$answer = isset( $response['answer'] ) ? (string) $response['answer'] : '';
		$status = isset( $response['status'] ) ? (int) $response['status'] : 0;
		$error = isset( $response['error'] ) ? (string) $response['error'] : '';
		$response_time = isset( $response['response_time'] ) ? (float) $response['response_time'] : 0.0;

		// Perform comparison if expected answer is provided
		$expected_answer = isset( $prompt_item['expected_answer'] ) ? (string) $prompt_item['expected_answer'] : '';
		$comparison_score = null;
		$comparison_failed = 0;
		
		if ( ! empty( $expected_answer ) && ! empty( $answer ) ) {
			$comparison_result = LLMVM_Comparison::compare_response( $answer, $expected_answer, $prompt_text );
			
			if ( is_array( $comparison_result ) && isset( $comparison_result['failed'] ) && $comparison_result['failed'] ) {
				$comparison_score = null;
				$comparison_failed = 1;
			} else {
				$comparison_score = $comparison_result;
				$comparison_failed = 0;
			}
		}
		
		LLMVM_Logger::log( 'Inserting result', [ 
			'prompt_text' => $prompt_text, 
			'resp_model' => $resp_model, 
			'answer_length' => strlen( $answer ), 
			'user_id' => $user_id,
			'response_time_ms' => round( $response_time * 1000, 2 ),
			'comparison_score' => $comparison_score
		] );
		
		$result_id = LLMVM_Database::insert_result( $prompt_text, $resp_model, $answer, $user_id, $expected_answer, $comparison_score, $comparison_failed );
		
		// Track this result for the current run
		if ( $result_id ) {
			$current_run_results[] = [
				'id' => $result_id,
				'prompt' => $prompt_text,
				'model' => $resp_model,
				'answer' => $answer,
				'user_id' => $user_id,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'response_time' => $response_time,
				'expected_answer' => $expected_answer,
				'comparison_score' => $comparison_score
			];
		}
		
		if ( $status && $status >= 400 ) {
			LLMVM_Logger::log( 'OpenRouter error stored', [ 
				'status' => $status, 
				'error' => $error,
				'response_time_ms' => round( $response_time * 1000, 2 )
			] );
		}
	}

	/**
	 * Process prompts for a specific user.
	 */
	private function process_user_prompts( int $user_id, array $all_prompts, $client, string $api_key, string $model ): void {
		// Filter prompts to only include those belonging to the specified user
		$user_prompts = [];
		foreach ( $all_prompts as $prompt_item ) {
			$prompt_user_id = isset( $prompt_item['user_id'] ) ? (int) $prompt_item['user_id'] : 1;
			if ( $prompt_user_id === $user_id ) {
				$user_prompts[] = $prompt_item;
			}
		}

		// Debug: Log the filtered prompts for the user
		LLMVM_Logger::log( 'User prompts filtered', [
			'user_id' => $user_id,
			'total_prompts' => count( $all_prompts ),
			'user_prompts_count' => count( $user_prompts ),
			'user_prompt_ids' => array_column( $user_prompts, 'id' ),
			'user_prompt_texts' => array_column( $user_prompts, 'text' )
		] );

		if ( empty( $user_prompts ) ) {
			LLMVM_Logger::log( 'No prompts found for user', [ 'user_id' => $user_id ] );
			return;
		}

		// Track results from this run
		$current_run_results = [];
		$queued_jobs = [];

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

			// Process each model for this prompt
			foreach ( $prompt_models as $prompt_model ) {
				// Append :online to model if web search is enabled
				$model_to_use = $prompt_model;
				if ( ! empty( $prompt_item['web_search'] ) ) {
					$model_to_use = $prompt_model . ':online';
				}
				
				// Add to queue for asynchronous processing
				$job_id = $this->queue_llm_request( 
					$api_key, 
					$prompt_text, 
					$model_to_use, 
					$user_id, 
					$prompt_item['id'] ?? '',
					0, // Normal priority
					true, // is_batch_run for "run all prompts now"
					$prompt_item['expected_answer'] ?? ''
				);
				
				if ( $job_id ) {
					$queued_jobs[] = $job_id;
					LLMVM_Logger::log( 'Queued LLM request', [ 
						'job_id' => $job_id,
						'model' => $model_to_use, 
						'prompt_id' => $prompt_item['id'] ?? '',
						'user_id' => $user_id 
					] );
					
					// Trigger immediate queue processing
					wp_schedule_single_event( time(), 'llmvm_process_queue' );
				} else {
					// Fallback to synchronous processing if queue fails
					LLMVM_Logger::log( 'Queue failed, falling back to synchronous processing', [ 'model' => $model_to_use ] );
					$this->process_single_llm_request( $api_key, $prompt_text, $model_to_use, $user_id, $prompt_item, $current_run_results );
				}
			}
		}
		LLMVM_Logger::log( 'User prompts processed', [ 'user_id' => $user_id ] );

		// Track usage for non-admin users
		if ( ! current_user_can( 'llmvm_manage_settings', $user_id ) ) {
			$total_runs = 0;
			foreach ( $user_prompts as $prompt_item ) {
				if ( isset( $prompt_item['models'] ) && is_array( $prompt_item['models'] ) ) {
					$total_runs += count( $prompt_item['models'] );
				} else {
					$total_runs += 1; // Fallback for single model
				}
			}
			LLMVM_Database::increment_usage( $user_id, 0, $total_runs );
			LLMVM_Logger::log( 'Usage tracked for run', [ 'user_id' => $user_id, 'runs' => $total_runs ] );
		}

		// Queue system handles email reporting when all jobs complete
		LLMVM_Logger::log( 'Queue system handles email reporting when all jobs complete', array( 'user_id' => $user_id ) );
	}

	/**
	 * Run a single prompt by ID.
	 */
	public function run_single_prompt( string $prompt_id ): void {
		LLMVM_Logger::log( 'run_single_prompt method called', array( 'prompt_id' => $prompt_id ) );
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

		// Get models for this prompt (handle both old 'model' and new 'models' format)
		$prompt_models = array();
		if ( isset( $target_prompt['models'] ) && is_array( $target_prompt['models'] ) ) {
			$prompt_models = $target_prompt['models'];
		} elseif ( isset( $target_prompt['model'] ) && '' !== trim( $target_prompt['model'] ) ) {
			$prompt_models = array( $target_prompt['model'] );
		} else {
			$prompt_models = array( $model ); // Fall back to global default
		}
		
		LLMVM_Logger::log( 'Single prompt models extracted', array(
			'prompt_id' => $prompt_id,
			'prompt_models' => $prompt_models,
			'has_models_array' => isset( $target_prompt['models'] ),
			'has_model_single' => isset( $target_prompt['model'] ),
			'target_prompt' => $target_prompt
		) );

		// Check usage limits for non-admin users
		if ( ! $is_admin ) {
			$runs_needed = count( $prompt_models );
			if ( ! LLMVM_Usage_Manager::can_run_prompts( $current_user_id, $runs_needed ) ) {
				LLMVM_Logger::log( 'Single prompt run aborted: insufficient runs remaining', [
					'user_id' => $current_user_id,
					'runs_needed' => $runs_needed,
					'prompt_id' => $prompt_id
				] );
				
				// Send limit notification email
				$email_reporter = new LLMVM_Email_Reporter();
				$email_reporter->send_limit_notification( $current_user_id );
				
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

		// Use the current user ID who is running the job
		$user_id = $current_user_id;

		// Track results from this run
		$current_run_results = [];

		// Generate a unique run ID for this single prompt run
		$single_run_id = $prompt_id . '_single_' . time() . '_' . wp_generate_password( 8, false );

		// Queue system is always enabled
		$queued_jobs = [];
		
		LLMVM_Logger::log( 'Single prompt processing', array( 
			'prompt_id' => $prompt_id, 
			'prompt_models_count' => count( $prompt_models ),
			'single_run_id' => $single_run_id
		) );

		// Process each model for this prompt
		foreach ( $prompt_models as $prompt_model ) {
			// Append :online to model if web search is enabled
			$model_to_use = $prompt_model;
			if ( ! empty( $target_prompt['web_search'] ) ) {
				$model_to_use = $prompt_model . ':online';
			}
			
			// Always use queue system
			LLMVM_Logger::log( 'Attempting to queue single prompt model', array(
				'model' => $model_to_use,
				'prompt_id' => $prompt_id,
				'user_id' => $user_id
			) );
			
			// Add to queue for asynchronous processing
			$job_id = $this->queue_llm_request( 
				$api_key, 
				$prompt_text, 
				$model_to_use, 
				$user_id, 
				$prompt_id,
				0, // Normal priority
				false, // is_batch_run for single prompt
				$target_prompt['expected_answer'] ?? '',
				$single_run_id // Pass the single run ID for filtering
			);
			
			if ( $job_id ) {
				$queued_jobs[] = $job_id;
				LLMVM_Logger::log( 'Queued single prompt LLM request', [ 
					'job_id' => $job_id,
					'model' => $model_to_use, 
					'prompt_id' => $prompt_id,
					'user_id' => $user_id 
				] );
				
				// Trigger immediate queue processing
				wp_schedule_single_event( time(), 'llmvm_process_queue' );
			} else {
				// Fallback to synchronous processing if queue fails
				LLMVM_Logger::log( 'Queue failed for single prompt, falling back to synchronous processing', [ 'model' => $model_to_use ] );
				$this->process_single_llm_request( $api_key, $prompt_text, $model_to_use, $user_id, $target_prompt, $current_run_results );
			}
		}

		LLMVM_Logger::log( 'Single prompt run completed', [ 'prompt_id' => $prompt_id ] );

		// Track usage for non-admin users
		if ( ! current_user_can( 'llmvm_manage_settings' ) ) {
			$runs_count = count( $prompt_models );
			LLMVM_Database::increment_usage( $current_user_id, 0, $runs_count );
			LLMVM_Logger::log( 'Usage tracked for single prompt run', [ 'user_id' => $current_user_id, 'runs' => $runs_count ] );
		}

		// Queue system handles email reporting when all jobs complete
		LLMVM_Logger::log( 'Queue system handles email reporting when all jobs complete', array( 'user_id' => $current_user_id ) );
	}
	
	/**
	 * Run all prompts with progress tracking
	 */
	public function run_with_progress( string $run_id ): void {
		// Set execution time limit for long-running operations
		set_time_limit( 720 ); // 12 minutes
		ini_set( 'max_execution_time', 720 );
		
		$options = get_option( 'llmvm_options', [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}
		$raw_key = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
		$api_key = $raw_key !== '' ? self::decrypt_api_key( $raw_key ) : '';
		$model = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
		$prompts = get_option( 'llmvm_prompts', [] );
		if ( ! is_array( $prompts ) ) {
			$prompts = [];
		}

		$current_user_id = get_current_user_id();
		$is_admin = current_user_can( 'llmvm_manage_settings' );

		// Filter prompts for the current user first
		$user_prompts = array();
		foreach ( $prompts as $prompt ) {
			$prompt_user_id = isset( $prompt['user_id'] ) ? (int) $prompt['user_id'] : 1;
			
			// Skip prompts not owned by current user (unless admin)
			if ( ! $is_admin && $prompt_user_id !== $current_user_id ) {
				continue;
			}

			$prompt_text = isset( $prompt['text'] ) ? (string) $prompt['text'] : '';
			if ( '' === trim( $prompt_text ) ) {
				continue;
			}

			$user_prompts[] = $prompt;
		}

		// Calculate total steps for progress tracking based on filtered prompts
		$total_steps = 0;
		foreach ( $user_prompts as $prompt ) {
			if ( isset( $prompt['models'] ) && is_array( $prompt['models'] ) ) {
				$total_steps += count( $prompt['models'] );
			} else {
				$total_steps += 1; // Default to 1 if no models array
			}
		}

		// Initialize progress tracking
		LLMVM_Progress_Tracker::init_progress( $run_id, $total_steps, 'Starting prompt execution...' );

		// Check usage limits for non-admin users
		if ( ! $is_admin ) {
			$total_runs = $total_steps; // Use the same calculation
			
			if ( ! LLMVM_Usage_Manager::can_run_prompts( $current_user_id, $total_runs ) ) {
				LLMVM_Progress_Tracker::complete_progress( $run_id, 'Run aborted: insufficient runs remaining' );
				
				// Send limit notification email
				$email_reporter = new LLMVM_Email_Reporter();
				$email_reporter->send_limit_notification( $current_user_id );
				
				return;
			}
		}

		$client = new LLMVM_OpenRouter_Client();
		$current_step = 0;
		$current_run_results = [];

		// Process prompts for the current user
		foreach ( $user_prompts as $prompt ) {
			$prompt_user_id = isset( $prompt['user_id'] ) ? (int) $prompt['user_id'] : 1;
			$prompt_text = isset( $prompt['text'] ) ? (string) $prompt['text'] : '';

			// Get models for this prompt
			$prompt_models = array();
			if ( isset( $prompt['models'] ) && is_array( $prompt['models'] ) ) {
				$prompt_models = $prompt['models'];
			} elseif ( isset( $prompt['model'] ) && '' !== trim( $prompt['model'] ) ) {
				$prompt_models = array( $prompt['model'] );
			} else {
				$prompt_models = array( $model );
			}

		// Process each model for this prompt
		foreach ( $prompt_models as $prompt_model ) {
			// Show model name with :online suffix if web search is enabled
			$display_model = $prompt_model;
			if ( ! empty( $prompt['web_search'] ) ) {
				$display_model = $prompt_model . ':online';
			}
			
			// Update progress to show we're starting this model
			LLMVM_Progress_Tracker::update_progress( $run_id, $current_step, 'Starting model: ' . $display_model );

			// Append :online to model if web search is enabled
			$model_to_use = $prompt_model;
			if ( ! empty( $prompt['web_search'] ) ) {
				$model_to_use = $prompt_model . ':online';
			}

			$response = $client->query( $api_key, $prompt_text, $model_to_use );
			$resp_model = isset( $response['model'] ) ? (string) $response['model'] : 'unknown';
			$answer = isset( $response['answer'] ) ? (string) $response['answer'] : '';
			$status = isset( $response['status'] ) ? (int) $response['status'] : 0;
			$error = isset( $response['error'] ) ? (string) $response['error'] : '';
			$response_time = isset( $response['response_time'] ) ? (float) $response['response_time'] : 0.0;

			// Store result
			$result = LLMVM_Database::insert_result( $prompt_text, $resp_model, $answer, $prompt_user_id );
			if ( $result ) {
				$current_run_results[] = [
					'id' => $result,
					'prompt' => $prompt_text,
					'model' => $resp_model,
					'answer' => $answer,
					'user_id' => $prompt_user_id,
					'created_at' => gmdate( 'Y-m-d H:i:s' ),
					'status' => $status,
					'error' => $error,
					'response_time' => $response_time
				];
			}

			if ( $status && $status >= 400 ) {
				LLMVM_Logger::log( 'OpenRouter error stored', [ 'status' => $status, 'error' => $error ] );
			}
			
			// Update progress after model is completed with response time info
			$current_step++;
			$completion_message = sprintf( 
				'Completed model: %s (%.2fs)', 
				$display_model, 
				$response_time 
			);
			LLMVM_Progress_Tracker::update_progress( $run_id, $current_step, $completion_message );
		}
		}

		// Complete progress tracking
		LLMVM_Progress_Tracker::complete_progress( $run_id, 'All prompts completed successfully!' );

		// Track usage for non-admin users
		if ( ! $is_admin ) {
			$runs_count = $current_step;
			LLMVM_Database::increment_usage( $current_user_id, 0, $runs_count );
		}

		// Queue system handles email reporting when all jobs complete
		LLMVM_Logger::log( 'Queue system handles email reporting when all jobs complete', array( 'user_id' => $current_user_id ) );
	}
	
	/**
	 * Run single prompt with progress tracking
	 */
	public function run_single_prompt_with_progress( string $prompt_id, string $run_id ): void {
		// Set execution time limit for long-running operations
		set_time_limit( 720 ); // 12 minutes
		ini_set( 'max_execution_time', 720 );
		
		$options = get_option( 'llmvm_options', [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}
		$raw_key = isset( $options['api_key'] ) ? (string) $options['api_key'] : '';
		$api_key = $raw_key !== '' ? self::decrypt_api_key( $raw_key ) : '';
		$model = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
		$prompts = get_option( 'llmvm_prompts', [] );
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
			LLMVM_Progress_Tracker::complete_progress( $run_id, 'Error: Prompt not found' );
			return;
		}

		// Check if current user can run this prompt
		$current_user_id = get_current_user_id();
		$prompt_user_id = isset( $target_prompt['user_id'] ) ? (int) $target_prompt['user_id'] : 1;
		$is_admin = current_user_can( 'llmvm_manage_settings' );

		if ( ! $is_admin && $prompt_user_id !== $current_user_id ) {
			LLMVM_Progress_Tracker::complete_progress( $run_id, 'Error: Not authorized to run this prompt' );
			return;
		}

		// Get models for this prompt
		$prompt_models = array();
		if ( isset( $target_prompt['models'] ) && is_array( $target_prompt['models'] ) ) {
			$prompt_models = $target_prompt['models'];
		} elseif ( isset( $target_prompt['model'] ) && '' !== trim( $target_prompt['model'] ) ) {
			$prompt_models = array( $target_prompt['model'] );
		} else {
			$prompt_models = array( $model );
		}

		// Check usage limits for non-admin users
		if ( ! $is_admin ) {
			$runs_needed = count( $prompt_models );
			if ( ! LLMVM_Usage_Manager::can_run_prompts( $current_user_id, $runs_needed ) ) {
				LLMVM_Progress_Tracker::complete_progress( $run_id, 'Error: Insufficient runs remaining' );
				
				// Send limit notification email
				$email_reporter = new LLMVM_Email_Reporter();
				$email_reporter->send_limit_notification( $current_user_id );
				
				return;
			}
		}

		// Initialize progress tracking
		LLMVM_Progress_Tracker::init_progress( $run_id, count( $prompt_models ), 'Starting single prompt execution...' );

		$client = new LLMVM_OpenRouter_Client();
		$prompt_text = isset( $target_prompt['text'] ) ? (string) $target_prompt['text'] : '';
		$current_step = 0;
		$current_run_results = [];

		// Process each model for this prompt
		foreach ( $prompt_models as $prompt_model ) {
			// Show model name with :online suffix if web search is enabled
			$display_model = $prompt_model;
			if ( ! empty( $target_prompt['web_search'] ) ) {
				$display_model = $prompt_model . ':online';
			}
			
			// Update progress to show we're starting this model
			LLMVM_Progress_Tracker::update_progress( $run_id, $current_step, 'Starting model: ' . $display_model );

			// Append :online to model if web search is enabled
			$model_to_use = $prompt_model;
			if ( ! empty( $target_prompt['web_search'] ) ) {
				$model_to_use = $prompt_model . ':online';
			}

			$response = $client->query( $api_key, $prompt_text, $model_to_use );
			$resp_model = isset( $response['model'] ) ? (string) $response['model'] : 'unknown';
			$answer = isset( $response['answer'] ) ? (string) $response['answer'] : '';
			$status = isset( $response['status'] ) ? (int) $response['status'] : 0;
			$error = isset( $response['error'] ) ? (string) $response['error'] : '';
			$response_time = isset( $response['response_time'] ) ? (float) $response['response_time'] : 0.0;

			// Store result
			$result = LLMVM_Database::insert_result( $prompt_text, $resp_model, $answer, $current_user_id );
			if ( $result ) {
				$current_run_results[] = [
					'id' => $result,
					'prompt' => $prompt_text,
					'model' => $resp_model,
					'answer' => $answer,
					'user_id' => $current_user_id,
					'created_at' => gmdate( 'Y-m-d H:i:s' ),
					'status' => $status,
					'error' => $error,
					'response_time' => $response_time
				];
			}

			if ( $status && $status >= 400 ) {
				LLMVM_Logger::log( 'OpenRouter error stored for single prompt', [ 'status' => $status, 'error' => $error ] );
			}
			
			// Update progress after model is completed with response time info
			$current_step++;
			$completion_message = sprintf( 
				'Completed model: %s (%.2fs)', 
				$display_model, 
				$response_time 
			);
			LLMVM_Progress_Tracker::update_progress( $run_id, $current_step, $completion_message );
		}

		// Complete progress tracking
		LLMVM_Progress_Tracker::complete_progress( $run_id, 'Single prompt completed successfully!' );

		// Track usage for non-admin users
		if ( ! $is_admin ) {
			$runs_count = count( $prompt_models );
			LLMVM_Database::increment_usage( $current_user_id, 0, $runs_count );
		}

		// Queue system handles email reporting when all jobs complete
		LLMVM_Logger::log( 'Queue system handles email reporting when all jobs complete', array( 'user_id' => $current_user_id ) );
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
		
		// Get run ID for progress tracking
		$run_id = isset( $_GET['run_id'] ) ? sanitize_text_field( wp_unslash( $_GET['run_id'] ) ) : '';
		
		LLMVM_Logger::log( 'Run Now triggered', [ 'user_id' => get_current_user_id(), 'user_roles' => implode( ', ', wp_get_current_user()->roles ), 'run_id' => $run_id ] );
		
		// Run with progress tracking if run_id is provided
		if ( ! empty( $run_id ) ) {
			$this->run_with_progress( $run_id );
		} else {
			$this->run();
		}
		
		wp_safe_redirect( wp_nonce_url( admin_url( 'tools.php?page=llmvm-dashboard&llmvm_ran=1' ), 'llmvm_run_completed' ) ?: '' );
		exit;
	}

	/**
	 * Handle manual run of a single prompt.
	 */
	public function handle_run_single_prompt(): void {
		LLMVM_Logger::log( 'handle_run_single_prompt method called' );
		
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
		
		// Get run ID for progress tracking
		$run_id = isset( $_GET['run_id'] ) ? sanitize_text_field( wp_unslash( $_GET['run_id'] ) ) : '';

		LLMVM_Logger::log( 'Single prompt run triggered', [
			'user_id' => get_current_user_id(),
			'user_roles' => implode( ', ', wp_get_current_user()->roles ),
			'prompt_id' => $prompt_id,
			'run_id' => $run_id
		] );

		// Run with progress tracking if run_id is provided
		if ( ! empty( $run_id ) ) {
			$this->run_single_prompt_with_progress( $prompt_id, $run_id );
		} else {
			$this->run_single_prompt( $prompt_id );
		}

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




