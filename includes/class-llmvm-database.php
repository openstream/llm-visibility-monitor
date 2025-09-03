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
	private const DB_VERSION = '1.1.0';

	/**
	 * Return the fully qualified table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'llm_visibility_results';
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
		update_option( 'llmvm_db_version', self::DB_VERSION );

		// Migrate existing prompts to include model field.
		self::migrate_prompts();

		// Clean up duplicate prompts.
		self::cleanup_duplicate_prompts();
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
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY user_id (user_id)
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
	 */
	public static function insert_result( string $prompt, string $model, string $answer, int $user_id = 1 ): void {
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
			'created_at' => current_time( 'mysql', true ),
			'prompt'     => $prompt,
			'model'      => $model,
			'answer'     => $answer,
			'user_id'    => $user_id,
		);

		LLMVM_Logger::log( 'Insert data prepared', array( 'data' => $insert_data ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries. $wpdb->insert() is the proper WordPress method for custom table inserts.
		$result = $wpdb->insert(
			self::table_name(),
			$insert_data,
			array( '%s', '%s', '%s', '%s', '%d' )
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
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY id ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'created_at':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY created_at ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'prompt':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY prompt ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'model':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY model ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'user_id':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY user_id ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$user_id,
						$limit,
						$offset
					), ARRAY_A );
					break;
				default:
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' WHERE user_id = %d ORDER BY created_at ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
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
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' ORDER BY id ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'created_at':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' ORDER BY created_at ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'prompt':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' ORDER BY prompt ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'model':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' ORDER BY model ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				case 'user_id':
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' ORDER BY user_id ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
						$limit,
						$offset
					), ARRAY_A );
					break;
				default:
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries using proper WordPress $wpdb->get_results() method with prepared statements.
					$rows = $wpdb->get_results( $wpdb->prepare(
						'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' ORDER BY created_at ' . $order . ', id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::table_name() returns constant string, $order is validated to be ASC/DESC only.
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
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' WHERE id = %d AND user_id = %d', $id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table queries using proper WordPress $wpdb->get_row() method with prepared statements. self::table_name() returns constant string.
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, created_at, prompt, model, answer, user_id FROM ' . self::table_name() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table queries using proper WordPress $wpdb->get_row() method with prepared statements. self::table_name() returns constant string.
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
}
