#!/usr/local/bin/php
<?php
/**
 * Combine bank transactions and Stripe transactions into a single chronological table
 */

function parse_bank_transactions($filepath) {
    $transactions = [];

    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $header = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 8) continue;

            // Map to associative array
            $row = array_combine($header, $data);

            // Skip if no date
            if (empty($row['Post Date'])) continue;

            // Parse date
            $date = DateTime::createFromFormat('n/j/Y', $row['Post Date']);
            if (!$date) continue;

            // Set time to noon for bank transactions (no time given)
            $date->setTime(12, 0, 0);

            // Determine amount (negative for debits, positive for credits)
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
                'fee' => '',
                'net_amount' => $amount,
                'refunded' => '',
                'customer_email' => ''
            ];
        }
        fclose($handle);
    }

    return $transactions;
}

function parse_stripe_transactions($filepath) {
    $transactions = [];

    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $header = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 10) continue;

            // Map to associative array
            $row = array_combine($header, $data);

            // Skip if no date
            if (empty($row['Created date (UTC)'])) continue;

            // Parse date with time
            $date = DateTime::createFromFormat('n/j/Y G:i', $row['Created date (UTC)']);
            if (!$date) continue;

            // Parse amount and fee
            $amount = !empty($row['Amount']) ? floatval($row['Amount']) : 0;
            $fee = !empty($row['Fee']) ? floatval($row['Fee']) : 0;
            $refunded = !empty($row['Amount Refunded']) ? floatval($row['Amount Refunded']) : 0;

            // Net amount after fees
            $net_amount = $amount - $fee;

            // If refunded, mark as negative
            if ($refunded > 0) {
                $net_amount = -$net_amount;
            }

            // Get description
            $description = $row['Description'] ?? '';
            if (empty($description)) {
                // Try to construct from other fields
                if (!empty($row['payer_name (metadata)'])) {
                    $description = "Payment from " . $row['payer_name (metadata)'];
                } elseif (!empty($row['Customer Email'])) {
                    $description = "Payment from " . $row['Customer Email'];
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
                'customer_email' => $row['Customer Email'] ?? '',
                'type' => 'Stripe Payment',
                'debit' => '',
                'credit' => '',
                'check_num' => ''
            ];
        }
        fclose($handle);
    }

    return $transactions;
}

function generate_combined_table($bank_file, $stripe_file, $output_file) {
    // Parse both sources
    echo "Parsing bank transactions...\n";
    $bank_trans = parse_bank_transactions($bank_file);
    echo "Found " . count($bank_trans) . " bank transactions\n";

    echo "Parsing Stripe transactions...\n";
    $stripe_trans = parse_stripe_transactions($stripe_file);
    echo "Found " . count($stripe_trans) . " Stripe transactions\n";

    // Combine and sort by date
    $all_trans = array_merge($bank_trans, $stripe_trans);
    usort($all_trans, function($a, $b) {
        return $a['date_sort'] - $b['date_sort'];
    });

    // Write to CSV
    echo "\nWriting combined table to $output_file...\n";
    $fp = fopen($output_file, 'w');

    $fieldnames = ['date', 'source', 'type', 'description', 'amount', 'fee', 'net_amount',
                   'debit', 'credit', 'refunded', 'status', 'check_num', 'customer_email'];
    fputcsv($fp, $fieldnames);

    foreach ($all_trans as $trans) {
        // Format date for output
        $row = [
            $trans['date']->format('Y-m-d H:i'),
            $trans['source'],
            $trans['type'],
            $trans['description'],
            $trans['amount'] !== '' ? number_format($trans['amount'], 2, '.', '') : '',
            $trans['fee'] !== '' ? number_format($trans['fee'], 2, '.', '') : '',
            number_format($trans['net_amount'], 2, '.', ''),
            $trans['debit'] !== '' ? number_format($trans['debit'], 2, '.', '') : '',
            $trans['credit'] !== '' ? number_format($trans['credit'], 2, '.', '') : '',
            $trans['refunded'] !== '' ? number_format($trans['refunded'], 2, '.', '') : '',
            $trans['status'],
            $trans['check_num'],
            $trans['customer_email']
        ];
        fputcsv($fp, $row);
    }

    fclose($fp);

    echo "✓ Combined " . count($all_trans) . " total transactions\n";

    // Print summary
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "TRANSACTION SUMMARY\n";
    echo str_repeat("=", 80) . "\n";
    echo "Total transactions: " . count($all_trans) . "\n";
    echo "  - Bank transactions: " . count($bank_trans) . "\n";
    echo "  - Stripe transactions: " . count($stripe_trans) . "\n";

    // Calculate totals
    $bank_credits = array_sum(array_column($bank_trans, 'credit'));
    $bank_debits = array_sum(array_column($bank_trans, 'debit'));

    $stripe_gross = 0;
    $stripe_fees = 0;
    $stripe_net = 0;
    $stripe_refunded = 0;

    foreach ($stripe_trans as $t) {
        if ($t['refunded'] == 0) {
            $stripe_gross += $t['amount'];
            $stripe_net += $t['net_amount'];
        }
        $stripe_fees += $t['fee'];
        $stripe_refunded += $t['refunded'];
    }

    echo "\nBank Summary:\n";
    echo "  Total Credits (Income): $" . number_format($bank_credits, 2) . "\n";
    echo "  Total Debits (Expenses): $" . number_format($bank_debits, 2) . "\n";
    echo "  Net: $" . number_format($bank_credits - $bank_debits, 2) . "\n";

    echo "\nStripe Summary:\n";
    echo "  Gross Payments: $" . number_format($stripe_gross, 2) . "\n";
    echo "  Stripe Fees: $" . number_format($stripe_fees, 2) . "\n";
    echo "  Net Payments: $" . number_format($stripe_net, 2) . "\n";
    echo "  Total Refunded: $" . number_format($stripe_refunded, 2) . "\n";

    echo "\n" . str_repeat("=", 80) . "\n";
    echo "Output saved to: $output_file\n";
    echo str_repeat("=", 80) . "\n";
}

// Main execution
$bank_file = '/home/swca/scripts/501c3PO/treasurer-docs/MoutainOne Bank AccountHistory_Jan - Sept 2025.csv';
$stripe_file = '/home/swca/scripts/501c3PO/treasurer-docs/STRIPE unified_payments_Jan - Sept 2025.csv';
$output_file = '/home/swca/scripts/501c3PO/treasurer-docs/COMBINED_Transactions_Jan-Sept_2025.csv';

generate_combined_table($bank_file, $stripe_file, $output_file);
?>
