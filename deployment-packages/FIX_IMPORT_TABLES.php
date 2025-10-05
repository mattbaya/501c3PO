<?php
/**
 * Fix Import Tables Script - Run this ONCE to fix the table prefix issue
 * Upload to WordPress root and access: https://southwilliamstown.org/FIX_IMPORT_TABLES.php
 */

require_once('wp-load.php');

// Only allow admin users
if (!current_user_can('manage_options')) {
    die('Access denied. Please login as administrator first.');
}

global $wpdb;

echo "<h1>Fixing SWCA Import Table Prefixes</h1>";
echo "<pre style='background:#f5f5f5; padding:20px; border:1px solid #ddd;'>";

echo "Current table prefix: '{$wpdb->prefix}'\n";
echo "Expected table format: {$wpdb->prefix}swca_*\n\n";

// Tables that should be renamed from wp_swca_* to {prefix}swca_*
$tables_to_fix = array(
    'wp_swca_committees',
    'wp_swca_drive_folders', 
    'wp_swca_email_templates',
    'wp_swca_agendas',
    'wp_swca_committee_reports',
    'wp_swca_documents',
    'wp_swca_emails',
    'wp_swca_events',
    'wp_swca_financial_reports',
    'wp_swca_minutes',
    'wp_swca_volunteer_slots'
);

$renamed_count = 0;

foreach ($tables_to_fix as $old_table) {
    // Check if old table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'") === $old_table) {
        $new_table = str_replace('wp_swca_', $wpdb->prefix . 'swca_', $old_table);
        
        // Check if new table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$new_table'") === $new_table) {
            echo "⚠️  $new_table already exists, skipping $old_table\n";
        } else {
            // Rename the table
            $result = $wpdb->query("RENAME TABLE `$old_table` TO `$new_table`");
            if ($result !== false) {
                echo "✅ Renamed $old_table → $new_table\n";
                $renamed_count++;
            } else {
                echo "❌ Failed to rename $old_table\n";
            }
        }
    } else {
        echo "⚠️  Table $old_table doesn't exist\n";
    }
}

echo "\n=== CRITICAL: Members Table Missing ===\n";
echo "The main members table 'wp_swca_members' was never created during import!\n";
echo "This means the member data was never actually imported.\n\n";

echo "📋 NEXT STEPS:\n";
echo "1. The import failed to create the members table\n";
echo "2. You need to re-run the import with the fixed plugin\n";
echo "3. The import should now work correctly\n\n";

echo "Summary: Renamed $renamed_count tables\n";

echo "</pre>";

echo "<p><strong>Now re-upload the plugin and try importing the member data again!</strong></p>";
echo "<p style='color:red;'>⚠️ DELETE THIS FILE after running!</p>";
?>