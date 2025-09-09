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
.column-web-search {
    width: 10%;
    text-align: center;
}
.llmvm-admin-table .column-web-search {
    width: 8%;
}
.llmvm-admin-table .column-model {
    width: 30%;
}
.llmvm-admin-table .column-owner {
    width: 12%;
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

/* Multi-model selection styles */
.llmvm-multi-model-container {
    position: relative;
    width: 100%;
}
.llmvm-multi-model-container input[type="text"] {
    width: 100%;
    margin-bottom: 8px;
}
.llmvm-selected-models {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 8px;
    min-height: 20px;
}
.llmvm-model-tag {
    background: #0073aa;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.llmvm-model-tag .remove {
    cursor: pointer;
    font-weight: bold;
    color: #fff;
    text-decoration: none;
}
.llmvm-model-tag .remove:hover {
    color: #ff6b6b;
}
.ui-autocomplete {
    max-height: 200px;
    overflow-y: auto;
    z-index: 999999 !important;
    position: absolute !important;
    background: white !important;
    border: 1px solid #ccc !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
}
.ui-autocomplete .ui-menu-item {
    padding: 4px 8px;
    cursor: pointer;
}
.ui-autocomplete .ui-menu-item:hover {
    background: #f0f0f0;
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

    <?php
    // Display usage information for non-admin users
    if ( ! $is_admin ) {
        $usage_summary = LLMVM_Usage_Manager::get_usage_summary( $current_user_id );
        
        ?>
        <div class="llmvm-usage-display" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <h3 style="margin-top: 0;"><?php echo esc_html__( 'Your Usage Summary', 'llm-visibility-monitor' ); ?> (<?php echo esc_html( $usage_summary['plan_name'] ); ?> Plan)</h3>
            
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div>
                    <strong><?php echo esc_html__( 'Prompts:', 'llm-visibility-monitor' ); ?></strong>
                    <span style="color: <?php echo $usage_summary['prompts']['used'] >= $usage_summary['prompts']['limit'] ? '#d63638' : '#00a32a'; ?>">
                        <?php echo esc_html( $usage_summary['prompts']['used'] ); ?> / <?php echo esc_html( $usage_summary['prompts']['limit'] ); ?>
                    </span>
                    <?php if ( $usage_summary['prompts']['remaining'] > 0 ) : ?>
                        <span style="color: #666;">(<?php echo esc_html( $usage_summary['prompts']['remaining'] ); ?> <?php echo esc_html__( 'remaining', 'llm-visibility-monitor' ); ?>)</span>
                    <?php endif; ?>
                </div>
                
                <div>
                    <strong><?php echo esc_html__( 'Runs this month:', 'llm-visibility-monitor' ); ?></strong>
                    <span style="color: <?php echo $usage_summary['runs']['used'] >= $usage_summary['runs']['limit'] ? '#d63638' : '#00a32a'; ?>">
                        <?php echo esc_html( $usage_summary['runs']['used'] ); ?> / <?php echo esc_html( $usage_summary['runs']['limit'] ); ?>
                    </span>
                    <?php if ( $usage_summary['runs']['remaining'] > 0 ) : ?>
                        <span style="color: #666;">(<?php echo esc_html( $usage_summary['runs']['remaining'] ); ?> <?php echo esc_html__( 'remaining', 'llm-visibility-monitor' ); ?>)</span>
                    <?php endif; ?>
                </div>
                
                <div>
                    <strong><?php echo esc_html__( 'Max models per prompt:', 'llm-visibility-monitor' ); ?></strong>
                    <span><?php echo esc_html( $usage_summary['models_per_prompt_limit'] ); ?></span>
                </div>
            </div>
            
            <?php if ( $usage_summary['prompts']['used'] >= $usage_summary['prompts']['limit'] ) : ?>
                <div style="color: #d63638; margin-top: 10px; font-weight: bold;">
                    ⚠️ <?php echo esc_html__( 'You have reached your prompt limit. Delete some prompts to create new ones.', 'llm-visibility-monitor' ); ?>
                </div>
            <?php endif; ?>
            
            <?php if ( $usage_summary['runs']['used'] >= $usage_summary['runs']['limit'] ) : ?>
                <div style="color: #d63638; margin-top: 10px; font-weight: bold;">
                    ⚠️ <?php echo esc_html__( 'You have reached your monthly run limit. Your limit will reset next month.', 'llm-visibility-monitor' ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>

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
            <label for="llmvm-new-prompt-models"><?php echo esc_html__( 'Models (optional, uses default if empty):', 'llm-visibility-monitor' ); ?></label>
            <div id="llmvm-new-prompt-models-container" class="llmvm-multi-model-container">
                <input type="text" id="llmvm-new-prompt-models-search" class="regular-text" placeholder="<?php echo esc_attr__( 'Click to see all models or type to filter...', 'llm-visibility-monitor' ); ?>" />
                <div id="llmvm-new-prompt-models-selected" class="llmvm-selected-models"></div>
                <input type="hidden" id="llmvm-new-prompt-models-input" name="prompt_models[]" value="" />
            </div>
        </p>
        <p>
            <label for="llmvm-new-prompt-web-search">
                <input type="checkbox" id="llmvm-new-prompt-web-search" name="web_search" value="1" />
                <?php echo esc_html__( 'Enable Web Search (appends :online to models)', 'llm-visibility-monitor' ); ?>
            </label>
            <br><small class="description"><?php echo esc_html__( 'Uses OpenRouter web search plugin to find relevant information from the web.', 'llm-visibility-monitor' ); ?></small>
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
                    <th class="column-web-search"><?php echo esc_html__( 'Web Search', 'llm-visibility-monitor' ); ?></th>
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
                                <label for="llmvm-prompt-models-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>"><?php echo esc_html__( 'Models:', 'llm-visibility-monitor' ); ?></label>
                                <?php
                                // Get current models for this prompt (handle both old 'model' and new 'models' format)
                                $current_models = array();
                                if ( isset( $prompt['models'] ) && is_array( $prompt['models'] ) ) {
                                    $current_models = $prompt['models'];
                                } elseif ( isset( $prompt['model'] ) && ! empty( $prompt['model'] ) ) {
                                    $current_models = array( $prompt['model'] );
                                }
                                ?>
                                <div id="llmvm-prompt-models-container-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" class="llmvm-multi-model-container" data-prompt-id="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" data-current-models="<?php echo esc_attr( json_encode( $current_models ) ); ?>">
                                    <input type="text" id="llmvm-prompt-models-search-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( 'Click to see all models or type to filter...', 'llm-visibility-monitor' ); ?>" />
                                    <div id="llmvm-prompt-models-selected-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" class="llmvm-selected-models"></div>
                                    <input type="hidden" id="llmvm-prompt-models-input-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" name="prompt_models[]" value="" />
                                </div>
                                <br><br>
                                <div class="llmvm-all-buttons">
                                    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display: inline;">
                                        <?php wp_nonce_field( 'llmvm_edit_prompt' ); ?>
                                        <input type="hidden" name="action" value="llmvm_edit_prompt" />
                                        <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                        <input type="hidden" name="prompt_text" value="<?php echo esc_attr( (string) ( $prompt['text'] ?? '' ) ); ?>" />
                                        <input type="hidden" name="prompt_models[]" value="" />
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
                        <td class="column-web-search">
                            <?php if ( $is_owner ) : ?>
                                <label for="llmvm-prompt-web-search-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>">
                                    <input type="checkbox" 
                                           id="llmvm-prompt-web-search-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" 
                                           name="web_search[<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>]" 
                                           value="1" 
                                           <?php checked( ! empty( $prompt['web_search'] ) ); ?> />
                                    <?php echo esc_html__( 'Web Search', 'llm-visibility-monitor' ); ?>
                                </label>
                            <?php else : ?>
                                <?php echo ! empty( $prompt['web_search'] ) ? '✅' : '❌'; ?>
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
                            <label for="llmvm-prompt-models-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>"><?php echo esc_html__( 'Models:', 'llm-visibility-monitor' ); ?></label>
                            <?php
                            // Get current models for this prompt (handle both old 'model' and new 'models' format)
                            $current_models = array();
                            if ( isset( $prompt['models'] ) && is_array( $prompt['models'] ) ) {
                                $current_models = $prompt['models'];
                            } elseif ( isset( $prompt['model'] ) && ! empty( $prompt['model'] ) ) {
                                $current_models = array( $prompt['model'] );
                            }
                            ?>
                            <div id="llmvm-prompt-models-container-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" class="llmvm-multi-model-container" data-prompt-id="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" data-current-models="<?php echo esc_attr( json_encode( $current_models ) ); ?>">
                                <input type="text" id="llmvm-prompt-models-search-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( 'Search and select models...', 'llm-visibility-monitor' ); ?>" />
                                <div id="llmvm-prompt-models-selected-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" class="llmvm-selected-models"></div>
                                <input type="hidden" id="llmvm-prompt-models-input-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" name="prompt_models[]" value="" />
                            </div>
                            <br><br>
                            <div class="llmvm-all-buttons">
                                <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display: inline;">
                                    <?php wp_nonce_field( 'llmvm_edit_prompt' ); ?>
                                    <input type="hidden" name="action" value="llmvm_edit_prompt" />
                                    <input type="hidden" name="prompt_id" value="<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" />
                                    <input type="hidden" name="prompt_text" value="<?php echo esc_attr( (string) ( $prompt['text'] ?? '' ) ); ?>" />
                                    <input type="hidden" name="prompt_models[]" value="" />
                                    <label for="llmvm-prompt-web-search-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>">
                                        <input type="checkbox" 
                                               id="llmvm-prompt-web-search-<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>" 
                                               name="web_search[<?php echo esc_attr( (string) ( $prompt['id'] ?? '' ) ); ?>]" 
                                               value="1" 
                                               <?php checked( ! empty( $prompt['web_search'] ) ); ?> />
                                        <?php echo esc_html__( 'Web Search', 'llm-visibility-monitor' ); ?>
                                    </label>
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
console.log('=== SCRIPT TAG LOADED ===');
jQuery(document).ready(function($) {
    try {
        console.log('=== JQUERY READY FUNCTION STARTING ===');
    // Get available models for autocomplete
    var availableModels = <?php 
        $models = LLMVM_Admin::get_openrouter_models();
        echo json_encode( $models, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
    ?>;
    
    // Get user limits for validation
    var userLimits = <?php 
        $limits = LLMVM_Usage_Manager::get_user_limits( $current_user_id );
        echo json_encode( $limits, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
    ?>;
    
    // TEMPORARY: Force free plan limits for testing (remove this after testing)
    // Uncomment the next 3 lines to test with free plan limits regardless of user role
    // userLimits = {
    //     max_models_per_prompt: <?php 
    //         $options = get_option( 'llmvm_options', array() );
    //         echo isset( $options['free_max_models'] ) ? (int) $options['free_max_models'] : 3;
    //     ?>,
    //     plan_name: 'Free (Test Mode)'
    // };
    
    
    // Fallback models if none are available
    if (!availableModels || !Array.isArray(availableModels) || availableModels.length === 0) {
        availableModels = [
            { id: 'openrouter/stub-model-v1', name: 'Stub Model (for testing)' },
            { id: 'openai/gpt-4o-mini', name: 'GPT-4o Mini' },
            { id: 'openai/gpt-4o', name: 'GPT-4o' },
            { id: 'openai/gpt-5', name: 'GPT-5' },
            { id: 'anthropic/claude-3-5-sonnet', name: 'Claude 3.5 Sonnet' },
            { id: 'anthropic/claude-3-opus', name: 'Claude 3 Opus' },
            { id: 'google/gemini-pro', name: 'Gemini Pro' },
            { id: 'meta-llama/llama-3.1-8b-instruct', name: 'Llama 3.1 8B Instruct' },
            { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B Instruct' }
        ];
    }
    
    // Debug: Log models to console
    console.log('=== SCRIPT STARTING ===');
    console.log('Available models:', availableModels);
    console.log('jQuery version:', $.fn.jquery);
    console.log('Document ready state:', document.readyState);
    if (availableModels && availableModels.length > 0) {
        console.log('First model example:', availableModels[0]);
        console.log('Model structure check - has name:', 'name' in availableModels[0], 'has id:', 'id' in availableModels[0]);
    }
    
    // Initialize multi-model selectors
    function initializeMultiModelSelector(containerId) {
        console.log('=== initializeMultiModelSelector called for:', containerId, '===');
        // Escape special characters in the ID for jQuery selector
        var escapedId = containerId.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&');
        var $container = $('#' + escapedId);
        console.log('Escaped ID:', escapedId);
        console.log('Container found:', $container.length > 0);
        var $searchInput = $container.find('input[type="text"]');
        console.log('Search input found:', $searchInput.length > 0, $searchInput.attr('id'));
        var $selectedDiv = $container.find('.llmvm-selected-models');
        console.log('Selected div found:', $selectedDiv.length > 0);
        var $hiddenInput = $container.find('input[type="hidden"]');
        console.log('Hidden input found:', $hiddenInput.length > 0);
        var selectedModels = [];
        
        // Load existing models if any
        var currentModelsData = $container.data('current-models');
        console.log('Current models data:', currentModelsData);
        console.log('Is array:', Array.isArray(currentModelsData));
        if (currentModelsData && Array.isArray(currentModelsData)) {
            selectedModels = currentModelsData.slice(); // Copy the array
            console.log('Loaded existing models:', selectedModels);
            updateDisplay();
            updateHiddenInput();
        } else if (currentModelsData && typeof currentModelsData === 'string') {
            // Handle case where models are stored as comma-separated string
            selectedModels = currentModelsData.split(',').filter(function(model) {
                return model.trim() !== '';
            });
            console.log('Loaded existing models from string:', selectedModels);
            updateDisplay();
            updateHiddenInput();
        }
        
        // Prepare all models with label/value format
        var allModels = availableModels.map(function(model) {
            return {
                label: model.name + ' (' + model.id + ')',
                value: model.id,
                id: model.id,
                name: model.name
            };
        });
        
        // Skip jQuery UI Autocomplete entirely - use only custom dropdown
        console.log('Skipping jQuery UI Autocomplete, using custom dropdown only');
        
        // Create a simple custom dropdown for testing
        var $customDropdown = $('<div class="llmvm-custom-dropdown" style="display:none; position:relative; background:white; border:2px solid red; max-height:400px; overflow-y:scroll; z-index:999999; margin-top:5px; width:100%;"></div>');
        $searchInput.after($customDropdown);
        
        // Helper function to populate dropdown with models
        function populateDropdown(models) {
            $customDropdown.empty();
            
            $.each(models, function(index, model) {
                var $item = $('<div style="padding:5px; cursor:pointer; border-bottom:1px solid #eee;">' + model.name + ' (' + model.id + ')</div>');
                $item.data('model', model);
                $customDropdown.append($item);
            });
            
            console.log('Populated dropdown for', containerId, 'with', models.length, 'items');
            
            // Show dropdown if it has items
            if (models.length > 0) {
                $customDropdown.css({
                    'display': 'block',
                    'width': '100%'
                });
            } else {
                $customDropdown.hide();
            }
        }
        
        // Show all models when input is focused (clicked)
        $searchInput.on('focus', function() {
            console.log('Input focused for', containerId, ', showing all models');
            populateDropdown(availableModels);
        });
        
        // Handle clicks on custom dropdown items
        $customDropdown.on('click', 'div', function() {
            var model = $(this).data('model');
            console.log('Custom dropdown item selected:', model);
            addModel(model);
            $searchInput.val('');
            $customDropdown.hide();
        });
        
        // Filter dropdown when user types
        $searchInput.on('keyup', function() {
            var searchTerm = $(this).val().toLowerCase();
            console.log('Filtering dropdown with search term:', searchTerm);
            
            if (searchTerm === '') {
                // Show all models if search is empty
                populateDropdown(availableModels);
            } else {
                // Filter models based on search term
                var filteredModels = availableModels.filter(function(model) {
                    return model.name.toLowerCase().indexOf(searchTerm) !== -1 || 
                           model.id.toLowerCase().indexOf(searchTerm) !== -1;
                });
                
                console.log('Filtered to', filteredModels.length, 'models for', containerId);
                populateDropdown(filteredModels);
            }
        });
        
        // Hide dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest($container).length) {
                $customDropdown.hide();
            }
        });
        
        // Also handle click event for custom dropdown
        $searchInput.on('click', function() {
            console.log('Input clicked for', containerId, ', showing all models');
            populateDropdown(availableModels);
        });
        
        // Debug: Check custom dropdown
        setTimeout(function() {
            console.log('Custom dropdown element:', $customDropdown);
            console.log('Custom dropdown is visible:', $customDropdown.is(':visible'));
        }, 1000);
        
        // Add model to selection
        function addModel(model) {
            if (selectedModels.indexOf(model.id) === -1) {
                // Check if user has reached the model limit
                if (selectedModels.length >= userLimits.max_models_per_prompt) {
                    alert('❌ Model limit reached!\n\n' +
                          'You have reached your limit of ' + userLimits.max_models_per_prompt + ' models per prompt.\n' +
                          'Plan: ' + userLimits.plan_name + '\n\n' +
                          'Please remove a model first or upgrade your plan.');
                    return;
                }
                
                selectedModels.push(model.id);
                updateDisplay();
                updateHiddenInput();
            }
        }
        
        // Remove model from selection
        function removeModel(modelId) {
            var index = selectedModels.indexOf(modelId);
            if (index > -1) {
                selectedModels.splice(index, 1);
                updateDisplay();
                updateHiddenInput();
            }
        }
        
        // Update the display of selected models
        function updateDisplay() {
            $selectedDiv.empty();
            selectedModels.forEach(function(modelId) {
                var model = availableModels.find(function(m) { return m.id === modelId; });
                if (model) {
                    var $tag = $('<span class="llmvm-model-tag">' + 
                        model.name + ' (' + model.id + ')' +
                        ' <a href="#" class="remove" data-model="' + modelId + '">&times;</a>' +
                        '</span>');
                    $selectedDiv.append($tag);
                } else {
                    // Handle case where model is not found in available models
                    // Extract a readable name from the model ID
                    var displayName = modelId;
                    if (modelId.includes('/')) {
                        var parts = modelId.split('/');
                        if (parts.length >= 2) {
                            displayName = parts[1].replace(/-/g, ' ').replace(/_/g, ' ');
                            // Capitalize first letter of each word
                            displayName = displayName.replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                        }
                    }
                    var $tag = $('<span class="llmvm-model-tag">' + 
                        displayName + ' (' + modelId + ')' +
                        ' <a href="#" class="remove" data-model="' + modelId + '">&times;</a>' +
                        '</span>');
                    $selectedDiv.append($tag);
                }
            });
            
            // Add model counter
            var counterText = selectedModels.length + ' / ' + userLimits.max_models_per_prompt + ' models selected';
            var counterColor = selectedModels.length >= userLimits.max_models_per_prompt ? '#d63638' : '#00a32a';
            var $counter = $('<div class="llmvm-model-counter" style="margin-top: 5px; font-size: 12px; color: ' + counterColor + '; font-weight: bold;">' + counterText + '</div>');
            $selectedDiv.append($counter);
        }
        
        // Update hidden input with selected models
        function updateHiddenInput() {
            // Clear existing hidden inputs
            $container.find('input[name="prompt_models[]"]').remove();
            
            // Create new hidden inputs for each selected model
            selectedModels.forEach(function(modelId) {
                $container.append('<input type="hidden" name="prompt_models[]" value="' + modelId + '" />');
            });
        }
        
        // Handle remove button clicks
        $selectedDiv.on('click', '.remove', function(e) {
            e.preventDefault();
            var modelId = $(this).data('model');
            removeModel(modelId);
        });
        
        // Expose methods for external access
        $container.data('addModel', addModel);
        $container.data('removeModel', removeModel);
        $container.data('getSelectedModels', function() { return selectedModels; });
    }
    
    // Initialize all multi-model selectors
    console.log('Looking for .llmvm-multi-model-container elements...');
    var containers = $('.llmvm-multi-model-container');
    console.log('Found', containers.length, 'containers');
    
    // Debug: Check if any elements exist at all
    console.log('Total divs on page:', $('div').length);
    console.log('Total inputs on page:', $('input').length);
    console.log('Elements with llmvm in class:', $('[class*="llmvm"]').length);
    
    containers.each(function() {
        var containerId = $(this).attr('id');
        console.log('Processing container:', containerId);
        if (containerId) {
            initializeMultiModelSelector(containerId);
        }
    });
    
    // Sync textarea content with hidden input fields
    $('.llmvm-prompt-cell textarea').on('input', function() {
        var promptId = $(this).closest('tr').find('input[name="prompt_id"]').val();
        var textareaValue = $(this).val();
        $('input[name="prompt_text"][form*="' + promptId + '"]').val(textareaValue);
    });
    
    // Sync content before form submission to ensure latest content is saved
    var forms = $('form[action*="admin-post.php"]');
    console.log('Found', forms.length, 'forms with admin-post.php action');
    forms.each(function(index) {
        console.log('Form', index, ':', $(this).attr('action'), 'ID:', $(this).attr('id'), 'Class:', $(this).attr('class'));
    });
    
    // Removed conflicting form submission handler
    
    // Handle delete confirmation
    $('.delete-prompt-form').on('submit', function(e) {
        if (!confirm('<?php echo esc_js( __( 'Delete this prompt?', 'llm-visibility-monitor' ) ); ?>')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Removed debugging submit button handler
    
    // Removed all debugging handlers
    
    // Simple approach: just sync model data on form submission
    console.log('=== SETTING UP SIMPLE MODEL SYNC ===');
    
    // Simple approach: sync model data before form submission
    console.log('=== SETTING UP MODEL SYNC ===');
    
    // Removed problematic timer-based sync that was interfering with model display
    
    // Removed click handler - relying on timer sync instead
    
    // Handle form submission for editing prompts
    $('form[action*="admin-post.php"]').on('submit', function(e) {
        var $form = $(this);
        var promptId = $form.find('input[name="prompt_id"]').val();
        
        // Only handle edit prompt forms
        if ($form.find('input[name="action"]').val() === 'llmvm_edit_prompt') {
            console.log('=== EDIT PROMPT FORM SUBMISSION ===');
            console.log('Prompt ID:', promptId);
            
            // Get current text from textarea
            var $textarea = $form.closest('tr').find('textarea[name="prompt_text"]');
            var currentText = $textarea.val();
            console.log('Current text:', currentText);
            
            // Update hidden text field
            $form.find('input[name="prompt_text"]').val(currentText);
            
            // Get current models from the model selector
            var $modelContainer = $form.closest('tr').find('.llmvm-multi-model-container');
            var getSelectedModelsFunction = $modelContainer.data('getSelectedModels');
            var selectedModels = [];
            
            if (typeof getSelectedModelsFunction === 'function') {
                selectedModels = getSelectedModelsFunction();
                console.log('Selected models from function:', selectedModels);
            } else {
                console.log('getSelectedModels function not found');
            }
            
            // Clear existing hidden model inputs in the form
            $form.find('input[name="prompt_models[]"]').remove();
            
            // Create new hidden inputs for each selected model
            selectedModels.forEach(function(modelId) {
                $form.append('<input type="hidden" name="prompt_models[]" value="' + modelId + '" />');
            });
            
            console.log('Updated hidden text:', $form.find('input[name="prompt_text"]').val());
            console.log('Updated hidden models count:', selectedModels.length);
        }
    });
    
    // Add confirmation for "Run All Prompts Now" button
    $('a[href*="llmvm_run_now"]').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalHref = $button.attr('href');
        
        // Calculate total runs needed
        var totalRuns = 0;
        var promptCount = 0;
        
        // Count prompts and models for current user
        <?php if ( ! $is_admin ) : ?>
        // For non-admin users, only count their own prompts
        var userPrompts = <?php echo json_encode( $user_prompts ); ?>;
        userPrompts.forEach(function(prompt) {
            promptCount++;
            if (prompt.models && Array.isArray(prompt.models)) {
                totalRuns += prompt.models.length;
            } else {
                totalRuns += 1; // Fallback for single model
            }
        });
        <?php else : ?>
        // For admin users, count all prompts
        var allPrompts = <?php echo json_encode( $all_prompts ); ?>;
        allPrompts.forEach(function(prompt) {
            promptCount++;
            if (prompt.models && Array.isArray(prompt.models)) {
                totalRuns += prompt.models.length;
            } else {
                totalRuns += 1; // Fallback for single model
            }
        });
        <?php endif; ?>
        
        <?php if ( $is_admin ) : ?>
        // Admin users - simplified confirmation
        var confirmed = confirm('🚀 Run All Prompts Confirmation\n\n' +
                               'Prompts to run: ' + promptCount + '\n' +
                               'Total runs: ' + totalRuns + '\n\n' +
                               'This may take several minutes. Continue?');
        <?php else : ?>
        // Regular users - full confirmation with usage info
        var currentUsage = <?php echo json_encode( LLMVM_Usage_Manager::get_usage_summary( $current_user_id ) ); ?>;
        var remainingRuns = currentUsage.runs.remaining;
        
        // Check if user has enough runs
        if (totalRuns > remainingRuns) {
            alert('❌ Not enough runs remaining!\n\n' +
                  'Runs needed: ' + totalRuns + '\n' +
                  'Runs remaining: ' + remainingRuns + '\n' +
                  'Plan: ' + currentUsage.plan_name + '\n\n' +
                  'Please wait for next month or upgrade your plan.');
            return false;
        }
        
        // Show confirmation
        var confirmed = confirm('🚀 Run All Prompts Confirmation\n\n' +
                               'Prompts to run: ' + promptCount + '\n' +
                               'Total runs: ' + totalRuns + '\n' +
                               'Runs remaining after: ' + (remainingRuns - totalRuns) + '\n' +
                               'Plan: ' + currentUsage.plan_name + '\n\n' +
                               'This may take several minutes. Continue?');
        <?php endif; ?>
        
        if (confirmed) {
            window.location.href = originalHref;
        }
    });
    
    // Add confirmation for individual "Run Now" buttons
    $('a[href*="llmvm_run_single"]').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalHref = $button.attr('href');
        
        // Extract prompt ID from href
        var urlParams = new URLSearchParams(originalHref.split('?')[1]);
        var promptId = urlParams.get('prompt_id');
        
        // Find the prompt data
        var promptData = null;
        <?php if ( ! $is_admin ) : ?>
        var userPrompts = <?php echo json_encode( $user_prompts ); ?>;
        userPrompts.forEach(function(prompt) {
            if (prompt.id === promptId) {
                promptData = prompt;
            }
        });
        <?php else : ?>
        var allPrompts = <?php echo json_encode( $all_prompts ); ?>;
        allPrompts.forEach(function(prompt) {
            if (prompt.id === promptId) {
                promptData = prompt;
            }
        });
        <?php endif; ?>
        
        if (!promptData) {
            alert('❌ Prompt not found!');
            return false;
        }
        
        // Calculate runs needed for this prompt
        var runsNeeded = 1;
        if (promptData.models && Array.isArray(promptData.models)) {
            runsNeeded = promptData.models.length;
        }
        
        <?php if ( $is_admin ) : ?>
        // Admin users - simplified confirmation
        var confirmed = confirm('🚀 Run Prompt Confirmation\n\n' +
                               'Prompt: "' + (promptData.text || 'Untitled').substring(0, 50) + '..."\n' +
                               'Models: ' + runsNeeded + '\n\n' +
                               'Continue?');
        <?php else : ?>
        // Regular users - full confirmation with usage info
        var currentUsage = <?php echo json_encode( LLMVM_Usage_Manager::get_usage_summary( $current_user_id ) ); ?>;
        var remainingRuns = currentUsage.runs.remaining;
        
        // Check if user has enough runs
        if (runsNeeded > remainingRuns) {
            alert('❌ Not enough runs remaining!\n\n' +
                  'Runs needed: ' + runsNeeded + '\n' +
                  'Runs remaining: ' + remainingRuns + '\n' +
                  'Plan: ' + currentUsage.plan_name + '\n\n' +
                  'Please wait for next month or upgrade your plan.');
            return false;
        }
        
        // Show confirmation
        var confirmed = confirm('🚀 Run Prompt Confirmation\n\n' +
                               'Prompt: "' + (promptData.text || 'Untitled').substring(0, 50) + '..."\n' +
                               'Models: ' + runsNeeded + '\n' +
                               'Runs remaining after: ' + (remainingRuns - runsNeeded) + '\n' +
                               'Plan: ' + currentUsage.plan_name + '\n\n' +
                               'Continue?');
        <?php endif; ?>
        
        if (confirmed) {
            window.location.href = originalHref;
        }
    });
    
    } catch (error) {
        console.error('JavaScript error in prompts page:', error);
        console.error('Error stack:', error.stack);
    }
});
</script>
