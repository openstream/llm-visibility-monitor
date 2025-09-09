<?php
/**
 * LLM Visibility Monitor Progress Tracker
 *
 * Handles real-time progress tracking for prompt execution
 *
 * @package LLM_Visibility_Monitor
 * @since 0.10.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Progress_Tracker {
    
    /**
     * Get progress for a specific run
     *
     * @param string $run_id The unique run identifier
     * @return array Progress data
     */
    public static function get_progress( string $run_id ): array {
        $progress = get_transient( 'llmvm_progress_' . $run_id );
        
        if ( false === $progress ) {
            return [
                'status' => 'not_found',
                'current' => 0,
                'total' => 0,
                'percentage' => 0,
                'message' => 'Progress not found',
                'completed' => false
            ];
        }
        
        return $progress;
    }
    
    /**
     * Initialize progress for a new run
     *
     * @param string $run_id The unique run identifier
     * @param int $total_steps Total number of steps
     * @param string $message Initial message
     * @return bool Success status
     */
    public static function init_progress( string $run_id, int $total_steps, string $message = 'Starting...' ): bool {
        $progress = [
            'status' => 'running',
            'current' => 0,
            'total' => $total_steps,
            'percentage' => 0,
            'message' => $message,
            'completed' => false,
            'started_at' => current_time( 'timestamp' ),
            'updated_at' => current_time( 'timestamp' )
        ];
        
        // Store for 1 hour (3600 seconds)
        return set_transient( 'llmvm_progress_' . $run_id, $progress, 3600 );
    }
    
    /**
     * Update progress for a run
     *
     * @param string $run_id The unique run identifier
     * @param int $current_step Current step number
     * @param string $message Progress message
     * @return bool Success status
     */
    public static function update_progress( string $run_id, int $current_step, string $message = '' ): bool {
        $progress = self::get_progress( $run_id );
        
        if ( 'not_found' === $progress['status'] ) {
            return false;
        }
        
        $progress['current'] = $current_step;
        $progress['percentage'] = $progress['total'] > 0 ? round( ( $current_step / $progress['total'] ) * 100 ) : 0;
        $progress['updated_at'] = current_time( 'timestamp' );
        
        if ( ! empty( $message ) ) {
            $progress['message'] = $message;
        }
        
        // Auto-generate message if not provided
        if ( empty( $message ) ) {
            $progress['message'] = self::get_progress_message( $progress['percentage'] );
        }
        
        // Mark as completed if we've reached the total
        if ( $current_step >= $progress['total'] ) {
            $progress['status'] = 'completed';
            $progress['completed'] = true;
            $progress['message'] = 'Completed successfully!';
        }
        
        // Store for 1 hour (3600 seconds)
        return set_transient( 'llmvm_progress_' . $run_id, $progress, 3600 );
    }
    
    /**
     * Complete progress for a run
     *
     * @param string $run_id The unique run identifier
     * @param string $message Completion message
     * @return bool Success status
     */
    public static function complete_progress( string $run_id, string $message = 'Completed successfully!' ): bool {
        $progress = self::get_progress( $run_id );
        
        if ( 'not_found' === $progress['status'] ) {
            return false;
        }
        
        $progress['status'] = 'completed';
        $progress['completed'] = true;
        $progress['current'] = $progress['total'];
        $progress['percentage'] = 100;
        $progress['message'] = $message;
        $progress['completed_at'] = current_time( 'timestamp' );
        $progress['updated_at'] = current_time( 'timestamp' );
        
        // Store for 1 hour (3600 seconds)
        return set_transient( 'llmvm_progress_' . $run_id, $progress, 3600 );
    }
    
    /**
     * Get progress message based on percentage
     *
     * @param int $percentage Progress percentage
     * @return string Progress message
     */
    private static function get_progress_message( int $percentage ): string {
        if ( $percentage < 10 ) {
            return 'Initializing prompts...';
        } elseif ( $percentage < 25 ) {
            return 'Setting up AI models...';
        } elseif ( $percentage < 50 ) {
            return 'Processing AI models...';
        } elseif ( $percentage < 75 ) {
            return 'Generating responses...';
        } elseif ( $percentage < 90 ) {
            return 'Finalizing results...';
        } elseif ( $percentage < 100 ) {
            return 'Almost complete...';
        } else {
            return 'Completed successfully!';
        }
    }
    
    /**
     * Clean up old progress data
     *
     * @return int Number of cleaned up entries
     */
    public static function cleanup_old_progress(): int {
        global $wpdb;
        
        // Get all transients with our prefix
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_llmvm_progress_%'
            )
        );
        
        $cleaned = 0;
        $cutoff_time = current_time( 'timestamp' ) - 3600; // 1 hour ago
        
        foreach ( $transients as $transient ) {
            $transient_name = str_replace( '_transient_', '', $transient->option_name );
            $progress = get_transient( $transient_name );
            
            if ( $progress && isset( $progress['updated_at'] ) && $progress['updated_at'] < $cutoff_time ) {
                delete_transient( $transient_name );
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Generate a unique run ID
     *
     * @return string Unique run identifier
     */
    public static function generate_run_id(): string {
        return 'run_' . wp_generate_uuid4();
    }
}
