<?php
/**
 * Queue manager for LLM Visibility Monitor.
 * Handles asynchronous processing of LLM requests to prevent timeouts.
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue manager class for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */
class LLMVM_Queue_Manager {

	/**
	 * Queue table name.
	 */
	private const QUEUE_TABLE = 'llmvm_queue';


	/**
	 * Job timeout in seconds.
	 */
	private const JOB_TIMEOUT = 300; // 5 minutes

	/**
	 * Maximum concurrent jobs to process at once.
	 */
	private const MAX_CONCURRENT_JOBS = 1;

	/**
	 * Current job ID being processed.
	 */
	private $current_job_id = 0;

	/**
	 * Results from the current run for email reporting.
	 */
	private static $current_run_results = [];

	/**
	 * Current run ID to track when we're in the same run.
	 */
	private static $current_run_id = null;

	/**
	 * Initialize the queue manager.
	 */
	public function __construct() {
		$this->create_queue_table();
		$this->create_current_run_results_table();
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks(): void {
		// Process queue via WordPress cron (every minute)
		add_action( 'llmvm_process_queue', array( $this, 'process_queue' ) );
		
		// Process queue on admin_init (for immediate processing when admin visits)
		add_action( 'admin_init', array( $this, 'process_queue' ) );
		
		// Clean up old completed jobs
		add_action( 'llmvm_cleanup_queue', array( $this, 'cleanup_old_jobs' ) );
		
		// Schedule queue processing if not already scheduled
		if ( ! wp_next_scheduled( 'llmvm_process_queue' ) ) {
			wp_schedule_event( time(), 'llmvm_queue_interval', 'llmvm_process_queue' );
		}
		
		// Schedule cleanup if not already scheduled
		if ( ! wp_next_scheduled( 'llmvm_cleanup_queue' ) ) {
			wp_schedule_event( time(), 'daily', 'llmvm_cleanup_queue' );
		}
		
		// Add custom cron interval for queue processing
		add_filter( 'cron_schedules', array( $this, 'add_queue_cron_interval' ) );
	}

	/**
	 * Create the queue table if it doesn't exist.
	 */
	private function create_queue_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::QUEUE_TABLE;
		
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL DEFAULT 0,
			job_type varchar(50) NOT NULL,
			job_data longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			priority int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			started_at datetime NULL,
			completed_at datetime NULL,
			error_message text NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY priority (priority),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		
		// Check if user_id column exists, if not add it (migration)
		$this->migrate_add_user_id_column();
	}

	/**
	 * Migrate existing queue table to add user_id column.
	 */
	private function migrate_add_user_id_column(): void {
		global $wpdb;
		
		$table_name = $wpdb->prefix . self::QUEUE_TABLE;
		
		// Check if user_id column exists
		$column_exists = $wpdb->get_results( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'user_id'",
			DB_NAME,
			$table_name
		) );
		
		if ( empty( $column_exists ) ) {
			// Add user_id column
			$wpdb->query( "ALTER TABLE $table_name ADD COLUMN user_id bigint(20) NOT NULL DEFAULT 0 AFTER id" );
			$wpdb->query( "ALTER TABLE $table_name ADD KEY user_id (user_id)" );
			
			LLMVM_Logger::log( 'Added user_id column to queue table', array( 'table' => $table_name ) );
		}
	}

	/**
	 * Create the current run results table if it doesn't exist.
	 */
	private function create_current_run_results_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'llmvm_current_run_results';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			run_id varchar(100) NOT NULL,
			result_id bigint(20) NOT NULL,
			prompt text NOT NULL,
			model varchar(100) NOT NULL,
			answer longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY run_id (run_id),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add a job to the queue.
	 *
	 * @param string $job_type Type of job (e.g., 'llm_request').
	 * @param array  $job_data Job data.
	 * @param int    $priority Job priority (higher = more important).
	 * @return int|false Job ID on success, false on failure.
	 */
	public function add_job( string $job_type, array $job_data, int $priority = 0 ) {
		// Generate a run ID based on user_id and prompt_id to group jobs from the same prompt
		$user_id = $job_data['user_id'] ?? 0;
		$prompt_id = $job_data['prompt_id'] ?? '';
		
		// Check if this is a batch run (run all prompts now)
		$is_batch_run = $job_data['is_batch_run'] ?? false;
		if ( $is_batch_run ) {
			// For batch runs, use a single run ID for all prompts from the same user
			// Check if there's already a batch run in progress for this user
			global $wpdb;
			$existing_batch = $wpdb->get_var( $wpdb->prepare(
				"SELECT DISTINCT JSON_EXTRACT(job_data, '$.batch_run_id') 
				FROM {$wpdb->prefix}llmvm_queue 
				WHERE user_id = %d 
				AND JSON_EXTRACT(job_data, '$.is_batch_run') = 1 
				AND status IN ('pending', 'processing')
				LIMIT 1",
				$user_id
			) );
			
			if ( $existing_batch ) {
				$run_id = $existing_batch;
				LLMVM_Logger::log( 'Using existing batch run ID', array(
					'user_id' => $user_id,
					'batch_run_id' => $run_id
				) );
			} else {
				$run_id = $user_id . '_batch_' . date( 'YmdHis' );
				LLMVM_Logger::log( 'Created new batch run ID', array(
					'user_id' => $user_id,
					'batch_run_id' => $run_id
				) );
			}
			
			// Store the batch run ID in job data for future reference
			$job_data['batch_run_id'] = $run_id;
		} else {
			// For single prompt runs, check if a run_id was provided
			if ( ! empty( $job_data['batch_run_id'] ) ) {
				// Use the provided run_id (from single prompt run)
				$run_id = $job_data['batch_run_id'];
				LLMVM_Logger::log( 'Using provided single run ID', array(
					'user_id' => $user_id,
					'prompt_id' => $prompt_id,
					'run_id' => $run_id
				) );
			} else {
				// Fallback to prompt_id for backward compatibility
				$run_id = $user_id . '_' . $prompt_id;
				$job_data['batch_run_id'] = $run_id;
				LLMVM_Logger::log( 'Using fallback run ID', array(
					'user_id' => $user_id,
					'prompt_id' => $prompt_id,
					'run_id' => $run_id
				) );
			}
		}
		
		// Only clear results if this is a new run (different prompt)
		if ( self::$current_run_id !== $run_id ) {
			self::$current_run_results = [];
			self::$current_run_id = $run_id;
			LLMVM_Logger::log( 'New run detected, cleared results', array(
				'user_id' => $user_id,
				'prompt_id' => $prompt_id,
				'run_id' => $run_id,
				'previous_run_id' => self::$current_run_id
			) );
		} else {
			LLMVM_Logger::log( 'Same run, keeping existing results', array(
				'user_id' => $user_id,
				'prompt_id' => $prompt_id,
				'run_id' => $run_id,
				'current_results_count' => count( self::$current_run_results )
			) );
		}
		
		global $wpdb;

		$table_name = $wpdb->prefix . self::QUEUE_TABLE;

		// Debug logging
		LLMVM_Logger::log( 'Attempting to add job to queue', array( 
			'job_type' => $job_type, 
			'job_data' => $job_data,
			'priority' => $priority,
			'table_name' => $table_name
		) );

		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'      => $user_id,
				'job_type'     => $job_type,
				'job_data'     => wp_json_encode( $job_data ),
				'priority'     => $priority,
				'status'       => 'pending',
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( $result === false ) {
			LLMVM_Logger::log( 'Failed to add job to queue', array( 
				'job_type' => $job_type, 
				'error' => $wpdb->last_error,
				'last_query' => $wpdb->last_query
			) );
			return false;
		}

		$job_id = $wpdb->insert_id;
		LLMVM_Logger::log( 'Job added to queue successfully', array( 
			'job_id' => $job_id, 
			'job_type' => $job_type, 
			'priority' => $priority,
			'insert_id' => $job_id
		) );

		return $job_id;
	}

	/**
	 * Add custom cron interval for queue processing.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_queue_cron_interval( array $schedules ): array {
		$schedules['llmvm_queue_interval'] = array(
			'interval' => 60, // Every minute
			'display'  => __( 'Every Minute (LLM Queue)', 'llm-visibility-monitor' ),
		);
		return $schedules;
	}

	/**
	 * Process pending jobs in the queue.
	 */
	public function process_queue(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::QUEUE_TABLE;

		// Get concurrency limit from settings
		$options = get_option( 'llmvm_options', array() );
		$max_concurrent = isset( $options['queue_concurrency'] ) ? (int) $options['queue_concurrency'] : self::MAX_CONCURRENT_JOBS;
		
		// Ensure it's within reasonable bounds
		$max_concurrent = max( 1, min( 5, $max_concurrent ) );

		// Check how many jobs are currently being processed
		$current_processing = $wpdb->get_var(
			"SELECT COUNT(*) FROM $table_name WHERE status = 'processing'"
		);

		// If we're at the concurrency limit, don't process more jobs
		if ( $current_processing >= $max_concurrent ) {
			LLMVM_Logger::log( 'Concurrency limit reached, skipping queue processing', array(
				'current_processing' => $current_processing,
				'max_concurrent' => $max_concurrent
			) );
			return;
		}

		// Calculate how many jobs we can process
		$jobs_to_process = $max_concurrent - $current_processing;

		// Get pending jobs, ordered by priority and creation time
		// Use a more specific query to prevent race conditions
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name 
				WHERE status = 'pending' 
				AND (started_at IS NULL OR started_at = '0000-00-00 00:00:00')
				ORDER BY priority DESC, created_at ASC 
				LIMIT %d",
				$jobs_to_process
			),
			ARRAY_A
		);

		// Log job details for debugging
		foreach ( $jobs as $job ) {
			LLMVM_Logger::log( 'Found pending job', array(
				'job_id' => $job['id'],
				'status' => $job['status'],
				'created_at' => $job['created_at']
			) );
		}

		if ( empty( $jobs ) ) {
			return;
		}

		LLMVM_Logger::log( 'Processing queue', array( 
			'job_count' => count( $jobs ),
			'current_processing' => $current_processing,
			'max_concurrent' => $max_concurrent,
			'jobs_to_process' => $jobs_to_process
		) );

		foreach ( $jobs as $job ) {
			$this->process_job( $job );
		}

		// After processing all jobs, check if any users have completed all their jobs
		$this->check_completed_users_for_email();
	}

	/**
	 * Get current job ID being processed.
	 *
	 * @return int Current job ID.
	 */
	private function get_current_job_id(): int {
		return $this->current_job_id;
	}

	/**
	 * Check if any users have completed all their jobs and fire email actions.
	 */
	private function check_completed_users_for_email(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::QUEUE_TABLE;

		// Get all users who have jobs in the queue
		$users_with_jobs = $wpdb->get_col(
			"SELECT DISTINCT JSON_EXTRACT(job_data, '$.user_id') as user_id 
			FROM $table_name 
			WHERE status IN ('pending', 'processing', 'completed')"
		);

		foreach ( $users_with_jobs as $user_id ) {
			$user_id = (int) $user_id;
			if ( $user_id <= 0 ) {
				continue;
			}

			// Check if this user has any pending or processing jobs
			$pending_jobs = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name 
				WHERE JSON_EXTRACT(job_data, '$.user_id') = %d 
				AND status IN ('pending', 'processing')",
				$user_id
			) );

			// If no pending jobs, check if they have completed jobs and fire email
			if ( $pending_jobs == 0 ) {
				$completed_jobs = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name 
					WHERE JSON_EXTRACT(job_data, '$.user_id') = %d 
					AND status = 'completed'",
					$user_id
				) );

				if ( $completed_jobs > 0 ) {
					LLMVM_Logger::log( 'All jobs completed for user, checking email action', array(
						'user_id' => $user_id,
						'completed_jobs' => $completed_jobs
					) );
					$this->check_and_fire_email_action( $user_id );
				}
			}
		}
	}

	/**
	 * Store result for email reporting and check if all jobs for this run are complete.
	 *
	 * @param int $result_id Result ID.
	 * @param int $user_id User ID.
	 */
	/**
	 * Get recent results for a user from the database.
	 *
	 * @param int $user_id User ID.
	 * @param int $minutes_back How many minutes back to look for results.
	 * @return array Array of recent results.
	 */
	private function get_recent_results_for_user( int $user_id, int $minutes_back = 5 ): array {
		global $wpdb;
		
		$start_time = microtime( true );
		
		// Use UTC time to match database storage
		$cutoff_time = date( 'Y-m-d H:i:s', time() - ( $minutes_back * 60 ) );
		
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}llm_visibility_results 
			WHERE user_id = %d 
			AND created_at >= %s 
			ORDER BY created_at DESC",
			$user_id,
			$cutoff_time
		), ARRAY_A );
		
		// Debug: also check what results exist for this user (without time filter)
		$all_user_results = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, created_at FROM {$wpdb->prefix}llm_visibility_results 
			WHERE user_id = %d 
			ORDER BY created_at DESC 
			LIMIT 5",
			$user_id
		), ARRAY_A );
		
		// Debug: check if there are any results at all in the table
		$total_results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}llm_visibility_results" );
		$recent_results_all_users = $wpdb->get_results( 
			"SELECT id, user_id, created_at FROM {$wpdb->prefix}llm_visibility_results 
			ORDER BY created_at DESC 
			LIMIT 10",
			ARRAY_A 
		);
		
		$query_time = microtime( true ) - $start_time;
		
		LLMVM_Logger::log( 'Retrieved recent results from database', array(
			'user_id' => $user_id,
			'minutes_back' => $minutes_back,
			'cutoff_time_utc' => $cutoff_time,
			'current_time_utc' => date( 'Y-m-d H:i:s' ),
			'current_time_wp' => gmdate( 'Y-m-d H:i:s' ),
			'results_count' => count( $results ),
			'all_user_results' => $all_user_results,
			'total_results_in_table' => $total_results,
			'recent_results_all_users' => $recent_results_all_users,
			'query_time_ms' => round( $query_time * 1000, 2 )
		) );
		
		return $results;
	}

	/**
	 * Check if all jobs for a user are complete and fire email action if so.
	 *
	 * @param int $user_id User ID to check.
	 */
	private function check_and_fire_email_action( int $user_id ): void {
		global $wpdb;
		
		$start_time = microtime( true );
		
		// Get the most recent completed job for this user to get the run_id
		$recent_job = $wpdb->get_row( $wpdb->prepare(
			"SELECT job_data FROM {$wpdb->prefix}llmvm_queue
			WHERE JSON_EXTRACT(job_data, '$.user_id') = %d
			AND status = 'completed'
			ORDER BY completed_at DESC
			LIMIT 1",
			$user_id
		), ARRAY_A );

		$prompt_id = '';
		$batch_run_id = '';
		if ( $recent_job && isset( $recent_job['job_data'] ) ) {
			$job_data = json_decode( $recent_job['job_data'], true );
			$prompt_id = $job_data['prompt_id'] ?? '';
			$batch_run_id = $job_data['batch_run_id'] ?? '';
			
			// Clean up batch_run_id if it has extra quotes from JSON encoding
			if ( is_string( $batch_run_id ) && strlen( $batch_run_id ) > 2 && $batch_run_id[0] === '"' && $batch_run_id[-1] === '"' ) {
				$batch_run_id = trim( $batch_run_id, '"' );
			}
		}
		
		// Check if all pending jobs for this user are complete
		$pending_check_start = microtime( true );
		$pending_jobs = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}llmvm_queue WHERE user_id = %d AND status IN ('pending', 'processing')",
			$user_id
		) );
		$pending_check_time = microtime( true ) - $pending_check_start;
		
		// Ensure pending_jobs is an integer
		$pending_jobs = (int) $pending_jobs;

		LLMVM_Logger::log( 'Checking pending jobs for email trigger', array(
			'user_id' => $user_id,
			'pending_jobs' => $pending_jobs
		) );

		// If no pending jobs, fire the email action
		if ( $pending_jobs == 0 ) {
			$email_action_start = microtime( true );
			
			// Use current run results from database
			// For batch runs, use batch_run_id; for single runs, use prompt_id
			$run_id = $batch_run_id ?: ( $user_id . '_' . $prompt_id );
			$table_name = $wpdb->prefix . 'llmvm_current_run_results';
			
			$recent_results = $wpdb->get_results( $wpdb->prepare(
				"SELECT result_id as id, prompt, model, answer, user_id, created_at 
				FROM $table_name 
				WHERE run_id = %s OR run_id = %s
				ORDER BY created_at ASC",
				$run_id,
				'"' . $run_id . '"'
			), ARRAY_A );
			
			LLMVM_Logger::log( 'Using current run results for email from database', array(
				'user_id' => $user_id,
				'run_id' => $run_id,
				'results_count' => count( $recent_results ),
				'results' => $recent_results
			) );
			
			if ( ! empty( $recent_results ) ) {
				LLMVM_Logger::log( 'Firing email action for completed queue jobs', array(
					'user_id' => $user_id,
					'results_count' => count( $recent_results ),
					'first_result' => $recent_results[0] ?? null,
					'is_array' => is_array( $recent_results ),
					'is_empty' => empty( $recent_results )
				) );
				
				// Store results in global variable for email reporter
				$GLOBALS['llmvm_current_run_results'] = $recent_results;
				
				LLMVM_Logger::log( 'Stored results in global variable', array(
					'user_id' => $user_id,
					'global_results_count' => count( $GLOBALS['llmvm_current_run_results'] ),
					'global_variable_set' => isset( $GLOBALS['llmvm_current_run_results'] )
				) );
				
				// Fire action hook for email reporter with database results
				do_action( 'llmvm_run_completed', $user_id, $recent_results );
				$email_action_time = microtime( true ) - $email_action_start;
				
				// Clear current run results after email is sent
				$run_id = $user_id . '_' . $prompt_id;
				$table_name = $wpdb->prefix . 'llmvm_current_run_results';
				$deleted = $wpdb->delete( $table_name, array( 'run_id' => $run_id ), array( '%s' ) );
				
				LLMVM_Logger::log( 'Cleared current run results from database', array(
					'user_id' => $user_id,
					'run_id' => $run_id,
					'deleted_rows' => $deleted
				) );
				
				$total_time = microtime( true ) - $start_time;
				LLMVM_Logger::log( 'All queue jobs completed for user, firing email action', array(
					'user_id' => $user_id,
					'results_count' => count( $recent_results ),
					'total_time_ms' => round( $total_time * 1000, 2 ),
					'pending_check_time_ms' => round( $pending_check_time * 1000, 2 ),
					'email_action_time_ms' => round( $email_action_time * 1000, 2 )
				) );
			} else {
				LLMVM_Logger::log( 'No recent results found for email reporting', array(
					'user_id' => $user_id,
					'results_count' => 0
				) );
			}
		} else {
			$total_time = microtime( true ) - $start_time;
			LLMVM_Logger::log( 'Still pending jobs, not firing email action', array(
				'user_id' => $user_id,
				'pending_jobs' => $pending_jobs,
				'total_time_ms' => round( $total_time * 1000, 2 ),
				'pending_check_time_ms' => round( $pending_check_time * 1000, 2 )
			) );
		}
	}

	/**
	 * Process a single job.
	 *
	 * @param array $job Job data.
	 */
	private function process_job( array $job ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::QUEUE_TABLE;
		$job_id = (int) $job['id'];
		$this->current_job_id = $job_id;
		$start_time = microtime( true );

		// Mark job as processing (atomic update to prevent race conditions)
		$db_update_start = microtime( true );
		$updated = $wpdb->update(
			$table_name,
			array(
				'status'     => 'processing',
				'started_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 
				'id' => $job_id,
				'status' => 'pending' // Only update if still pending
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
		$db_update_time = microtime( true ) - $db_update_start;

		// If no rows were updated, job was already being processed by another instance
		if ( $updated === 0 ) {
			LLMVM_Logger::log( 'Job already being processed, skipping', array( 'job_id' => $job_id ) );
			return;
		}

		LLMVM_Logger::log( 'Processing job', array( 
			'job_id' => $job_id, 
			'job_type' => $job['job_type'],
			'db_update_time_ms' => round( $db_update_time * 1000, 2 )
		) );

		try {
			$json_decode_start = microtime( true );
			$job_data = json_decode( $job['job_data'], true );
			$json_decode_time = microtime( true ) - $json_decode_start;
			
			if ( ! is_array( $job_data ) ) {
				throw new Exception( 'Invalid job data' );
			}

			// Extract user_id for email action check
			$user_id = $job_data['user_id'] ?? 0;

			// Process based on job type
			$processing_start = microtime( true );
			switch ( $job['job_type'] ) {
				case 'llm_request':
					$this->process_llm_request( $job_data );
					break;
				default:
					throw new Exception( 'Unknown job type: ' . $job['job_type'] );
			}
			$processing_time = microtime( true ) - $processing_start;

			// Mark job as completed
			$completion_start = microtime( true );
			$wpdb->update(
				$table_name,
				array(
					'status'       => 'completed',
					'completed_at' => gmdate( 'Y-m-d H:i:s' ),
				),
				array( 'id' => $job_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			$completion_time = microtime( true ) - $completion_start;

			$total_time = microtime( true ) - $start_time;
			LLMVM_Logger::log( 'Job completed', array( 
				'job_id' => $job_id,
				'total_time_ms' => round( $total_time * 1000, 2 ),
				'json_decode_time_ms' => round( $json_decode_time * 1000, 2 ),
				'processing_time_ms' => round( $processing_time * 1000, 2 ),
				'completion_time_ms' => round( $completion_time * 1000, 2 ),
				'db_update_time_ms' => round( $db_update_time * 1000, 2 )
			) );

			// Check if this was the last job for a prompt and generate summary if needed
			if ( $job['job_type'] === 'llm_request' ) {
				$this->check_prompt_completion( $job_data );
			}

		} catch ( Exception $e ) {
			// Mark job as failed
			$wpdb->update(
				$table_name,
				array(
					'status'        => 'failed',
					'error_message' => $e->getMessage(),
					'completed_at'  => gmdate( 'Y-m-d H:i:s' ),
				),
				array( 'id' => $job_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			LLMVM_Logger::log( 'Job failed', array( 
				'job_id' => $job_id, 
				'error' => $e->getMessage()
			) );
		}
	}

	/**
	 * Process an LLM request job.
	 *
	 * @param array $job_data Job data containing prompt, model, etc.
	 */
	private function process_llm_request( array $job_data ): void {
		$api_key = $job_data['api_key'] ?? '';
		$prompt = $job_data['prompt'] ?? '';
		$model = $job_data['model'] ?? '';
		$user_id = $job_data['user_id'] ?? 0;
		$prompt_id = $job_data['prompt_id'] ?? '';

		if ( empty( $api_key ) || empty( $prompt ) || empty( $model ) ) {
			throw new Exception( 'Missing required job data' );
		}

		// Create OpenRouter client and make the request
		$api_call_start = microtime( true );
		$client = new LLMVM_OpenRouter_Client();
		$response = $client->query( $api_key, $prompt, $model );
		$api_call_time = microtime( true ) - $api_call_start;

		// Extract response data
		$resp_model = $response['model'] ?? 'unknown';
		$answer = $response['answer'] ?? '';
		$status = $response['status'] ?? 0;
		$error = $response['error'] ?? '';
		$response_time = $response['response_time'] ?? 0.0;

		// Perform comparison if expected answer is provided
		$expected_answer = $job_data['expected_answer'] ?? '';
		$comparison_score = null;
		$comparison_failed = 0; // Initialize comparison_failed
		
		if ( ! empty( $expected_answer ) && ! empty( $answer ) ) {
			LLMVM_Logger::log( 'Starting comparison', array(
				'expected_answer' => $expected_answer,
				'answer_length' => strlen( $answer ),
				'prompt_length' => strlen( $prompt )
			) );
			$comparison_result = LLMVM_Comparison::compare_response( $answer, $expected_answer, $prompt );
			
			if ( is_array( $comparison_result ) && isset( $comparison_result['failed'] ) && $comparison_result['failed'] ) {
				$comparison_score = null;
				$comparison_failed = 1;
				LLMVM_Logger::log( 'Comparison failed', array(
					'reason' => $comparison_result['reason'] ?? 'Unknown error'
				) );
			} else {
				$comparison_score = $comparison_result;
				$comparison_failed = 0;
				LLMVM_Logger::log( 'Comparison completed', array(
					'comparison_score' => $comparison_score
				) );
			}
		} else {
			LLMVM_Logger::log( 'Skipping comparison', array(
				'expected_answer_empty' => empty( $expected_answer ),
				'answer_empty' => empty( $answer ),
				'expected_answer' => $expected_answer
			) );
		}
		
		// Store result in database
		$db_insert_start = microtime( true );
		$result_id = LLMVM_Database::insert_result( $prompt, $resp_model, $answer, $user_id, $expected_answer, $comparison_score, $comparison_failed );
		$db_insert_time = microtime( true ) - $db_insert_start;
		
		if ( ! $result_id ) {
			throw new Exception( 'Failed to store result in database' );
		}

		// Add result to current run results for email reporting using database
		// Use batch_run_id if available, otherwise use prompt_id
		$run_id = $job_data['batch_run_id'] ?? ( $user_id . '_' . $prompt_id );
		
		// Clean up run_id if it has extra quotes from JSON encoding
		if ( is_string( $run_id ) && strlen( $run_id ) > 2 && $run_id[0] === '"' && $run_id[-1] === '"' ) {
			$run_id = trim( $run_id, '"' );
		}
		
		// Store result in database
		global $wpdb;
		$table_name = $wpdb->prefix . 'llmvm_current_run_results';
		
		$insert_result = $wpdb->insert(
			$table_name,
			array(
				'user_id' => $user_id,
				'run_id' => $run_id,
				'result_id' => $result_id,
				'prompt' => $prompt,
				'model' => $resp_model,
				'answer' => $answer,
				'created_at' => gmdate( 'Y-m-d H:i:s' )
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		
		if ( $insert_result === false ) {
			LLMVM_Logger::log( 'Failed to insert result into current run results table', array(
				'user_id' => $user_id,
				'result_id' => $result_id,
				'run_id' => $run_id,
				'error' => $wpdb->last_error
			) );
		} else {
			// Get current count for this run
			$current_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE run_id = %s",
				$run_id
			) );
			
			LLMVM_Logger::log( 'Added result to current run results database', array(
				'user_id' => $user_id,
				'result_id' => $result_id,
				'model' => $resp_model,
				'run_id' => $run_id,
				'results_count' => $current_count,
				'insert_id' => $wpdb->insert_id
			) );
		}

		// Update job data with response time and detailed timing breakdown for display
		$job_update_start = microtime( true );
		global $wpdb;
		$table_name = $wpdb->prefix . self::QUEUE_TABLE;
		$updated_job_data = $job_data;
		$updated_job_data['response_time'] = $response_time;
		
		// Add detailed timing breakdown for debugging
		$updated_job_data['timing_breakdown'] = array(
			'api_call_time_ms' => round( $api_call_time * 1000, 2 ),
			'db_insert_time_ms' => round( $db_insert_time * 1000, 2 ),
			'total_processing_time_ms' => round( ( $api_call_time + $db_insert_time ) * 1000, 2 )
		);
		
		$wpdb->update(
			$table_name,
			array( 'job_data' => wp_json_encode( $updated_job_data ) ),
			array( 'id' => $this->get_current_job_id() ),
			array( '%s' ),
			array( '%d' )
		);
		$job_update_time = microtime( true ) - $job_update_start;
		
		// Update job data with job update time
		$updated_job_data['db_operations_time_ms'] = round( ( $db_insert_time + $job_update_time ) * 1000, 2 );
		$updated_job_data['timing_breakdown']['job_update_time_ms'] = round( $job_update_time * 1000, 2 );
		$updated_job_data['timing_breakdown']['total_processing_time_ms'] = round( ( $api_call_time + $db_insert_time + $job_update_time ) * 1000, 2 );
		
		// Update again with complete timing data
		$wpdb->update(
			$table_name,
			array( 'job_data' => wp_json_encode( $updated_job_data ) ),
			array( 'id' => $this->get_current_job_id() ),
			array( '%s' ),
			array( '%d' )
		);

		// Log the completion with response time
		LLMVM_Logger::log( 'Queued LLM request completed', array(
			'result_id' => $result_id,
			'model' => $resp_model,
			'response_time_ms' => round( $response_time * 1000, 2 ),
			'api_call_time_ms' => round( $api_call_time * 1000, 2 ),
			'db_insert_time_ms' => round( $db_insert_time * 1000, 2 ),
			'job_update_time_ms' => round( $job_update_time * 1000, 2 ),
			'status' => $status,
			'prompt_id' => $prompt_id,
			'user_id' => $user_id
		) );

		// Results are stored in database, email check happens after job completion
		LLMVM_Logger::log( 'Result stored in database for email reporting', array(
			'result_id' => $result_id,
			'user_id' => $user_id
		) );

		// If there was an error, log it but don't fail the job
		if ( $status >= 400 ) {
			LLMVM_Logger::log( 'Queued LLM request had API error', array(
				'status' => $status,
				'error' => $error,
				'response_time_ms' => round( $response_time * 1000, 2 )
			) );
		}
	}

	/**
	 * Get queue status information.
	 *
	 * @return array Queue status data.
	 */
	public function get_queue_status(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::QUEUE_TABLE;

		$status_counts = $wpdb->get_results(
			"SELECT status, COUNT(*) as count 
			FROM $table_name 
			GROUP BY status",
			ARRAY_A
		);

		$status = array(
			'pending'    => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
		);

		foreach ( $status_counts as $row ) {
			$status[ $row['status'] ] = (int) $row['count'];
		}

		return $status;
	}

	/**
	 * Get queue jobs with optional user filtering.
	 *
	 * @param int|null $user_id User ID to filter by (null for all users).
	 * @param string   $status  Status to filter by (null for all statuses).
	 * @param int      $limit   Maximum number of jobs to return.
	 * @return array Queue jobs.
	 */
	public function get_queue_jobs( ?int $user_id = null, ?string $status = null, int $limit = 50 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::QUEUE_TABLE;
		$where_conditions = array();
		$where_values = array();

		// Add user filter if specified
		if ( $user_id !== null ) {
			$where_conditions[] = "JSON_EXTRACT(job_data, '$.user_id') = %d";
			$where_values[] = $user_id;
		}

		// Add status filter if specified
		if ( $status !== null ) {
			$where_conditions[] = "status = %s";
			$where_values[] = $status;
		}

		// Build WHERE clause
		$where_clause = '';
		if ( ! empty( $where_conditions ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
		}

		// Build and execute query
		$query = "SELECT * FROM $table_name $where_clause ORDER BY id DESC LIMIT %d";
		$where_values[] = $limit;

		$jobs = $wpdb->get_results(
			$wpdb->prepare( $query, $where_values ),
			ARRAY_A
		);

		// Decode job data for each job
		foreach ( $jobs as &$job ) {
			$job['job_data'] = json_decode( $job['job_data'], true );
		}

		return $jobs;
	}

	/**
	 * Get queue jobs for a specific user.
	 *
	 * @param int $user_id User ID.
	 * @return array User's queue jobs.
	 */
	public function get_user_queue_jobs( int $user_id ): array {
		return $this->get_queue_jobs( $user_id );
	}

	/**
	 * Clean up old completed and failed jobs.
	 */
	public function cleanup_old_jobs(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::QUEUE_TABLE;

		// Delete completed jobs older than 7 days
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name 
				WHERE status IN ('completed', 'failed') 
				AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				7
			)
		);

		if ( $deleted > 0 ) {
			LLMVM_Logger::log( 'Cleaned up old queue jobs', array( 'deleted_count' => $deleted ) );
		}
	}

	/**
	 * Clear all jobs from the queue.
	 */
	public function clear_queue(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::QUEUE_TABLE;
		$deleted = $wpdb->query( "DELETE FROM $table_name" );

		LLMVM_Logger::log( 'Cleared all queue jobs', array( 'deleted_count' => $deleted ) );
	}

	/**
	 * Check if all models for a prompt have completed and generate summary if needed.
	 *
	 * @param array $job_data The job data from the completed job.
	 */
	private function check_prompt_completion( array $job_data ): void {
		$prompt_id = $job_data['prompt_id'] ?? '';
		$expected_answer = $job_data['expected_answer'] ?? '';
		$user_id = $job_data['user_id'] ?? 0;

		// Only check for prompts with expected answers
		if ( empty( $prompt_id ) || empty( $expected_answer ) || $user_id <= 0 ) {
			return;
		}

		LLMVM_Logger::log( 'Checking prompt completion for summary generation', array(
			'prompt_id' => $prompt_id,
			'user_id' => $user_id,
			'has_expected_answer' => ! empty( $expected_answer )
		) );

		// Get the original prompt data to find expected models
		$prompts = get_option( 'llmvm_prompts', array() );
		$target_prompt = null;

		foreach ( $prompts as $prompt ) {
			if ( isset( $prompt['id'] ) && $prompt['id'] === $prompt_id ) {
				$target_prompt = $prompt;
				break;
			}
		}

		if ( ! $target_prompt ) {
			LLMVM_Logger::log( 'Target prompt not found for summary generation', array( 'prompt_id' => $prompt_id ) );
			return;
		}

		// Get expected models for this prompt
		$expected_models = array();
		if ( isset( $target_prompt['models'] ) && is_array( $target_prompt['models'] ) ) {
			$expected_models = $target_prompt['models'];
		} elseif ( isset( $target_prompt['model'] ) && ! empty( $target_prompt['model'] ) ) {
			$expected_models = array( $target_prompt['model'] );
		}

		if ( empty( $expected_models ) ) {
			LLMVM_Logger::log( 'No expected models found for prompt', array( 'prompt_id' => $prompt_id ) );
			return;
		}

		// Check if all models have completed
		if ( ! LLMVM_Comparison::are_all_models_complete( $prompt_id, $expected_models ) ) {
			LLMVM_Logger::log( 'Not all models completed yet for prompt', array(
				'prompt_id' => $prompt_id,
				'expected_models' => $expected_models
			) );
			return;
		}

		// Check if summary already exists and if it's up to date
		$prompt_text = $target_prompt['text'] ?? '';
		if ( $this->prompt_summary_exists( $prompt_id, $prompt_text, $expected_answer ) ) {
			// Check if there are newer results than the existing summary
			if ( ! $this->has_newer_results_than_summary( $prompt_id, $prompt_text, $expected_answer ) ) {
				LLMVM_Logger::log( 'Summary already exists and is up to date', array( 
					'prompt_id' => $prompt_id,
					'prompt_text' => substr( $prompt_text, 0, 50 ) . '...',
					'expected_answer' => $expected_answer
				) );
				return;
			} else {
				LLMVM_Logger::log( 'Summary exists but newer results found, regenerating', array( 
					'prompt_id' => $prompt_id,
					'prompt_text' => substr( $prompt_text, 0, 50 ) . '...',
					'expected_answer' => $expected_answer
				) );
				// Delete the old summary so we can create a new one
				$this->delete_prompt_summary( $prompt_id, $prompt_text, $expected_answer );
			}
		}

		// Get all results for this prompt with the same expected answer
		// Get batch run ID from job data to filter by current run only
		$batch_run_id = $job_data['batch_run_id'] ?? '';
		
		$results = LLMVM_Comparison::get_prompt_results( $prompt_id, $expected_models, $expected_answer, $batch_run_id );

		if ( empty( $results ) ) {
			LLMVM_Logger::log( 'No results found for prompt summary', array( 'prompt_id' => $prompt_id ) );
			return;
		}

		// Filter to only valid results (remove empty/failed answers)
		$valid_results = array_filter( $results, function( $result ) {
			$answer = trim( $result['answer'] ?? '' );
			return ! empty( $answer ) && $answer !== 'No answer received';
		} );

		// Generate summary only if we have at least one valid result
		if ( empty( $valid_results ) ) {
			LLMVM_Logger::log( 'No valid answers found, skipping summary generation', array( 
				'prompt_id' => $prompt_id,
				'total_results' => count( $results ),
				'valid_results' => count( $valid_results )
			) );
			return;
		}

		// Log if some models failed but we're still generating summary
		if ( count( $valid_results ) < count( $results ) ) {
			LLMVM_Logger::log( 'Some models failed, generating summary with valid results only', array( 
				'prompt_id' => $prompt_id,
				'total_results' => count( $results ),
				'valid_results' => count( $valid_results )
			) );
		}

		// Use only valid results for summary generation
		$results = $valid_results;

		LLMVM_Logger::log( 'Generating prompt summary', array(
			'prompt_id' => $prompt_id,
			'results_count' => count( $results ),
			'expected_models' => $expected_models
		) );

		// Generate prompt summary
		$summary_data = LLMVM_Comparison::generate_prompt_summary(
			$prompt_id,
			$target_prompt['text'] ?? '',
			$expected_answer,
			$results
		);

		if ( $summary_data ) {
			// Store the summary in the database
			$summary_id = LLMVM_Database::insert_prompt_summary(
				$prompt_id,
				$target_prompt['text'] ?? '',
				$expected_answer,
				$user_id,
				$summary_data
			);

			LLMVM_Logger::log( 'Prompt summary generated and stored', array(
				'prompt_id' => $prompt_id,
				'summary_id' => $summary_id,
				'average_score' => $summary_data['average_score'] ?? null
			) );
		} else {
			LLMVM_Logger::log( 'Failed to generate prompt summary', array( 'prompt_id' => $prompt_id ) );
		}
	}

	/**
	 * Check if a prompt summary already exists for the current prompt content.
	 *
	 * @param string $prompt_id The prompt ID.
	 * @param string $prompt_text The current prompt text.
	 * @param string $expected_answer The current expected answer.
	 * @return bool True if summary exists for the same content.
	 */
	private function prompt_summary_exists( string $prompt_id, string $prompt_text = '', string $expected_answer = '' ): bool {
		global $wpdb;

		$table_name = LLMVM_Database::prompt_summaries_table_name();
		
		// Check if summary exists for the same prompt content
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE prompt_id = %s AND prompt_text = %s AND expected_answer = %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table_name() returns constant string
			$prompt_id,
			$prompt_text,
			$expected_answer
		) );

		return $exists > 0;
	}

	/**
	 * Check if there are newer results than the existing summary.
	 *
	 * @param string $prompt_id The prompt ID.
	 * @param string $prompt_text The current prompt text.
	 * @param string $expected_answer The current expected answer.
	 * @return bool True if there are newer results than the summary.
	 */
	private function has_newer_results_than_summary( string $prompt_id, string $prompt_text = '', string $expected_answer = '' ): bool {
		global $wpdb;

		$summaries_table = LLMVM_Database::prompt_summaries_table_name();
		$results_table = LLMVM_Database::table_name();
		
		// Get the latest summary completion time
		$summary_time = $wpdb->get_var( $wpdb->prepare(
			"SELECT completed_at FROM {$summaries_table} WHERE prompt_id = %s AND prompt_text = %s AND expected_answer = %s ORDER BY completed_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table_name() returns constant string
			$prompt_id,
			$prompt_text,
			$expected_answer
		) );
		
		if ( ! $summary_time ) {
			return true; // No summary exists, so we need to create one
		}
		
		// Check if there are any results newer than the summary
		$newer_results = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$results_table} WHERE prompt = %s AND expected_answer = %s AND created_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table_name() returns constant string
			$prompt_text,
			$expected_answer,
			$summary_time
		) );
		
		return $newer_results > 0;
	}

	/**
	 * Delete a prompt summary.
	 *
	 * @param string $prompt_id The prompt ID.
	 * @param string $prompt_text The current prompt text.
	 * @param string $expected_answer The current expected answer.
	 * @return bool True if summary was deleted.
	 */
	private function delete_prompt_summary( string $prompt_id, string $prompt_text = '', string $expected_answer = '' ): bool {
		global $wpdb;

		$table_name = LLMVM_Database::prompt_summaries_table_name();
		
		$deleted = $wpdb->delete(
			$table_name,
			array(
				'prompt_id' => $prompt_id,
				'prompt_text' => $prompt_text,
				'expected_answer' => $expected_answer
			),
			array( '%s', '%s', '%s' )
		);
		
		return $deleted > 0;
	}
}
