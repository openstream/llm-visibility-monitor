<?php
/**
 * Debug script for automatic processing issues
 * Run this via: ddev exec "php /var/www/html/wp-content/plugins/llm-visibility-monitor/debug-automatic-processing.php"
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

echo "🔍 LLM Visibility Monitor - Automatic Processing Debug\n";
echo "====================================================\n\n";

// Check WordPress cron status
echo "1. WordPress Cron Status:\n";
echo "   DISABLE_WP_CRON: " . (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'YES' : 'NO') . "\n";
echo "   DOING_CRON: " . (defined('DOING_CRON') && DOING_CRON ? 'YES' : 'NO') . "\n";

// Check queue processing
$next_queue = wp_next_scheduled('llmvm_process_queue');
echo "   Next queue run: " . ($next_queue ? gmdate('Y-m-d H:i:s', $next_queue) : 'NOT SCHEDULED') . "\n\n";

// Check prompts
echo "2. Configured Prompts:\n";
$prompts = get_option('llmvm_prompts', array());
echo "   Total prompts: " . count($prompts) . "\n";

foreach ($prompts as $prompt) {
    $hook = 'llmvm_run_prompt_' . $prompt['id'];
    $next_run = wp_next_scheduled($hook);
    $frequency = $prompt['cron_frequency'] ?? 'daily';
    
    echo "   - Prompt: " . substr($prompt['text'], 0, 30) . "...\n";
    echo "     Frequency: $frequency\n";
    echo "     Next run: " . ($next_run ? gmdate('Y-m-d H:i:s', $next_run) : 'NOT SCHEDULED') . "\n";
    echo "     Is due: " . ($next_run && $next_run <= time() ? 'YES' : 'NO') . "\n";
    echo "     Is overdue: " . ($next_run && $next_run < (time() - 3600) ? 'YES' : 'NO') . "\n\n";
}

// Check queue table
echo "3. Queue Status:\n";
global $wpdb;
$table_name = $wpdb->prefix . 'llmvm_queue';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if ($table_exists) {
    $status_counts = $wpdb->get_results("SELECT status, COUNT(*) as count FROM $table_name GROUP BY status", ARRAY_A);
    echo "   Queue table exists: YES\n";
    echo "   Job counts:\n";
    foreach ($status_counts as $status) {
        echo "     " . $status['status'] . ": " . $status['count'] . "\n";
    }
} else {
    echo "   Queue table exists: NO\n";
}

// Check recent logs
echo "\n4. Recent Log Activity:\n";
$log_file = '/var/www/html/wp-content/plugins/llm-visibility-monitor/llmvm-master.log';
if (file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    $lines = explode("\n", $logs);
    $recent_lines = array_slice($lines, -10);
    
    echo "   Recent log entries:\n";
    foreach ($recent_lines as $line) {
        if (!empty(trim($line))) {
            echo "     " . $line . "\n";
        }
    }
} else {
    echo "   Log file not found\n";
}

echo "\n5. Recommendations:\n";

if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
    echo "   ⚠️  WordPress cron is disabled. You need a system cron job.\n";
    echo "      Add to crontab: * * * * * curl -s 'https://yourdomain.com/wp-cron.php' > /dev/null 2>&1\n";
}

$unscheduled_prompts = 0;
foreach ($prompts as $prompt) {
    $hook = 'llmvm_run_prompt_' . $prompt['id'];
    $next_run = wp_next_scheduled($hook);
    if (!$next_run) {
        $unscheduled_prompts++;
    }
}

if ($unscheduled_prompts > 0) {
    echo "   ⚠️  $unscheduled_prompts prompts are not scheduled. Use 'Reschedule Crons' button.\n";
}

$overdue_prompts = 0;
foreach ($prompts as $prompt) {
    $hook = 'llmvm_run_prompt_' . $prompt['id'];
    $next_run = wp_next_scheduled($hook);
    if ($next_run && $next_run < (time() - 3600)) {
        $overdue_prompts++;
    }
}

if ($overdue_prompts > 0) {
    echo "   ⚠️  $overdue_prompts prompts are overdue. Check if cron is working.\n";
}

echo "\n✅ Debug complete!\n";
