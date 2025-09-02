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
.llmvm-prompt-display {
    background: #f9f9f9;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.llmvm-prompt-display p {
    margin: 0 0 8px 0;
}

.llmvm-prompt-display p:last-child {
    margin-bottom: 0;
}

.llmvm-prompt-cell {
    width: 40%;
}

.column-model {
    width: 20%;
}

.column-owner {
    width: 15%;
}

.column-actions {
    width: 15%;
}

.wp-list-table td {
    vertical-align: top;
    padding: 8px 10px;
}

.wp-list-table th {
    padding: 8px 10px;
}
</style>

<div class="wrap">
    <h1><?php echo esc_html__( 'LLM Prompts Management', 'llm-visibility-monitor' ); ?></h1>

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
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th class="llmvm-prompt-cell"><?php echo esc_html__( 'Prompt', 'llm-visibility-monitor' ); ?></th>
                    <th class="column-model"><?php echo esc_html__( 'Model', 'llm-visibility-monitor' ); ?></th>
                    <th class="column-owner"><?php echo esc_html__( 'Owner', 'llm-visibility-monitor' ); ?></th>
                    <th class="column-actions"><?php echo esc_html__( 'Actions', 'llm-visibility-monitor' ); ?></th>
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
                            <?php else : ?>
                                <div class="llmvm-prompt-display">
                                    <p><?php echo esc_html( (string) ( $prompt['text'] ?? '' ) ); ?></p>
                                    <p><em><?php echo esc_html__( 'Model:', 'llm-visibility-monitor' ); ?> 
                                    <?php
                                    $current_model = isset( $prompt['model'] ) ? (string) $prompt['model'] : '';
                                    if ( '' === trim( $current_model ) ) {
                                        echo '<em>' . esc_html__( 'Default model', 'llm-visibility-monitor' ) . '</em>';
                                    } else {
                                        echo esc_html( $current_model );
                                    }
                                    ?>
                                    </em></p>
                                </div>
                            <?php endif; ?>
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
                            <?php echo esc_html( $owner_name ); ?>
                        </td>
                        <td>
                            <?php if ( $is_owner ) : ?>
                                <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this prompt?', 'llm-visibility-monitor' ) ); ?>');">
                                    <?php wp_nonce_field( 'llmvm_delete_prompt' ); ?>
                                    <input type="hidden" name="action" value="llmvm_delete_prompt" />
                                    <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                    <?php submit_button( __( 'Delete', 'llm-visibility-monitor' ), 'link-delete', '', false ); ?>
                                </form>
                            <?php else : ?>
                                <span class="description"><?php echo esc_html__( 'Read-only', 'llm-visibility-monitor' ); ?></span>
                            <?php endif; ?>
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
                    <th class="column-model"><?php echo esc_html__( 'Model', 'llm-visibility-monitor' ); ?></th>
                    <th class="column-actions"><?php echo esc_html__( 'Actions', 'llm-visibility-monitor' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $user_prompts as $prompt ) : ?>
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
