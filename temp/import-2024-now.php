<?php
/**
 * Import 2024 bank transactions directly
 */

require_once('/home/swca/public_html/wp-load.php');

global $wpdb;

echo "Importing 2024 Bank Transactions\n";
echo str_repeat("=", 80) . "\n\n";

$csv_file = '/home/swca/scripts/501c3PO/bank-2024-full-year.csv';

if (!file_exists($csv_file)) {
    die("ERROR: CSV file not found!\n");
}

$handle = fopen($csv_file, 'r');
$headers = fgetcsv($handle); // Skip header row

$imported = 0;
$skipped = 0;
$errors = 0;

while (($data = fgetcsv($handle)) !== FALSE) {
    if (count($data) < 8) {
        echo "Skip (incomplete row)\n";
        continue;
    }

    list($account, $post_date, $check, $desc, $debit, $credit, $status, $balance) = $data;

    // Convert date format from MM/DD/YYYY to YYYY-MM-DD
    $date_parts = explode('/', $post_date);
    if (count($date_parts) == 3) {
        $formatted_date = sprintf('%04d-%02d-%02d', $date_parts[2], $date_parts[0], $date_parts[1]);
    } else {
        echo "Skip (invalid date: $post_date)\n";
        $errors++;
        continue;
    }

    // Check for duplicates
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM wp_swca_bank_transactions
         WHERE post_date = %s AND description = %s AND debit = %f AND credit = %f",
        $formatted_date, $desc, floatval($debit), floatval($credit)
    ));

    if ($exists > 0) {
        $skipped++;
        continue;
    }

    // Insert transaction
    $result = $wpdb->insert('wp_swca_bank_transactions', [
        'account_number' => $account,
        'post_date' => $formatted_date,
        'check_number' => $check,
        'description' => $desc,
        'debit' => floatval($debit),
        'credit' => floatval($credit),
        'status' => $status,
        'balance' => floatval($balance)
    ]);

    if ($result) {
        $imported++;
        if ($imported % 10 == 0) {
            echo ".";
        }
    } else {
        echo "✗ ERROR: $formatted_date - $desc\n";
        $errors++;
    }
}

fclose($handle);

echo "\n\n" . str_repeat("=", 80) . "\n";
echo "Import Complete!\n";
echo "  Imported: $imported transactions\n";
echo "  Skipped (duplicates): $skipped transactions\n";
echo "  Errors: $errors transactions\n\n";

// Verify by counting
$count_2024 = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE YEAR(post_date) = 2024");
echo "Total 2024 transactions in database: $count_2024\n";

$balance_check = $wpdb->get_row("
    SELECT
        MIN(post_date) as first_date,
        MAX(post_date) as last_date,
        (SELECT balance FROM wp_swca_bank_transactions WHERE YEAR(post_date) = 2024 ORDER BY post_date ASC, id ASC LIMIT 1) as first_balance,
        (SELECT balance FROM wp_swca_bank_transactions WHERE YEAR(post_date) = 2024 ORDER BY post_date DESC, id DESC LIMIT 1) as last_balance
    FROM wp_swca_bank_transactions
    WHERE YEAR(post_date) = 2024
");

echo "\nDate Range: {$balance_check->first_date} to {$balance_check->last_date}\n";
echo "Starting Balance: \${$balance_check->first_balance}\n";
echo "Ending Balance: \${$balance_check->last_balance}\n";
