<?php
/**
 * Production Debug Script - Upload this to your WordPress root
 * Access it at: https://southwilliamstown.org/PRODUCTION_DEBUG.php
 */

require_once('wp-load.php');

// Only allow admin users
if (!current_user_can('manage_options')) {
    die('Access denied. Please login as administrator first.');
}

global $wpdb;

echo "<h1>SWCA Production Debugging</h1>";
echo "<pre style='background:#f5f5f5; padding:20px; border:1px solid #ddd;'>";

echo "=== WordPress Configuration ===\n";
echo "Site URL: " . get_site_url() . "\n";
echo "Table Prefix: '" . $wpdb->prefix . "'\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";

echo "\n=== Table Analysis ===\n";
$expected_table = $wpdb->prefix . 'swca_members';
echo "Expected table name: $expected_table\n";

// Check if expected table exists
if ($wpdb->get_var("SHOW TABLES LIKE '$expected_table'") === $expected_table) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM `$expected_table`");
    echo "✅ Table exists with $count members\n";
    
    // Show sample data
    $sample = $wpdb->get_results("SELECT first_name, last_name, email_1 FROM `$expected_table` LIMIT 3");
    echo "Sample members:\n";
    foreach ($sample as $member) {
        echo "  - {$member->first_name} {$member->last_name} ({$member->email_1})\n";
    }
} else {
    echo "❌ Table '$expected_table' NOT FOUND\n";
}

echo "\n=== All SWCA Tables ===\n";
$all_tables = $wpdb->get_col("SHOW TABLES");
$swca_tables = array();
foreach ($all_tables as $table) {
    if (strpos($table, 'swca_') !== false) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        echo "$table - Rows: $count\n";
        $swca_tables[] = $table;
    }
}

echo "\n=== Plugin Status ===\n";
if (function_exists('swca_member_directory_handler')) {
    echo "✅ swca_member_directory_handler function exists\n";
    
    // Test the function directly
    echo "\n=== Testing Member Directory Function ===\n";
    ob_start();
    $output = swca_member_directory_handler(array());
    $captured = ob_get_clean();
    
    if (strpos($output, 'No members found') !== false) {
        echo "❌ Function returns 'No members found'\n";
        echo "This means the function is using wrong table names\n";
    } else {
        echo "✅ Function returns member data\n";
    }
    
    if (!empty($captured)) {
        echo "Function output/errors: $captured\n";
    }
} else {
    echo "❌ swca_member_directory_handler function does not exist\n";
}

echo "\n=== Direct Query Test ===\n";
$test_query = "SELECT COUNT(*) FROM {$wpdb->prefix}swca_members";
echo "Testing query: $test_query\n";
$result = $wpdb->get_var($test_query);
if ($result !== null) {
    echo "✅ Query successful: $result members found\n";
} else {
    echo "❌ Query failed\n";
    if ($wpdb->last_error) {
        echo "Error: " . $wpdb->last_error . "\n";
    }
}

echo "\n=== String Interpolation Test ===\n";
$test_string = "Table name: {$wpdb->prefix}swca_members";
echo "Test string: $test_string\n";
if (strpos($test_string, '{$wpdb->prefix}') !== false) {
    echo "❌ String interpolation not working (single quotes?)\n";
} else {
    echo "✅ String interpolation working\n";
}

echo "</pre>";

echo "<p style='color:red;'>⚠️ DELETE THIS FILE after debugging!</p>";
?>