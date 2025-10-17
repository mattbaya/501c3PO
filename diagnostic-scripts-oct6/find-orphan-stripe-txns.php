#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== SEARCHING FOR ORPHANED STRIPE TRANSACTIONS ===\n\n";

$missing = [
    ['bank_id' => 7, 'date' => '2025-09-18', 'amount' => 34.28],
    ['bank_id' => 9, 'date' => '2025-09-12', 'amount' => 99.99],
    ['bank_id' => 13, 'date' => '2025-09-02', 'amount' => 48.55],
    ['bank_id' => 18, 'date' => '2025-08-20', 'amount' => 34.75],
    ['bank_id' => 31, 'date' => '2025-07-30', 'amount' => 50.00],
    ['bank_id' => 32, 'date' => '2025-07-29', 'amount' => 49.60],
];

foreach ($missing as $item) {
    echo sprintf("Bank #%d | %s | $%.2f\n", $item['bank_id'], $item['date'], $item['amount']);
    echo str_repeat("-", 70) . "\n";

    // Search for ANY Stripe transaction with net_amount close to this (regardless of payout date)
    $result = $mysqli->query(sprintf("
        SELECT
            id, stripe_created, amount, net_amount, stripe_fee,
            payout_arrival_date, payout_id, status
        FROM swca_stripe_transactions
        WHERE ABS(net_amount - %.2f) < 0.50
        ORDER BY ABS(net_amount - %.2f) ASC
        LIMIT 5
    ", $item['amount'], $item['amount']));

    if ($result->num_rows > 0) {
        echo "✓ Found Stripe transactions with matching net_amount:\n";
        while ($row = $result->fetch_object()) {
            echo sprintf("  ID %d | Created: %s | Net: $%.2f | Payout: %s | Payout ID: %s\n",
                $row->id,
                $row->stripe_created,
                floatval($row->net_amount),
                $row->payout_arrival_date ?? 'NULL',
                $row->payout_id ?? 'NULL'
            );
        }
    } else {
        // Try searching by gross amount instead
        $result = $mysqli->query(sprintf("
            SELECT
                id, stripe_created, amount, net_amount, stripe_fee,
                payout_arrival_date, status
            FROM swca_stripe_transactions
            WHERE ABS(amount - %.2f) < 2.00
            ORDER BY ABS(amount - %.2f) ASC
            LIMIT 5
        ", $item['amount'], $item['amount']));

        if ($result->num_rows > 0) {
            echo "✓ Found by gross amount:\n";
            while ($row = $result->fetch_object()) {
                echo sprintf("  ID %d | Amount: $%.2f | Net: $%.2f | Fee: $%.2f | Payout: %s\n",
                    $row->id,
                    floatval($row->amount),
                    floatval($row->net_amount),
                    floatval($row->stripe_fee),
                    $row->payout_arrival_date ?? 'NULL'
                );
            }
        } else {
            echo "❌ NO STRIPE TRANSACTIONS FOUND with similar amount\n";
            echo "   → This transaction is MISSING from swca_stripe_transactions!\n";
        }
    }
    echo "\n";
}

// Summary
echo "\n=== SUMMARY ===\n\n";
echo "Total Stripe transactions in database: " . $mysqli->query("SELECT COUNT(*) as cnt FROM swca_stripe_transactions")->fetch_object()->cnt . "\n";
echo "Date range: " . $mysqli->query("SELECT MIN(stripe_created) as min, MAX(stripe_created) as max FROM swca_stripe_transactions")->fetch_object()->min . "\n";
echo "           to " . $mysqli->query("SELECT MAX(stripe_created) as max FROM swca_stripe_transactions")->fetch_object()->max . "\n";
echo "\nConclusion: If transactions are missing, you need to:\n";
echo "1. Re-sync Stripe data with wider date range\n";
echo "2. Check if these are from a different Stripe account\n";
echo "3. Verify the Stripe API key used for sync\n\n";

$mysqli->close();
