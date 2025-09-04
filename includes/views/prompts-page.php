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
        if (currentModelsData && Array.isArray(currentModelsData)) {
            selectedModels = currentModelsData.slice(); // Copy the array
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
                }
            });
        }
        
        // Update hidden input with selected models
        function updateHiddenInput() {
            $hiddenInput.val(selectedModels.join(','));
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
    
    console.log('=== ATTACHING FORM SUBMISSION HANDLER ===');
    $('form[action*="admin-post.php"]').on('submit', function(e) {
        var $form = $(this);
        var promptId = $form.find('input[name="prompt_id"]').val();
        console.log('=== FORM SUBMISSION STARTING ===');
        console.log('Form submitted! Event triggered.');
        console.log('Form prompt ID:', promptId);
        console.log('Form action:', $form.attr('action'));
        
        // Temporary alert to test if this handler is being called
        alert('Form submission handler called!');
        
        // Sync textarea content
        var $textarea = $form.closest('tr').find('.llmvm-prompt-cell textarea');
        var $hiddenTextInput = $form.find('input[name="prompt_text"]');
        if ($textarea.length && $hiddenTextInput.length) {
            $hiddenTextInput.val($textarea.val());
        }
        
        // Sync multi-model selector content
        // For "Your Prompts" section, the model container is in the same table cell as the form
        var $modelContainer = $form.closest('td').find('.llmvm-multi-model-container');
        console.log('Form submission - Looking in td, found containers:', $modelContainer.length);
        if ($modelContainer.length === 0) {
            // Fallback: try to find it in the closest tr
            $modelContainer = $form.closest('tr').find('.llmvm-multi-model-container');
            console.log('Form submission - Looking in tr, found containers:', $modelContainer.length);
        }
        
        // Additional debugging: check if we can find the container by ID
        if ($modelContainer.length === 0) {
            console.log('Form submission - Trying to find container by ID pattern');
            var promptId = $form.find('input[name="prompt_id"]').val();
            if (promptId) {
                var containerId = 'llmvm-prompt-models-container-' + promptId;
                $modelContainer = $('#' + containerId.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&'));
                console.log('Form submission - Looking for container ID:', containerId);
                console.log('Form submission - Found by ID:', $modelContainer.length);
            }
        }
        var $hiddenModelInput = $form.find('input[name="prompt_models[]"]');
        console.log('Form submission - Model container found:', $modelContainer.length > 0);
        console.log('Form submission - Hidden model input found:', $hiddenModelInput.length > 0);
        console.log('Form submission - Model container ID:', $modelContainer.attr('id'));
        
        if ($modelContainer.length && $hiddenModelInput.length) {
            var getSelectedModelsFunction = $modelContainer.data('getSelectedModels');
            console.log('Form submission - getSelectedModels function exists:', typeof getSelectedModelsFunction === 'function');
            if (typeof getSelectedModelsFunction === 'function') {
                var selectedModels = getSelectedModelsFunction();
                console.log('Form submission - Selected models:', selectedModels);
                $hiddenModelInput.val(selectedModels.join(','));
                console.log('Form submission - Hidden input value set to:', $hiddenModelInput.val());
            } else {
                console.log('Form submission - getSelectedModels function not found');
            }
        }
        console.log('=== FORM SUBMISSION COMPLETE ===');
    });
    
    // Handle delete confirmation
    $('.delete-prompt-form').on('submit', function(e) {
        if (!confirm('<?php echo esc_js( __( 'Delete this prompt?', 'llm-visibility-monitor' ) ); ?>')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Debug: Check submit buttons using multiple event types
    $(document).on('click', 'input[type="submit"]', function(e) {
        console.log('=== SUBMIT BUTTON CLICKED ===');
        console.log('Button value:', $(this).val());
        console.log('Button name:', $(this).attr('name'));
        console.log('Button form:', $(this).closest('form').attr('action'));
        console.log('Form has admin-post.php action:', $(this).closest('form').attr('action').indexOf('admin-post.php') !== -1);
        
        // If this is a save button, prevent default and handle manually
        if ($(this).val() === 'Speichern') {
            console.log('=== INTERCEPTING SAVE BUTTON CLICK ===');
            e.preventDefault();
            
            var $form = $(this).closest('form');
            console.log('Form found:', $form.length);
            
            // Sync the model data first
            var $modelContainer = $form.closest('td').find('.llmvm-multi-model-container');
            if ($modelContainer.length === 0) {
                $modelContainer = $form.closest('tr').find('.llmvm-multi-model-container');
            }
            var $hiddenModelInput = $form.find('input[name="prompt_models[]"]');
            
            console.log('Model container found:', $modelContainer.length);
            console.log('Hidden model input found:', $hiddenModelInput.length);
            
            if ($modelContainer.length && $hiddenModelInput.length) {
                var getSelectedModelsFunction = $modelContainer.data('getSelectedModels');
                if (typeof getSelectedModelsFunction === 'function') {
                    var selectedModels = getSelectedModelsFunction();
                    console.log('Selected models:', selectedModels);
                    $hiddenModelInput.val(selectedModels.join(','));
                    console.log('Hidden input value set to:', $hiddenModelInput.val());
                }
            }
            
            // Now submit the form
            console.log('=== MANUALLY SUBMITTING FORM ===');
            $form[0].submit();
        }
    });
    
    // Also try mousedown and mouseup events
    $(document).on('mousedown', 'input[type="submit"]', function(e) {
        console.log('=== SUBMIT BUTTON MOUSEDOWN ===');
        console.log('Button value:', $(this).val());
    });
    
    $(document).on('mouseup', 'input[type="submit"]', function(e) {
        console.log('=== SUBMIT BUTTON MOUSEUP ===');
        console.log('Button value:', $(this).val());
    });
    
    // Try form submit event as well
    $(document).on('submit', 'form[action*="admin-post.php"]', function(e) {
        console.log('=== FORM SUBMIT EVENT ===');
        console.log('Form action:', $(this).attr('action'));
        console.log('Form method:', $(this).attr('method'));
    });
    
    // Try to catch any click on the page
    $(document).on('click', '*', function(e) {
        if ($(this).is('input[type="submit"]')) {
            console.log('=== ANY CLICK ON SUBMIT BUTTON ===');
            console.log('Button value:', $(this).val());
            console.log('Button form:', $(this).closest('form').attr('action'));
        }
    });
    
    // Check for JavaScript errors
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        console.log('=== JAVASCRIPT ERROR ===');
        console.log('Error:', msg);
        console.log('URL:', url);
        console.log('Line:', lineNo);
        console.log('Column:', columnNo);
        console.log('Error object:', error);
        return false;
    };
    
    // Try to add event listeners directly to the submit buttons
    setTimeout(function() {
        $('input[type="submit"]').each(function(index) {
            var $btn = $(this);
            console.log('Adding direct event listener to button', index, ':', $btn.val());
            
            // Try native addEventListener
            if (this.addEventListener) {
                this.addEventListener('click', function(e) {
                    console.log('=== NATIVE CLICK EVENT ===');
                    console.log('Button value:', this.value);
                    console.log('Button form:', this.form.action);
                });
            }
            
            // Try jQuery on
            $btn.on('click', function(e) {
                console.log('=== JQUERY CLICK EVENT ===');
                console.log('Button value:', $(this).val());
                console.log('Button form:', $(this).closest('form').attr('action'));
            });
        });
        
        // Add a very simple test - click on any element
        $(document).on('click', '*', function(e) {
            if ($(this).is('input[type="submit"]')) {
                console.log('=== ANY ELEMENT CLICK ON SUBMIT BUTTON ===');
                console.log('Button value:', $(this).val());
                console.log('Button form:', $(this).closest('form').attr('action'));
            }
        });
        
        // Test if we can programmatically click the button
        setTimeout(function() {
            console.log('=== TESTING PROGRAMMATIC CLICK ===');
            var $saveButtons = $('input[type="submit"][value="Speichern"]');
            console.log('Found save buttons:', $saveButtons.length);
            if ($saveButtons.length > 0) {
                console.log('Testing click on first save button');
                $saveButtons.first().trigger('click');
            }
        }, 2000);
    }, 1000);
    
    // Try to intercept form submission at the document level
    // Removed conflicting document-level form submission handler
    
    // Also try to catch any form submission at the window level
    window.addEventListener('beforeunload', function(e) {
        console.log('=== WINDOW BEFOREUNLOAD ===');
        console.log('Page is about to unload');
    });
    
    // Try to catch any navigation
    window.addEventListener('unload', function(e) {
        console.log('=== WINDOW UNLOAD ===');
        console.log('Page is unloading');
    });
    
    // Check if the form is actually submitting by monitoring the page
    var originalSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function() {
        console.log('=== NATIVE FORM SUBMIT CALLED ===');
        console.log('Form action:', this.action);
        console.log('Form method:', this.method);
        return originalSubmit.call(this);
    };
    
    // Debug: Check if submit buttons exist
    console.log('Submit buttons found:', $('input[type="submit"]').length);
    $('input[type="submit"]').each(function(index) {
        console.log('Submit button', index, ':', $(this).val(), 'in form:', $(this).closest('form').attr('action'));
        console.log('Button HTML:', this.outerHTML);
        console.log('Form HTML:', $(this).closest('form')[0].outerHTML);
    });
    
    // Debug: Check save forms without triggering submission
    setTimeout(function() {
        console.log('=== CHECKING SAVE FORMS ===');
        var $saveForms = $('form[action*="admin-post.php"]').has('input[type="submit"][value*="Speichern"]');
        console.log('Found save forms:', $saveForms.length);
        $saveForms.each(function(index) {
            console.log('Save form', index, ':', $(this).attr('action'));
            console.log('Form has prompt_id:', $(this).find('input[name="prompt_id"]').length > 0);
        });
        
        // Add a simple test to see if we can detect any form submission
        console.log('=== ADDING FORM SUBMISSION TEST ===');
        $saveForms.each(function(index) {
            var $form = $(this);
            console.log('Adding test handler to form', index);
            
            var $submitButton = $form.find('input[type="submit"]');
            console.log('Submit button found:', $submitButton.length);
            console.log('Submit button is visible:', $submitButton.is(':visible'));
            console.log('Submit button is enabled:', !$submitButton.prop('disabled'));
            console.log('Submit button CSS display:', $submitButton.css('display'));
            console.log('Submit button CSS pointer-events:', $submitButton.css('pointer-events'));
            
            // Add a simple click handler to the submit button
            $submitButton.on('click', function(e) {
                console.log('=== SUBMIT BUTTON CLICKED (TEST) ===');
                console.log('Button value:', $(this).val());
                console.log('Form action:', $form.attr('action'));
                
                // Don't prevent default, let it submit normally
                // But add a small delay to see if we can catch it
                setTimeout(function() {
                    console.log('=== FORM SHOULD HAVE SUBMITTED BY NOW ===');
                }, 100);
            });
            
            // Also try mousedown and mouseup events
            $submitButton.on('mousedown', function(e) {
                console.log('=== SUBMIT BUTTON MOUSEDOWN (TEST) ===');
            });
            
            $submitButton.on('mouseup', function(e) {
                console.log('=== SUBMIT BUTTON MOUSEUP (TEST) ===');
            });
            
            // Try to catch any mouse events on the button
            $submitButton.on('mouseenter', function(e) {
                console.log('=== SUBMIT BUTTON MOUSEENTER (TEST) ===');
            });
            
            $submitButton.on('mouseleave', function(e) {
                console.log('=== SUBMIT BUTTON MOUSELEAVE (TEST) ===');
            });
            
            // Try to catch any touch events (for mobile)
            $submitButton.on('touchstart', function(e) {
                console.log('=== SUBMIT BUTTON TOUCHSTART (TEST) ===');
            });
            
            // Try to catch any focus events
            $submitButton.on('focus', function(e) {
                console.log('=== SUBMIT BUTTON FOCUS (TEST) ===');
            });
            
            // Try to catch any blur events
            $submitButton.on('blur', function(e) {
                console.log('=== SUBMIT BUTTON BLUR (TEST) ===');
            });
            
            // Remove the red background and onclick alert
            $submitButton.css('background-color', '');
            $submitButton.removeAttr('onclick');
        });
    }, 2000);
    } catch (error) {
        console.error('JavaScript error in prompts page:', error);
        console.error('Error stack:', error.stack);
    }
});
</script>
