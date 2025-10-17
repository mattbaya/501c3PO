#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== CHECKING FOR BATCHED PAYOUTS ===\n\n";

// Get all unmatched bank deposits
$result = $mysqli->query("
    SELECT *
    FROM wp_swca_bank_transactions
    WHERE credit > 0
    AND id NOT IN (SELECT bank_transaction_id FROM swca_transaction_matches WHERE bank_transaction_id IS NOT NULL)
    ORDER BY post_date DESC, credit DESC
");

$unmatched = [];
while ($row = $result->fetch_object()) {
    $unmatched[] = $row;
}

echo "Unmatched bank deposits:\n";
foreach ($unmatched as $bank) {
    echo sprintf("  #%d | %s | $%.2f | %s\n",
        $bank->id,
        $bank->post_date,
        floatval($bank->credit),
        substr($bank->description, 0, 50)
    );
}

// Check if smaller deposits are included in the larger $1235.00 deposit
echo "\n\n=== TESTING: Are smaller amounts part of the $1235.00 deposit? ===\n\n";

$large_deposit = 1235.00;
$smaller_amounts = [34.28, 99.99, 48.55];

echo "Large deposit: $" . number_format($large_deposit, 2) . "\n";
echo "Smaller deposits: $" . implode(", $", array_map(function($a) { return number_format($a, 2); }, $smaller_amounts)) . "\n";
echo "Sum of smaller: $" . number_format(array_sum($smaller_amounts), 2) . "\n";
echo "Difference: $" . number_format($large_deposit - array_sum($smaller_amounts), 2) . "\n\n";

// Check Stripe payouts in that date range
echo "=== STRIPE PAYOUTS AROUND SEPT 2025 ===\n\n";
$result = $mysqli->query("
    SELECT
        payout_arrival_date,
        COUNT(*) as charge_count,
        SUM(amount) as gross_amount,
        SUM(net_amount) as net_amount,
        SUM(stripe_fee) as total_fees,
        GROUP_CONCAT(CONCAT('$', ROUND(net_amount, 2)) ORDER BY net_amount DESC SEPARATOR ', ') as individual_nets
    FROM swca_stripe_transactions
    WHERE payout_arrival_date BETWEEN '2025-09-01' AND '2025-09-30'
    AND net_amount > 0
    GROUP BY payout_arrival_date
    ORDER BY payout_arrival_date DESC
");

echo sprintf("%-15s %7s %12s %12s %12s\n", "Payout Date", "Charges", "Gross", "Net", "Fees");
echo str_repeat("-", 70) . "\n";
while ($row = $result->fetch_object()) {
    echo sprintf(
        "%-15s %7d %12.2f %12.2f %12.2f\n",
        $row->payout_arrival_date,
        $row->charge_count,
        floatval($row->gross_amount),
        floatval($row->net_amount),
        floatval($row->total_fees)
    );
    echo "  Individual nets: " . $row->individual_nets . "\n";
}

// Check if there's a payout matching $1235.00
echo "\n\n=== SEARCHING FOR $1235.00 PAYOUT ===\n\n";
$result = $mysqli->query("
    SELECT
        payout_arrival_date,
        SUM(net_amount) as total_net,
        COUNT(*) as charge_count
    FROM swca_stripe_transactions
    WHERE ABS(net_amount - 1235.00) < 50.00
       OR (payout_arrival_date IS NOT NULL
           AND payout_arrival_date IN (
               SELECT payout_arrival_date
               FROM swca_stripe_transactions
               GROUP BY payout_arrival_date
               HAVING ABS(SUM(net_amount) - 1235.00) < 10.00
           ))
    GROUP BY payout_arrival_date
    ORDER BY ABS(SUM(net_amount) - 1235.00) ASC
    LIMIT 5
");

if ($result->num_rows > 0) {
    echo "Possible matches:\n";
    while ($row = $result->fetch_object()) {
        echo sprintf("  %s | %d charges | $%.2f net\n",
            $row->payout_arrival_date,
            $row->charge_count,
            floatval($row->total_net)
        );
    }
} else {
    echo "No matches found for $1235.00\n";
}

$mysqli->close();
echo "\n";
