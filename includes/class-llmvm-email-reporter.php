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
     * Send email report after a cron run completes.
     */
    public function send_report_after_run( int $user_id = 0, array $user_results = [] ): void {
        // Email reporter started
        
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
        
        // If no user results provided, fall back to old behavior (for backward compatibility)
        if ( empty( $user_results ) ) {
            $user_results = LLMVM_Database::get_latest_results( 10 );
        }
        
        if ( empty( $user_results ) ) {
            LLMVM_Logger::log( 'Email report: no results found, skipping' );
            return;
        }

        // Determine recipient and results based on user role
        $current_user = get_user_by( 'id', $user_id );
        $is_admin = $current_user && current_user_can( 'llmvm_manage_settings', $user_id );
        
        if ( $is_admin ) {
            // Admin gets all results sent to admin email
            $recipient_email = get_option( 'admin_email' );
            $results_to_send = LLMVM_Database::get_latest_results( 10, 'created_at', 'DESC', 0, 0 ); // All results
            $email_type = 'admin';
        } else {
            // Regular user gets only their results sent to their email
            $recipient_email = $current_user ? $current_user->user_email : '';
            $results_to_send = $user_results; // Only user's results
            $email_type = 'user';
        }
        
        if ( empty( $recipient_email ) ) {
            LLMVM_Logger::log( 'Email report failed: no recipient email found', [ 'user_id' => $user_id, 'email_type' => $email_type ] );
            return;
        }

        // Prepare email content
        $subject = sprintf( '[%s] LLM Visibility Monitor Report', get_bloginfo( 'name' ) );
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
        foreach ( $results as $result ) {
            $answer = isset( $result['answer'] ) ? (string) $result['answer'] : '';
            if ( '' === trim( $answer ) || strpos( $answer, 'No answer' ) !== false ) {
                $error_count++;
            } else {
                $success_count++;
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
            max-width: 600px;
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
            border-collapse: collapse;
            margin: 20px 0;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .results-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 15px 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }
        
        .results-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
            font-size: 14px;
        }
        
        .results-table tr:last-child td {
            border-bottom: none;
        }
        
        .results-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Column widths for desktop - optimized for content */
        .date-col { width: 12%; min-width: 100px; }
        .prompt-col { width: 25%; min-width: 180px; }
        .model-col { width: 18%; min-width: 140px; }
        .answer-col { width: 45%; }
        .user-col { width: 10%; min-width: 80px; }
        
        /* Mobile responsive styles */
        @media only screen and (max-width: 600px) {
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
            
            /* Mobile table options */
            .results-section {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .results-table {
                min-width: 500px;
                font-size: 13px;
            }
            
            .results-table th,
            .results-table td {
                padding: 10px 8px;
            }
            
            /* Alternative: Stack table columns on very small screens */
            @media only screen and (max-width: 480px) {
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
                    padding: 15px;
                    background: #ffffff;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                
                .results-table td {
                    border: none;
                    position: relative;
                    padding: 8px 0;
                    padding-left: 35%;
                    font-size: 14px;
                }
                
                .results-table td:before {
                    content: attr(data-label) ": ";
                    position: absolute;
                    left: 6px;
                    width: 30%;
                    padding-right: 10px;
                    white-space: nowrap;
                    font-weight: 600;
                    color: #495057;
                    font-size: 12px;
                }
                
                .answer-content {
                    max-height: 200px;
                    overflow-y: auto;
                    border: 1px solid #e9ecef;
                    border-radius: 4px;
                    padding: 10px;
                    background: #f8f9fa;
                }
            }
        }
        
        /* Content formatting */
        .answer-content {
            line-height: 1.6;
        }
        
        .answer-content h1, .answer-content h2, .answer-content h3 {
            color: #495057;
            margin: 15px 0 10px 0;
            font-weight: 600;
        }
        
        .answer-content h2 {
            font-size: 18px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 5px;
        }
        
        .answer-content h3 {
            font-size: 16px;
        }
        
        .answer-content ul, .answer-content ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .answer-content li {
            margin: 5px 0;
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
            $html .= '<p>Report for: ' . esc_html( $user->display_name ) . '</p>';
        } elseif ( $email_type === 'admin' ) {
            $html .= '<p>Administrator Report (All Users)</p>';
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
                </div>
            </div>';

        if ( ! empty( $results ) ) {
            $html .= '
            <div class="results-section">
                <h2>📋 Latest Results</h2>
                <p style="font-size: 12px; color: #6c757d; margin: 0 0 15px 0; font-style: italic;">💡 On mobile devices, you can scroll horizontally to view the full table.</p>
                <table class="results-table">
                    <thead>
                        <tr>';
                
            if ( $email_type === 'admin' ) {
                $html .= '<th class="user-col">User</th>';
            }
            
            $html .= '<th class="date-col">Date (UTC)</th>
                            <th class="prompt-col">Prompt</th>
                            <th class="model-col">Model</th>
                            <th class="answer-col">Answer</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ( $results as $result ) {
                $date = isset( $result['created_at'] ) ? (string) $result['created_at'] : '';
                $prompt = isset( $result['prompt'] ) ? (string) $result['prompt'] : '';
                $model = isset( $result['model'] ) ? (string) $result['model'] : '';
                $answer = isset( $result['answer'] ) ? (string) $result['answer'] : '';
                $result_user_id = isset( $result['user_id'] ) ? (int) $result['user_id'] : 0;

                // Format the answer with enhanced formatting
                $formatted_answer = $this->format_answer_for_email( $answer );
                $is_error = ( '' === trim( $answer ) || strpos( $answer, 'No answer' ) !== false );

                $html .= '
                        <tr>';
                
                if ( $email_type === 'admin' ) {
                    $result_user = get_user_by( 'id', $result_user_id );
                    $user_display = $result_user ? $result_user->display_name : 'Unknown User';
                    $html .= '<td class="user-col" data-label="User">' . esc_html( $user_display ) . '</td>';
                }
                
                $html .= '<td class="date-col" data-label="Date">' . esc_html( $date ) . '</td>
                            <td class="prompt-col" data-label="Prompt">' . esc_html( $prompt ) . '</td>
                            <td class="model-col" data-label="Model">' . esc_html( $model ) . '</td>
                            <td class="answer-col" data-label="Answer">
                                <div class="answer-content">' . $formatted_answer . '</div>
                            </td>
                        </tr>';
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
     * Format answer text for email display using WordPress core functions.
     */
    private function format_answer_for_email( string $answer ): string {
        if ( empty( $answer ) ) {
            return '<em>No answer received</em>';
        }

        // Convert markdown-style formatting to HTML
        $formatted = $answer;
        
        // Convert markdown lists to HTML lists
        $formatted = $this->convert_markdown_lists( $formatted );
        
        // Convert **bold** to <strong> (non-greedy match)
        $formatted = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $formatted );
        
        // Convert *italic* to <em> (non-greedy match, but not if it's part of **)
        $formatted = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $formatted );
        
        // Convert `code` to <code> (non-greedy match)
        $formatted = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $formatted );
        
        // Convert ## headings to <h2>
        $formatted = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $formatted );
        
        // Convert ### headings to <h3>
        $formatted = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $formatted );
        
        // Convert URLs to clickable links first
        $formatted = make_clickable( $formatted );
        
        // Convert line breaks to HTML
        $formatted = wpautop( $formatted );
        
        // Apply WordPress texturize for smart quotes, dashes, etc.
        $formatted = wptexturize( $formatted );
        
        return $formatted;
    }

    /**
     * Convert markdown lists to HTML lists.
     */
    private function convert_markdown_lists( string $text ): string {
        // Split into lines
        $lines = explode( "\n", $text );
        $in_list = false;
        $list_type = '';
        $result = [];
        
        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            
            // Check for unordered list items (starting with - or *)
            if ( preg_match( '/^[\s]*[-*][\s]+(.+)$/', $trimmed, $matches ) ) {
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
            // Check for ordered list items (starting with 1. 2. etc.)
            elseif ( preg_match( '/^[\s]*(\d+)\.\s+(.+)$/', $trimmed, $matches ) ) {
                if ( ! $in_list || $list_type !== 'ol' ) {
                    if ( $in_list ) {
                        $result[] = '</' . $list_type . '>';
                    }
                    $result[] = '<ol>';
                    $in_list = true;
                    $list_type = 'ol';
                }
                $result[] = '<li>' . trim( $matches[2] ) . '</li>';
            }
            // Empty line or non-list content
            else {
                if ( $in_list ) {
                    $result[] = '</' . $list_type . '>';
                    $in_list = false;
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
}