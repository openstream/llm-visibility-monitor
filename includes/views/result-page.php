<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'LLM Visibility Result', 'llm-visibility-monitor' ); ?></h1>

    <?php if ( empty( $row ) || ! is_array( $row ) ) : ?>
        <p><?php echo esc_html__( 'Result not found.', 'llm-visibility-monitor' ); ?></p>
        <p><a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=llmvm-dashboard' ) ); ?>"><?php echo esc_html__( 'Back to Dashboard', 'llm-visibility-monitor' ); ?></a></p>
    <?php else : ?>

        <table class="widefat fixed striped llmvm-top">
            <colgroup>
                <col class="llmvm-col-label" />
                <col class="llmvm-col-content" />
            </colgroup>
            <tbody>
                <tr>
                    <th><?php echo esc_html__( 'Date (UTC)', 'llm-visibility-monitor' ); ?></th>
                    <td><?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Model', 'llm-visibility-monitor' ); ?></th>
                    <td><?php echo esc_html( (string) ( $row['model'] ?? '' ) ); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></th>
                    <td><pre class="llmvm-pre-wrap"><?php echo esc_html( (string) ( $row['prompt'] ?? '' ) ); ?></pre></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Answer', 'llm-visibility-monitor' ); ?></th>
                    <td><pre class="llmvm-pre-wrap"><?php echo esc_html( (string) ( $row['answer'] ?? '' ) ); ?></pre></td>
                </tr>
            </tbody>
        </table>

        <p><a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=llmvm-dashboard' ) ); ?>"><?php echo esc_html__( 'Back to Dashboard', 'llm-visibility-monitor' ); ?></a></p>
    <?php endif; ?>
</div>


