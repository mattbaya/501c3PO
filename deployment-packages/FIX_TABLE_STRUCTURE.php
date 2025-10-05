<?php
/**
 * Fix SWCA Members Table Structure - Upload to WordPress root
 * This script adds missing columns to the existing swca_members table
 * Access: https://southwilliamstown.org/FIX_TABLE_STRUCTURE.php
 */

require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. Please login as administrator first.');
}

global $wpdb;

echo "<h1>SWCA Table Structure Fix</h1>";
echo "<pre style='background:#f5f5f5; padding:20px; border:1px solid #ddd;'>";

$members_table = $wpdb->prefix . 'swca_members';
echo "Working on table: $members_table\n\n";

// Check current columns
echo "=== Current Table Structure ===\n";
$current_columns = $wpdb->get_results("SHOW COLUMNS FROM `$members_table`");
$existing_columns = array();
foreach ($current_columns as $column) {
    $existing_columns[] = $column->Field;
    echo "✓ {$column->Field}\n";
}

echo "\n=== Adding Missing Columns ===\n";

// Define all required columns with their SQL definitions
$required_columns = array(
    'email_2' => "varchar(100) DEFAULT '' NOT NULL",
    'email_3' => "varchar(100) DEFAULT '' NOT NULL", 
    'email_4' => "varchar(100) DEFAULT '' NOT NULL",
    'alternate_phone' => "varchar(20) DEFAULT '' NOT NULL",
    'partner_first_name' => "varchar(100) DEFAULT '' NOT NULL",
    'partner_last_name' => "varchar(100) DEFAULT '' NOT NULL",
    'family_members' => "text DEFAULT '' NOT NULL",
    'alternate_address' => "text DEFAULT '' NOT NULL",
    'city' => "varchar(100) DEFAULT '' NOT NULL",
    'state' => "varchar(20) DEFAULT '' NOT NULL",
    'zip_code' => "varchar(20) DEFAULT '' NOT NULL",
    'membership_type' => "varchar(50) DEFAULT '' NOT NULL",
    'status_2024_2025' => "varchar(50) DEFAULT '' NOT NULL",
    'status_2023_2024' => "varchar(50) DEFAULT '' NOT NULL",
    'membership_amount' => "decimal(10,2) DEFAULT 0.00",
    'donation_amount' => "decimal(10,2) DEFAULT 0.00",
    'total_amount' => "decimal(10,2) DEFAULT 0.00",
    'payment_type' => "varchar(50) DEFAULT '' NOT NULL",
    'business_affiliation' => "varchar(200) DEFAULT '' NOT NULL",
    'on_swca_email_list' => "tinyint(1) DEFAULT 1",
    'notes' => "text DEFAULT '' NOT NULL",
    'membership_month' => "varchar(20) DEFAULT '' NOT NULL",
    'membership_month_2023' => "varchar(20) DEFAULT '' NOT NULL",
    'tags' => "text DEFAULT '' NOT NULL",
    'categories' => "text DEFAULT '' NOT NULL",
    'created_at' => "datetime DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

$added_count = 0;
foreach ($required_columns as $column_name => $column_definition) {
    if (!in_array($column_name, $existing_columns)) {
        $sql = "ALTER TABLE `$members_table` ADD COLUMN `$column_name` $column_definition";
        
        $result = $wpdb->query($sql);
        if ($result !== false) {
            echo "✅ Added column: $column_name\n";
            $added_count++;
        } else {
            echo "❌ Failed to add column: $column_name\n";
            echo "   Error: " . $wpdb->last_error . "\n";
        }
    } else {
        echo "⏩ Column already exists: $column_name\n";
    }
}

echo "\n=== Summary ===\n";
echo "Columns added: $added_count\n";

// Verify final structure
echo "\n=== Final Table Structure ===\n";
$final_columns = $wpdb->get_results("SHOW COLUMNS FROM `$members_table`");
echo "Total columns: " . count($final_columns) . "\n";

// Test a sample query to make sure no undefined property warnings
echo "\n=== Testing Sample Query ===\n";
$test_member = $wpdb->get_row("SELECT * FROM `$members_table` LIMIT 1");
if ($test_member) {
    echo "✅ Sample member loaded successfully\n";
    echo "- Name: {$test_member->first_name} {$test_member->last_name}\n";
    echo "- Email 1: {$test_member->email_1}\n";
    echo "- Email 2: {$test_member->email_2}\n";
    echo "- Phone: {$test_member->phone}\n";
    echo "- Alt Phone: {$test_member->alternate_phone}\n";
} else {
    echo "❌ No members found in table\n";
}

echo "\n✅ Table structure fix completed!\n";
echo "</pre>";
echo "<p style='color:red;'>⚠️ DELETE THIS FILE after running!</p>";
echo "<p><strong>Next step:</strong> Test the Member Directory at your WordPress dashboard.</p>";
?>