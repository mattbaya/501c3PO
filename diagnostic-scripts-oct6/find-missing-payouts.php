#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== SEARCHING FOR STRIPE CHARGES MATCHING UNMATCHED BANK DEPOSITS ===\n\n";

$unmatched_bank = [
    ['date' => '2025-09-18', 'amount' => 1235.00],
    ['date' => '2025-09-18', 'amount' => 34.28],
    ['date' => '2025-09-12', 'amount' => 99.99],
    ['date' => '2025-09-02', 'amount' => 48.55],
];

foreach ($unmatched_bank as $bank) {
    echo sprintf("Looking for matches to Bank: %s, $%.2f\n", $bank['date'], $bank['amount']);
    echo str_repeat("-", 80) . "\n";

    // Search for Stripe charges with net_amount close to bank amount
    // Allow for wider date range and amount tolerance
    $result = $mysqli->query(sprintf("
        SELECT
            id, stripe_created, amount, net_amount, stripe_fee,
            payout_arrival_date, status, description
        FROM swca_stripe_transactions
        WHERE stripe_created BETWEEN DATE_SUB('%s', INTERVAL 7 DAY) AND DATE_ADD('%s', INTERVAL 7 DAY)
        AND ABS(net_amount - %.2f) < 5.00
        ORDER BY ABS(net_amount - %.2f) ASC
        LIMIT 10
    ", $bank['date'], $bank['date'], $bank['amount'], $bank['amount']));

    $found = false;
    while ($row = $result->fetch_object()) {
        $found = true;
        $diff = abs(floatval($row->net_amount) - $bank['amount']);
        $days = round((strtotime($row->payout_arrival_date) - strtotime($bank['date'])) / 86400);
        echo sprintf(
            "  ✓ ID %d | Created: %s | Net: $%.2f | Payout: %s | Diff: $%.2f | Days: %d\n",
            $row->id,
            $row->stripe_created,
            floatval($row->net_amount),
            $row->payout_arrival_date,
            $diff,
            $days
        );
    }

    if (!$found) {
        echo "  ❌ No close matches found\n";
    }
    echo "\n";
}

// Check for charges with NULL or very different payout dates
echo "\n=== STRIPE CHARGES AROUND UNMATCHED DATES ===\n\n";

$result = $mysqli->query("
    SELECT
        stripe_created,
        COUNT(*) as count,
        SUM(net_amount) as total_net
    FROM swca_stripe_transactions
    WHERE stripe_created BETWEEN '2025-09-01' AND '2025-09-25'
    AND net_amount > 0
    GROUP BY DATE(stripe_created)
    ORDER BY stripe_created DESC
");

echo sprintf("%-12s %6s %12s\n", "Created", "Count", "Total Net");
echo str_repeat("-", 35) . "\n";
while ($row = $result->fetch_object()) {
    echo sprintf(
        "%-12s %6d %12.2f\n",
        substr($row->stripe_created, 0, 10),
        $row->count],
        floatval($row->total_net)
    );
}

$mysqli->close();
echo "\n";
