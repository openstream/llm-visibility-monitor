<?php
/**
 * Direct script to delete all prompt summaries for user ID 3.
 * This script can be executed directly via web browser or command line.
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Check if we're running from command line or web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Web execution - check permissions
    if (!current_user_can('manage_options')) {
        die('Insufficient permissions. Only administrators can run this script.');
    }
    
    echo "<h1>Delete Prompt Summaries for User ID 3</h1>";
    echo "<pre>";
}

$user_id = 3;

// Check if user exists
$user = get_user_by('id', $user_id);
if (!$user) {
    $message = "Error: User with ID $user_id does not exist.";
    if ($is_cli) {
        echo $message . "\n";
        exit(1);
    } else {
        echo $message . "</pre>";
        exit;
    }
}

echo "Deleting all prompt summaries for user ID: $user_id (Username: {$user->user_login})\n";

// Get count before deletion
$summaries_before = LLMVM_Database::get_latest_prompt_summaries($user_id, 999999);
$count_before = count($summaries_before);

echo "Found $count_before prompt summaries for this user.\n";

if ($count_before === 0) {
    $message = "No prompt summaries found for user ID $user_id. Nothing to delete.";
    if ($is_cli) {
        echo $message . "\n";
        exit(0);
    } else {
        echo $message . "</pre>";
        exit;
    }
}

// Delete the summaries
$deleted_count = LLMVM_Database::delete_prompt_summaries_for_user($user_id);

echo "Successfully deleted $deleted_count prompt summaries for user ID $user_id.\n";

// Verify deletion
$summaries_after = LLMVM_Database::get_latest_prompt_summaries($user_id, 999999);
$count_after = count($summaries_after);

if ($count_after === 0) {
    echo "Verification: All prompt summaries have been successfully deleted.\n";
} else {
    echo "Warning: $count_after prompt summaries still remain for user ID $user_id.\n";
}

echo "Script completed.\n";

if (!$is_cli) {
    echo "</pre>";
}
