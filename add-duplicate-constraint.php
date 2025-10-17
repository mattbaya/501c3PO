<?php
/**
 * Add Unique Constraint to Bank Transactions Table
 * Prevents duplicate transactions at database level
 */

require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== ADDING UNIQUE CONSTRAINT TO BANK TRANSACTIONS ===\n\n";

$bank_table = 'wp_swca_bank_transactions';

// First, check if constraint already exists
$existing_indexes = $wpdb->get_results("SHOW INDEX FROM $bank_table WHERE Key_name = 'unique_transaction'");

if (!empty($existing_indexes)) {
    echo "Unique constraint already exists.\n";
    exit;
}

// Add unique constraint on date + credit + debit
// This prevents the same transaction from being imported twice
$sql = "ALTER TABLE $bank_table
        ADD UNIQUE KEY unique_transaction (post_date, credit, debit)";

echo "Adding unique constraint: date + credit + debit\n";
echo "SQL: $sql\n\n";

$result = $wpdb->query($sql);

if ($result === false) {
    echo "ERROR: Failed to add unique constraint\n";
    echo "Error: " . $wpdb->last_error . "\n";
} else {
    echo "SUCCESS: Unique constraint added successfully\n";
    echo "Future imports will automatically skip duplicate transactions\n";
}

// Verify the constraint was added
$indexes = $wpdb->get_results("SHOW INDEX FROM $bank_table");
echo "\nCurrent indexes on $bank_table:\n";
foreach ($indexes as $idx) {
    echo "  {$idx->Key_name}: {$idx->Column_name}\n";
}
