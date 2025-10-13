<?php
/**
 * Find Bank Transaction Data
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');

echo "=== Finding Bank Transaction Data ===\n\n";

global $wpdb;

// Check financial_transactions table
echo "Checking swca_swca_financial_transactions:\n";
$financial_txns = $wpdb->get_results("
    SELECT * FROM swca_swca_financial_transactions
    ORDER BY transaction_date DESC
    LIMIT 10
");

if ($financial_txns) {
    foreach ($financial_txns as $txn) {
        printf("[%d] %s - \$%s / \$%s - %s - %s\n",
            $txn->id,
            $txn->transaction_date,
            number_format($txn->income_amount ?? 0, 2),
            number_format($txn->expense_amount ?? 0, 2),
            $txn->category ?? 'N/A',
            substr($txn->description ?? '', 0, 50)
        );
    }
}

echo "\n\nChecking which table transaction_matches references:\n";
$match_sample = $wpdb->get_row("
    SELECT * FROM swca_transaction_matches LIMIT 1
");

if ($match_sample) {
    print_r($match_sample);
}

echo "\n\nChecking what columns exist in swca_bank_transactions:\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM swca_bank_transactions");
foreach ($columns as $col) {
    echo "  {$col->Field} ({$col->Type})\n";
}
?>
