#!/usr/local/bin/php
<?php
/**
 * Generate combined transaction table with names from Gravity Forms
 */

$bank_file = '/home/swca/scripts/501c3PO/treasurer-docs/MoutainOne Bank AccountHistory_Jan - Sept 2025.csv';
$stripe_with_names_file = '/home/swca/scripts/501c3PO/treasurer-docs/stripe_with_names.csv';
$output_csv = '/home/swca/scripts/501c3PO/treasurer-docs/COMBINED_Transactions_With_Names.csv';
$output_html = '/home/swca/scripts/501c3PO/transactions-with-names.html';

echo "=== GENERATING COMBINED TRANSACTIONS WITH NAMES ===\n\n";

// Parse bank transactions
function parse_bank_transactions($filepath) {
    $transactions = [];
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (empty($data[1])) continue; // Skip if no date
            $row = array_combine($header, $data);

            $date = DateTime::createFromFormat('n/j/Y', $row['Post Date']);
            if (!$date) continue;
            $date->setTime(12, 0, 0);

            $debit = !empty($row['Debit']) ? floatval($row['Debit']) : 0;
            $credit = !empty($row['Credit']) ? floatval($row['Credit']) : 0;
            $amount = $credit - $debit;

            $transactions[] = [
                'date' => $date,
                'date_sort' => $date->getTimestamp(),
                'source' => 'Bank',
                'description' => $row['Description'],
                'amount' => $amount,
                'debit' => $debit,
                'credit' => $credit,
                'check_num' => $row['Check'] ?? '',
                'status' => $row['Status'],
                'type' => 'Bank Transaction',
                'name' => '', // No names for bank transactions
                'email' => '',
                'fee' => '',
                'net_amount' => $amount,
                'refunded' => '',
            ];
        }
        fclose($handle);
    }
    return $transactions;
}

// Parse Stripe transactions with matched names
function parse_stripe_with_names($filepath) {
    $transactions = [];
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $header = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 5) continue;
            $row = array_combine($header, $data);

            if (empty($row['Created date (UTC)'])) continue;

            // Try to parse date - handle both formats
            $date = DateTime::createFromFormat('n/j/Y G:i', $row['Created date (UTC)']);
            if (!$date) {
                $date = DateTime::createFromFormat('n/j/Y H:i', $row['Created date (UTC)']);
            }
            if (!$date) continue;

            $amount = !empty($row['Amount']) ? floatval($row['Amount']) : 0;
            $fee = !empty($row['Fee']) ? floatval($row['Fee']) : 0;
            $refunded = !empty($row['Amount Refunded']) ? floatval($row['Amount Refunded']) : 0;
            $net_amount = $amount - $fee;

            if ($refunded > 0) {
                $net_amount = -$net_amount;
            }

            // Get name from various sources
            $name = '';
            if (!empty($row['matched_name'])) {
                $name = $row['matched_name'];
            } elseif (!empty($row['payer_name (metadata)'])) {
                $name = $row['payer_name (metadata)'];
            } elseif (!empty($row['Customer Description'])) {
                $name = $row['Customer Description'];
            }

            // Get email
            $email = '';
            if (!empty($row['Customer Email'])) {
                $email = $row['Customer Email'];
            } elseif (!empty($row['payer_email (metadata)'])) {
                $email = $row['payer_email (metadata)'];
            }

            // Description
            $description = $row['Description'] ?? '';
            if (empty($description)) {
                if ($name) {
                    $description = "Payment from $name";
                } elseif ($email) {
                    $description = "Payment from $email";
                }
            }

            $transactions[] = [
                'date' => $date,
                'date_sort' => $date->getTimestamp(),
                'source' => 'Stripe',
                'description' => $description,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $net_amount,
                'refunded' => $refunded,
                'status' => $row['Status'] ?? '',
                'type' => 'Stripe Payment',
                'name' => $name,
                'email' => $email,
                'debit' => '',
                'credit' => '',
                'check_num' => '',
            ];
        }
        fclose($handle);
    }
    return $transactions;
}

// Parse data
echo "Parsing bank transactions...\n";
$bank_trans = parse_bank_transactions($bank_file);
echo "Found " . count($bank_trans) . " bank transactions\n";

echo "Parsing Stripe transactions with names...\n";
$stripe_trans = parse_stripe_with_names($stripe_with_names_file);
echo "Found " . count($stripe_trans) . " Stripe transactions\n";

// Count how many have names
$with_names = array_filter($stripe_trans, function($t) { return !empty($t['name']); });
echo "  - " . count($with_names) . " with names\n";
echo "  - " . (count($stripe_trans) - count($with_names)) . " without names\n";

// Combine and sort
$all_trans = array_merge($bank_trans, $stripe_trans);
usort($all_trans, function($a, $b) { return $a['date_sort'] - $b['date_sort']; });

// Save to CSV
echo "\nWriting CSV to $output_csv...\n";
$fp = fopen($output_csv, 'w');
$fieldnames = ['date', 'source', 'type', 'name', 'email', 'description', 'amount', 'fee',
               'net_amount', 'debit', 'credit', 'refunded', 'status', 'check_num'];
fputcsv($fp, $fieldnames);

foreach ($all_trans as $trans) {
    $row = [
        $trans['date']->format('Y-m-d H:i'),
        $trans['source'],
        $trans['type'],
        $trans['name'],
        $trans['email'],
        $trans['description'],
        $trans['amount'] !== '' ? number_format($trans['amount'], 2, '.', '') : '',
        $trans['fee'] !== '' ? number_format($trans['fee'], 2, '.', '') : '',
        number_format($trans['net_amount'], 2, '.', ''),
        $trans['debit'] !== '' ? number_format($trans['debit'], 2, '.', '') : '',
        $trans['credit'] !== '' ? number_format($trans['credit'], 2, '.', '') : '',
        $trans['refunded'] !== '' ? number_format($trans['refunded'], 2, '.', '') : '',
        $trans['status'],
        $trans['check_num'],
    ];
    fputcsv($fp, $row);
}
fclose($fp);

echo "✓ CSV saved: " . number_format(filesize($output_csv)) . " bytes\n";

// Generate HTML (same as before but with name column)
echo "\nGenerating HTML...\n";
include('/home/swca/scripts/501c3PO/generate_html_with_names_template.php');

echo "✓ HTML saved: " . number_format(filesize($output_html)) . " bytes\n";
echo "\n=== COMPLETE ===\n";
echo "Total transactions: " . count($all_trans) . "\n";
echo "CSV: $output_csv\n";
echo "HTML: $output_html\n";
?>
