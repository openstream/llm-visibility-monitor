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
     * Generate HTML email report.
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
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #0073aa; color: white; padding: 20px; }
        .content { padding: 20px; }
        .summary { background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
        ul, ol { margin: 10px 0; padding-left: 20px; }
        li { margin: 5px 0; }
        ol { list-style-type: decimal; }
        ol li { display: list-item; }
        th { background: #f5f5f5; font-weight: bold; color: #333; }
        .success { color: #333; }
        .error { color: #dc3545; }
        .prompt-cell { width: 15% !important; max-width: 200px !important; word-wrap: break-word; }
        .model-cell { width: 10% !important; max-width: 150px !important; word-wrap: break-word; }
        .answer-cell { width: 45% !important; max-width: 500px !important; word-wrap: break-word; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LLM Visibility Monitor Report</h1>
        <p>Generated on ' . current_time( 'Y-m-d H:i:s' ) . '</p>';
        
        if ( $email_type === 'user' && $user ) {
            $html .= '<p>Report for: ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</p>';
        } elseif ( $email_type === 'admin' ) {
            $html .= '<p>Administrator Report (All Users)</p>';
        }
        
        $html .= '</div>
    
    <div class="content">
        <div class="summary">
            <h2>Summary</h2>';
            
        if ( $email_type === 'user' && $user ) {
            $html .= '<p><strong>User:</strong> ' . esc_html( $user->display_name ) . '</p>';
        }
        
        $html .= '<p><strong>Total Results:</strong> ' . esc_html( (string) $total_results ) . '</p>
            <p><strong>Successful Responses:</strong> <span class="success">' . esc_html( (string) $success_count ) . '</span></p>
            <p><strong>Errors:</strong> <span class="error">' . esc_html( (string) $error_count ) . '</span></p>
        </div>';

        if ( ! empty( $results ) ) {
            $html .= '
        <h2>Latest Results</h2>
        <table>
            <thead>
                <tr>';
                
            if ( $email_type === 'admin' ) {
                $html .= '<th>User</th>';
            }
            
            $html .= '<th>Date (UTC)</th>
                    <th>Prompt</th>
                    <th>Model</th>
                    <th>Answer</th>
                </tr>
            </thead>
            <tbody>';

            foreach ( $results as $result ) {
                $date = isset( $result['created_at'] ) ? (string) $result['created_at'] : '';
                $prompt = isset( $result['prompt'] ) ? (string) $result['prompt'] : '';
                $model = isset( $result['model'] ) ? (string) $result['model'] : '';
                $answer = isset( $result['answer'] ) ? (string) $result['answer'] : '';
                $result_user_id = isset( $result['user_id'] ) ? (int) $result['user_id'] : 0;

                // Format the answer with WordPress functions
                $formatted_answer = $this->format_answer_for_email( $answer );

                $row_class = ( '' === trim( $answer ) || strpos( $answer, 'No answer' ) !== false ) ? 'error' : 'success';

                $html .= '
                <tr class="' . esc_attr( $row_class ) . '">';
                
                if ( $email_type === 'admin' ) {
                    $result_user = get_user_by( 'id', $result_user_id );
                    $user_display = $result_user ? $result_user->display_name : 'Unknown User';
                    $html .= '<td>' . esc_html( $user_display ) . '</td>';
                }
                
                $html .= '<td>' . esc_html( $date ) . '</td>
                    <td class="prompt-cell">' . esc_html( $prompt ) . '</td>
                    <td class="model-cell">' . esc_html( $model ) . '</td>
                    <td class="answer-cell">' . $formatted_answer . '</td>
                </tr>';
            }

            $html .= '
            </tbody>
        </table>';
        }

        $html .= '
        <div class="footer">
            <p>This report was automatically generated by the LLM Visibility Monitor plugin.</p>
            <p>To view full results, visit: <a href="' . admin_url( 'tools.php?page=llmvm-dashboard' ) . '">Dashboard</a></p>
            <p>To disable email reports, go to Settings → LLM Visibility Monitor.</p>
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
        
        // Close any open list
        if ( $in_list ) {
            $result[] = '</' . $list_type . '>';
        }
        
        return implode( "\n", $result );
    }
}
