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
        <a class="button llmvm-button-margin" href="<?php echo esc_url( admin_url( 'tools.php?page=llmvm-dashboard' ) ); ?>">
            <?php echo esc_html__( 'Open Dashboard', 'llm-visibility-monitor' ); ?>
        </a>
    </p>

    <?php
    // Check for run completion message with proper sanitization and nonce verification
    $run_completed = '';
    if ( isset( $_GET['llmvm_ran'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'llmvm_run_completed' ) ) {
        $run_completed = sanitize_text_field( wp_unslash( $_GET['llmvm_ran'] ) );
    }
    if ( '1' === $run_completed ) :
    ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Run completed. Latest responses are visible on the Dashboard.', 'llm-visibility-monitor' ) ?></p></div>
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
    
    <?php
    // Display admin notices
    $notice = get_transient( 'llmvm_notice' );
    if ( $notice && isset( $notice['type'] ) && isset( $notice['msg'] ) ) {
        $notice_class = 'notice-' . $notice['type'];
        echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $notice['msg'] ) . '</p></div>';
        delete_transient( 'llmvm_notice' );
    }
    ?>
    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
        <?php wp_nonce_field( 'llmvm_add_prompt' ); ?>
        <input type="hidden" name="action" value="llmvm_add_prompt" />
        <p>
            <label for="llmvm-new-prompt" class="screen-reader-text"><?php echo esc_html__( 'New Prompt', 'llm-visibility-monitor' ); ?></label>
            <textarea id="llmvm-new-prompt" name="prompt_text" class="large-text" rows="3" required></textarea>
        </p>
        <p>
            <label for="llmvm-new-prompt-model"><?php echo esc_html__( 'Model (optional, uses default if empty):', 'llm-visibility-monitor' ); ?></label>
            <select id="llmvm-new-prompt-model" name="prompt_model" class="regular-text">
                <option value=""><?php echo esc_html__( 'Use default model', 'llm-visibility-monitor' ); ?></option>
                <?php
                $options = get_option( 'llmvm_options', [] );
                $default_model = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
                $models = LLMVM_Admin::get_openrouter_models();
                foreach ( $models as $model ) {
                    $selected = ( $model['id'] === $default_model ) ? ' selected="selected"' : '';
                    echo '<option value="' . esc_attr( $model['id'] ) . '"' . esc_attr( $selected ) . '>';
                    echo esc_html( $model['name'] . ' (' . $model['id'] . ')' );
                    echo '</option>';
                }
                ?>
            </select>
        </p>
        <?php submit_button( __( 'Add Prompt', 'llm-visibility-monitor' ), 'secondary' ); ?>
    </form>

    <?php if ( ! empty( $prompts ) ) : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></th>
                    <th><?php echo esc_html__( 'Model', 'llm-visibility-monitor' ); ?></th>
                    <th><?php echo esc_html__( 'Actions', 'llm-visibility-monitor' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $prompts as $prompt ) : ?>
                    <tr>
                        <td class="llmvm-prompt-cell">
                            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                                <?php wp_nonce_field( 'llmvm_edit_prompt' ); ?>
                                <input type="hidden" name="action" value="llmvm_edit_prompt" />
                                <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                <textarea name="prompt_text" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $prompt['text'] ?? '' ) ); ?></textarea>
                                <p>
                                    <label for="llmvm-prompt-model-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>"><?php echo esc_html__( 'Model:', 'llm-visibility-monitor' ); ?></label>
                                    <select id="llmvm-prompt-model-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" name="prompt_model" class="regular-text">
                                        <option value=""><?php echo esc_html__( 'Use default model', 'llm-visibility-monitor' ); ?></option>
                                        <?php
                                        $options = get_option( 'llmvm_options', [] );
                                        $default_model = isset( $options['model'] ) ? (string) $options['model'] : 'openrouter/stub-model-v1';
                                        $models = LLMVM_Admin::get_openrouter_models();
                                        $current_model = isset( $prompt['model'] ) ? (string) $prompt['model'] : '';
                                        foreach ( $models as $model ) {
                                            $selected = ( $model['id'] === $current_model ) ? ' selected="selected"' : '';
                                            echo '<option value="' . esc_attr( $model['id'] ) . '"' . esc_attr( $selected ) . '>';
                                            echo esc_html( $model['name'] . ' (' . $model['id'] . ')' );
                                            echo '</option>';
                                        }
                                        ?>
                                    </select>
                                </p>
                                <?php submit_button( __( 'Save', 'llm-visibility-monitor' ), 'primary small', '', false ); ?>
                            </form>
                        </td>
                        <td>
                            <?php
                            $current_model = isset( $prompt['model'] ) ? (string) $prompt['model'] : '';
                            if ( '' === trim( $current_model ) ) {
                                echo '<em>' . esc_html__( 'Default model', 'llm-visibility-monitor' ) . '</em>';
                            } else {
                                echo esc_html( $current_model );
                            }
                            ?>
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


