<?php
/**
 * Script to regenerate .mo files from .po files
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

$languages_dir = dirname(__FILE__) . '/languages/';

// List of language files to process
$languages = [
    'de_DE',
    'de_DE_formal', 
    'de_CH',
    'de_CH_informal'
];

foreach ($languages as $lang) {
    $po_file = $languages_dir . "llm-visibility-monitor-{$lang}.po";
    $mo_file = $languages_dir . "llm-visibility-monitor-{$lang}.mo";
    
    if (file_exists($po_file)) {
        // Simple .mo file generation (basic implementation)
        $po_content = file_get_contents($po_file);
        
        // Parse .po file and create .mo file
        $entries = [];
        $lines = explode("\n", $po_content);
        $current_msgid = '';
        $current_msgstr = '';
        $in_msgid = false;
        $in_msgstr = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'msgid "') === 0) {
                $in_msgid = true;
                $in_msgstr = false;
                $current_msgid = substr($line, 7, -1);
            } elseif (strpos($line, 'msgstr "') === 0) {
                $in_msgid = false;
                $in_msgstr = true;
                $current_msgstr = substr($line, 8, -1);
            } elseif ($in_msgid && strpos($line, '"') === 0) {
                $current_msgid .= substr($line, 1, -1);
            } elseif ($in_msgstr && strpos($line, '"') === 0) {
                $current_msgstr .= substr($line, 1, -1);
            } elseif (empty($line) && $current_msgid && $current_msgstr) {
                if (!empty($current_msgid) && !empty($current_msgstr)) {
                    $entries[$current_msgid] = $current_msgstr;
                }
                $current_msgid = '';
                $current_msgstr = '';
                $in_msgid = false;
                $in_msgstr = false;
            }
        }
        
        // Write .mo file (simplified format)
        $mo_content = '';
        foreach ($entries as $msgid => $msgstr) {
            $mo_content .= $msgid . "\0" . $msgstr . "\0";
        }
        
        file_put_contents($mo_file, $mo_content);
        echo "Generated {$lang}.mo from {$lang}.po\n";
    } else {
        echo "PO file not found: {$po_file}\n";
    }
}

echo "MO file regeneration completed.\n";
