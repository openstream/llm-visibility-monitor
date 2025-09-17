<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current sorting parameters
$current_orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
$current_order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

// Helper function to generate sort URL
function llmvm_get_sort_url( $orderby, $order = 'DESC' ) {
    $url = add_query_arg( [ 'orderby' => $orderby, 'order' => $order ], admin_url( 'tools.php?page=llmvm-dashboard' ) );
    return esc_url( $url );
}

// Helper function to get sort indicator
function llmvm_get_sort_indicator( $column, $current_orderby, $current_order ) {
    if ( $column === $current_orderby ) {
        $indicator = 'asc' === strtolower( $current_order ) ? '↑' : '↓';
        return ' <span class="sorting-indicator">' . $indicator . '</span>';
    }
    return '';
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__( 'LLM Visibility Dashboard', 'llm-visibility-monitor' ); ?></h1>
    
    <hr class="wp-header-end">

    <style>
        .wp-list-table .column-answer {
            width: 55%;
            word-wrap: break-word;
        }
        .wp-list-table .column-score {
            width: 80px;
            text-align: center;
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
        .wp-list-table .column-prompt {
            width: 20%;
        }
        .wp-list-table .column-model {
            width: 18%;
            white-space: nowrap;
        }
        .wp-list-table .column-date {
            width: 18%;
            white-space: nowrap;
        }
        .wp-list-table .column-user {
            width: 12%;
        }
        .wp-list-table td {
            vertical-align: top !important;
            padding: 8px 10px;
            margin: 0 !important;
        }
        .wp-list-table th {
            padding: 8px 10px;
            vertical-align: top !important;
            margin: 0 !important;
        }
        /* Reset any default margins on content inside cells */
        .wp-list-table td * {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }
        .wp-list-table td p {
            margin: 0 !important;
        }
        /* Ensure all text content starts at the same baseline */
        .wp-list-table td {
            line-height: 1.4 !important;
        }
        /* Force consistent text positioning */
        .wp-list-table td,
        .wp-list-table td * {
            vertical-align: baseline !important;
        }
        /* Specific fix for answer column with nested content */
        .results-table .answer-col {
            margin-top: 0 !important;
            padding-top: 8px !important; /* Match other columns */
        }
        .results-table .answer-col .answer-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .results-table .answer-col .answer-content p:first-child {
            margin-top: 0 !important;
            margin-block-start: 0 !important;
            padding-top: 0 !important;
        }
        /* Apply margin-top: 0 to all paragraphs in answer column */
        .results-table .answer-col p {
            margin-top: 0 !important;
        }
        .results-table .answer-col div,
        .results-table .answer-col p,
        .results-table .answer-col ol,
        .results-table .answer-col ul,
        .results-table .answer-col li {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            margin-block-start: 0 !important;
            margin-block-end: 0 !important;
        }
        /* Force table to use full width and be wider */
        .wp-list-table {
            width: 100% !important;
            max-width: none !important;
            table-layout: fixed !important;
        }
        /* Make it even wider on desktop */
        @media (min-width: 768px) {
            .wp-list-table {
                width: 100% !important;
                min-width: 2000px !important;
            }
        }
        @media (min-width: 1200px) {
            .wp-list-table {
                width: 100% !important;
                min-width: 2200px !important;
            }
        }
        /* Ensure the container doesn't limit width */
        .wrap {
            max-width: none !important;
        }
    </style>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="llmvm-bulk-actions-form">
        <?php wp_nonce_field( 'llmvm_bulk_delete_results' ); ?>
        <input type="hidden" name="action" value="llmvm_bulk_delete_results" />
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-selector-top">
                    <option value="-1"><?php echo esc_html__( 'Bulk Actions', 'llm-visibility-monitor' ); ?></option>
                    <option value="delete"><?php echo esc_html__( 'Delete', 'llm-visibility-monitor' ); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php echo esc_attr__( 'Apply', 'llm-visibility-monitor' ); ?>" />
            </div>
            
            <div class="alignleft actions">
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_export_csv' ), 'llmvm_export_csv' ) ); ?>">
                    <?php echo esc_html__( 'Export CSV', 'llm-visibility-monitor' ); ?>
                </a>
                <a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=llmvm-prompts' ) ); ?>">
                    <?php echo esc_html__( 'Manage Prompts', 'llm-visibility-monitor' ); ?>
                </a>
            </div>
            
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php 
                    printf(
                        /* translators: %d: number of results */
                        esc_html( _n( '%d result', '%d results', $total_results, 'llm-visibility-monitor' ) ),
                        esc_html( $total_results )
                    ); 
                    ?>
                </span>
            </div>
        </div>

    <?php
    // Check for run completion message with proper sanitization and nonce verification
    $run_completed = '';
    if ( isset( $_GET['llmvm_ran'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'llmvm_run_completed' ) ) {
        $run_completed = sanitize_text_field( wp_unslash( $_GET['llmvm_ran'] ) );
    }
    if ( '1' === $run_completed ) :
    ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Run completed. Latest responses are shown below.', 'llm-visibility-monitor' ) ?></p></div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped posts">
        <thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="cb-select-all-1" />
                </td>
                <th scope="col" class="manage-column column-title sortable <?php echo esc_attr( $current_orderby === 'prompt' ? strtolower( $current_order ) : '' ); ?>">
                    <a href="<?php echo esc_url( llmvm_get_sort_url( 'prompt', $current_orderby === 'prompt' && $current_order === 'DESC' ? 'ASC' : 'DESC' ) ); ?>">
                        <span><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'prompt', $current_orderby, $current_order ) ); ?>
                    </a>
                </th>
                <th scope="col" class="manage-column column-model sortable <?php echo esc_attr( $current_orderby === 'model' ? strtolower( $current_order ) : '' ); ?>">
                    <a href="<?php echo esc_url( llmvm_get_sort_url( 'model', $current_orderby === 'model' && $current_order === 'DESC' ? 'ASC' : 'DESC' ) ); ?>">
                        <span><?php echo esc_html__( 'Model', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'model', $current_orderby, $current_order ) ); ?>
                    </a>
                </th>
                <th scope="col" class="manage-column column-date sortable <?php echo esc_attr( $current_orderby === 'created_at' ? strtolower( $current_order ) : '' ); ?>">
                    <a href="<?php echo esc_url( llmvm_get_sort_url( 'created_at', $current_orderby === 'created_at' && $current_order === 'DESC' ? 'ASC' : 'DESC' ) ); ?>">
                        <span><?php echo esc_html__( 'Date', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'created_at', $current_orderby, $current_order ) ); ?>
                    </a>
                </th>
                <th scope="col" class="manage-column column-score">
                    <span><?php echo esc_html__( 'Score', 'llm-visibility-monitor' ); ?></span>
                </th>
                <?php if ( current_user_can( 'llmvm_manage_settings' ) ) : ?>
                <th scope="col" class="manage-column column-user sortable <?php echo esc_attr( $current_orderby === 'user_id' ? strtolower( $current_order ) : '' ); ?>">
                    <a href="<?php echo esc_url( llmvm_get_sort_url( 'user_id', $current_orderby === 'user_id' && $current_order === 'DESC' ? 'ASC' : 'DESC' ) ); ?>">
                        <span><?php echo esc_html__( 'User', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'user_id', $current_orderby, $current_order ) ); ?>
                    </a>
                </th>
                <?php endif; ?>
                <th scope="col" class="manage-column column-answer">
                    <?php echo esc_html__( 'Answer', 'llm-visibility-monitor' ); ?>
                </th>
            </tr>
        </thead>

        <tbody id="the-list">
            <?php if ( empty( $results ) ) : ?>
                <tr>
                    <td colspan="<?php echo current_user_can( 'llmvm_manage_settings' ) ? '7' : '6'; ?>"><?php echo esc_html__( 'No results yet.', 'llm-visibility-monitor' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $results as $row ) : ?>
                    <tr id="result-<?php echo esc_attr( (string) ( $row['id'] ?? '' ) ); ?>" class="result-row">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="result_ids[]" value="<?php echo esc_attr( (string) ( $row['id'] ?? '' ) ); ?>" />
                        </th>
                        <td class="title column-title has-row-actions column-primary">
                            <strong>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'llmvm-result', 'id' => (int) ( $row['id'] ?? 0 ) ], admin_url( 'tools.php' ) ), 'llmvm_view_result' ) ); ?>" class="row-title">
                                    <?php echo esc_html( wp_trim_words( (string) ( $row['prompt'] ?? '' ), 24 ) ?: '' ); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'llmvm-result', 'id' => (int) ( $row['id'] ?? 0 ) ], admin_url( 'tools.php' ) ), 'llmvm_view_result' ) ); ?>">
                                        <?php echo esc_html__( 'View', 'llm-visibility-monitor' ); ?>
                                    </a> |
                                </span>
                                <span class="delete">
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'llmvm_delete_result', 'id' => (int) ( $row['id'] ?? 0 ) ], admin_url( 'admin-post.php' ) ), 'llmvm_delete_result' ) ); ?>" class="submitdelete" 
                                       onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this result?', 'llm-visibility-monitor' ) ); ?>');">
                                        <?php echo esc_html__( 'Delete', 'llm-visibility-monitor' ); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="model column-model">
                            <?php echo esc_html( (string) ( $row['model'] ?? '' ) ); ?>
                        </td>
                        <td class="date column-date">
                            <?php echo esc_html( LLMVM_Admin::convert_utc_to_user_timezone( $row['created_at'] ?? '' ) ); ?>
                        </td>
                        <td class="score column-score">
                            <?php 
                            $comparison_score = isset( $row['comparison_score'] ) ? (int) $row['comparison_score'] : null;
                            if ( $comparison_score !== null ) {
                                $score_class = $comparison_score >= 8 ? 'score-high' : ( $comparison_score >= 6 ? 'score-medium' : 'score-low' );
                                echo '<span class="score-badge ' . esc_attr( $score_class ) . '">' . esc_html( (string) $comparison_score ) . '/10</span>';
                            } else {
                                echo '<span class="score-badge score-none">N/A</span>';
                            }
                            ?>
                        </td>
                        <?php if ( current_user_can( 'llmvm_manage_settings' ) ) : ?>
                        <td class="user column-user">
                            <?php 
                            $user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
                            if ( $user_id > 0 ) {
                                $user = get_user_by( 'id', $user_id );
                                if ( $user ) {
                                    echo esc_html( $user->display_name );
                                } else {
                                    /* translators: %d: user ID */
                                    echo esc_html( sprintf( __( 'User %d', 'llm-visibility-monitor' ), $user_id ) );
                                }
                            } else {
                                echo esc_html( __( 'Unknown', 'llm-visibility-monitor' ) );
                            }
                            ?>
                        </td>
                        <?php endif; ?>
                        <td class="answer column-answer">
                            <?php
                            $answer = (string) ( $row['answer'] ?? '' );
                            if ( '' === trim( $answer ) ) {
                                echo '<em>' . esc_html__( 'No answer (see logs for details)', 'llm-visibility-monitor' ) . '</em>';
                            } else {
                                
                                echo esc_html( wp_trim_words( $answer, 36 ) ?: '' );
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>

        <tfoot>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="cb-select-all-2" />
                </td>
                <th scope="col" class="manage-column column-title sortable <?php echo esc_attr( $current_orderby === 'prompt' ? strtolower( $current_order ) : '' ); ?>">
                    <a href="<?php echo esc_url( llmvm_get_sort_url( 'prompt', $current_orderby === 'prompt' && $current_order === 'DESC' ? 'ASC' : 'DESC' ) ); ?>">
                        <span><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'prompt', $current_orderby, $current_order ) ); ?>
                    </a>
                </th>
                <th scope="col" class="manage-column column-model sortable <?php echo esc_attr( $current_orderby === 'model' ? strtolower( $current_order ) : '' ); ?>">
                    <a href="<?php echo esc_url( llmvm_get_sort_url( 'model', $current_orderby === 'model' && $current_order === 'DESC' ? 'ASC' : 'DESC' ) ); ?>">
                        <span><?php echo esc_html__( 'Model', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'model', $current_orderby, $current_order ) ); ?>
                    </a>
                </th>
                <th scope="col" class="manage-column column-date sortable <?php echo esc_attr( $current_orderby === 'created_at' ? strtolower( $current_order ) : '' ); ?>">
                    <a href="<?php echo esc_url( llmvm_get_sort_url( 'created_at', $current_orderby === 'created_at' && $current_order === 'DESC' ? 'ASC' : 'DESC' ) ); ?>">
                        <span><?php echo esc_html__( 'Date', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'created_at', $current_orderby, $current_order ) ); ?>
                    </a>
                </th>
                <th scope="col" class="manage-column column-score">
                    <span><?php echo esc_html__( 'Score', 'llm-visibility-monitor' ); ?></span>
                </th>
                <?php if ( current_user_can( 'llmvm_manage_settings' ) ) : ?>
                <th scope="col" class="manage-column column-user sortable <?php echo esc_attr( $current_orderby === 'user_id' ? strtolower( $current_order ) : '' ); ?>">
                    <a href="<?php echo esc_url( llmvm_get_sort_url( 'user_id', $current_orderby === 'user_id' && $current_order === 'DESC' ? 'ASC' : 'DESC' ) ); ?>">
                        <span><?php echo esc_html__( 'User', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'user_id', $current_orderby, $current_order ) ); ?>
                    </a>
                </th>
                <?php endif; ?>
                <th scope="col" class="manage-column column-answer">
                    <?php echo esc_html__( 'Answer', 'llm-visibility-monitor' ); ?>
                </th>
            </tr>
        </tfoot>
    </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-selector-bottom">
                    <option value="-1"><?php echo esc_html__( 'Bulk Actions', 'llm-visibility-monitor' ); ?></option>
                    <option value="delete"><?php echo esc_html__( 'Delete', 'llm-visibility-monitor' ); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php echo esc_attr__( 'Apply', 'llm-visibility-monitor' ); ?>" />
            </div>
            
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php 
                    printf(
                        /* translators: %d: number of results */
                        esc_html( _n( '%d result', '%d results', $total_results, 'llm-visibility-monitor' ) ),
                        esc_html( $total_results )
                    ); 
                    ?>
                </span>
            </div>
        </div>
    </form>
</div>

<?php
// Display prompt summaries section
$prompt_summaries = LLMVM_Database::get_latest_prompt_summaries( 0, 5 );
if ( ! empty( $prompt_summaries ) ) :
?>
<div class="wrap" style="margin-top: 30px;">
    <h2><?php echo esc_html__( '💬 Prompt Summaries', 'llm-visibility-monitor' ); ?></h2>
    <p style="color: #666; margin-bottom: 20px;">
        <?php echo esc_html__( 'AI-generated summaries of how well responses matched expected answers.', 'llm-visibility-monitor' ); ?>
    </p>
    
    <div class="prompt-summaries-container">
        <?php foreach ( $prompt_summaries as $summary ) : ?>
            <?php
            $prompt_text = isset( $summary['prompt_text'] ) ? (string) $summary['prompt_text'] : '';
            $expected_answer = isset( $summary['expected_answer'] ) ? (string) $summary['expected_answer'] : '';
            $comparison_summary = isset( $summary['comparison_summary'] ) ? (string) $summary['comparison_summary'] : '';
            $average_score = isset( $summary['average_score'] ) ? (float) $summary['average_score'] : null;
            $min_score = isset( $summary['min_score'] ) ? (int) $summary['min_score'] : null;
            $max_score = isset( $summary['max_score'] ) ? (int) $summary['max_score'] : null;
            $total_models = isset( $summary['total_models'] ) ? (int) $summary['total_models'] : 0;
            $completed_at = isset( $summary['completed_at'] ) ? (string) $summary['completed_at'] : '';
            
            // Truncate prompt for display
            $display_prompt = strlen( $prompt_text ) > 100 ? substr( $prompt_text, 0, 100 ) . '...' : $prompt_text;
            $display_expected = strlen( $expected_answer ) > 50 ? substr( $expected_answer, 0, 50 ) . '...' : $expected_answer;
            ?>
            <div class="summary-card" style="background: #f8f9fa; border-left: 4px solid #0073aa; padding: 15px; margin: 15px 0; border-radius: 4px;">
                <div style="margin-bottom: 10px;">
                    <strong style="color: #495057;"><?php echo esc_html__( 'Prompt:', 'llm-visibility-monitor' ); ?></strong> 
                    <?php echo esc_html( $display_prompt ); ?>
                </div>
                
                <?php if ( ! empty( $expected_answer ) ) : ?>
                <div style="margin-bottom: 10px; font-size: 14px; color: #6c757d;">
                    <strong><?php echo esc_html__( 'Expected:', 'llm-visibility-monitor' ); ?></strong> 
                    <?php echo esc_html( $display_expected ); ?>
                </div>
                <?php endif; ?>
                
                <?php if ( $average_score !== null ) : ?>
                    <?php
                    $score_color = $average_score >= 8 ? '#28a745' : ( $average_score >= 6 ? '#ffc107' : '#dc3545' );
                    $text_color = $average_score >= 6 && $average_score < 8 ? '#000' : '#fff';
                    ?>
                    <div style="margin-bottom: 10px; font-size: 14px;">
                        <span style="background: <?php echo esc_attr( $score_color ); ?>; color: <?php echo esc_attr( $text_color ); ?>; padding: 2px 6px; border-radius: 3px; font-weight: bold;">
                            <?php echo esc_html( (string) $average_score ); ?>/10 <?php echo esc_html__( 'avg', 'llm-visibility-monitor' ); ?>
                        </span>
                        
                        <?php if ( $total_models > 1 ) : ?>
                            <span style="color: #6c757d; font-size: 12px;">
                                (<?php echo esc_html__( 'Range:', 'llm-visibility-monitor' ); ?> <?php echo esc_html( (string) $min_score ); ?>-<?php echo esc_html( (string) $max_score ); ?>, <?php echo esc_html( (string) $total_models ); ?> <?php echo esc_html__( 'models', 'llm-visibility-monitor' ); ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ( ! empty( $comparison_summary ) ) : ?>
                <div style="font-style: italic; color: #495057; line-height: 1.5; margin-bottom: 10px;">
                    <?php echo esc_html( $comparison_summary ); ?>
                </div>
                <?php endif; ?>
                
                <div style="font-size: 12px; color: #6c757d;">
                    <?php echo esc_html__( 'Completed:', 'llm-visibility-monitor' ); ?> 
                    <?php echo esc_html( LLMVM_Admin::convert_utc_to_user_timezone( $completed_at, $summary['user_id'] ?? null ) ); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Scoring Legend -->
    <div style="margin-top: 20px; padding: 12px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px;">
        <h4 style="margin: 0 0 8px 0; font-size: 13px; color: #495057;">📊 <?php echo esc_html__( 'Scoring Legend', 'llm-visibility-monitor' ); ?></h4>
        <div style="font-size: 11px; color: #6c757d; line-height: 1.4;">
            <strong>0:</strong> <?php echo esc_html__( 'Expected answer not mentioned at all', 'llm-visibility-monitor' ); ?><br>
            <strong>1-3:</strong> <?php echo esc_html__( 'Expected answer mentioned briefly or incorrectly', 'llm-visibility-monitor' ); ?><br>
            <strong>4-7:</strong> <?php echo esc_html__( 'Expected answer mentioned correctly but not prominently', 'llm-visibility-monitor' ); ?><br>
            <strong>8-10:</strong> <?php echo esc_html__( 'Expected answer mentioned correctly and prominently', 'llm-visibility-monitor' ); ?>
        </div>
    </div>
</div>
<?php endif; ?>


