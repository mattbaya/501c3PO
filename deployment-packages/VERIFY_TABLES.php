<?php
/**
 * Table Verification Script - Upload to WordPress root
 * Access: https://southwilliamstown.org/VERIFY_TABLES.php
 */

require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. Please login as administrator first.');
}

global $wpdb;

echo "<h1>SWCA Table Verification</h1>";
echo "<pre style='background:#f5f5f5; padding:20px; border:1px solid #ddd;'>";

echo "=== WordPress Configuration ===\n";
echo "Site URL: " . get_site_url() . "\n";
echo "Table Prefix: '" . $wpdb->prefix . "'\n";

echo "\n=== Members Table Analysis ===\n";
$members_table = $wpdb->prefix . 'swca_members';
echo "Expected table name: $members_table\n";

// Check if table exists
if ($wpdb->get_var("SHOW TABLES LIKE '$members_table'") === $members_table) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM `$members_table`");
    echo "✅ Table exists with $count members\n\n";
    
    // Show table structure
    echo "=== Table Structure ===\n";
    $columns = $wpdb->get_results("SHOW COLUMNS FROM `$members_table`");
    foreach ($columns as $column) {
        echo "Column: {$column->Field} ({$column->Type})\n";
    }
    
    echo "\n=== Sample Data ===\n";
    $sample = $wpdb->get_results("SELECT first_name, last_name, email_1, email_2, phone, alternate_phone FROM `$members_table` LIMIT 3");
    foreach ($sample as $member) {
        echo "- {$member->first_name} {$member->last_name}\n";
        echo "  Email 1: {$member->email_1}\n";
        echo "  Email 2: {$member->email_2}\n"; 
        echo "  Phone: {$member->phone}\n";
        echo "  Alt Phone: {$member->alternate_phone}\n\n";
    }
    
} else {
    echo "❌ Table '$members_table' NOT FOUND\n";
    echo "The plugin needs to be activated to create the table.\n";
}

echo "\n=== All SWCA Tables ===\n";
$all_tables = $wpdb->get_col("SHOW TABLES");
foreach ($all_tables as $table) {
    if (strpos($table, 'swca_') !== false) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        echo "$table - Rows: $count\n";
    }
}

echo "</pre>";
echo "<p style='color:red;'>⚠️ DELETE THIS FILE after verification!</p>";
?>