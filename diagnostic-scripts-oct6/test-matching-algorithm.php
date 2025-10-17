#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== TESTING MATCHING ALGORITHM ===\n\n";

// Test the exact query used by the matching algorithm
$bank_date = '2025-09-18';
$bank_amount = 34.28;

echo "Testing: Bank deposit on $bank_date for $$bank_amount\n\n";

$sql = "
    SELECT
        payout_arrival_date,
        COUNT(*) as txn_count,
        SUM(net_amount) as payout_net_total,
        SUM(stripe_fee) as payout_fees_total,
        GROUP_CONCAT(id) as stripe_ids
    FROM swca_stripe_transactions
    WHERE payout_arrival_date BETWEEN DATE_SUB('$bank_date', INTERVAL 2 DAY) AND DATE_ADD('$bank_date', INTERVAL 2 DAY)
    AND net_amount > 0
    GROUP BY payout_arrival_date
    HAVING payout_net_total > 0
    ORDER BY ABS(DATEDIFF(payout_arrival_date, '$bank_date')) ASC
";

echo "SQL Query:\n$sql\n\n";

$result = $mysqli->query($sql);

echo "Results:\n";
echo sprintf("%-15s %7s %12s %12s\n", "Payout Date", "Count", "Net Total", "Fees");
echo str_repeat("-", 60) . "\n";

while ($row = $result->fetch_object()) {
    $diff = abs(floatval($row->payout_net_total) - $bank_amount);
    $match = ($diff <= 1.00) ? "✓ MATCH" : "✗ No match";

    echo sprintf("%-15s %7d %12.2f %12.2f | Diff: $%.2f | %s\n",
        $row->payout_arrival_date,
        $row->txn_count,
        floatval($row->payout_net_total),
        floatval($row->payout_fees_total),
        $diff,
        $match
    );
}

// Now test with the CORRECT table prefix
echo "\n\n=== TESTING WITH CORRECT TABLE PREFIX ===\n\n";

// The matching code uses $wpdb->prefix which would be 'swca_' not 'wp_'
$sql_corrected = "
    SELECT
        payout_arrival_date,
        COUNT(*) as txn_count,
        SUM(net_amount) as payout_net_total,
        SUM(stripe_fee) as payout_fees_total,
        GROUP_CONCAT(id) as stripe_ids
    FROM swca_stripe_transactions
    WHERE payout_arrival_date BETWEEN DATE_SUB('$bank_date', INTERVAL 2 DAY) AND DATE_ADD('$bank_date', INTERVAL 2 DAY)
    AND net_amount > 0
    GROUP BY payout_arrival_date
    HAVING payout_net_total > 0
    ORDER BY ABS(DATEDIFF(payout_arrival_date, '$bank_date')) ASC
";

$result = $mysqli->query($sql_corrected);

if ($result->num_rows > 0) {
    echo "Found " . $result->num_rows . " payout groups\n\n";
    while ($row = $result->fetch_object()) {
        echo sprintf("Payout: %s | Net: $%.2f | IDs: %s\n",
            $row->payout_arrival_date,
            floatval($row->payout_net_total),
            $row->stripe_ids
        );

        // Check net_amount of individual transactions
        $ids = $row->stripe_ids;
        $detail_result = $mysqli->query("
            SELECT id, amount, net_amount, stripe_fee, amount_refunded
            FROM swca_stripe_transactions
            WHERE id IN ($ids)
        ");

        echo "  Individual transactions:\n";
        while ($detail = $detail_result->fetch_object()) {
            echo sprintf("    ID %d: Amount=$%.2f, Net=$%.2f, Fee=$%.2f, Refunded=$%.2f\n",
                $detail->id,
                floatval($detail->amount),
                floatval($detail->net_amount),
                floatval($detail->stripe_fee),
                floatval($detail->amount_refunded)
            );
        }
    }
} else {
    echo "❌ NO RESULTS - This is the problem!\n";
}

$mysqli->close();
echo "\n";
