<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'LLM Visibility Dashboard', 'llm-visibility-monitor' ); ?></h1>

    <p>
        <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_export_csv' ), 'llmvm_export_csv' ) ); ?>">
            <?php echo esc_html__( 'Export CSV', 'llm-visibility-monitor' ); ?>
        </a>
        <a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=llmvm-settings' ) ); ?>" style="margin-left:8px;">
            <?php echo esc_html__( 'Manage Prompts', 'llm-visibility-monitor' ); ?>
        </a>
        <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_run_now' ), 'llmvm_run_now' ) ); ?>" style="margin-left:8px;">
            <?php echo esc_html__( 'Run Now', 'llm-visibility-monitor' ); ?>
        </a>
    </p>

    <?php
    // Check for run completion message with proper sanitization.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is just a display flag, not a form submission.
    $run_completed = isset( $_GET['llmvm_ran'] ) ? sanitize_text_field( wp_unslash( $_GET['llmvm_ran'] ) ) : '';
    if ( '1' === $run_completed ) :
    ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Run completed. Latest responses are shown below.', 'llm-visibility-monitor' ); ?></p></div>
    <?php endif; ?>

    <style>
        .llmvm-dashboard-table {
            width: 100%;
            border-collapse: collapse;
        }
        .llmvm-dashboard-table th,
        .llmvm-dashboard-table td {
            padding: 8px;
            text-align: left;
            vertical-align: top;
            border: 1px solid #ddd;
        }
        .llmvm-dashboard-table th {
            background-color: #f1f1f1;
            font-weight: bold;
        }
        .llmvm-dashboard-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .llmvm-dashboard-table tr:nth-child(odd) {
            background-color: #ffffff;
        }
        .llmvm-dashboard-table .col-date {
            width: 140px;
            min-width: 140px;
        }
        .llmvm-dashboard-table .col-prompt {
            width: 20%;
            min-width: 150px;
        }
        .llmvm-dashboard-table .col-model {
            width: 120px;
            min-width: 120px;
        }
        .llmvm-dashboard-table .col-answer {
            width: auto;
        }
        .llmvm-dashboard-table .col-actions {
            width: 100px;
            min-width: 100px;
            text-align: center;
        }
        .llmvm-dashboard-table .action-links {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
        }
        .llmvm-dashboard-table .action-links a {
            text-decoration: none;
            font-size: 12px;
        }
        .llmvm-dashboard-table .action-links .delete-link {
            color: #a00;
        }
        .llmvm-dashboard-table .action-links .delete-link:hover {
            color: #dc3232;
        }
        
        /* Mobile responsive */
        @media screen and (max-width: 768px) {
            .llmvm-dashboard-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .llmvm-dashboard-table .col-date {
                width: 120px;
                min-width: 120px;
            }
            .llmvm-dashboard-table .col-prompt {
                width: 15%;
                min-width: 120px;
            }
            .llmvm-dashboard-table .col-model {
                width: 100px;
                min-width: 100px;
            }
            .llmvm-dashboard-table .col-actions {
                width: 80px;
                min-width: 80px;
            }
        }
        
        @media screen and (max-width: 480px) {
            .llmvm-dashboard-table .col-date {
                width: 100px;
                min-width: 100px;
            }
            .llmvm-dashboard-table .col-prompt {
                width: 12%;
                min-width: 100px;
            }
            .llmvm-dashboard-table .col-model {
                width: 80px;
                min-width: 80px;
            }
            .llmvm-dashboard-table .col-actions {
                width: 70px;
                min-width: 70px;
            }
            .llmvm-dashboard-table .action-links {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
    
    <table class="widefat fixed striped llmvm-dashboard-table">
        <colgroup>
            <col class="col-date" />
            <col class="col-prompt" />
            <col class="col-model" />
            <col class="col-actions" />
            <col class="col-answer" />
        </colgroup>
        <thead>
            <tr>
                <th><?php echo esc_html__( 'Date (UTC)', 'llm-visibility-monitor' ); ?></th>
                <th><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></th>
                <th><?php echo esc_html__( 'Model', 'llm-visibility-monitor' ); ?></th>
                <th><?php echo esc_html__( 'Actions', 'llm-visibility-monitor' ); ?></th>
                <th><?php echo esc_html__( 'Answer', 'llm-visibility-monitor' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $results ) ) : ?>
                <tr>
                    <td colspan="5"><?php echo esc_html__( 'No results yet.', 'llm-visibility-monitor' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $results as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( wp_trim_words( (string) ( $row['prompt'] ?? '' ), 24 ) ?: '' ); ?></td>
                        <td><?php echo esc_html( (string) ( $row['model'] ?? '' ) ); ?></td>
                        <td>
                            <div class="action-links">
                                <?php
                                $detail_url = add_query_arg(
                                    [ 'page' => 'llmvm-result', 'id' => (int) ( $row['id'] ?? 0 ) ],
                                    admin_url( 'tools.php' )
                                );
                                $delete_url = wp_nonce_url(
                                    add_query_arg(
                                        [ 'action' => 'llmvm_delete_result', 'id' => (int) ( $row['id'] ?? 0 ) ],
                                        admin_url( 'admin-post.php' )
                                    ),
                                    'llmvm_delete_result'
                                );
                                ?>
                                <a href="<?php echo esc_url( $detail_url ); ?>">
                                    <?php echo esc_html__( 'Details', 'llm-visibility-monitor' ); ?>
                                </a>
                                <a href="<?php echo esc_url( $delete_url ); ?>" class="delete-link" 
                                   onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this result?', 'llm-visibility-monitor' ) ); ?>');">
                                    <?php echo esc_html__( 'Delete', 'llm-visibility-monitor' ); ?>
                                </a>
                            </div>
                        </td>
                        <td>
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
    </table>
</div>


