#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== PAYOUT DATE COVERAGE ANALYSIS ===\n\n";

// Check how many transactions have payout dates
$result = $mysqli->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN payout_arrival_date IS NOT NULL THEN 1 ELSE 0 END) as with_payout,
        SUM(CASE WHEN payout_arrival_date IS NULL THEN 1 ELSE 0 END) as without_payout,
        MIN(stripe_created) as oldest_txn,
        MAX(stripe_created) as newest_txn
    FROM swca_stripe_transactions
");

$stats = $result->fetch_object();

echo sprintf("Total transactions: %d\n", $stats->total);
echo sprintf("With payout_arrival_date: %d (%.1f%%)\n",
    $stats->with_payout,
    ($stats->with_payout / $stats->total) * 100
);
echo sprintf("Without payout_arrival_date: %d (%.1f%%)\n",
    $stats->without_payout,
    ($stats->without_payout / $stats->total) * 100
);
echo sprintf("Date range: %s to %s\n", $stats->oldest_txn, $stats->newest_txn);

// Check the 6 problem dates
echo "\n\n=== CHECKING THE 6 UNMATCHED BANK DEPOSITS ===\n\n";

$problem_dates = [
    '2025-09-18' => 34.28,
    '2025-09-12' => 99.99,
    '2025-09-02' => 48.55,
    '2025-08-20' => 34.75,
    '2025-07-30' => 50.00,
    '2025-07-29' => 49.60,
];

foreach ($problem_dates as $date => $amount) {
    echo sprintf("Bank: %s | $%.2f\n", $date, $amount);

    // Search for Stripe charges created within ±7 days
    $result = $mysqli->query(sprintf("
        SELECT
            id, stripe_created, amount, net_amount, stripe_fee,
            payout_arrival_date, payout_id, status
        FROM swca_stripe_transactions
        WHERE stripe_created BETWEEN DATE_SUB('%s', INTERVAL 7 DAY) AND DATE_ADD('%s', INTERVAL 7 DAY)
        AND ABS(amount - %.2f) < 5.00
        ORDER BY ABS(amount - %.2f) ASC
        LIMIT 3
    ", $date, $date, $amount, $amount));

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_object()) {
            $net = floatval($row->amount) - floatval($row->stripe_fee);
            echo sprintf("  → ID %d | Created: %s | Amount: $%.2f | Net: $%.2f | Payout Date: %s\n",
                $row->id,
                $row->stripe_created,
                floatval($row->amount),
                $net,
                $row->payout_arrival_date ?? 'NULL'
            );
        }
    } else {
        echo "  ❌ No Stripe charges found near this date\n";
    }
    echo "\n";
}

// Check if we need to re-sync recent data
echo "\n=== RECOMMENDATION ===\n\n";

$recent_without_payout = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM swca_stripe_transactions
    WHERE stripe_created >= '2025-07-01'
    AND payout_arrival_date IS NULL
")->fetch_object()->cnt;

if ($recent_without_payout > 0) {
    echo "⚠️  WARNING: $recent_without_payout recent transactions (since July 2025) are missing payout dates!\n";
    echo "Action: Re-sync Stripe data for July-September 2025 (90 days)\n\n";
} else {
    echo "✓ All recent transactions have payout dates\n";
    echo "Issue: Payout dates exist but don't match bank deposit dates\n";
    echo "Action: Investigate why Stripe payout dates differ from bank dates\n\n";
}

$mysqli->close();
