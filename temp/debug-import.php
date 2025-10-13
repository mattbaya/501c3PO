<?php
/**
 * Debug import issues
 */

require_once('/home/swca/public_html/wp-load.php');

global $wpdb;

echo "Debug Import\n";
echo str_repeat("=", 80) . "\n\n";

// Try inserting one row with error reporting
$wpdb->show_errors();

$test_data = [
    'account_number' => '0880269618',
    'post_date' => '2024-01-02',
    'transaction_date' => '2024-01-02',
    'check_number' => '619',
    'description' => 'Check',
    'debit' => 93.70,
    'credit' => 0.00,
    'status' => 'Posted',
    'balance' => 6755.58
];

echo "Attempting to insert test transaction...\n";
$result = $wpdb->insert('wp_swca_bank_transactions', $test_data);

if ($result) {
    echo "✓ SUCCESS!\n";
} else {
    echo "✗ FAILED\n";
    echo "Error: " . $wpdb->last_error . "\n";
    echo "Query: " . $wpdb->last_query . "\n";
}

// Check table structure
echo "\nTable structure:\n";
$columns = $wpdb->get_results("DESCRIBE wp_swca_bank_transactions");
foreach ($columns as $col) {
    echo "  - {$col->Field} ({$col->Type}) {$col->Null} {$col->Key}\n";
}
