<?php
/**
 * Prompts Management Page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current user ID for filtering
$current_user_id = get_current_user_id();
$is_admin = current_user_can( 'llmvm_manage_settings' );

// Filter prompts to show only user's own prompts (unless admin)
$user_prompts = [];
$all_prompts = [];

foreach ( $prompts as $prompt ) {
    $prompt_user_id = isset( $prompt['user_id'] ) ? (int) $prompt['user_id'] : 1;
    
    // Always add to user_prompts if it belongs to current user
    if ( $prompt_user_id === $current_user_id ) {
        $user_prompts[] = $prompt;
    }
    
    // Add to all_prompts if admin (for viewing all prompts)
    if ( $is_admin ) {
        $all_prompts[] = $prompt;
    }
}

// Get user display names for admin view
$user_names = [];
if ( $is_admin ) {
    $users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );
    foreach ( $users as $user ) {
        $user_names[ $user->ID ] = $user->display_name;
    }
}
?>

<style>
.llmvm-prompt-cell {
    width: 60%;
}
.column-model {
    width: 40%;
}
/* Admin table with 3 columns */
.llmvm-admin-table .llmvm-prompt-cell {
    width: 50%;
}
.llmvm-admin-table .column-model {
    width: 35%;
}
.column-owner {
    width: 15%;
}
.llmvm-prompt-display {
    padding: 8px 10px;
}
.column-model select {
    width: 100%;
    margin-bottom: 8px;
}
.column-model .button {
    margin-top: 8px;
}
.llmvm-button-group {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 8px;
}
.llmvm-button-group .button,
.llmvm-button-group input[type="submit"] {
    margin: 0;
}
.llmvm-all-buttons {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    align-items: center;
    margin-top: 8px;
}
.llmvm-all-buttons .button,
.llmvm-all-buttons input[type="submit"] {
    margin: 0;
}
/* Remove the margin between button groups */
.llmvm-button-group {
    margin-bottom: 0;
}
/* Ensure proper spacing and alignment */
.column-model {
    padding: 8px;
    position: relative;
    min-height: 120px;
}
.llmvm-prompt-cell textarea {
    margin-bottom: 0;
}
/* Make the form take available space and push action buttons to bottom */
.column-model form {
    margin-bottom: 8px;
}
/* Position action buttons at the bottom */
.llmvm-action-buttons {
    position: absolute;
    bottom: 8px;
    right: 8px;
    left: 8px;
    justify-content: flex-end;
}
/* Responsive design for mobile devices */
@media screen and (max-width: 782px) {
    .llmvm-prompt-cell {
        width: 100%;
        margin-bottom: 16px;
    }
    .column-model {
        width: 100%;
        min-height: auto;
        position: relative;
    }
    .llmvm-all-buttons {
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 12px;
    }
    .llmvm-all-buttons .button,
    .llmvm-all-buttons input[type="submit"] {
        flex: 1;
        min-width: 80px;
        text-align: center;
    }
    /* Stack buttons vertically on very small screens */
    @media screen and (max-width: 480px) {
        .llmvm-all-buttons {
            flex-direction: column;
            align-items: stretch;
        }
        .llmvm-all-buttons .button,
        .llmvm-all-buttons input[type="submit"] {
            flex: none;
            width: 100%;
        }
    }
}
</style>

<div class="wrap">
    <h1><?php echo esc_html__( 'LLM Prompts Management', 'llm-visibility-monitor' ); ?></h1>

    <p>
        <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_run_now' ), 'llmvm_run_now' ) ); ?>">
            <?php echo esc_html__( 'Run All Prompts Now', 'llm-visibility-monitor' ); ?>
        </a>
        <a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=llmvm-dashboard' ) ); ?>">
            <?php echo esc_html__( 'View Dashboard', 'llm-visibility-monitor' ); ?>
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

    <h2><?php echo esc_html__( 'Add New Prompt', 'llm-visibility-monitor' ); ?></h2>
    
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

    <?php if ( $is_admin && ! empty( $all_prompts ) ) : ?>
        <h2><?php echo esc_html__( 'All Prompts (Admin View)', 'llm-visibility-monitor' ); ?></h2>
        <table class="widefat fixed striped llmvm-admin-table">
            <thead>
                <tr>
                    <th class="llmvm-prompt-cell"><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></th>
                    <th class="column-model"><?php echo esc_html__( 'Model & Actions', 'llm-visibility-monitor' ); ?></th>
                    <th class="column-owner"><?php echo esc_html__( 'Owner', 'llm-visibility-monitor' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $all_prompts as $prompt ) : ?>
                    <?php 
                    $prompt_user_id = isset( $prompt['user_id'] ) ? (int) $prompt['user_id'] : 1;
                    $is_owner = ( $prompt_user_id === $current_user_id );
                    $owner_name = isset( $user_names[ $prompt_user_id ] ) ? $user_names[ $prompt_user_id ] : 'Unknown User';
                    ?>
                    <tr>
                        <td class="llmvm-prompt-cell">
                            <?php if ( $is_owner ) : ?>
                                <textarea name="prompt_text" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $prompt['text'] ?? '' ) ); ?></textarea>
                            <?php else : ?>
                                <div class="llmvm-prompt-display">
                                    <p><?php echo esc_html( (string) ( $prompt['text'] ?? '' ) ); ?></p>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $is_owner ) : ?>
                                <label for="llmvm-prompt-model-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>"><?php echo esc_html__( 'Model:', 'llm-visibility-monitor' ); ?></label>
                                <select id="llmvm-prompt-model-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" class="regular-text" data-prompt-id="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>">
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
                                <br><br>
                                <div class="llmvm-all-buttons">
                                    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display: inline;">
                                        <?php wp_nonce_field( 'llmvm_edit_prompt' ); ?>
                                        <input type="hidden" name="action" value="llmvm_edit_prompt" />
                                        <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                        <input type="hidden" name="prompt_text" value="<?php echo esc_attr( (string) ( $prompt['text'] ?? '' ) ); ?>" />
                                        <input type="hidden" name="prompt_model" value="<?php echo esc_attr( (string) ( $prompt['model'] ?? '' ) ); ?>" />
                                        <?php submit_button( __( 'Save', 'llm-visibility-monitor' ), 'primary', '', false ); ?>
                                    </form>
                                    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display: inline;" class="delete-prompt-form">
                                        <?php wp_nonce_field( 'llmvm_delete_prompt' ); ?>
                                        <input type="hidden" name="action" value="llmvm_delete_prompt" />
                                        <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                        <?php submit_button( __( 'Delete', 'llm-visibility-monitor' ), 'link-delete', '', false ); ?>
                                    </form>
                                    <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_run_single_prompt&prompt_id=' . urlencode( (string) ( $prompt['id'] ?? '' ) ) ), 'llmvm_run_single_prompt' ) ); ?>">
                                        <?php echo esc_html__( 'Run Now', 'llm-visibility-monitor' ); ?>
                                    </a>
                                </div>
                            <?php else : ?>
                                <span class="description"><?php echo esc_html__( 'Read-only', 'llm-visibility-monitor' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html( $owner_name ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ( ! empty( $user_prompts ) ) : ?>
        <h2><?php echo esc_html__( 'Your Prompts', 'llm-visibility-monitor' ); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th class="llmvm-prompt-cell"><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></th>
                    <th class="column-model"><?php echo esc_html__( 'Model & Actions', 'llm-visibility-monitor' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $user_prompts as $prompt ) : ?>
                    <?php 
                    // For user prompts, the current user is always the owner
                    $is_owner = true;
                    ?>
                    <tr>
                        <td class="llmvm-prompt-cell">
                            <textarea name="prompt_text" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $prompt['text'] ?? '' ) ); ?></textarea>
                        </td>
                        <td>
                            <label for="llmvm-prompt-model-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>"><?php echo esc_html__( 'Model:', 'llm-visibility-monitor' ); ?></label>
                            <select id="llmvm-prompt-model-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" class="regular-text" data-prompt-id="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>">
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
                            <br><br>
                            <div class="llmvm-all-buttons">
                                <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display: inline;">
                                    <?php wp_nonce_field( 'llmvm_edit_prompt' ); ?>
                                    <input type="hidden" name="action" value="llmvm_edit_prompt" />
                                    <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                    <input type="hidden" name="prompt_text" value="<?php echo esc_attr( (string) ( $prompt['text'] ?? '' ) ); ?>" />
                                    <input type="hidden" name="prompt_model" value="<?php echo esc_attr( (string) ( $prompt['model'] ?? '' ) ); ?>" />
                                    <?php submit_button( __( 'Save', 'llm-visibility-monitor' ), 'primary', '', false ); ?>
                                </form>
                                <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display: inline;" class="delete-prompt-form">
                                    <?php wp_nonce_field( 'llmvm_delete_prompt' ); ?>
                                    <input type="hidden" name="action" value="llmvm_delete_prompt" />
                                    <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                    <?php submit_button( __( 'Delete', 'llm-visibility-monitor' ), 'link-delete', '', false ); ?>
                                    </form>
                                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=llmvm_run_single_prompt&prompt_id=' . urlencode( (string) ( $prompt['id'] ?? '' ) ) ), 'llmvm_run_single_prompt' ) ); ?>">
                                    <?php echo esc_html__( 'Run Now', 'llm-visibility-monitor' ); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php echo esc_html__( 'No prompts found.', 'llm-visibility-monitor' ); ?></p>
    <?php endif; ?>

    <hr />
    
    <p>
        <a href="<?php echo esc_url( admin_url( 'tools.php?page=llmvm-dashboard' ) ); ?>" class="button button-secondary">
            <?php echo esc_html__( 'View Dashboard', 'llm-visibility-monitor' ); ?>
        </a>
        <?php if ( $is_admin ) : ?>
            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=llmvm-settings' ) ); ?>" class="button button-secondary">
                <?php echo esc_html__( 'Plugin Settings', 'llm-visibility-monitor' ); ?>
            </a>
        <?php endif; ?>
    </p>
</div>

<script>
jQuery(document).ready(function($) {
    // Sync textarea content with hidden input fields
    $('.llmvm-prompt-cell textarea').on('input', function() {
        var promptId = $(this).closest('tr').find('input[name="prompt_id"]').val();
        var textareaValue = $(this).val();
        $('input[name="prompt_text"][form*="' + promptId + '"]').val(textareaValue);
    });
    
    // Sync model selector with hidden input field
    $('select[id^="llmvm-prompt-model-"]').on('change', function() {
        var promptId = $(this).data('prompt-id');
        var modelValue = $(this).val();
        $('input[name="prompt_model"][form*="' + promptId + '"]').val(modelValue);
    });
    
    // Sync content before form submission to ensure latest content is saved
    $('form[action*="admin-post.php"]').on('submit', function(e) {
        var $form = $(this);
        var promptId = $form.find('input[name="prompt_id"]').val();
        
        // Sync textarea content
        var $textarea = $form.closest('tr').find('.llmvm-prompt-cell textarea');
        var $hiddenTextInput = $form.find('input[name="prompt_text"]');
        if ($textarea.length && $hiddenTextInput.length) {
            $hiddenTextInput.val($textarea.val());
        }
        
        // Sync model selector content
        var $modelSelect = $form.closest('tr').find('select[id^="llmvm-prompt-model-"]');
        var $hiddenModelInput = $form.find('input[name="prompt_model"]');
        if ($modelSelect.length && $hiddenModelInput.length) {
            $hiddenModelInput.val($modelSelect.val());
        }
    });
    
    // Handle delete confirmation
    $('.delete-prompt-form').on('submit', function(e) {
        if (!confirm('<?php echo esc_js( __( 'Delete this prompt?', 'llm-visibility-monitor' ) ); ?>')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
