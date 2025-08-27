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
    </p>

    <table class="widefat fixed striped">
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
                        <td><?php echo esc_html( wp_trim_words( (string) ( $row['answer'] ?? '' ), 36 ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>


