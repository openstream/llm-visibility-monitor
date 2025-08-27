<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'LLM Visibility Monitor - Settings', 'llm-visibility-monitor' ); ?></h1>

    <p>
        <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_run_now' ), 'llmvm_run_now' ) ); ?>">
            <?php echo esc_html__( 'Run Now', 'llm-visibility-monitor' ); ?>
        </a>
        <a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=llmvm-dashboard' ) ); ?>" style="margin-left:8px;">
            <?php echo esc_html__( 'Open Dashboard', 'llm-visibility-monitor' ); ?>
        </a>
    </p>

    <?php
    // Check for run completion message with proper sanitization.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is just a display flag, not a form submission.
    $run_completed = isset( $_GET['llmvm_ran'] ) ? sanitize_text_field( wp_unslash( $_GET['llmvm_ran'] ) ) : '';
    if ( '1' === $run_completed ) :
    ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Run completed. Latest responses are visible on the Dashboard.', 'llm-visibility-monitor' ); ?></p></div>
    <?php endif; ?>

    <form action="options.php" method="post">
        <?php
        settings_fields( 'llmvm_settings' );
        do_settings_sections( 'llmvm-settings' );
        submit_button( __( 'Save Settings', 'llm-visibility-monitor' ) );
        ?>
    </form>

    <hr />

    <h2><?php echo esc_html__( 'Manage Prompts', 'llm-visibility-monitor' ); ?></h2>
    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
        <?php wp_nonce_field( 'llmvm_add_prompt' ); ?>
        <input type="hidden" name="action" value="llmvm_add_prompt" />
        <p>
            <label for="llmvm-new-prompt" class="screen-reader-text"><?php echo esc_html__( 'New Prompt', 'llm-visibility-monitor' ); ?></label>
            <textarea id="llmvm-new-prompt" name="prompt_text" class="large-text" rows="3" required></textarea>
        </p>
        <?php submit_button( __( 'Add Prompt', 'llm-visibility-monitor' ), 'secondary' ); ?>
    </form>

    <?php if ( ! empty( $prompts ) ) : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></th>
                    <th><?php echo esc_html__( 'Actions', 'llm-visibility-monitor' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $prompts as $prompt ) : ?>
                    <tr>
                        <td style="width:70%">
                            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                                <?php wp_nonce_field( 'llmvm_edit_prompt' ); ?>
                                <input type="hidden" name="action" value="llmvm_edit_prompt" />
                                <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                <textarea name="prompt_text" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $prompt['text'] ?? '' ) ); ?></textarea>
                                <?php submit_button( __( 'Save', 'llm-visibility-monitor' ), 'primary small', '', false ); ?>
                            </form>
                        </td>
                        <td>
                            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this prompt?', 'llm-visibility-monitor' ) ); ?>');">
                                <?php wp_nonce_field( 'llmvm_delete_prompt' ); ?>
                                <input type="hidden" name="action" value="llmvm_delete_prompt" />
                                <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                <?php submit_button( __( 'Delete', 'llm-visibility-monitor' ), 'link-delete', '', false ); ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php echo esc_html__( 'No prompts added yet.', 'llm-visibility-monitor' ); ?></p>
    <?php endif; ?>
</div>


