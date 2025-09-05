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
        .wp-list-table .column-answer {
            margin-top: 0 !important;
            padding-top: 8px !important; /* Match other columns */
        }
        .wp-list-table .column-answer .answer-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .wp-list-table .column-answer .answer-content p:first-child {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        .wp-list-table .column-answer div,
        .wp-list-table .column-answer p,
        .wp-list-table .column-answer ol,
        .wp-list-table .column-answer ul,
        .wp-list-table .column-answer li {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
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
                        <span><?php echo esc_html__( 'Date (UTC)', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'created_at', $current_orderby, $current_order ) ); ?>
                    </a>
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
                    <td colspan="<?php echo current_user_can( 'llmvm_manage_settings' ) ? '6' : '5'; ?>"><?php echo esc_html__( 'No results yet.', 'llm-visibility-monitor' ); ?></td>
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
                            <?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?>
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
                        <span><?php echo esc_html__( 'Date (UTC)', 'llm-visibility-monitor' ); ?></span>
                        <?php echo wp_kses_post( llmvm_get_sort_indicator( 'created_at', $current_orderby, $current_order ) ); ?>
                    </a>
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


