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

    <?php if ( isset( $_GET['llmvm_ran'] ) && '1' === (string) $_GET['llmvm_ran'] ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Run completed. Latest responses are shown below.', 'llm-visibility-monitor' ); ?></p></div>
    <?php endif; ?>

    <table class="widefat fixed striped">
        <colgroup>
            <col style="width: 180px;" />
            <col />
            <col style="width: 180px;" />
            <col />
        </colgroup>
        <thead>
            <tr>
                <th><?php echo esc_html__( 'Date (UTC)', 'llm-visibility-monitor' ); ?></th>
                <th><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></th>
                <th><?php echo esc_html__( 'Model', 'llm-visibility-monitor' ); ?></th>
                <th><?php echo esc_html__( 'Answer', 'llm-visibility-monitor' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $results ) ) : ?>
                <tr>
                    <td colspan="4"><?php echo esc_html__( 'No results yet.', 'llm-visibility-monitor' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $results as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( wp_trim_words( (string) ( $row['prompt'] ?? '' ), 24 ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $row['model'] ?? '' ) ); ?></td>
                        <td>
                            <?php
                            $answer = (string) ( $row['answer'] ?? '' );
                            if ( '' === trim( $answer ) ) {
                                echo '<em>' . esc_html__( 'No answer (see logs for details)', 'llm-visibility-monitor' ) . '</em>';
                            } else {
                                $detail_url = add_query_arg(
                                    [ 'page' => 'llmvm-result', 'id' => (int) ( $row['id'] ?? 0 ) ],
                                    admin_url( 'tools.php' )
                                );
                                echo '<a href="' . esc_url( $detail_url ) . '">' . esc_html( wp_trim_words( $answer, 36 ) ) . '</a>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>


