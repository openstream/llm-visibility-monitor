<?php
/**
 * Database operations for LLM Visibility Monitor plugin.
 *
 * This class handles all database operations for the custom llm_visibility_results table.
 * It uses WordPress $wpdb methods (insert, get_results, get_var, get_row, query)
 * which are the proper WordPress way to interact with custom database tables.
 *
 * Note: phpcs:ignore annotations are used throughout this file because:
 * 1. WordPress.DB.DirectDatabaseQuery.DirectQuery - These are NOT direct database calls,
 *    but proper WordPress $wpdb methods for custom table operations
 * 2. WordPress.DB.DirectDatabaseQuery.NoCaching - Custom table data doesn't benefit
 *    from WordPress object cache as it's not part of the core WordPress schema
 *
 * @package LLM_Visibility_Monitor
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database operations class for LLM Visibility Monitor.
 *
 * Handles all database operations including creating tables,
 * inserting results, retrieving data, and managing user-specific data.
 *
 * @package LLM_Visibility_Monitor
 */
class LLMVM_Database {
	/**
	 * Current DB schema version for this plugin.
	 */
	private const DB_VERSION = '1.7.0';

	/**
	 * Return the fully qualified table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'llm_visibility_results';
	}

	/**
	 * Return the fully qualified usage tracking table name.
	 */
	public static function usage_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'llm_visibility_usage';
	}

	/**
	 * Return the fully qualified queue table name.
	 */
	public static function queue_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'llm_visibility_queue';
	}

	/**
	 * Return the fully qualified prompt summaries table name.
	 */
	public static function prompt_summaries_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'llm_visibility_prompt_summaries';
	}

	/**
	 * Create or upgrade the custom table.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'llmvm_db_version', '' );

		// Ensure we have a proper value to prevent PHP 8.1 deprecation warnings.
		if ( false === $installed ) {
			$installed = '';
		}
		if ( self::DB_VERSION === $installed ) {
			// Even if version matches, ensure table exists.
			self::ensure_table_exists();
			return;
		}

		self::create_table();
		self::create_usage_table();
		self::create_queue_table();
		self::create_prompt_summaries_table();
		update_option( 'llmvm_db_version', self::DB_VERSION );

		// Migrate existing prompts to include model field.
		self::migrate_prompts();

		// Migrate prompts to support multiple models (v1.2.0).
		self::migrate_prompts_to_multi_model();

		// Clean up duplicate prompts.
		self::cleanup_duplicate_prompts();
		
		// Migrate prompts to support web search (v1.4.0).
		self::migrate_prompts_to_web_search();
		
		// Migrate prompts to support cron frequency (v1.5.0).
		self::migrate_prompts_to_cron_frequency();
		
		// Migrate results table to support comparison fields (v1.6.0).
		self::migrate_results_to_comparison_fields();
		
		// Create prompt summaries table (v1.7.0).
		self::migrate_to_prompt_summaries();
	}

	/**
	 * Migrate existing prompts to include user_id field.
	 */
	private static function migrate_prompts(): void {
		$prompts = get_option( 'llmvm_prompts', array() );
		if ( ! is_array( $prompts ) ) {
			return;
		}

		$options       = get_option( 'llmvm_options', array() );
		$default_model = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';

		$migrated = false;
		foreach ( $prompts as &$prompt ) {
			if ( ! isset( $prompt['model'] ) ) {
				$prompt['model'] = $default_model;
				$migrated        = true;
			}
			if ( ! isset( $prompt['user_id'] ) ) {
				$prompt['user_id'] = 1; // Default to admin user.
				$migrated          = true;
			}
		}
		unset( $prompt );

		if ( $migrated ) {
			update_option( 'llmvm_prompts', $prompts, false );
		}
	}

	/**
	 * Migrate prompts to support multiple models (v1.2.0).
	 */
	private static function migrate_prompts_to_multi_model(): void {
		$prompts = get_option( 'llmvm_prompts', array() );
		if ( ! is_array( $prompts ) ) {
			return;
		}

		$migrated = false;
		foreach ( $prompts as &$prompt ) {
			// If prompt has single 'model' field, convert to 'models' array
			if ( isset( $prompt['model'] ) && ! isset( $prompt['models'] ) ) {
				$model = $prompt['model'];
				$prompt['models'] = array( $model );
				unset( $prompt['model'] );
				$migrated = true;
			}
			// If prompt has neither 'model' nor 'models', add default model
			elseif ( ! isset( $prompt['model'] ) && ! isset( $prompt['models'] ) ) {
				$options = get_option( 'llmvm_options', array() );
				$default_model = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
				$prompt['models'] = array( $default_model );
				$migrated = true;
			}
		}
		unset( $prompt );

		if ( $migrated ) {
			update_option( 'llmvm_prompts', $prompts, false );
		}
	}

	/**
	 * Clean up duplicate prompts based on text content.
	 */
	private static function cleanup_duplicate_prompts(): void {
		$prompts = get_option( 'llmvm_prompts', array() );
		if ( ! is_array( $prompts ) ) {
			return;
		}

		$seen_texts     = array();
		$unique_prompts = array();
		$cleaned        = false;

		foreach ( $prompts as $prompt ) {
			if ( ! isset( $prompt['text'] ) ) {
				continue;
			}

			$text = trim( $prompt['text'] );
			if ( '' === $text ) {
				continue;
			}

			// If we've seen this text before, skip it (keep the first occurrence).
			if ( in_array( $text, $seen_texts, true ) ) {
				$cleaned = true;
				continue;
			}

			$seen_texts[]     = $text;
			$unique_prompts[] = $prompt;
		}

		if ( $cleaned ) {
			update_option( 'llmvm_prompts', $unique_prompts, false );
		}
	}

	/**
	 * Migrate prompts to support web search (v1.4.0).
	 */
	private static function migrate_prompts_to_web_search(): void {
		$prompts = get_option( 'llmvm_prompts', array() );
		if ( ! is_array( $prompts ) ) {
			return;
		}

		$migrated = false;
		foreach ( $prompts as &$prompt ) {
			// If prompt doesn't have web_search field, add it as false
			if ( ! isset( $prompt['web_search'] ) ) {
				$prompt['web_search'] = false;
				$migrated = true;
			}
		}
		unset( $prompt );

		if ( $migrated ) {
			update_option( 'llmvm_prompts', $prompts, false );
		}
	}

	/**
	 * Migrate prompts to support cron frequency (v1.5.0).
	 * Updated: Convert daily prompts to monthly (daily is no longer supported).
	 */
	private static function migrate_prompts_to_cron_frequency(): void {
		$prompts = get_option( 'llmvm_prompts', array() );
		if ( ! is_array( $prompts ) ) {
			return;
		}

		$migrated = false;
		$cron = new LLMVM_Cron();

		foreach ( $prompts as &$prompt ) {
			// If prompt doesn't have cron_frequency field, add it as monthly
			if ( ! isset( $prompt['cron_frequency'] ) ) {
				$prompt['cron_frequency'] = 'monthly';
				$migrated = true;

				// Schedule cron job for this new prompt
				if ( isset( $prompt['id'] ) ) {
					$cron->schedule_prompt_cron( $prompt['id'], 'monthly' );
				}
			}
			// Convert daily prompts to monthly (daily is no longer supported)
			elseif ( 'daily' === $prompt['cron_frequency'] ) {
				$prompt['cron_frequency'] = 'monthly';
				$migrated = true;

				// Reschedule cron job for this prompt with new monthly frequency
				if ( isset( $prompt['id'] ) ) {
					$cron->schedule_prompt_cron( $prompt['id'], 'monthly' );
					LLMVM_Logger::log( 'Migrated daily prompt to monthly', [
						'prompt_id' => $prompt['id'],
						'old_frequency' => 'daily',
						'new_frequency' => 'monthly'
					] );
				}
			}
			// Weekly and monthly prompts stay as they are
		}
		unset( $prompt );

		if ( $migrated ) {
			update_option( 'llmvm_prompts', $prompts, false );
			LLMVM_Logger::log( 'Completed cron frequency migration - daily converted to monthly' );
		}
	}

	/**
	 * Create custom results table.
	 */
	private static function create_table(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            prompt TEXT NOT NULL,
            model VARCHAR(191) NOT NULL,
            answer LONGTEXT NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            expected_answer LONGTEXT NULL,
            comparison_score TINYINT UNSIGNED NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY user_id (user_id),
            KEY comparison_score (comparison_score)
        ) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create usage tracking table.
	 */
	private static function create_usage_table(): void {
		global $wpdb;

		$table_name      = self::usage_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            month_year VARCHAR(7) NOT NULL,
            prompts_used INT UNSIGNED NOT NULL DEFAULT 0,
            runs_used INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_month (user_id, month_year),
            KEY user_id (user_id),
            KEY month_year (month_year)
        ) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create queue table for background processing.
	 */
	private static function create_queue_table(): void {
		global $wpdb;

		$table_name      = self::queue_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            prompt_id VARCHAR(191) NOT NULL,
            models JSON NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            error_message TEXT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Clear all results from the database (for testing/cleanup).
	 */
	public static function clear_all_results(): int {
		global $wpdb;

		// Ensure table exists before clearing.
		self::ensure_table_exists();

		$result = $wpdb->query( 'TRUNCATE TABLE ' . self::table_name() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries. self::table_name() returns constant string.

		if ( false === $result ) {
			LLMVM_Logger::log( 'Failed to clear results table', array( 'error' => $wpdb->last_error ) );
			return 0;
		}

		LLMVM_Logger::log( 'Results table cleared successfully' );
		return 1;
	}

	/**
	 * Insert a result row.
	 *
	 * @param string $prompt   The prompt text sent to the LLM.
	 * @param string $model    The model used for the response.
	 * @param string $answer   The response from the LLM.
	 * @param int    $user_id  The user ID who owns this result.
	 * @return int The ID of the inserted result, or 0 if insertion failed.
	 */
	public static function insert_result( string $prompt, string $model, string $answer, int $user_id = 1, string $expected_answer = null, int $comparison_score = null, int $comparison_failed = 0 ): int {
		global $wpdb;

		// Log the insert attempt.
		LLMVM_Logger::log(
			'Database insert attempt',
			array(
				'prompt_length' => strlen( $prompt ),
				'model'         => $model,
				'answer_length' => strlen( $answer ),
				'user_id'       => $user_id,
			)
		);

		// Ensure table exists before inserting.
		self::ensure_table_exists();

		$insert_data = array(
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
			'prompt'     => $prompt,
			'model'      => $model,
			'answer'     => $answer,
			'user_id'    => $user_id,
			'expected_answer' => $expected_answer,
			'comparison_score' => $comparison_score,
			'comparison_failed' => $comparison_failed,
		);

		LLMVM_Logger::log( 'Insert data prepared', array( 'data' => $insert_data ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->insert() is the proper WordPress method for custom table inserts.
		$result = $wpdb->insert(
			self::table_name(),
			$insert_data,
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->insert() is the proper WordPress method for custom table inserts.

		if ( false === $result ) {
			LLMVM_Logger::log(
				'Database insert failed',
				array(
					'error'         => $wpdb->last_error,
					'prompt_length' => strlen( $prompt ),
					'model'         => $model,
					'answer_length' => strlen( $answer ),
					'user_id'       => $user_id,
				)
			);
			return 0;
		} else {
			LLMVM_Logger::log(
				'Database insert successful',
				array(
					'insert_id'     => $wpdb->insert_id,
					'prompt_length' => strlen( $prompt ),
					'model'         => $model,
					'answer_length' => strlen( $answer ),
					'user_id'       => $user_id,
				)
			);
			return $wpdb->insert_id;
		}
	}

	/**
	 * Get latest results with sorting and pagination.
	 *
	 * @param int    $limit   Number of results to fetch.
	 * @param string $orderby Column to order by.
	 * @param string $order   Order direction (ASC or DESC).
	 * @param int    $offset  Offset for pagination.
	 * @param int    $user_id User ID to filter results by (0 for all users).
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_latest_results( int $limit = 20, string $orderby = 'created_at', string $order = 'DESC', int $offset = 0, int $user_id = 0 ): array {
		global $wpdb;

		// Ensure table exists before querying.
		self::ensure_table_exists();

		// Validate and sanitize orderby parameter.
		$allowed_columns = array( 'id', 'created_at', 'prompt', 'model', 'user_id' );
		if ( ! in_array( $orderby, $allowed_columns, true ) ) {
			$orderby = 'created_at';
		}

		// Validate and sanitize order parameter.
		$order = strtoupper( $order );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		// Sanitize limit and offset.
		$limit  = max( 1, min( 1000, $limit ) );
		$offset = max( 0, $offset );

		// Use predefined SQL strings to avoid dynamic construction.
		if ( $user_id > 0 ) {
			switch ( $orderby ) {
				case 'id':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY id ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'created_at':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY created_at ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'prompt':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY prompt ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'model':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY model ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'user_id':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY user_id ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				default:
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY created_at ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
			}
		} else {
			switch ( $orderby ) {
				case 'id':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' ORDER BY id ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'created_at':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' ORDER BY created_at ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'prompt':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' ORDER BY prompt ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'model':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' ORDER BY model ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'user_id':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' ORDER BY user_id ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				default:
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' ORDER BY created_at ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
			}
		}
		
		// Ensure we always return an array, even if $wpdb->get_results() returns null or false.
		if ( ! is_array( $rows ) ) {
			return [];
		}
		return $rows;
	}

	/**
	 * Get total count of results.
	 *
	 * @param int $user_id User ID to filter results by (0 for all users).
	 * @return int
	 */
	public static function get_total_results( int $user_id = 0 ): int {
		global $wpdb;
		
		// Ensure table exists before querying.
		self::ensure_table_exists();

		if ( $user_id > 0 ) {
			$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE user_id = %d', $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table queries using proper WordPress $wpdb->get_var() method with prepared statements. self::table_name() returns constant string.
		} else {
			$count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table queries using proper WordPress $wpdb->get_var() method. self::table_name() returns constant string.
		}
		
		return (int) $count;
	}

	/**
	 * Delete multiple results by IDs.
	 *
	 * @param array<int> $ids     Array of result IDs to delete.
	 * @param int        $user_id User ID to verify ownership (0 to skip ownership check).
	 * @return int Number of deleted rows.
	 */
	public static function delete_results_by_ids( array $ids, int $user_id = 0 ): int {
		if ( empty( $ids ) ) {
			return 0;
		}
		
		global $wpdb;
		
		// Ensure table exists before querying.
		self::ensure_table_exists();
		
		// Sanitize IDs
		$ids = array_map( 'intval', $ids );
		$ids = array_filter( $ids );
		
		if ( empty( $ids ) ) {
			return 0;
		}
		
		if ( $user_id > 0 ) {
			// Delete only results owned by the specified user
			$deleted = 0;
			foreach ( $ids as $id ) {
				$deleted += $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' WHERE id = %d AND user_id = %d', $id, $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries. self::table_name() returns constant string.
			}
		} else {
			// Delete results regardless of ownership (admin function)
			$deleted = 0;
			foreach ( $ids as $id ) {
				$deleted += $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries. self::table_name() returns constant string.
			}
		}
		
		return (int) $deleted;
	}

	/**
	 * Get a single result by id.
	 *
	 * @param int $id      Row id.
	 * @param int $user_id User ID to verify ownership (0 to skip ownership check).
	 * @return array<string,mixed>|null
	 */
	public static function get_result_by_id( int $id, int $user_id = 0 ): ?array {
		global $wpdb;
		
		// Ensure table exists before querying.
		self::ensure_table_exists();
		
		if ( $user_id > 0 ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' WHERE id = %d AND user_id = %d', $id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table queries using proper WordPress $wpdb->get_row() method with prepared statements. self::table_name() returns constant string.
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, created_at, prompt, model, answer, user_id, expected_answer, comparison_score FROM ' . self::table_name() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table queries using proper WordPress $wpdb->get_row() method with prepared statements. self::table_name() returns constant string.
		}
		
		// Ensure we return null if $wpdb->get_row() returns null, false, or non-array.
		if ( ! is_array( $row ) ) {
			return null;
		}
		return $row;
	}

	/**
	 * Ensure the custom table exists.
	 */
	private static function ensure_table_exists(): void {
		global $wpdb;
		$table_name = self::table_name();

		// Check if the table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. self::table_name() returns constant string.
			return;
		}

		// If not, create it.
		self::create_table();
		LLMVM_Logger::log( 'Custom table did not exist, creating it.', array( 'table_name' => $table_name ) );
	}

	/**
	 * Manually create the table (for testing/debugging).
	 */
	public static function force_create_table(): void {
		self::create_table();
		update_option( 'llmvm_db_version', self::DB_VERSION );
		LLMVM_Logger::log( 'Custom table created manually.', array( 'table_name' => self::table_name() ) );
	}

	/**
	 * Get current usage for a user for the current month.
	 */
	public static function get_user_usage( int $user_id ): array {
		global $wpdb;
		
		$month_year = gmdate( 'Y-m' );
		$table_name = self::usage_table_name();
		
		$usage = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->get_row() is the proper WordPress method for custom table queries.
			'SELECT prompts_used, runs_used FROM ' . $table_name . ' WHERE user_id = %d AND month_year = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::usage_table_name() returns constant string
			$user_id, 
			$month_year 
		), ARRAY_A );
		
		if ( ! is_array( $usage ) ) {
			return array( 'prompts_used' => 0, 'runs_used' => 0 );
		}
		
		return array(
			'prompts_used' => (int) $usage['prompts_used'],
			'runs_used' => (int) $usage['runs_used']
		);
	}

	/**
	 * Increment usage for a user.
	 */
	public static function increment_usage( int $user_id, int $prompts_increment = 0, int $runs_increment = 0 ): void {
		global $wpdb;
		
		$month_year = gmdate( 'Y-m' );
		$table_name = self::usage_table_name();
		$now = gmdate( 'Y-m-d H:i:s' );
		
		// Try to update existing record
		$updated = $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->query() is the proper WordPress method for custom table updates.
			'UPDATE ' . $table_name . ' SET prompts_used = prompts_used + %d, runs_used = runs_used + %d, updated_at = %s WHERE user_id = %d AND month_year = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::usage_table_name() returns constant string
			$prompts_increment,
			$runs_increment,
			$now,
			$user_id,
			$month_year
		) );
		
		// If no rows were updated, insert new record
		if ( 0 === $updated ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table operations require direct queries. $wpdb->insert() is the proper WordPress method for custom table inserts.
				$table_name,
				array(
					'user_id' => $user_id,
					'month_year' => $month_year,
					'prompts_used' => $prompts_increment,
					'runs_used' => $runs_increment,
					'created_at' => $now,
					'updated_at' => $now
				),
				array( '%d', '%s', '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Add job to queue.
	 */
	public static function add_to_queue( int $user_id, string $prompt_id, array $models ): int {
		global $wpdb;
		
		$table_name = self::queue_table_name();
		$now = gmdate( 'Y-m-d H:i:s' );
		
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table operations require direct queries. $wpdb->insert() is the proper WordPress method for custom table inserts.
			$table_name,
			array(
				'user_id' => $user_id,
				'prompt_id' => $prompt_id,
				'models' => wp_json_encode( $models ),
				'status' => 'pending',
				'created_at' => $now
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
		
		if ( false === $result ) {
			return 0;
		}
		
		return $wpdb->insert_id;
	}

	/**
	 * Get pending jobs from queue.
	 */
	public static function get_pending_jobs( int $limit = 10 ): array {
		global $wpdb;
		
		$table_name = self::queue_table_name();
		
		$jobs = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->get_results() is the proper WordPress method for custom table queries.
			'SELECT id, user_id, prompt_id, models, created_at FROM ' . $table_name . ' WHERE status = %s ORDER BY created_at ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::queue_table_name() returns constant string
			'pending',
			$limit
		), ARRAY_A );
		
		if ( ! is_array( $jobs ) ) {
			return array();
		}
		
		// Decode JSON models for each job
		foreach ( $jobs as &$job ) {
			$job['models'] = json_decode( $job['models'], true );
		}
		unset( $job );
		
		return $jobs;
	}

	/**
	 * Update job status in queue.
	 */
	public static function update_job_status( int $job_id, string $status, string $error_message = null ): void {
		global $wpdb;
		
		$table_name = self::queue_table_name();
		$now = gmdate( 'Y-m-d H:i:s' );
		
		$update_data = array( 'status' => $status );
		$update_format = array( '%s' );
		
		if ( 'processing' === $status ) {
			$update_data['started_at'] = $now;
			$update_format[] = '%s';
		} elseif ( in_array( $status, array( 'completed', 'failed' ), true ) ) {
			$update_data['completed_at'] = $now;
			$update_format[] = '%s';
		}
		
		if ( null !== $error_message ) {
			$update_data['error_message'] = $error_message;
			$update_format[] = '%s';
		}
		
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->update() is the proper WordPress method for custom table updates.
			$table_name,
			$update_data,
			array( 'id' => $job_id ),
			$update_format,
			array( '%d' )
		);
	}

	/**
	 * Get queue status for a user.
	 */
	public static function get_user_queue_status( int $user_id ): array {
		global $wpdb;
		
		$table_name = self::queue_table_name();
		
		$status = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->get_row() is the proper WordPress method for custom table queries.
			'SELECT 
				COUNT(*) as total_jobs,
				SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_jobs,
				SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing_jobs,
				SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_jobs,
				SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_jobs
			FROM ' . $table_name . ' WHERE user_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::queue_table_name() returns constant string
			$user_id
		), ARRAY_A );
		
		if ( ! is_array( $status ) ) {
			return array(
				'total_jobs' => 0,
				'pending_jobs' => 0,
				'processing_jobs' => 0,
				'completed_jobs' => 0,
				'failed_jobs' => 0
			);
		}
		
		return array(
			'total_jobs' => (int) $status['total_jobs'],
			'pending_jobs' => (int) $status['pending_jobs'],
			'processing_jobs' => (int) $status['processing_jobs'],
			'completed_jobs' => (int) $status['completed_jobs'],
			'failed_jobs' => (int) $status['failed_jobs']
		);
	}

	/**
	 * Migrate results table to support comparison fields (v1.6.0).
	 */
	private static function migrate_results_to_comparison_fields(): void {
		global $wpdb;
		
		$table_name = self::table_name();
		
		// Check if expected_answer column exists
		$expected_answer_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'expected_answer'",
			DB_NAME,
			$table_name
		) );
		
		// Add expected_answer column if it doesn't exist
		if ( ! $expected_answer_exists ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN expected_answer LONGTEXT NULL" );
		}
		
		// Check if comparison_score column exists
		$comparison_score_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'comparison_score'",
			DB_NAME,
			$table_name
		) );
		
		// Add comparison_score column if it doesn't exist
		if ( ! $comparison_score_exists ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN comparison_score TINYINT UNSIGNED NULL" );
		}
		
		// Add index for comparison_score if it doesn't exist
		$index_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'comparison_score'",
			DB_NAME,
			$table_name
		) );
		
		if ( ! $index_exists ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD KEY comparison_score (comparison_score)" );
		}
	}

	/**
	 * Create prompt summaries table.
	 */
	private static function create_prompt_summaries_table(): void {
		global $wpdb;

		$table_name      = self::prompt_summaries_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			prompt_id VARCHAR(191) NOT NULL,
			prompt_text TEXT NOT NULL,
			expected_answer LONGTEXT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			completed_at DATETIME NOT NULL,
			comparison_summary LONGTEXT NULL,
			average_score DECIMAL(3,1) NULL,
			min_score TINYINT UNSIGNED NULL,
			max_score TINYINT UNSIGNED NULL,
			total_models INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY prompt_id (prompt_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Migrate to prompt summaries table (v1.7.0).
	 */
	private static function migrate_to_prompt_summaries(): void {
		// Check if the table already exists
		global $wpdb;
		$table_name = self::prompt_summaries_table_name();
		
		$table_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
			DB_NAME,
			$table_name
		) );

		if ( ! $table_exists ) {
			self::create_prompt_summaries_table();
			LLMVM_Logger::log( 'Created prompt summaries table', array( 'table_name' => $table_name ) );
		}
	}

	/**
	 * Insert a prompt summary.
	 */
	public static function insert_prompt_summary( string $prompt_id, string $prompt_text, string $expected_answer, int $user_id, array $comparison_data ): int {
		global $wpdb;

		$insert_data = array(
			'prompt_id'          => $prompt_id,
			'prompt_text'        => $prompt_text,
			'expected_answer'    => $expected_answer,
			'user_id'            => $user_id,
			'created_at'         => gmdate( 'Y-m-d H:i:s' ),
			'completed_at'       => gmdate( 'Y-m-d H:i:s' ),
			'comparison_summary' => $comparison_data['summary'] ?? null,
			'average_score'      => $comparison_data['average_score'] ?? null,
			'min_score'          => $comparison_data['min_score'] ?? null,
			'max_score'          => $comparison_data['max_score'] ?? null,
			'total_models'       => $comparison_data['total_models'] ?? 0,
		);

		$result = $wpdb->insert(
			self::prompt_summaries_table_name(),
			$insert_data,
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%f', '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			LLMVM_Logger::log( 'Failed to insert prompt summary', array( 'wpdb_error' => $wpdb->last_error ) );
			return 0;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get latest prompt summaries for a user.
	 */
	public static function get_latest_prompt_summaries( int $user_id = 0, int $limit = 10 ): array {
		global $wpdb;

		$table_name = self::prompt_summaries_table_name();
		
		if ( $user_id > 0 ) {
			$results = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->get_results() is the proper WordPress method for custom table queries.
				'SELECT * FROM ' . $table_name . ' WHERE user_id = %d ORDER BY completed_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::prompt_summaries_table_name() returns constant string
				$user_id,
				$limit
			), ARRAY_A );
		} else {
			$results = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->get_results() is the proper WordPress method for custom table queries.
				'SELECT * FROM ' . $table_name . ' ORDER BY completed_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::prompt_summaries_table_name() returns constant string
				$limit
			), ARRAY_A );
		}

		return $results ? $results : array();
	}

	/**
	 * Delete all prompt summaries for a specific user.
	 *
	 * @param int $user_id The user ID to delete summaries for.
	 * @return int Number of deleted summaries.
	 */
	public static function delete_prompt_summaries_for_user( int $user_id ): int {
		global $wpdb;

		$table_name = self::prompt_summaries_table_name();
		
		$deleted = $wpdb->delete(
			$table_name,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		LLMVM_Logger::log( 'Deleted prompt summaries for user', array(
			'user_id' => $user_id,
			'deleted_count' => $deleted
		) );

		return $deleted;
	}
}
