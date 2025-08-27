<?php
/**
 * Activation tasks for LLM Visibility Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Activator {

    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        // Create or upgrade DB table.
        LLMVM_Database::maybe_upgrade();

        // Schedule cron based on current setting.
        $options        = get_option( 'llmvm_options', [] );
        $cron_frequency = isset( $options['cron_frequency'] ) ? sanitize_text_field( (string) $options['cron_frequency'] ) : 'daily';

        $cron = new LLMVM_Cron();
        $cron->reschedule( $cron_frequency );
    }
}


