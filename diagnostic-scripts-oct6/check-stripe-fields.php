#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== STRIPE TABLE STRUCTURE ===\n\n";
$result = $mysqli->query("DESCRIBE swca_stripe_transactions");
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-30s %s\n", $row['Field'], $row['Type']);
}

echo "\n=== SAMPLE STRIPE TRANSACTIONS (with payout 2025-09-18) ===\n\n";
$result = $mysqli->query("
    SELECT id, stripe_created, amount, net_amount, stripe_fee, payout_arrival_date, status
    FROM swca_stripe_transactions
    WHERE payout_arrival_date = '2025-09-18'
    LIMIT 5
");

while ($row = $result->fetch_assoc()) {
    echo sprintf(
        "ID: %d | Created: %s | Amount: $%.2f | Net: $%.2f | Fee: $%.2f | Payout: %s | Status: %s\n",
        $row['id'],
        $row['stripe_created'],
        floatval($row['amount']),
        floatval($row['net_amount']),
        floatval($row['stripe_fee']),
        $row['payout_arrival_date'],
        $row['status']
    );
}

echo "\n=== TOTALS BY PAYOUT DATE (showing amount vs net_amount) ===\n\n";
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
echo "\n";
