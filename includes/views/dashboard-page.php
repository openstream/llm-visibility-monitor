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
                <a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=llmvm-settings' ) ); ?>">
                    <?php echo esc_html__( 'Manage Prompts', 'llm-visibility-monitor' ); ?>
                </a>
                <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_run_now' ), 'llmvm_run_now' ) ); ?>">
                    <?php echo esc_html__( 'Run Now', 'llm-visibility-monitor' ); ?>
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
                <th scope="col" class="manage-column column-answer">
                    <?php echo esc_html__( 'Answer', 'llm-visibility-monitor' ); ?>
                </th>
            </tr>
        </thead>

        <tbody id="the-list">
            <?php if ( empty( $results ) ) : ?>
                <tr>
                    <td colspan="5"><?php echo esc_html__( 'No results yet.', 'llm-visibility-monitor' ); ?></td>
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


