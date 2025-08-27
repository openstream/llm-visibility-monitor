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
        $nonce = isset( $_GET['_wpnonce'] ) ? (string) $_GET['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'llmvm_export_csv' ) ) {
            wp_die( esc_html__( 'Invalid nonce', 'llm-visibility-monitor' ) );
        }

        $results = LLMVM_Database::get_latest_results( 1000 );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=llm-visibility-results.csv' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'Date', 'Prompt', 'Model', 'Answer' ] );
        foreach ( $results as $row ) {
            fputcsv( $output, [
                $row['created_at'] ?? '',
                $row['prompt'] ?? '',
                $row['model'] ?? '',
                $row['answer'] ?? '',
            ] );
        }
        fclose( $output );
        exit;
    }
}


