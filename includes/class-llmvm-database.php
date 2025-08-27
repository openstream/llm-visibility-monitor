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
     * Get latest results.
     *
     * @param int $limit Number of results to fetch.
     * @return array<int,array<string,mixed>>
     */
    public static function get_latest_results( int $limit = 20 ): array {
        global $wpdb;
        $table = self::table_name();
        $sql   = $wpdb->prepare( "SELECT id, created_at, prompt, model, answer FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit );
        /** @var array<int,array<string,mixed>> $rows */
        $rows  = $wpdb->get_results( $sql, ARRAY_A );
        return is_array( $rows ) ? $rows : [];
    }
}


