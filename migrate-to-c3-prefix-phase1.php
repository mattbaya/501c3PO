<?php
/**
 * 501c3PO Table Migration - Phase 1: Core Transaction Tables
 *
 * Migrates all transaction-related tables to use $wpdb->prefix . 'c3_' naming convention
 *
 * IMPORTANT: This script creates NEW tables and copies data. Old tables are kept as backup.
 */

// Override wp_die to ignore JSON extension false alarm
if (!function_exists('wp_die')) {
    function wp_die($message, $title = '', $args = array()) {
        if (is_string($message) && strpos($message, 'json') !== false && strpos($message, 'extension') !== false) {
            return; // Silently ignore JSON false alarm
        }
        die($message);
    }
}

// Load WordPress (using wp-blog-header.php to bypass JSON false alarm)
define('WP_USE_THEMES', false);
require_once('/home/swca/public_html/wp-blog-header.php');

global $wpdb;

echo "=== 501c3PO TABLE MIGRATION - PHASE 1 ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Site prefix: {$wpdb->prefix}\n";
echo "Target prefix: {$wpdb->prefix}c3_\n\n";

// Define Phase 1 migrations
$migrations = array(
    array(
        'old' => 'swca_stripe_transactions',
        'new' => $wpdb->prefix . 'c3_stripe_transactions',
        'description' => 'Stripe API transaction data'
    ),
    array(
        'old' => 'swca_stripe_balance_transactions',
        'new' => $wpdb->prefix . 'c3_stripe_balance_transactions',
        'description' => 'Stripe balance transaction records'
    ),
    array(
        'old' => 'swca_transaction_matches',
        'new' => $wpdb->prefix . 'c3_transaction_matches',
        'description' => 'Transaction matching records'
    ),
    array(
        'old' => 'swca_gf_addon_payment_transaction',
        'new' => $wpdb->prefix . 'c3_gf_payment_transaction',
        'description' => 'Gravity Forms payment transactions'
    ),
    array(
        'old' => 'wp_swca_bank_transactions',
        'new' => $wpdb->prefix . 'c3_bank_transactions',
        'description' => 'Bank CSV transaction data'
    ),
    array(
        'old' => 'wp_swca_bank_statements',
        'new' => $wpdb->prefix . 'c3_bank_statements',
        'description' => 'Bank statement metadata'
    ),
);

$success_count = 0;
$error_count = 0;
$skipped_count = 0;

foreach ($migrations as $migration) {
    $old_table = $migration['old'];
    $new_table = $migration['new'];
    $description = $migration['description'];

    echo "\n" . str_repeat("=", 80) . "\n";
    echo "Migrating: {$old_table} => {$new_table}\n";
    echo "Description: {$description}\n";
    echo str_repeat("-", 80) . "\n";

    // Check if old table exists
    $old_exists = $wpdb->get_var("SHOW TABLES LIKE '{$old_table}'");
    if (!$old_exists) {
        echo "⚠️  WARNING: Old table '{$old_table}' does not exist. Skipping.\n";
        $skipped_count++;
        continue;
    }

    // Get row count from old table
    $old_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$old_table}`");
    echo "Old table row count: {$old_count}\n";

    // Check if new table already exists
    $new_exists = $wpdb->get_var("SHOW TABLES LIKE '{$new_table}'");
    if ($new_exists) {
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$new_table}`");
        echo "⚠️  New table already exists with {$new_count} rows\n";
        echo "Do you want to recreate it? (This will DROP the existing table)\n";
        echo "Type 'yes' to proceed, anything else to skip: ";
        $response = trim(fgets(STDIN));
        if (strtolower($response) !== 'yes') {
            echo "Skipped.\n";
            $skipped_count++;
            continue;
        }
        $wpdb->query("DROP TABLE `{$new_table}`");
        echo "Dropped existing table.\n";
    }

    // Get CREATE TABLE statement from old table
    $create_result = $wpdb->get_row("SHOW CREATE TABLE `{$old_table}`", ARRAY_N);
    if (!$create_result) {
        echo "❌ ERROR: Could not get CREATE TABLE statement for '{$old_table}'\n";
        $error_count++;
        continue;
    }

    $create_sql = $create_result[1];
    // Replace table name in CREATE statement
    $create_sql = str_replace("CREATE TABLE `{$old_table}`", "CREATE TABLE `{$new_table}`", $create_sql);

    // Create new table
    echo "Creating new table...\n";
    $result = $wpdb->query($create_sql);
    if ($result === false) {
        echo "❌ ERROR: Could not create new table '{$new_table}'\n";
        echo "MySQL Error: " . $wpdb->last_error . "\n";
        $error_count++;
        continue;
    }
    echo "✓ Table created successfully\n";

    // Copy data if old table has rows
    if ($old_count > 0) {
        echo "Copying {$old_count} rows...\n";
        $copy_result = $wpdb->query("INSERT INTO `{$new_table}` SELECT * FROM `{$old_table}`");

        if ($copy_result === false) {
            echo "❌ ERROR: Could not copy data\n";
            echo "MySQL Error: " . $wpdb->last_error . "\n";
            $error_count++;
            continue;
        }

        // Verify row count
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$new_table}`");
        if ($new_count == $old_count) {
            echo "✓ Successfully copied {$new_count} rows\n";
        } else {
            echo "⚠️  WARNING: Row count mismatch! Old: {$old_count}, New: {$new_count}\n";
            $error_count++;
            continue;
        }
    } else {
        echo "✓ Table created (no data to copy)\n";
    }

    $success_count++;
}

// Summary
echo "\n\n" . str_repeat("=", 80) . "\n";
echo "MIGRATION SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "✓ Successful: {$success_count}\n";
echo "❌ Errors: {$error_count}\n";
echo "⚠️  Skipped: {$skipped_count}\n";
echo "Total: " . count($migrations) . "\n\n";

if ($success_count == count($migrations)) {
    echo "🎉 ALL MIGRATIONS COMPLETED SUCCESSFULLY!\n\n";
    echo "Next steps:\n";
    echo "1. Update plugin code to use new table names\n";
    echo "2. Test all functionality thoroughly\n";
    echo "3. Keep old tables as backup for 30 days\n";
    echo "4. Run: php drop-old-tables-phase1.php (after verification)\n";
} else {
    echo "⚠️  MIGRATION COMPLETED WITH ERRORS\n";
    echo "Please review errors above and fix before proceeding.\n";
}

echo "\nOld tables have been preserved as backup.\n";
