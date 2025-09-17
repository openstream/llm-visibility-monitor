<?php
/**
 * Email reporting functionality for LLM Visibility Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_Email_Reporter {

    /**
     * Register hooks.
     */
    public function hooks(): void {
        // Send email report after each cron run
        add_action( 'llmvm_run_completed', [ $this, 'send_report_after_run' ], 20 );
    }

    /**
     * Send limit notification email when user hits their usage limits.
     */
    public function send_limit_notification( int $user_id ): void {
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }

        // Check if email reporting is enabled
        if ( empty( $options['email_reports'] ) ) {
            LLMVM_Logger::log( 'Limit notification: email reports disabled in settings' );
            return;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            LLMVM_Logger::log( 'Limit notification: user not found', [ 'user_id' => $user_id ] );
            return;
        }

        $recipient_email = $user->user_email;
        if ( empty( $recipient_email ) ) {
            LLMVM_Logger::log( 'Limit notification: no email address for user', [ 'user_id' => $user_id ] );
            return;
        }

        // Get usage summary
        $usage = LLMVM_Usage_Manager::get_usage_summary( $user_id );
        
        // Prepare email content
        $subject = sprintf( '[%s] LLM Visibility Monitor - Usage Limit Reached', get_bloginfo( 'name' ) );
        $message = $this->generate_limit_notification_email( $usage, $user );

        // Send email
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        LLMVM_Logger::log( 'Limit notification: sending', [ 'to' => $recipient_email, 'user_id' => $user_id ] );
        
        $sent = wp_mail( $recipient_email, $subject, $message, $headers );

        if ( $sent ) {
            LLMVM_Logger::log( 'Limit notification: sent successfully', [ 'to' => $recipient_email, 'user_id' => $user_id ] );
        } else {
            LLMVM_Logger::log( 'Limit notification: failed to send', [ 'to' => $recipient_email, 'user_id' => $user_id ] );
        }
    }

    /**
     * Send email report after a cron run completes.
     */
    public function send_report_after_run( $user_id = 0, $user_results_or_key = [] ): void {
        // Email reporter started
        LLMVM_Logger::log( 'Email reporter called', [ 
            'user_id' => $user_id,
            'has_global_key' => isset( $GLOBALS['llmvm_current_run_transient_key'] ),
            'global_key' => $GLOBALS['llmvm_current_run_transient_key'] ?? 'none',
            'has_global_results' => isset( $GLOBALS['llmvm_current_run_results'] ),
            'global_results_count' => isset( $GLOBALS['llmvm_current_run_results'] ) ? count( $GLOBALS['llmvm_current_run_results'] ) : 0,
            'user_results_or_key_type' => gettype( $user_results_or_key ),
            'user_results_or_key_value' => is_array( $user_results_or_key ) ? count( $user_results_or_key ) : $user_results_or_key,
            'user_results_or_key_raw' => $user_results_or_key,
            'func_num_args' => func_num_args(),
            'func_get_args' => func_get_args()
        ] );
        
        $options = get_option( 'llmvm_options', [] );
        if ( ! is_array( $options ) ) {
            $options = [];
        }

        // Check if email reporting is enabled
        if ( empty( $options['email_reports'] ) ) {
            LLMVM_Logger::log( 'Email report: disabled in settings' );
            return;
        }
        
        // Email reports enabled
        
        // Get results - new format passes results directly, old format uses transients
        $user_results = [];
        if ( is_array( $user_results_or_key ) && ! empty( $user_results_or_key ) ) {
            // New format: results passed directly from queue manager
            $user_results = $user_results_or_key;
            LLMVM_Logger::log( 'Using results passed directly from queue manager', [ 
                'results_count' => count( $user_results ),
                'is_array' => is_array( $user_results ),
                'is_empty' => empty( $user_results ),
                'first_result' => ! empty( $user_results ) ? $user_results[0] : null
            ] );
        } elseif ( isset( $GLOBALS['llmvm_current_run_results'] ) ) {
            // Fallback: results stored in global variable by queue manager
            $user_results = $GLOBALS['llmvm_current_run_results'];
            LLMVM_Logger::log( 'Using results from global variable', [ 
                'results_count' => count( $user_results ),
                'is_array' => is_array( $user_results ),
                'is_empty' => empty( $user_results ),
                'first_result' => ! empty( $user_results ) ? $user_results[0] : null
            ] );
            // Clean up global variable
            unset( $GLOBALS['llmvm_current_run_results'] );
        } elseif ( isset( $GLOBALS['llmvm_current_run_transient_key'] ) ) {
            // Fallback: old format with global transient key
            $transient_key = $GLOBALS['llmvm_current_run_transient_key'];
            $user_results = get_transient( $transient_key );
            LLMVM_Logger::log( 'Retrieved results from current run transient', [ 'results_count' => is_array( $user_results ) ? count( $user_results ) : 0 ] );
            if ( $user_results !== false ) {
                delete_transient( $transient_key ); // Clean up
                unset( $GLOBALS['llmvm_current_run_transient_key'] ); // Clean up global
            }
        } elseif ( is_string( $user_results_or_key ) && strpos( $user_results_or_key, 'llmvm_current_run_results_' ) === 0 ) {
            // Fallback: old format with transient key parameter
            $user_results = get_transient( $user_results_or_key );
            LLMVM_Logger::log( 'Retrieved results from parameter transient', [ 'results_count' => is_array( $user_results ) ? count( $user_results ) : 0 ] );
            if ( $user_results !== false ) {
                delete_transient( $user_results_or_key ); // Clean up
            }
        }
        
        // If no user results provided, skip email (no results from current run)
        if ( empty( $user_results ) ) {
            LLMVM_Logger::log( 'Email report: no results from current run, skipping', [ 'user_id' => $user_id ] );
            return;
        }

        // Determine recipient and results based on user role
        $current_user = get_user_by( 'id', $user_id );
        $is_admin = $current_user && current_user_can( 'llmvm_manage_settings', $user_id );
        
        if ( $is_admin ) {
            // Admin gets current run results sent to admin email
            $recipient_email = get_option( 'admin_email' );
            $results_to_send = $user_results; // Current run results
            $email_type = 'admin';
        } else {
            // Regular user gets only their current run results sent to their email
            $recipient_email = $current_user ? $current_user->user_email : '';
            $results_to_send = $user_results; // Current run results
            $email_type = 'user';
        }
        
        if ( empty( $recipient_email ) ) {
            LLMVM_Logger::log( 'Email report failed: no recipient email found', [ 'user_id' => $user_id, 'email_type' => $email_type ] );
            return;
        }

        // Prepare email content
        $subject = sprintf( '[%s] LLM Visibility Monitor - Run Results', get_bloginfo( 'name' ) );
        $message = $this->generate_report_email( $results_to_send, $email_type, $current_user );

        // Send email
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        LLMVM_Logger::log( 'Email report: sending', [ 'to' => $recipient_email, 'user_id' => $user_id, 'email_type' => $email_type, 'results_count' => count( $results_to_send ) ] );
        
        $sent = wp_mail( $recipient_email, $subject, $message, $headers );

        if ( $sent ) {
            LLMVM_Logger::log( 'Email report: sent successfully', [ 'to' => $recipient_email, 'user_id' => $user_id, 'email_type' => $email_type ] );
        } else {
            LLMVM_Logger::log( 'Email report: failed to send', [ 'to' => $recipient_email, 'user_id' => $user_id, 'email_type' => $email_type ] );
        }
    }

    /**
     * Generate HTML email report with improved mobile responsiveness and modern design.
     */
    private function generate_report_email( array $results, string $email_type, $user = null ): string {
        $total_results = count( $results );
        $success_count = 0;
        $error_count = 0;

        // Count successes and errors
        $comparison_scores = array();
        $has_comparison_scores = false;
        
        foreach ( $results as $result ) {
            $answer = isset( $result['answer'] ) ? (string) $result['answer'] : '';
            if ( '' === trim( $answer ) || strpos( $answer, 'No answer' ) !== false ) {
                $error_count++;
            } else {
                $success_count++;
            }
            
            // Collect comparison scores
            if ( isset( $result['comparison_score'] ) && $result['comparison_score'] !== null ) {
                $comparison_scores[] = (int) $result['comparison_score'];
                $has_comparison_scores = true;
            }
        }

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>LLM Visibility Monitor Report</title>
    <style>
        /* Reset and base styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        
        /* Main styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            width: 100% !important;
            min-width: 100%;
        }
        
        .email-container {
            max-width: 900px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .header p {
            margin: 5px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px 20px;
        }
        
        .summary-card {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .summary-card h2 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 20px;
            font-weight: 600;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
            margin: 15px 0;
        }
        
        .stat-item {
            display: table-cell;
            text-align: center;
            padding: 10px;
            vertical-align: top;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .success-stat { color: #28a745; }
        .error-stat { color: #dc3545; }
        .total-stat { color: #007bff; }
        
        .results-section {
            margin: 30px 0;
        }
        
        .results-section h2 {
            color: #495057;
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 20px 0;
        }
        
        /* Mobile-first responsive table */
        .results-table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            table-layout: fixed;
        }
        
        .results-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 15px 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
        }
        
        .results-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top !important;
            font-size: 13px;
        }
        
        /* Fix vertical alignment for answer column paragraphs */
        .results-table .answer-col p {
            margin-top: 0 !important;
            margin-block-start: 0 !important;
        }
        .results-table .answer-col .answer-content p:first-child {
            margin-top: 0 !important;
            margin-block-start: 0 !important;
        }
        
        .results-table tr:last-child td {
            border-bottom: none;
        }
        
        .results-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Column widths for desktop - optimized for content */
        .date-col { width: 15%; min-width: 150px; }
        .prompt-col { width: 35%; min-width: 250px; }
        .model-col { width: 20%; min-width: 160px; }
        .answer-col { width: 65%; }
        .user-col { width: 10%; min-width: 100px; }
        
        /* Combined column styles for better space utilization */
        .combined-meta-col { width: 20%; min-width: 180px; }
        .combined-meta-col .meta-item { 
            margin-bottom: 3px; 
            font-size: 12px; 
            line-height: 1.3;
        }
        .combined-meta-col .meta-label { 
            font-weight: 600; 
            color: #495057; 
            display: inline-block; 
            min-width: 50px; 
        }
        .model-badge {
            background: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
            margin-top: 2px;
        }
        .date-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
            margin-top: 2px;
        }
        
        .score-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 40px;
        }
        
        .score-high {
            background: #28a745;
            color: white;
        }
        
        .score-medium {
            background: #ffc107;
            color: #212529;
        }
        
        .score-low {
            background: #dc3545;
            color: white;
        }
        
        .score-none {
            background: #6c757d;
            color: white;
        }
        
        .score-col {
            width: 80px;
            text-align: center;
            vertical-align: middle;
        }
        
        /* Mobile responsive styles */
        @media only screen and (max-width: 768px) {
            .email-container {
                width: 100% !important;
            }
            
            .header {
                padding: 20px 15px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .content {
                padding: 20px 15px;
            }
            
            .summary-card {
                padding: 15px;
            }
            
            .stats-grid {
                display: block;
            }
            
            .stat-item {
                display: block;
                text-align: left;
                padding: 8px 0;
                border-bottom: 1px solid #e9ecef;
            }
            
            .stat-item:last-child {
                border-bottom: none;
            }
            
            /* Mobile table optimization - better width utilization */
            .results-section {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .results-table {
                min-width: 100%;
                font-size: 13px;
                table-layout: auto;
            }
            
            .results-table th,
            .results-table td {
                padding: 12px 10px;
            }
            
            /* Optimized column widths for mobile */
            .combined-meta-col { width: 18%; min-width: 140px; }
            .prompt-col { width: 40%; min-width: 200px; }
            .answer-col { width: 60%; }
            
            /* Alternative: Stack table columns on very small screens */
            @media only screen and (max-width: 600px) {
                .results-table,
                .results-table thead,
                .results-table tbody,
                .results-table th,
                .results-table td,
                .results-table tr {
                    display: block;
                }
                
                .results-table thead tr {
                    position: absolute;
                    top: -9999px;
                    left: -9999px;
                }
                
                .results-table tr {
                    border: 1px solid #e9ecef;
                    border-radius: 8px;
                    margin-bottom: 15px;
                    margin-left: 0;
                    margin-right: 0;
                    padding: 15px;
                    background: #ffffff;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    width: 90%;
                }
                
                .results-table td {
                    border: none;
                    position: relative;
                    padding: 8px 0;
                    padding-left: 90px;
                    font-size: 14px;
                    width: 100%;
                }
                
                .results-table td:before {
                    content: attr(data-label) ": ";
                    position: absolute;
                    left: 6px;
                    width: 80px;
                    padding-right: 8px;
                    white-space: nowrap;
                    font-weight: 600;
                    color: #495057;
                    font-size: 12px;
                }
                
                .answer-content {
                    max-height: 300px;
                }
                
                .score-col {
                    width: 100%;
                    text-align: left;
                }
                
                .score-badge {
                    font-size: 12px;
                    padding: 3px 6px;
                    min-width: 35px;
                }
                    overflow-y: auto;
                    border: 1px solid #e9ecef;
                    border-radius: 4px;
                    padding: 12px;
                    background: #f8f9fa;
                    width: 100%;
                    box-sizing: border-box;
                }
                
                /* Full width utilization for prompt and answer */
                .results-table td[data-label="Prompt"],
                .results-table td[data-label="Answer"],
                .results-table td[data-label="Meta"] {
                    padding-left: 0;
                }
                
                .results-table td[data-label="Prompt"]:before,
                .results-table td[data-label="Answer"]:before,
                .results-table td[data-label="Meta"]:before {
                    display: none;
                }
                
                .results-table td[data-label="Prompt"] {
                    font-weight: 600;
                    color: #495057;
                    margin-bottom: 8px;
                }
                
                .results-table td[data-label="Answer"] {
                    margin-top: 8px;
                }
            }
        }
        
        /* Content formatting */
        .answer-content {
            line-height: 1.6;
        }
        
        .answer-content h1, .answer-content h2, .answer-content h3, .answer-content h4 {
            color: #495057;
            margin: 15px 0 10px 0;
            font-weight: 600;
        }
        
        .answer-content h1 {
            font-size: 20px;
            border-bottom: 3px solid #007bff;
            padding-bottom: 8px;
        }
        
        .answer-content h2 {
            font-size: 18px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 5px;
        }
        
        .answer-content h3 {
            font-size: 16px;
        }
        
        .answer-content h4 {
            font-size: 14px;
        }
        
        .answer-content ul, .answer-content ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .answer-content ol {
            list-style-type: decimal !important;
            counter-reset: item;
        }
        
        .answer-content ul {
            list-style-type: disc !important;
        }
        
        .answer-content li {
            margin: 5px 0;
            display: list-item !important;
        }
        
        .answer-content ol li {
            list-style-type: decimal !important;
        }
        
        .answer-content strong {
            font-weight: 600;
            color: #495057;
        }
        
        .answer-content em {
            font-style: italic;
            color: #6c757d;
        }
        
        .answer-content code {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 3px;
            padding: 2px 6px;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 13px;
            color: #e83e8c;
        }
        
        .answer-content blockquote {
            border-left: 4px solid #007bff;
            margin: 15px 0;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 0 4px 4px 0;
        }
        
        .status-success {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-error {
            color: #dc3545;
            font-weight: 600;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
        }
        
        .footer a {
            color: #007bff;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .action-buttons {
            margin: 20px 0;
            text-align: center;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 5px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #0056b3;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="email-container">
    <div class="header">
            <h1>🤖 LLM Visibility Monitor</h1>
            <p>Generated on ' . current_time( 'F j, Y \a\t g:i A T' ) . '</p>';
        
        if ( $email_type === 'user' && $user ) {
            $html .= '<p>Run Results for: ' . esc_html( $user->display_name ) . '</p>';
        } elseif ( $email_type === 'admin' ) {
            $html .= '<p>Administrator Run Results</p>';
        }
        
        $html .= '</div>
    
    <div class="content">
            <div class="summary-card">
                <h2>📊 Summary</h2>';
            
        if ( $email_type === 'user' && $user ) {
            $html .= '<p><strong>User:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</p>';
        }
        
        $html .= '<div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number total-stat">' . esc_html( (string) $total_results ) . '</span>
                        <span class="stat-label">Total Results</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number success-stat">' . esc_html( (string) $success_count ) . '</span>
                        <span class="stat-label">Successful</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number error-stat">' . esc_html( (string) $error_count ) . '</span>
                        <span class="stat-label">Errors</span>
                    </div>
                </div>';
        
        // Add comparison score summary if available
        if ( $has_comparison_scores && ! empty( $comparison_scores ) ) {
            $avg_score = round( array_sum( $comparison_scores ) / count( $comparison_scores ), 1 );
            $min_score = min( $comparison_scores );
            $max_score = max( $comparison_scores );
            
            $html .= '
            <div class="summary-card">
                <h2>🎯 Comparison Scores</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number">' . esc_html( (string) $avg_score ) . '</span>
                        <span class="stat-label">Average Score</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">' . esc_html( (string) $min_score ) . '</span>
                        <span class="stat-label">Lowest Score</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">' . esc_html( (string) $max_score ) . '</span>
                        <span class="stat-label">Highest Score</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">' . esc_html( (string) count( $comparison_scores ) ) . '</span>
                        <span class="stat-label">Scored Results</span>
                    </div>
                </div>
            </div>';
        }
        
        // Add prompt summaries section
        $prompt_summaries = $this->get_relevant_prompt_summaries( $results, $user );
        if ( ! empty( $prompt_summaries ) ) {
            $html .= $this->generate_prompt_summaries_section( $prompt_summaries );
        }
        
        $html .= '</div>';

        if ( ! empty( $results ) ) {
            $html .= '
            <div class="results-section">
                <h2>📋 Latest Results</h2>
                <p style="font-size: 12px; color: #6c757d; margin: 0 0 15px 0; font-style: italic;">💡 On mobile devices, results are displayed in optimized cards for better readability.</p>
                <table class="results-table">
            <thead>
                <tr>';
                
            if ( $email_type === 'admin' ) {
                $html .= '<th class="prompt-col">User & Prompt</th>';
            } else {
                $html .= '<th class="prompt-col">Prompt</th>';
            }
            
            $html .= '<th class="answer-col">Answer</th>';
            
            // Add comparison score column if we have comparison scores
            if ( $has_comparison_scores ) {
                $html .= '<th class="score-col">Score</th>';
            }
            
            $html .= '</tr>
            </thead>
            <tbody>';

            foreach ( $results as $result ) {
                $date = isset( $result['created_at'] ) ? LLMVM_Admin::convert_utc_to_user_timezone( $result['created_at'], $result['user_id'] ?? null ) : '';
                $prompt = isset( $result['prompt'] ) ? (string) $result['prompt'] : '';
                $model = isset( $result['model'] ) ? (string) $result['model'] : '';
                $answer = isset( $result['answer'] ) ? (string) $result['answer'] : '';
                $result_user_id = isset( $result['user_id'] ) ? (int) $result['user_id'] : 0;

                // Format the answer with enhanced formatting
                $formatted_answer = $this->format_answer_for_email( $answer );
                $is_error = ( '' === trim( $answer ) || strpos( $answer, 'No answer' ) !== false );

                $html .= '
                        <tr>';
                
                // Prompt column with date and model info
                $html .= '<td class="prompt-col" data-label="Prompt">';
                
                if ( $email_type === 'admin' ) {
                    $result_user = get_user_by( 'id', $result_user_id );
                    $user_display = $result_user ? $result_user->display_name : 'Unknown User';
                    $html .= '<div class="meta-item" style="margin-bottom: 8px;"><strong>User:</strong> ' . esc_html( $user_display ) . '</div>';
                }
                
                $html .= '<div style="margin-bottom: 8px;">' . esc_html( $prompt ) . '</div>
                            <div class="meta-item" style="margin-bottom: 3px;"><span class="date-badge">' . esc_html( $date ) . '</span></div>
                            <div class="meta-item"><span class="model-badge">' . esc_html( $model ) . '</span></div>
                        </td>
                        <td class="answer-col" data-label="Answer">
                            <div class="answer-content">' . $formatted_answer . '</div>
                        </td>';
                
                // Add comparison score cell if we have comparison scores
                if ( $has_comparison_scores ) {
                    $comparison_score = isset( $result['comparison_score'] ) ? (int) $result['comparison_score'] : null;
                    if ( $comparison_score !== null ) {
                        $score_class = $comparison_score >= 8 ? 'score-high' : ( $comparison_score >= 6 ? 'score-medium' : 'score-low' );
                        $html .= '<td class="score-col" data-label="Score">
                            <span class="score-badge ' . $score_class . '">' . esc_html( (string) $comparison_score ) . '/10</span>
                        </td>';
                    } else {
                        $html .= '<td class="score-col" data-label="Score">
                            <span class="score-badge score-none">N/A</span>
                        </td>';
                    }
                }
                
                $html .= '</tr>';
            }

            $html .= '
            </tbody>
                </table>
            </div>';
        }

        $html .= '
            <div class="action-buttons">
                <a href="' . admin_url( 'tools.php?page=llmvm-dashboard' ) . '" class="btn">View Dashboard</a>
                <a href="' . admin_url( 'tools.php?page=llmvm-prompts' ) . '" class="btn btn-secondary">Manage Prompts</a>
            </div>
        </div>
        
        <div class="footer">
            <p>This report was automatically generated by the LLM Visibility Monitor plugin.</p>
            <p>To disable email reports, go to <a href="' . admin_url( 'options-general.php?page=llmvm-settings' ) . '">Settings → LLM Visibility Monitor</a></p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Generate HTML limit notification email.
     */
    private function generate_limit_notification_email( array $usage, $user ): string {
        $upgrade_url = admin_url( 'admin.php?page=subscription' );
        $dashboard_url = admin_url( 'tools.php?page=llmvm-prompts' );
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>LLM Visibility Monitor - Usage Limit Reached</title>
    <style>
        /* Reset and base styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        
        /* Main styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            width: 100% !important;
            min-width: 100%;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .header p {
            margin: 5px 0;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px 20px;
        }
        
        .limit-card {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .limit-card h2 {
            color: #c53030;
            margin: 0 0 15px 0;
            font-size: 20px;
        }
        
        .usage-stats {
            background: #f7fafc;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .usage-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .usage-item:last-child {
            border-bottom: none;
        }
        
        .usage-label {
            font-weight: 500;
            color: #4a5568;
        }
        
        .usage-value {
            font-weight: 600;
            color: #2d3748;
        }
        
        .usage-value.exceeded {
            color: #c53030;
        }
        
        .cta-section {
            text-align: center;
            margin: 30px 0;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 10px;
            transition: all 0.3s ease;
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .secondary-button {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .secondary-button:hover {
            background: #cbd5e0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .footer {
            background: #f7fafc;
            padding: 20px;
            text-align: center;
            color: #718096;
            font-size: 14px;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            
            .content {
                padding: 20px 15px;
            }
            
            .cta-button {
                display: block;
                margin: 10px 0;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>⚠️ Usage Limit Reached</h1>
            <p>Your LLM Visibility Monitor usage has exceeded your plan limits</p>
        </div>
        
        <div class="content">
            <p>Hello ' . esc_html( $user->display_name ) . ',</p>
            
            <p>Your scheduled LLM monitoring run could not be completed because you have reached your usage limits. Here\'s your current usage status:</p>
            
            <div class="limit-card">
                <h2>Current Usage Status</h2>
                <div class="usage-stats">
                    <div class="usage-item">
                        <span class="usage-label">Plan:</span>
                        <span class="usage-value">' . esc_html( $usage['plan_name'] ) . '</span>
                    </div>
                    <div class="usage-item">
                        <span class="usage-label">Prompts:</span>
                        <span class="usage-value ' . ( $usage['prompts']['used'] >= $usage['prompts']['limit'] ? 'exceeded' : '' ) . '">
                            ' . esc_html( $usage['prompts']['used'] ) . ' / ' . esc_html( $usage['prompts']['limit'] ) . '
                        </span>
                    </div>
                    <div class="usage-item">
                        <span class="usage-label">Runs This Month:</span>
                        <span class="usage-value ' . ( $usage['runs']['used'] >= $usage['runs']['limit'] ? 'exceeded' : '' ) . '">
                            ' . esc_html( $usage['runs']['used'] ) . ' / ' . esc_html( $usage['runs']['limit'] ) . '
                        </span>
                    </div>
                    <div class="usage-item">
                        <span class="usage-label">Max Models per Prompt:</span>
                        <span class="usage-value">' . esc_html( $usage['models_per_prompt'] ) . '</span>
                    </div>
                </div>
            </div>
            
            <div class="cta-section">
                <h3>Upgrade Your Plan</h3>
                <p>To continue monitoring your LLM visibility, please upgrade to a higher plan with more generous limits.</p>
                
                <a href="' . esc_url( $upgrade_url ) . '" class="cta-button">
                    🚀 Upgrade Now
                </a>
                
                <a href="' . esc_url( $dashboard_url ) . '" class="cta-button secondary-button">
                    📊 View Dashboard
                </a>
            </div>
            
            <p>If you have any questions about your usage or need help with your account, please don\'t hesitate to contact our support team.</p>
            
            <p>Best regards,<br>
            The LLM Visibility Monitor Team</p>
        </div>
        
        <div class="footer">
            <p>This email was sent because your scheduled LLM monitoring run could not be completed due to usage limits.</p>
            <p><a href="' . esc_url( $dashboard_url ) . '">Manage your prompts</a> | <a href="' . esc_url( $upgrade_url ) . '">Upgrade your plan</a></p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Format answer text for email display using WordPress core functions.
     */
    private function format_answer_for_email( string $answer ): string {
        if ( empty( $answer ) ) {
            return '<em>No answer received</em>';
        }

        // Convert markdown-style formatting to HTML
        $formatted = $answer;
        
        // Convert markdown tables to HTML tables (do this first to avoid conflicts)
        $formatted = $this->convert_markdown_tables( $formatted );
        
        // Convert markdown lists to HTML lists (do this after tables to avoid conflicts)
        $formatted = $this->convert_markdown_lists( $formatted );
        
        // Convert markdown links to HTML links
        $formatted = $this->convert_markdown_links( $formatted );
        
        // Debug: Log the original and formatted result to see what's being generated
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'LLMVM Email Original: ' . substr( $answer, 0, 500 ) );
            error_log( 'LLMVM Email Formatted: ' . substr( $formatted, 0, 500 ) );
        }
        
        
        // Convert **bold** to <strong> (non-greedy match)
        $formatted = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $formatted );
        
        // Convert *italic* to <em> (non-greedy match, but not if it's part of **)
        $formatted = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $formatted );
        
        // Convert `code` to <code> (non-greedy match)
        $formatted = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $formatted );
        
        // Convert headings (must be before wpautop)
        $formatted = preg_replace( '/^#### (.+)$/m', '<h4>$1</h4>', $formatted );
        $formatted = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $formatted );
        $formatted = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $formatted );
        $formatted = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $formatted );
        
        // Handle alternative heading formats (some LLMs use different styles)
        $formatted = preg_replace( '/^(.+)\n={3,}$/m', '<h1>$1</h1>', $formatted );
        $formatted = preg_replace( '/^(.+)\n-{3,}$/m', '<h2>$1</h2>', $formatted );
        
        // Convert URLs to clickable links first
        $formatted = make_clickable( $formatted );
        
        // Convert line breaks to HTML
        $formatted = wpautop( $formatted );
        
        // Apply WordPress texturize for smart quotes, dashes, etc.
        $formatted = wptexturize( $formatted );
        
        return $formatted;
    }

    /**
     * Convert markdown lists to HTML lists with robust handling for different LLM formats.
     */
    private function convert_markdown_lists( string $text ): string {
        // Debug: Log the input text
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'LLMVM List Input: ' . substr( $text, 0, 300 ) );
        }
        
        
        // Split into lines
        $lines = explode( "\n", $text );
        $in_list = false;
        $list_type = '';
        $result = [];
        $list_counter = 1; // For manual numbering if needed
        
        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            
            // Debug: Log each line being processed
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && preg_match( '/^\s*\d+[\.\):]/', $trimmed ) ) {
                error_log( 'LLMVM Processing line: ' . $trimmed );
            }
            
            
            // Check for unordered list items (starting with - or * or +)
            if ( preg_match( '/^[\s]*[-*+][\s]+(.+)$/', $trimmed, $matches ) ) {
                if ( ! $in_list || $list_type !== 'ul' ) {
                    if ( $in_list ) {
                        $result[] = '</' . $list_type . '>';
                    }
                    $result[] = '<ul>';
                    $in_list = true;
                    $list_type = 'ul';
                }
                $result[] = '<li>' . trim( $matches[1] ) . '</li>';
            }
            // Check for ordered list items (starting with any number followed by a dot, parenthesis, or colon)
            elseif ( preg_match( '/^[\s]*\d+[\.\):]\s*(.+)$/', $trimmed, $matches ) ) {
                if ( ! $in_list || $list_type !== 'ol' ) {
                    if ( $in_list ) {
                        $result[] = '</' . $list_type . '>';
                    }
                    $result[] = '<ol style="list-style-type: decimal; padding-left: 20px;">';
                    $in_list = true;
                    $list_type = 'ol';
                }
                // Keep the original content as-is, don't add our own numbers
                $content = trim( $matches[1] );
                $result[] = '<li style="margin: 5px 0;">' . $content . '</li>';
            }
            // Check for list items that might be missing proper formatting (fallback)
            elseif ( preg_match( '/^[\s]*(\d+)[\s]+(.+)$/', $trimmed, $matches ) && strlen( $trimmed ) > 10 ) {
                if ( ! $in_list || $list_type !== 'ol' ) {
                    if ( $in_list ) {
                        $result[] = '</' . $list_type . '>';
                    }
                    $result[] = '<ol style="list-style-type: decimal; padding-left: 20px;">';
                    $in_list = true;
                    $list_type = 'ol';
                }
                // Keep the original content as-is
                $content = trim( $matches[2] );
                $result[] = '<li style="margin: 5px 0;">' . $content . '</li>';
            }
            // Empty line - don't break the list, just add the line
            elseif ( $trimmed === '' ) {
                $result[] = $line;
            }
            // Non-list content - break the list
            else {
                if ( $in_list ) {
                    $result[] = '</' . $list_type . '>';
                    $in_list = false;
                    $list_counter = 1; // Reset counter
                }
                $result[] = $line;
            }
        }
        
        // Close any remaining list
        if ( $in_list ) {
            $result[] = '</' . $list_type . '>';
        }
        
        return implode( "\n", $result );
    }

    /**
     * Convert markdown tables to HTML tables with mobile-responsive styling.
     */
    private function convert_markdown_tables( string $text ): string {
        // Split into lines
        $lines = explode( "\n", $text );
        $result = [];
        $in_table = false;
        $table_rows = [];
        $header_row = null;
        
        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            
            // Check if this line looks like a table row (contains |)
            if ( strpos( $trimmed, '|' ) !== false && ! empty( $trimmed ) ) {
                // Check if this is a separator row (contains only |, -, :, and spaces)
                if ( preg_match( '/^[\s]*\|?[\s]*:?-+:?[\s]*(\|[\s]*:?-+:?[\s]*)*\|?[\s]*$/', $trimmed ) ) {
                    // This is a separator row, skip it
                    continue;
                }
                
                // This is a data row
                if ( ! $in_table ) {
                    $in_table = true;
                    $table_rows = [];
                }
                
                // Split by | and clean up
                $cells = array_map( 'trim', explode( '|', $trimmed ) );
                // Remove empty first/last elements if they exist (from leading/trailing |)
                if ( empty( $cells[0] ) ) {
                    array_shift( $cells );
                }
                if ( empty( $cells[count( $cells ) - 1] ) ) {
                    array_pop( $cells );
                }
                
                $table_rows[] = $cells;
            } else {
                // Not a table row
                if ( $in_table ) {
                    // Close the table
                    $result[] = $this->generate_html_table( $table_rows );
                    $in_table = false;
                    $table_rows = [];
                }
                $result[] = $line;
            }
        }
        
        // Close any remaining table
        if ( $in_table ) {
            $result[] = $this->generate_html_table( $table_rows );
        }
        
        return implode( "\n", $result );
    }

    /**
     * Generate HTML table from array of rows with mobile-responsive styling.
     */
    private function generate_html_table( array $rows ): string {
        if ( empty( $rows ) ) {
            return '';
        }
        
        $html = '<div style="overflow-x: auto; margin: 15px 0;">';
        $html .= '<table style="width: 100%; border-collapse: collapse; border: 1px solid #e9ecef; border-radius: 8px; overflow: hidden; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
        
        foreach ( $rows as $index => $row ) {
            $is_header = ( $index === 0 );
            $tag = $is_header ? 'th' : 'td';
            $style = $is_header 
                ? 'background: #f8f9fa; color: #495057; font-weight: 600; padding: 12px 8px; border-bottom: 2px solid #dee2e6; text-align: left; font-size: 13px;'
                : 'padding: 10px 8px; border-bottom: 1px solid #e9ecef; font-size: 13px; vertical-align: top;';
            
            $html .= '<tr>';
            foreach ( $row as $cell ) {
                $html .= '<' . $tag . ' style="' . $style . '">' . esc_html( $cell ) . '</' . $tag . '>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Convert markdown links to HTML links.
     * Supports format: [link text](URL)
     */
    private function convert_markdown_links( string $text ): string {
        // Pattern to match markdown links: [text](url)
        // This regex handles:
        // - [text](url)
        // - [text with spaces](url)
        // - [text](url with spaces)
        // - URLs with various protocols (http, https, ftp, etc.)
        $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';
        
        return preg_replace_callback( $pattern, function( $matches ) {
            $link_text = $matches[1];
            $url = $matches[2];
            
            // Basic URL validation and sanitization
            $url = trim( $url );
            
            // If URL doesn't start with a protocol, assume it's http
            if ( ! preg_match( '/^https?:\/\//', $url ) && ! preg_match( '/^ftp:\/\//', $url ) && ! preg_match( '/^mailto:/', $url ) ) {
                $url = 'http://' . $url;
            }
            
            // Escape the URL for HTML attributes
            $escaped_url = esc_url( $url );
            $escaped_text = esc_html( $link_text );
            
            // Return HTML link with email-friendly styling
            return '<a href="' . $escaped_url . '" style="color: #0073aa; text-decoration: underline; word-break: break-all;">' . $escaped_text . '</a>';
        }, $text );
    }

    /**
     * Get relevant prompt summaries for the email report.
     *
     * @param array $results Array of results.
     * @param object|null $user User object.
     * @return array Array of prompt summaries.
     */
    private function get_relevant_prompt_summaries( array $results, $user = null ): array {
        if ( empty( $results ) ) {
            return array();
        }

		// Extract unique prompt texts from results and check if all answers are valid
		$prompt_texts = array();
		$prompt_validity = array(); // Track if all answers for each prompt are valid
		
		foreach ( $results as $result ) {
			$prompt_text = isset( $result['prompt'] ) ? (string) $result['prompt'] : '';
			if ( ! empty( $prompt_text ) ) {
				$prompt_texts[] = $prompt_text;
				
				// Check if this result has a valid answer
				$answer = trim( $result['answer'] ?? '' );
				$is_valid = ! empty( $answer ) && $answer !== 'No answer received';
				
				// Track validity per prompt
				if ( ! isset( $prompt_validity[ $prompt_text ] ) ) {
					$prompt_validity[ $prompt_text ] = array();
				}
				$prompt_validity[ $prompt_text ][] = $is_valid;
			}
		}

		if ( empty( $prompt_texts ) ) {
			return array();
		}

		// Remove duplicates and filter to only prompts where at least some answers are valid
		$prompt_texts = array_unique( $prompt_texts );
		$valid_prompt_texts = array();
		
		foreach ( $prompt_texts as $prompt_text ) {
			$has_any_valid = in_array( true, $prompt_validity[ $prompt_text ], true );
			if ( $has_any_valid ) {
				$valid_prompt_texts[] = $prompt_text;
			}
		}
		
		// If no prompts have any valid answers, don't show any summaries
		if ( empty( $valid_prompt_texts ) ) {
			return array();
		}
		
		$prompt_texts = $valid_prompt_texts;

		// Get summaries for these prompt texts
		global $wpdb;
		$table_name = LLMVM_Database::prompt_summaries_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $prompt_texts ), '%s' ) );

		$summaries = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries.
			"SELECT * FROM {$table_name} WHERE prompt_text IN ({$placeholders}) ORDER BY completed_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table_name() returns constant string, placeholders are safely generated
			...$prompt_texts
		), ARRAY_A );

        // If user-specific report, filter by user
        if ( $user && ! current_user_can( 'llmvm_manage_settings' ) ) {
            $summaries = array_filter( $summaries, function( $summary ) use ( $user ) {
                return isset( $summary['user_id'] ) && (int) $summary['user_id'] === $user->ID;
            } );
        }

        return $summaries ? $summaries : array();
    }

    /**
     * Generate the prompt summaries section for email reports.
     *
     * @param array $summaries Array of prompt summaries.
     * @return string HTML content for the summaries section.
     */
    private function generate_prompt_summaries_section( array $summaries ): string {
        if ( empty( $summaries ) ) {
            return '';
        }

        $html = '
        <div class="summary-card">
            <h2>💬 Prompt Summaries</h2>
            <p style="margin: 0 0 15px 0; color: #6c757d; font-size: 14px;">
                AI-generated summaries of how well responses matched your expected answers.
            </p>';

        foreach ( $summaries as $summary ) {
            $prompt_text = isset( $summary['prompt_text'] ) ? (string) $summary['prompt_text'] : '';
            $expected_answer = isset( $summary['expected_answer'] ) ? (string) $summary['expected_answer'] : '';
            $comparison_summary = isset( $summary['comparison_summary'] ) ? (string) $summary['comparison_summary'] : '';
            $average_score = isset( $summary['average_score'] ) ? (float) $summary['average_score'] : null;
            $min_score = isset( $summary['min_score'] ) ? (int) $summary['min_score'] : null;
            $max_score = isset( $summary['max_score'] ) ? (int) $summary['max_score'] : null;
            $total_models = isset( $summary['total_models'] ) ? (int) $summary['total_models'] : 0;

            // Truncate prompt for display
            $display_prompt = strlen( $prompt_text ) > 100 ? substr( $prompt_text, 0, 100 ) . '...' : $prompt_text;

            $html .= '
            <div style="background: #f8f9fa; border-left: 4px solid #0073aa; padding: 15px; margin: 15px 0; border-radius: 4px;">
                <div style="margin-bottom: 10px;">
                    <strong style="color: #495057;">Prompt:</strong> ' . esc_html( $display_prompt ) . '
                </div>';

            if ( ! empty( $expected_answer ) ) {
                $display_expected = strlen( $expected_answer ) > 50 ? substr( $expected_answer, 0, 50 ) . '...' : $expected_answer;
                $html .= '
                <div style="margin-bottom: 10px; font-size: 14px; color: #6c757d;">
                    <strong>Expected:</strong> ' . esc_html( $display_expected ) . '
                </div>';
            }

            // Score statistics
            if ( $average_score !== null ) {
                $score_color = $average_score >= 8 ? '#28a745' : ( $average_score >= 6 ? '#ffc107' : '#dc3545' );
                $html .= '
                <div style="margin-bottom: 10px; font-size: 14px;">
                    <span style="background: ' . $score_color . '; color: ' . ( $average_score >= 6 && $average_score < 8 ? '#000' : '#fff' ) . '; padding: 2px 6px; border-radius: 3px; font-weight: bold;">
                        ' . esc_html( (string) $average_score ) . '/10 avg
                    </span>';

                if ( $total_models > 1 ) {
                    $html .= ' <span style="color: #6c757d; font-size: 12px;">
                        (Range: ' . esc_html( (string) $min_score ) . '-' . esc_html( (string) $max_score ) . ', ' . esc_html( (string) $total_models ) . ' models)
                    </span>';
                }

                $html .= '</div>';
            }

            // Summary text
            if ( ! empty( $comparison_summary ) ) {
                $html .= '
                <div style="font-style: italic; color: #495057; line-height: 1.5;">
                    ' . esc_html( $comparison_summary ) . '
                </div>';
            }

            $html .= '</div>';
        }

        // Add scoring legend
        $html .= '
        <div style="margin-top: 20px; padding: 12px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px;">
            <h4 style="margin: 0 0 8px 0; font-size: 13px; color: #495057;">📊 Scoring Legend</h4>
            <div style="font-size: 11px; color: #6c757d; line-height: 1.4;">
                <strong>0:</strong> Expected answer not mentioned at all<br>
                <strong>1-3:</strong> Expected answer mentioned briefly or incorrectly<br>
                <strong>4-7:</strong> Expected answer mentioned correctly but not prominently<br>
                <strong>8-10:</strong> Expected answer mentioned correctly and prominently
            </div>
        </div>';

        $html .= '</div>';

        return $html;
    }
}