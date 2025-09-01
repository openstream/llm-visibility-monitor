<?php
/**
 * CSV Exporter for results.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Exporter {

    public function hooks(): void {
        add_action( 'admin_post_llmvm_export_csv', [ $this, 'handle_export' ] );
    }

    public function handle_export(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'llm-visibility-monitor' ) );
        }
        
        // Verify nonce with proper sanitization.
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'llmvm_export_csv' ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }

        $results = LLMVM_Database::get_latest_results( 1000 );

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
        $csv_content .= "Date,Prompt,Model,Answer\n";
        
        foreach ( $results as $row ) {
            // Properly escape CSV values using WordPress functions where appropriate
            $created_at = wp_strip_all_tags( (string) ( $row['created_at'] ?? '' ) );
            $prompt = wp_strip_all_tags( (string) ( $row['prompt'] ?? '' ) );
            $model = wp_strip_all_tags( (string) ( $row['model'] ?? '' ) );
            $answer = wp_strip_all_tags( (string) ( $row['answer'] ?? '' ) );
            
            // Escape double quotes for CSV format (CSV standard)
            $created_at = str_replace( '"', '""', $created_at );
            $prompt = str_replace( '"', '""', $prompt );
            $model = str_replace( '"', '""', $model );
            $answer = str_replace( '"', '""', $answer );
            
            $csv_content .= sprintf(
                '"%s","%s","%s","%s"' . "\n",
                $created_at,
                $prompt,
                $model,
                $answer
            );
        }

        // Output CSV content - this is raw CSV data, not HTML
        echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV export, not HTML
        exit;
    }
}


