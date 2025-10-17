<?php
/**
 * 501c3PO Table Migration - Phase 1: Core Transaction Tables
 * Standalone version using mysqli (no WordPress dependency)
 */

// Database credentials
$host = 'localhost';
$user = 'swca_swca2019';
$pass = '5Corners!';
$dbname = 'swca_swca2019';

// Connect to database
$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// WordPress table prefix (from swca_options)
$prefix = 'swca_';

echo "=== 501c3PO TABLE MIGRATION - PHASE 1 ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Database: {$dbname}\n";
echo "Prefix: {$prefix}\n";
echo "Target: {$prefix}c3_\n\n";

// Define Phase 1 migrations
$migrations = array(
    array(
        'old' => 'swca_stripe_transactions',
        'new' => $prefix . 'c3_stripe_transactions',
        'desc' => 'Stripe API transactions'
    ),
    array(
        'old' => 'swca_stripe_balance_transactions',
        'new' => $prefix . 'c3_stripe_balance_transactions',
        'desc' => 'Stripe balance transactions'
    ),
    array(
        'old' => 'swca_transaction_matches',
        'new' => $prefix . 'c3_transaction_matches',
        'desc' => 'Transaction matches'
    ),
    array(
        'old' => 'swca_gf_addon_payment_transaction',
        'new' => $prefix . 'c3_gf_payment_transaction',
        'desc' => 'Gravity Forms payments'
    ),
    array(
        'old' => 'wp_swca_bank_transactions',
        'new' => $prefix . 'c3_bank_transactions',
        'desc' => 'Bank CSV transactions'
    ),
    array(
        'old' => 'wp_swca_bank_statements',
        'new' => $prefix . 'c3_bank_statements',
        'desc' => 'Bank statements'
    ),
);

$success = 0;
$errors = 0;
$skipped = 0;

foreach ($migrations as $m) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "Table: {$m['old']} => {$m['new']}\n";
    echo "Description: {$m['desc']}\n";
    echo str_repeat("-", 80) . "\n";

    // Check if old table exists
    $result = $mysqli->query("SHOW TABLES LIKE '{$m['old']}'");
    if ($result->num_rows == 0) {
        echo "⚠️  Old table does not exist. Skipping.\n";
        $skipped++;
        continue;
    }

    // Get row count
    $count_result = $mysqli->query("SELECT COUNT(*) as cnt FROM `{$m['old']}`");
    $count = $count_result->fetch_assoc()['cnt'];
    echo "Rows in old table: {$count}\n";

    // Check if new table exists
    $result = $mysqli->query("SHOW TABLES LIKE '{$m['new']}'");
    if ($result->num_rows > 0) {
        echo "⚠️  New table already exists. Dropping...\n";
        $mysqli->query("DROP TABLE `{$m['new']}`");
    }

    // Get CREATE TABLE statement
    $result = $mysqli->query("SHOW CREATE TABLE `{$m['old']}`");
    if (!$result) {
        echo "❌ ERROR: Could not get CREATE statement\n";
        $errors++;
        continue;
    }

    $row = $result->fetch_assoc();
    $create_sql = $row['Create Table'];
    $create_sql = str_replace("CREATE TABLE `{$m['old']}`", "CREATE TABLE `{$m['new']}`", $create_sql);

    // Create new table
    echo "Creating table...";
    if (!$mysqli->query($create_sql)) {
        echo " ❌ FAILED\n";
        echo "Error: " . $mysqli->error . "\n";
        $errors++;
        continue;
    }
    echo " ✓\n";

    // Copy data
    if ($count > 0) {
        echo "Copying {$count} rows...";
        if (!$mysqli->query("INSERT INTO `{$m['new']}` SELECT * FROM `{$m['old']}`")) {
            echo " ❌ FAILED\n";
            echo "Error: " . $mysqli->error . "\n";
            $errors++;
            continue;
        }

        // Verify
        $verify_result = $mysqli->query("SELECT COUNT(*) as cnt FROM `{$m['new']}`");
        $verify_count = $verify_result->fetch_assoc()['cnt'];

        if ($verify_count == $count) {
            echo " ✓ ({$verify_count} rows)\n";
            $success++;
        } else {
            echo " ⚠️  Count mismatch: {$verify_count} vs {$count}\n";
            $errors++;
        }
    } else {
        echo "No data to copy. ✓\n";
        $success++;
    }
}

// Summary
echo "\n\n" . str_repeat("=", 80) . "\n";
echo "MIGRATION SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "✓ Successful: {$success}\n";
echo "❌ Errors: {$errors}\n";
echo "⚠️  Skipped: {$skipped}\n";
echo "Total: " . count($migrations) . "\n\n";

if ($success == count($migrations)) {
    echo "🎉 ALL MIGRATIONS COMPLETED SUCCESSFULLY!\n\n";
    echo "Next steps:\n";
    echo "1. Verify new tables:\n";
    foreach ($migrations as $m) {
        echo "   - {$m['new']}\n";
    }
    echo "\n2. Update plugin code to use new table names\n";
    echo "3. Test thoroughly\n";
    echo "4. Old tables kept as backup\n";
} else {
    echo "⚠️  MIGRATION COMPLETED WITH ERRORS\n";
}

$mysqli->close();
