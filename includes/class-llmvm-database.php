<?php
/**
 * Database layer for LLM Visibility Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        $installed = get_option( 'llmvm_db_version' );
        // Ensure we have a proper value to prevent PHP 8.1 deprecation warnings.
        if ( false === $installed ) {
            $installed = '';
        }
        if ( $installed === self::DB_VERSION ) {
            return;
        }

        self::create_table();
        update_option( 'llmvm_db_version', self::DB_VERSION );
        
        // Migrate existing prompts to include model field
        self::migrate_prompts();
        
        // Clean up duplicate prompts
        self::cleanup_duplicate_prompts();
    }
    
    /**
     * Migrate existing prompts to include user_id field.
     */
    private static function migrate_prompts(): void {
        $prompts = get_option( 'llmvm_prompts', [] );
        if ( ! is_array( $prompts ) ) {
            return;
        }
        
        $options = get_option( 'llmvm_options', [] );
        $default_model = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
        
        $migrated = false;
        foreach ( $prompts as &$prompt ) {
            if ( ! isset( $prompt['model'] ) ) {
                $prompt['model'] = $default_model;
                $migrated = true;
            }
            if ( ! isset( $prompt['user_id'] ) ) {
                $prompt['user_id'] = 1; // Default to admin user
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
        $prompts = get_option( 'llmvm_prompts', [] );
        if ( ! is_array( $prompts ) ) {
            return;
        }
        
        $seen_texts = [];
        $unique_prompts = [];
        $cleaned = false;
        
        foreach ( $prompts as $prompt ) {
            if ( ! isset( $prompt['text'] ) ) {
                continue;
            }
            
            $text = trim( $prompt['text'] );
            if ( '' === $text ) {
                continue;
            }
            
            // If we've seen this text before, skip it (keep the first occurrence)
            if ( in_array( $text, $seen_texts, true ) ) {
                $cleaned = true;
                continue;
            }
            
            $seen_texts[] = $text;
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
        $table_name = self::table_name();
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table operations require direct queries.
        $result = $wpdb->query( "TRUNCATE TABLE {$table_name}" );
        
        if ( false === $result ) {
            LLMVM_Logger::log( 'Failed to clear results table', [ 'error' => $wpdb->last_error ] );
            return 0;
        }
        
        LLMVM_Logger::log( 'Results table cleared successfully' );
        return 1;
    }

    /**
     * Insert a result row.
     */
    public static function insert_result( string $prompt, string $model, string $answer, int $user_id = 1 ): void {
        global $wpdb;
        
        // Log the insert attempt
        LLMVM_Logger::log( 'Database insert attempt', [ 'prompt_length' => strlen( $prompt ), 'model' => $model, 'answer_length' => strlen( $answer ), 'user_id' => $user_id ] );
        
        // Check if table exists
        $table_name = self::table_name();
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        if ( ! $table_exists ) {
            LLMVM_Logger::log( 'Database table does not exist', [ 'table_name' => $table_name ] );
            return;
        }
        
        $insert_data = [
            'created_at' => current_time( 'mysql', true ),
            'prompt'     => $prompt,
            'model'      => $model,
            'answer'     => $answer,
            'user_id'    => $user_id,
        ];
        
        LLMVM_Logger::log( 'Insert data prepared', [ 'data' => $insert_data ] );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table operations require direct queries.
        $result = $wpdb->insert(
            self::table_name(),
            $insert_data,
            [ '%s', '%s', '%s', '%s', '%d' ]
        );
        
        if ( false === $result ) {
            LLMVM_Logger::log( 'Database insert failed', [ 'error' => $wpdb->last_error, 'prompt_length' => strlen( $prompt ), 'model' => $model, 'answer_length' => strlen( $answer ), 'user_id' => $user_id ] );
        } else {
            LLMVM_Logger::log( 'Database insert successful', [ 'insert_id' => $wpdb->insert_id, 'prompt_length' => strlen( $prompt ), 'model' => $model, 'answer_length' => strlen( $answer ), 'user_id' => $user_id ] );
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
        
        // Validate and sanitize orderby parameter
        $allowed_columns = [ 'id', 'created_at', 'prompt', 'model', 'user_id' ];
        if ( ! in_array( $orderby, $allowed_columns, true ) ) {
            $orderby = 'created_at';
        }
        
        // Validate and sanitize order parameter
        $order = strtoupper( $order );
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }
        
        // Sanitize limit and offset
        $limit = max( 1, min( 1000, $limit ) );
        $offset = max( 0, $offset );
        
        $table_name = self::table_name();
        
        // Build query with user filtering
        if ( $user_id > 0 ) {
            $query = sprintf(
                'SELECT id, created_at, prompt, model, answer, user_id FROM %s WHERE user_id = %%d ORDER BY %s %s, id DESC LIMIT %d OFFSET %d',
                $table_name,
                $orderby,
                $order,
                $limit,
                $offset
            );
            $query = $wpdb->prepare( $query, $user_id );
        } else {
            $query = sprintf(
                'SELECT id, created_at, prompt, model, answer, user_id FROM %s ORDER BY %s %s, id DESC LIMIT %d OFFSET %d',
                $table_name,
                $orderby,
                $order,
                $limit,
                $offset
            );
        }
        
        /** @var array<int,array<string,mixed>> $rows */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries.
        $rows = $wpdb->get_results( $query, ARRAY_A );
        
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
        $table_name = self::table_name();
        
        if ( $user_id > 0 ) {
            $query = sprintf( 'SELECT COUNT(*) FROM %s WHERE user_id = %%d', $table_name );
            $query = $wpdb->prepare( $query, $user_id );
        } else {
            $query = sprintf( 'SELECT COUNT(*) FROM %s', $table_name );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries.
        $count = $wpdb->get_var( $query );
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
        $table_name = self::table_name();
        
        // Sanitize IDs
        $ids = array_map( 'intval', $ids );
        $ids = array_filter( $ids );
        
        if ( empty( $ids ) ) {
            return 0;
        }
        
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        
        if ( $user_id > 0 ) {
            // Delete only results owned by the specified user
            $query = sprintf( 'DELETE FROM %s WHERE id IN (%s) AND user_id = %%d', $table_name, $placeholders );
            $query = $wpdb->prepare( $query, ...array_merge( $ids, [ $user_id ] ) );
        } else {
            // Delete results regardless of ownership (admin function)
            $query = sprintf( 'DELETE FROM %s WHERE id IN (%s)', $table_name, $placeholders );
            $query = $wpdb->prepare( $query, ...$ids );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries.
        $deleted = $wpdb->query( $query );
        
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
        $table_name = self::table_name();
        
        if ( $user_id > 0 ) {
            $query = sprintf( 'SELECT id, created_at, prompt, model, answer, user_id FROM %s WHERE id = %%d AND user_id = %%d', $table_name );
            $query = $wpdb->prepare( $query, $id, $user_id );
        } else {
            $query = sprintf( 'SELECT id, created_at, prompt, model, answer, user_id FROM %s WHERE id = %%d', $table_name );
            $query = $wpdb->prepare( $query, $id );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries.
        $row = $wpdb->get_row( $query, ARRAY_A );
        // Ensure we return null if $wpdb->get_row() returns null, false, or non-array.
        if ( ! is_array( $row ) ) {
            return null;
        }
        return $row;
    }
    
}


