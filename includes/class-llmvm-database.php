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
    private const DB_VERSION = '1.0.0';

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
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Insert a result row.
     */
    public static function insert_result( string $prompt, string $model, string $answer ): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table operations require direct queries.
        $wpdb->insert(
            self::table_name(),
            [
                'created_at' => current_time( 'mysql', true ),
                'prompt'     => $prompt,
                'model'      => $model,
                'answer'     => $answer,
            ],
            [ '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Get latest results with sorting and pagination.
     *
     * @param int    $limit  Number of results to fetch.
     * @param string $orderby Column to order by.
     * @param string $order   Order direction (ASC or DESC).
     * @param int    $offset  Offset for pagination.
     * @return array<int,array<string,mixed>>
     */
    public static function get_latest_results( int $limit = 20, string $orderby = 'created_at', string $order = 'DESC', int $offset = 0 ): array {
        global $wpdb;
        
        // Validate and sanitize orderby parameter
        $allowed_columns = [ 'id', 'created_at', 'prompt', 'model' ];
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
        /** @var array<int,array<string,mixed>> $rows */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries.
        // Build query with proper escaping for table name and column names
        $query = sprintf(
            'SELECT id, created_at, prompt, model, answer FROM %s ORDER BY %s %s, id DESC LIMIT %d OFFSET %d',
            $table_name,
            $orderby,
            $order,
            $limit,
            $offset
        );
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
     * @return int
     */
    public static function get_total_results(): int {
        global $wpdb;
        $table_name = self::table_name();
        // Build query with proper escaping for table name
        $query = sprintf( 'SELECT COUNT(*) FROM %s', $table_name );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries.
        $count = $wpdb->get_var( $query );
        return (int) $count;
    }

    /**
     * Delete multiple results by IDs.
     *
     * @param array<int> $ids Array of result IDs to delete.
     * @return int Number of deleted rows.
     */
    public static function delete_results_by_ids( array $ids ): int {
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries.
        // Build query with proper escaping for table name
        $query = sprintf( 'DELETE FROM %s WHERE id IN (%s)', $table_name, $placeholders );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is properly prepared with placeholders.
        $query = $wpdb->prepare( $query, ...$ids );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries.
        $deleted = $wpdb->query( $query );
        
        return (int) $deleted;
    }

    /**
     * Get a single result by id.
     *
     * @param int $id Row id.
     * @return array<string,mixed>|null
     */
    public static function get_result_by_id( int $id ): ?array {
        global $wpdb;
        // Use $wpdb->prepare() with placeholders as required by WordPress coding standards.
        $table_name = self::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries.
        // Build query with proper escaping for table name
        $query = sprintf( 'SELECT id, created_at, prompt, model, answer FROM %s WHERE id = %%d', $table_name );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is properly prepared with placeholders.
        $query = $wpdb->prepare( $query, $id );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table operations require direct queries.
        $row = $wpdb->get_row( $query, ARRAY_A );
        // Ensure we return null if $wpdb->get_row() returns null, false, or non-array.
        if ( ! is_array( $row ) ) {
            return null;
        }
        return $row;
    }
}


