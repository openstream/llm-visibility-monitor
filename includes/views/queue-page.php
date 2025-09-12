<?php
/**
 * Queue management page view.
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get variables passed from the admin class
$current_user_id = get_current_user_id();
$is_admin = current_user_can( 'llmvm_manage_settings' );
$queue_manager = class_exists( 'LLMVM_Queue_Manager' ) ? new LLMVM_Queue_Manager() : null;
$queue_status = $queue_manager ? $queue_manager->get_queue_status() : array();
$user_filter = $is_admin ? null : $current_user_id;
$queue_jobs = $queue_manager ? $queue_manager->get_queue_jobs( $user_filter, null, 100 ) : array();
?>

<div class="wrap">
    <h1><?php esc_html_e( 'LLM Queue Status', 'llm-visibility-monitor' ); ?></h1>
    
    <?php if ( ! $queue_manager ) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Queue system is not available.', 'llm-visibility-monitor' ); ?></p>
        </div>
    <?php else : ?>
        
        <!-- Queue Status Cards -->
        <div class="llmvm-queue-status">
            <h2><?php esc_html_e( 'Queue Status', 'llm-visibility-monitor' ); ?></h2>
            <div class="llmvm-status-cards">
                <div class="llmvm-status-card pending">
                    <strong><?php esc_html_e( 'Pending:', 'llm-visibility-monitor' ); ?></strong>
                    <span class="llmvm-count"><?php echo esc_html( $queue_status['pending'] ); ?></span>
                </div>
                <div class="llmvm-status-card processing">
                    <strong><?php esc_html_e( 'Processing:', 'llm-visibility-monitor' ); ?></strong>
                    <span class="llmvm-count"><?php echo esc_html( $queue_status['processing'] ); ?></span>
                </div>
                <div class="llmvm-status-card completed">
                    <strong><?php esc_html_e( 'Completed:', 'llm-visibility-monitor' ); ?></strong>
                    <span class="llmvm-count"><?php echo esc_html( $queue_status['completed'] ); ?></span>
                </div>
                <div class="llmvm-status-card failed">
                    <strong><?php esc_html_e( 'Failed:', 'llm-visibility-monitor' ); ?></strong>
                    <span class="llmvm-count"><?php echo esc_html( $queue_status['failed'] ); ?></span>
                </div>
            </div>
            
            <div class="llmvm-queue-actions">
                <button type="button" id="llmvm-refresh-queue" class="button">
                    <?php esc_html_e( 'Refresh', 'llm-visibility-monitor' ); ?>
                </button>
                <?php if ( $is_admin ) : ?>
                    <button type="button" id="llmvm-clear-queue" class="button button-secondary">
                        <?php esc_html_e( 'Clear All Jobs', 'llm-visibility-monitor' ); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status Message -->
        <?php if ( isset( $_GET['llmvm_queued'] ) ) : ?>
            <div class="notice notice-success is-dismissible" style="margin: 20px 0;">
                <p><strong><?php esc_html_e( 'Success!', 'llm-visibility-monitor' ); ?></strong> <?php esc_html_e( 'Your prompts have been queued for processing. Jobs will be processed sequentially to prevent server overload.', 'llm-visibility-monitor' ); ?></p>
            </div>
        <?php endif; ?>

        <!-- Queue Jobs Table -->
        <div class="llmvm-queue-jobs">
            <h2><?php esc_html_e( 'Recent Jobs', 'llm-visibility-monitor' ); ?></h2>
            
            <?php if ( empty( $queue_jobs ) ) : ?>
                <p><?php esc_html_e( 'No jobs in queue.', 'llm-visibility-monitor' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped llmvm-queue-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'llm-visibility-monitor' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'llm-visibility-monitor' ); ?></th>
                            <th><?php esc_html_e( 'Model', 'llm-visibility-monitor' ); ?></th>
                            <th><?php esc_html_e( 'Response Time', 'llm-visibility-monitor' ); ?></th>
                            <th><?php esc_html_e( 'Execution Time', 'llm-visibility-monitor' ); ?></th>
                            <th><?php esc_html_e( 'Queue Overhead', 'llm-visibility-monitor' ); ?></th>
                            <th><?php esc_html_e( 'Overhead Breakdown', 'llm-visibility-monitor' ); ?></th>
                            <th><?php esc_html_e( 'DB Operations', 'llm-visibility-monitor' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'llm-visibility-monitor' ); ?></th>
                            <th><?php esc_html_e( 'Attempts', 'llm-visibility-monitor' ); ?></th>
                            <?php if ( $is_admin ) : ?>
                                <th><?php esc_html_e( 'User', 'llm-visibility-monitor' ); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $queue_jobs as $job ) : ?>
                            <?php
                            $job_data = $job['job_data'] ?? array();
                            $model = $job_data['model'] ?? 'Unknown';
                            $user_id = $job_data['user_id'] ?? 0;
                            $user_name = $user_id ? ( get_user_by( 'id', $user_id )->display_name ?? 'User ' . $user_id ) : 'Unknown';
                            $created_time = strtotime( $job['created_at'] );
                            $time_ago = human_time_diff( $created_time, current_time( 'timestamp' ) );
                            
                            // Calculate execution time
                            $execution_time = '';
                            if ( $job['status'] === 'completed' && ! empty( $job['completed_at'] ) ) {
                                $start_time = strtotime( $job['created_at'] );
                                $end_time = strtotime( $job['completed_at'] );
                                $execution_seconds = $end_time - $start_time;
                                $execution_time = $execution_seconds . 's';
                            } elseif ( $job['status'] === 'processing' && ! empty( $job['started_at'] ) ) {
                                $start_time = strtotime( $job['started_at'] );
                                $current_time = current_time( 'timestamp' );
                                $execution_seconds = $current_time - $start_time;
                                $execution_time = $execution_seconds . 's (running)';
                            } else {
                                $execution_time = '-';
                            }
                            
                            // Get response time from job data (if available)
                            $response_time = '';
                            if ( isset( $job_data['response_time'] ) && is_numeric( $job_data['response_time'] ) ) {
                                $response_time = round( $job_data['response_time'] * 1000, 0 ) . 'ms';
                            } else {
                                $response_time = '-';
                            }
                            
                            // Calculate queue overhead (execution time - response time)
                            $queue_overhead = '';
                            if ( $job['status'] === 'completed' && ! empty( $job['completed_at'] ) && ! empty( $response_time ) && $response_time !== '-' ) {
                                $response_time_seconds = (float) $job_data['response_time'] ?? 0;
                                $start_time = strtotime( $job['created_at'] );
                                $end_time = strtotime( $job['completed_at'] );
                                $execution_seconds = $end_time - $start_time;
                                $overhead_seconds = $execution_seconds - $response_time_seconds;
                                // Ensure overhead is never negative (timing precision issues)
                                $overhead_seconds = max( 0, $overhead_seconds );
                                $queue_overhead = round( $overhead_seconds, 1 ) . 's';
                            } else {
                                $queue_overhead = '-';
                            }
                            
                            // Calculate overhead breakdown (from job data if available)
                            $overhead_breakdown = '';
                            if ( isset( $job_data['timing_breakdown'] ) && is_array( $job_data['timing_breakdown'] ) ) {
                                $breakdown = $job_data['timing_breakdown'];
                                $api_time = $breakdown['api_call_time_ms'] ?? 0;
                                $db_time = $breakdown['db_insert_time_ms'] ?? 0;
                                $job_time = $breakdown['job_update_time_ms'] ?? 0;
                                $total_processing = $breakdown['total_processing_time_ms'] ?? 0;
                                
                                // Calculate queue wait time (execution time - processing time)
                                if ( $job['status'] === 'completed' && ! empty( $job['completed_at'] ) ) {
                                    $start_time = strtotime( $job['created_at'] );
                                    $end_time = strtotime( $job['completed_at'] );
                                    $execution_seconds = $end_time - $start_time;
                                    $execution_ms = $execution_seconds * 1000;
                                    $queue_wait_ms = max( 0, $execution_ms - $total_processing );
                                    
                                    $overhead_breakdown = sprintf( 
                                        'Wait: %.0fms, Process: %.0fms', 
                                        $queue_wait_ms, 
                                        $total_processing 
                                    );
                                } else {
                                    $overhead_breakdown = sprintf( 
                                        'API: %.0fms, DB: %.0fms, Job: %.0fms', 
                                        $api_time, 
                                        $db_time, 
                                        $job_time 
                                    );
                                }
                            } else {
                                $overhead_breakdown = '-';
                            }
                            
                            // Calculate DB operations time (from job data if available)
                            $db_operations = '';
                            if ( isset( $job_data['db_operations_time_ms'] ) && is_numeric( $job_data['db_operations_time_ms'] ) ) {
                                $db_operations = round( $job_data['db_operations_time_ms'], 1 ) . 'ms';
                            } else {
                                $db_operations = '-';
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html( $job['id'] ); ?></td>
                                <td>
                                    <span class="llmvm-status-badge llmvm-status-<?php echo esc_attr( $job['status'] ); ?>">
                                        <?php echo esc_html( ucfirst( $job['status'] ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $model ); ?></td>
                                <td>
                                    <span class="llmvm-metric-value"><?php echo esc_html( $response_time ); ?></span>
                                </td>
                                <td>
                                    <span class="llmvm-metric-value"><?php echo esc_html( $execution_time ); ?></span>
                                </td>
                                <td>
                                    <span class="llmvm-metric-value"><?php echo esc_html( $queue_overhead ); ?></span>
                                </td>
                                <td>
                                    <span class="llmvm-metric-value" style="font-size: 11px;"><?php echo esc_html( $overhead_breakdown ); ?></span>
                                </td>
                                <td>
                                    <span class="llmvm-metric-value"><?php echo esc_html( $db_operations ); ?></span>
                                </td>
                                <td title="<?php echo esc_attr( $job['created_at'] ); ?>">
                                    <?php echo esc_html( $time_ago . ' ago' ); ?>
                                </td>
                                <td><?php echo esc_html( $job['attempts'] . '/' . $job['max_attempts'] ); ?></td>
                                <?php if ( $is_admin ) : ?>
                                    <td><?php echo esc_html( $user_name ); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="llmvm-time-explanation" style="background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 10px; margin-top: 15px; font-size: 13px;">
                    <strong><?php esc_html_e( 'Time Metrics Explained:', 'llm-visibility-monitor' ); ?></strong><br>
                    <strong><?php esc_html_e( 'Response Time:', 'llm-visibility-monitor' ); ?></strong> <?php esc_html_e( 'How long the OpenRouter API took to respond (pure API call time)', 'llm-visibility-monitor' ); ?><br>
                    <strong><?php esc_html_e( 'Execution Time:', 'llm-visibility-monitor' ); ?></strong> <?php esc_html_e( 'Total time from job creation to completion (includes queue processing, database operations, etc.)', 'llm-visibility-monitor' ); ?><br>
                    <strong><?php esc_html_e( 'Queue Overhead:', 'llm-visibility-monitor' ); ?></strong> <?php esc_html_e( 'Time spent in queue processing (execution time - response time)', 'llm-visibility-monitor' ); ?><br>
                    <strong><?php esc_html_e( 'Overhead Breakdown:', 'llm-visibility-monitor' ); ?></strong> <?php esc_html_e( 'Shows queue wait time vs processing time, or API/DB/Job timing details', 'llm-visibility-monitor' ); ?><br>
                    <strong><?php esc_html_e( 'DB Operations:', 'llm-visibility-monitor' ); ?></strong> <?php esc_html_e( 'Time spent on database operations (inserts, updates, etc.)', 'llm-visibility-monitor' ); ?><br>
                    <em><?php esc_html_e( 'Note: Execution time = Response Time + Queue Overhead. Queue Overhead includes DB operations, job management, and other processing.', 'llm-visibility-monitor' ); ?></em>
                </div>
            <?php endif; ?>
        </div>

        <!-- Auto-refresh info -->
        <div class="llmvm-queue-info">
            <p class="description">
                <?php esc_html_e( 'Queue processes jobs every minute via cron. This page auto-refreshes every 30 seconds.', 'llm-visibility-monitor' ); ?>
            </p>
        </div>

    <?php endif; ?>
</div>

<style>
.llmvm-status-cards {
    display: flex;
    gap: 15px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.llmvm-status-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px 20px;
    min-width: 150px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.llmvm-status-card.pending {
    border-left: 4px solid #f0ad4e;
}

.llmvm-status-card.processing {
    border-left: 4px solid #5bc0de;
}

.llmvm-status-card.completed {
    border-left: 4px solid #5cb85c;
}

.llmvm-status-card.failed {
    border-left: 4px solid #d9534f;
}

.llmvm-status-card .llmvm-count {
    font-size: 24px;
    font-weight: bold;
    display: block;
    margin-top: 5px;
}

.llmvm-queue-actions {
    margin: 20px 0;
}

.llmvm-status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.llmvm-status-badge.llmvm-status-pending {
    background: #f0ad4e;
    color: white;
}

.llmvm-status-badge.llmvm-status-processing {
    background: #5bc0de;
    color: white;
}

.llmvm-status-badge.llmvm-status-completed {
    background: #5cb85c;
    color: white;
}

.llmvm-status-badge.llmvm-status-failed {
    background: #d9534f;
    color: white;
}

.llmvm-queue-table th {
    background: #f1f1f1;
}

.llmvm-queue-info {
    margin-top: 30px;
    padding: 15px;
    background: #f9f9f9;
    border-left: 4px solid #00a0d2;
}

.llmvm-metric-value {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    color: #333;
    font-weight: bold;
}

.llmvm-queue-table th {
    background: #f1f1f1;
    font-weight: 600;
}

.llmvm-queue-table td {
    vertical-align: middle;
}

/* Column width optimization */
.llmvm-queue-table th:nth-child(1),
.llmvm-queue-table td:nth-child(1) {
    width: 50px; /* ID column - narrow */
}

.llmvm-queue-table th:nth-child(2),
.llmvm-queue-table td:nth-child(2) {
    width: 80px; /* Status column - narrow */
}

.llmvm-queue-table th:nth-child(3),
.llmvm-queue-table td:nth-child(3) {
    width: 200px; /* Model column - wider */
    min-width: 200px;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.llmvm-queue-table th:nth-child(4),
.llmvm-queue-table td:nth-child(4) {
    width: 100px; /* Response Time column */
}

.llmvm-queue-table th:nth-child(5),
.llmvm-queue-table td:nth-child(5) {
    width: 100px; /* Execution Time column */
}

.llmvm-queue-table th:nth-child(6),
.llmvm-queue-table td:nth-child(6) {
    width: 100px; /* Queue Overhead column */
}

.llmvm-queue-table th:nth-child(7),
.llmvm-queue-table td:nth-child(7) {
    width: 150px; /* Overhead Breakdown column */
    white-space: nowrap;
}

.llmvm-queue-table th:nth-child(8),
.llmvm-queue-table td:nth-child(8) {
    width: 100px; /* DB Operations column */
}

.llmvm-queue-table th:nth-child(9),
.llmvm-queue-table td:nth-child(9) {
    width: 120px; /* Created column */
}

.llmvm-queue-table th:nth-child(10),
.llmvm-queue-table td:nth-child(10) {
    width: 80px; /* Attempts column */
}

.llmvm-queue-table th:nth-child(11),
.llmvm-queue-table td:nth-child(11) {
    width: 100px; /* User column (admin only) */
}
</style>

<script>
jQuery(document).ready(function($) {
    // Auto-refresh every 30 seconds
    setInterval(function() {
        refreshQueueStatus();
    }, 30000);

    // Manual refresh button
    $('#llmvm-refresh-queue').on('click', function() {
        refreshQueueStatus();
    });

    // Clear queue button (admin only)
    $('#llmvm-clear-queue').on('click', function() {
        if (confirm('<?php echo esc_js( __( 'Are you sure you want to clear all jobs from the queue?', 'llm-visibility-monitor' ) ); ?>')) {
            clearQueue();
        }
    });

    function refreshQueueStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'llmvm_get_queue_status',
                nonce: '<?php echo wp_create_nonce( 'llmvm_queue_nonce' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    updateQueueDisplay(response.data);
                }
            },
            error: function() {
                console.log('Failed to refresh queue status');
            }
        });
    }

    function clearQueue() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'llmvm_clear_queue',
                nonce: '<?php echo wp_create_nonce( 'llmvm_queue_nonce' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php echo esc_js( __( 'Queue cleared successfully', 'llm-visibility-monitor' ) ); ?>');
                    location.reload();
                } else {
                    alert('<?php echo esc_js( __( 'Failed to clear queue', 'llm-visibility-monitor' ) ); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js( __( 'Failed to clear queue', 'llm-visibility-monitor' ) ); ?>');
            }
        });
    }

    function updateQueueDisplay(data) {
        // Update status cards
        $('.llmvm-status-card.pending .llmvm-count').text(data.status.pending);
        $('.llmvm-status-card.processing .llmvm-count').text(data.status.processing);
        $('.llmvm-status-card.completed .llmvm-count').text(data.status.completed);
        $('.llmvm-status-card.failed .llmvm-count').text(data.status.failed);

        // Update jobs table (simplified - just reload page for now)
        // In a more sophisticated implementation, you'd update the table rows
        location.reload();
    }
});
</script>
