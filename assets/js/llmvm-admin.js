/**
 * LLM Visibility Monitor Admin JavaScript
 */
jQuery(document).ready(function($) {
    // Only run model selection sync on settings page
    if (window.location.href.indexOf('page=llmvm-settings') !== -1) {
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
    }
    
    // Bulk actions functionality
    var $selectAll1 = $("#cb-select-all-1");
    var $selectAll2 = $("#cb-select-all-2");
    var $checkboxes = $("input[name=\"result_ids[]\"]");
    
    // Handle "select all" checkboxes
    $selectAll1.add($selectAll2).on("change", function() {
        var isChecked = $(this).is(":checked");
        $checkboxes.prop("checked", isChecked);
        updateBulkActionButton();
    });
    
    // Handle individual checkboxes
    $checkboxes.on("change", function() {
        updateSelectAllCheckboxes();
        updateBulkActionButton();
    });
    
    // Update "select all" checkboxes based on individual selections
    function updateSelectAllCheckboxes() {
        var totalCheckboxes = $checkboxes.length;
        var checkedCheckboxes = $checkboxes.filter(":checked").length;
        
        if (checkedCheckboxes === 0) {
            $selectAll1.add($selectAll2).prop("checked", false).prop("indeterminate", false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $selectAll1.add($selectAll2).prop("checked", true).prop("indeterminate", false);
        } else {
            $selectAll1.add($selectAll2).prop("checked", false).prop("indeterminate", true);
        }
    }
    
    // Update bulk action button state
    function updateBulkActionButton() {
        var checkedCount = $checkboxes.filter(":checked").length;
        var $bulkActionButtons = $("#llmvm-bulk-actions-form input[type=\"submit\"]");
        
        if (checkedCount > 0) {
            $bulkActionButtons.prop("disabled", false);
        } else {
            $bulkActionButtons.prop("disabled", true);
        }
    }
    
    // Initialize bulk action button state
    updateBulkActionButton();
    
    // Sync bulk action dropdowns
    $("#bulk-action-selector-top, #bulk-action-selector-bottom").on("change", function() {
        var selectedValue = $(this).val();
        $("#bulk-action-selector-top, #bulk-action-selector-bottom").val(selectedValue);
    });

    // Handle bulk action form submission
    $("#llmvm-bulk-actions-form").on("submit", function(e) {
        var $form = $(this);
        var $select = $form.find("select[name=\"bulk_action\"]");
        var selectedAction = $select.val();
        var checkedCount = $checkboxes.filter(":checked").length;
        
        if (selectedAction === "-1" || checkedCount === 0) {
            e.preventDefault();
            alert("Please select an action and at least one result.");
            return false;
        }
        
        if (selectedAction === "delete") {
            if (!confirm("Are you sure you want to delete " + checkedCount + " result(s)?")) {
                e.preventDefault();
                return false;
            }
        }
    });
});
