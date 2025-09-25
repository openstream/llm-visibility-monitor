<?php
/**
 * Test script to verify scheduled prompt processing
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

echo "🧪 Testing Scheduled Prompt Processing\n";
echo "=====================================\n\n";

// Get the first prompt
$prompts = get_option('llmvm_prompts', array());
if (empty($prompts)) {
    echo "❌ No prompts found\n";
    exit;
}

$prompt = $prompts[0];
$hook = 'llmvm_run_prompt_' . $prompt['id'];

echo "1. Current prompt: " . substr($prompt['text'], 0, 50) . "...\n";
echo "2. Current schedule: " . (wp_next_scheduled($hook) ? gmdate('Y-m-d H:i:s', wp_next_scheduled($hook)) : 'Not scheduled') . "\n";

// Clear existing schedule
wp_clear_scheduled_hook($hook);
echo "3. Cleared existing schedule\n";

// Schedule for 1 minute ago (overdue)
wp_schedule_single_event(time() - 60, $hook);
echo "4. Scheduled for 1 minute ago (overdue)\n";

// Check if it's now due
$next_run = wp_next_scheduled($hook);
$is_due = $next_run && $next_run <= time();
echo "5. Is due: " . ($is_due ? 'YES' : 'NO') . "\n";

if ($is_due) {
    echo "6. Triggering manual cron to process...\n";
    
    // Trigger wp-cron manually
    curl_exec(curl_init("https://llm-visibility-monitor.openstream.ch.ddev.site/wp-cron.php"));
    
    echo "7. Check logs for 'Triggering scheduled prompt via queue processing'\n";
} else {
    echo "❌ Prompt is not due\n";
}

echo "\n✅ Test complete!\n";
