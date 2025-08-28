/**
 * LLM Visibility Monitor Admin JavaScript
 */
jQuery(document).ready(function($) {
    // Model selection sync functionality
    var $select = $("#llmvm-model-select");
    var $custom = $("#llmvm-model-custom");
    
    if ($select.length && $custom.length) {
        $select.on("change", function() {
            $custom.val($(this).val());
        });
        
        $custom.on("input", function() {
            var customValue = $(this).val();
            if (customValue && !$select.find("option[value=\"" + customValue + "\"]").length) {
                $select.val("");
            } else {
                $select.val(customValue);
            }
        });
        
        // Update the hidden field when either field changes
        $select.add($custom).on("change input", function() {
            var finalValue = $custom.val() || $select.val();
            $("input[name=\"llmvm_options[model]\"]").val(finalValue);
        });
    }
});
