<?php
/**
 * SWCA Diagnostic Script - Run this on your production site
 * 
 * Upload this file to your WordPress root directory and access it directly
 * Example: https://southwilliamstown.org/DIAGNOSTIC_SCRIPT.php
 */

// Load WordPress
require_once('wp-load.php');

// Only allow admin users
if (!current_user_can('manage_options')) {
    die('Access denied. Please login as administrator.');
}

global $wpdb;

echo "<h1>SWCA Membership Plugin Diagnostic</h1>";
echo "<pre style='background:#f5f5f5; padding:20px; border:1px solid #ddd;'>";

echo "=== 1. WordPress Configuration ===\n";
echo "Site URL: " . get_site_url() . "\n";
echo "Table Prefix: " . $wpdb->prefix . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . phpversion() . "\n";

echo "\n=== 2. SWCA Tables Analysis ===\n";
$expected_table = $wpdb->prefix . 'swca_members';
echo "Expected members table: $expected_table\n";

// Check if expected table exists
if ($wpdb->get_var("SHOW TABLES LIKE '$expected_table'") === $expected_table) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM `$expected_table`");
    echo "✅ Expected table exists with $count members\n";
} else {
    echo "❌ Expected table NOT FOUND\n";
}

// Check for tables with wrong prefix
echo "\n=== 3. All SWCA Tables Found ===\n";
$all_tables = $wpdb->get_col("SHOW TABLES");
$swca_tables = array();
foreach ($all_tables as $table) {
    if (strpos($table, 'swca_') !== false) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        echo "$table - Rows: $count\n";
        $swca_tables[] = $table;
    }
}

// Specific check for wp_swca_members
if ($wpdb->prefix !== 'wp_' && in_array('wp_swca_members', $swca_tables)) {
    echo "\n⚠️  PROBLEM DETECTED: Found 'wp_swca_members' table but your prefix is '{$wpdb->prefix}'\n";
    echo "This means the import created tables with the wrong prefix!\n";
}

echo "\n=== 4. Plugin File Check ===\n";
$plugin_file = ABSPATH . 'wp-content/plugins/swca-membership-management/swca-membership-management.php';
$plugin_file_alt = ABSPATH . 'wp-content/plugins/swca-membership-export-corrected/swca-membership-export-corrected.php';

if (file_exists($plugin_file)) {
    echo "✅ Plugin file found: swca-membership-management.php\n";
    // Check version
    $plugin_content = file_get_contents($plugin_file);
    if (preg_match('/Version:\s*([0-9.]+)/', $plugin_content, $matches)) {
        echo "Plugin Version: " . $matches[1] . "\n";
    }
} elseif (file_exists($plugin_file_alt)) {
    echo "✅ Plugin file found: swca-membership-export-corrected.php\n";
} else {
    echo "❌ Plugin file not found\n";
}

echo "\n=== 5. Test Member Directory Query ===\n";
$test_query = "SELECT * FROM {$wpdb->prefix}swca_members ORDER BY last_name, first_name LIMIT 5";
echo "Running query: $test_query\n";

$results = $wpdb->get_results($test_query);
if ($results) {
    echo "✅ Query successful, found " . count($results) . " members:\n";
    foreach ($results as $member) {
        echo "  - {$member->first_name} {$member->last_name}\n";
    }
} else {
    echo "❌ Query returned no results\n";
    $error = $wpdb->last_error;
    if ($error) {
        echo "Database error: $error\n";
    }
}

echo "\n=== 6. Recommendations ===\n";
if ($wpdb->prefix !== 'wp_' && in_array('wp_swca_members', $swca_tables)) {
    echo "SOLUTION: Your data is in the wrong tables. You need to:\n";
    echo "1. Rename all wp_swca_* tables to {$wpdb->prefix}swca_*\n";
    echo "2. Or re-import with the fixed plugin version\n";
}

echo "</pre>";
echo "<p><a href='" . admin_url() . "'>Return to WordPress Admin</a></p>";

// Security: Delete this file after use
echo "<p style='color:red;'>⚠️ SECURITY: Delete this diagnostic file after use!</p>";
?>