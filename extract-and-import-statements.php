<?php
/**
 * Extract bank transactions from PDFs and import to database
 */

require_once('/home/swca/public_html/wp-load.php');

global $wpdb;

echo "PDF Bank Statement Import Tool\n";
echo str_repeat("=", 80) . "\n\n";

// I'll manually extract the data from each PDF based on what I can see
// January 2024 data extracted from the PDF shown

$transactions_2024_01 = [
    ['date' => '2024-01-02', 'check' => '619', 'desc' => 'Check', 'debit' => 93.70, 'credit' => 0],
    ['date' => '2024-01-02', 'check' => '622', 'desc' => 'Check', 'debit' => 11.00, 'credit' => 0],
    ['date' => '2024-01-04', 'check' => '615', 'desc' => 'Check', 'debit' => 43.94, 'credit' => 0],
    ['date' => '2024-01-04', 'check' => '623', 'desc' => 'Check', 'debit' => 637.20, 'credit' => 0],
    ['date' => '2024-01-04', 'check' => '', 'desc' => 'ACH Credit TRANSFER STRIPE ID4270465600', 'debit' => 0, 'credit' => 130.48],
    ['date' => '2024-01-05', 'check' => '', 'desc' => 'ACH Credit TRANSFER STRIPE ID4270465600', 'debit' => 0, 'credit' => 33.68],
    ['date' => '2024-01-09', 'check' => '', 'desc' => 'ACH Credit TRANSFER STRIPE ID4270465600', 'debit' => 0, 'credit' => 33.68],
    ['date' => '2024-01-12', 'check' => '', 'desc' => 'Deposit', 'debit' => 0, 'credit' => 35.00],
    ['date' => '2024-01-16', 'check' => '', 'desc' => 'ACH Credit TRANSFER STRIPE ID4270465600', 'debit' => 0, 'credit' => 33.68],
    ['date' => '2024-01-19', 'check' => '624', 'desc' => 'Check', 'debit' => 125.00, 'credit' => 0],
    ['date' => '2024-01-31', 'check' => '', 'desc' => 'ACH Credit TRANSFER STRIPE ID4270465600', 'debit' => 0, 'credit' => 33.68],
    ['date' => '2024-01-31', 'check' => '', 'desc' => 'Interest Credit', 'debit' => 0, 'credit' => 2.66],
];

$starting_balance_jan_2024 = 6849.28;

echo "Importing January 2024 transactions...\n";

// Calculate running balances
$balance = $starting_balance_jan_2024;
$imported = 0;

foreach ($transactions_2024_01 as $txn) {
    // Update balance
    $balance = $balance + $txn['credit'] - $txn['debit'];

    // Check if already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM wp_swca_bank_transactions
         WHERE post_date = %s AND description = %s AND debit = %f AND credit = %f",
        $txn['date'], $txn['desc'], $txn['debit'], $txn['credit']
    ));

    if ($exists > 0) {
        echo "  Skip (duplicate): {$txn['date']} - {$txn['desc']}\n";
        continue;
    }

    $result = $wpdb->insert('wp_swca_bank_transactions', [
        'account_number' => '0880269618',
        'post_date' => $txn['date'],
        'transaction_date' => $txn['date'],
        'check_number' => $txn['check'],
        'description' => $txn['desc'],
        'debit' => $txn['debit'],
        'credit' => $txn['credit'],
        'status' => 'Posted',
        'balance' => $balance
    ]);

    if ($result) {
        $imported++;
        echo "  ✓ {$txn['date']} - {$txn['desc']} - Balance: \${$balance}\n";
    } else {
        echo "  ✗ ERROR: {$txn['date']} - {$txn['desc']}\n";
    }
}

echo "\n✓ January 2024: Imported $imported transactions\n";
echo "  Final balance: \$$balance (Expected: \$6,241.30)\n\n";

echo str_repeat("=", 80) . "\n";
echo "NOTE: This script extracted January 2024 data from the PDF.\n";
echo "To complete the import, I need to read all 12 PDF files for 2024.\n";
echo "Would you like me to continue with the remaining months?\n";
