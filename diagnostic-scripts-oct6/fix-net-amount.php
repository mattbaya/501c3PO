#!/usr/bin/env php
<?php
/**
 * Fix net_amount field in Stripe transactions
 * Calculate: net_amount = amount - stripe_fee - amount_refunded
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "\n=== FIXING NET_AMOUNT FIELD ===\n\n";

// Check how many need fixing
$result = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM swca_stripe_transactions
    WHERE net_amount = 0
    AND amount > 0
");
$row = $result->fetch_object();
echo "Transactions with net_amount = 0 but amount > 0: {$row->cnt}\n\n";

// Show some examples before fixing
echo "Examples BEFORE fix:\n";
$result = $mysqli->query("
    SELECT id, amount, stripe_fee, amount_refunded, net_amount,
           (amount - stripe_fee - IFNULL(amount_refunded, 0)) as calculated_net
    FROM swca_stripe_transactions
    WHERE net_amount = 0 AND amount > 0
    LIMIT 5
");

while ($row = $result->fetch_object()) {
    echo sprintf(
        "  ID %d: Amount=$%.2f - Fee=$%.2f - Refunded=$%.2f = Net=$%.2f (currently: $%.2f)\n",
        $row->id,
        floatval($row->amount),
        floatval($row->stripe_fee),
        floatval($row->amount_refunded),
        floatval($row->calculated_net),
        floatval($row->net_amount)
    );
}

echo "\nProceeding with UPDATE...\n";

// Update net_amount
$result = $mysqli->query("
    UPDATE swca_stripe_transactions
    SET net_amount = amount - stripe_fee - IFNULL(amount_refunded, 0)
    WHERE net_amount = 0 AND amount > 0
");

if ($result) {
    echo "✓ Updated {$mysqli->affected_rows} rows\n";
} else {
    echo "✗ Error: " . $mysqli->error . "\n";
}

// Verify
echo "\nVerification - checking for remaining zero net_amounts:\n";
$result = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM swca_stripe_transactions
    WHERE net_amount = 0
    AND amount > 0
");
$row = $result->fetch_object();
echo "Remaining transactions with net_amount = 0: {$row->cnt}\n";

// Show totals by payout date after fix
echo "\n=== PAYOUT TOTALS AFTER FIX ===\n\n";
$result = $mysqli->query("
    SELECT
        payout_arrival_date,
        COUNT(*) as count,
        SUM(amount) as total_amount,
        SUM(net_amount) as total_net,
        SUM(stripe_fee) as total_fees
    FROM swca_stripe_transactions
    WHERE payout_arrival_date BETWEEN '2025-09-01' AND '2025-09-30'
    GROUP BY payout_arrival_date
    ORDER BY payout_arrival_date DESC
");

echo sprintf("%-15s %5s %12s %12s %12s\n", "Payout Date", "Count", "Amount", "Net", "Fees");
echo str_repeat("-", 65) . "\n";
while ($row = $result->fetch_assoc()) {
    echo sprintf(
        "%-15s %5d %12.2f %12.2f %12.2f\n",
        $row['payout_arrival_date'],
        $row['count'],
        floatval($row['total_amount']),
        floatval($row['total_net']),
        floatval($row['total_fees'])
    );
}

$mysqli->close();
echo "\n✓ Done!\n\n";
