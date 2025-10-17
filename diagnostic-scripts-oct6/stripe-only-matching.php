#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== STRIPE-ONLY BANK DEPOSITS (Unmatched) ===\n\n";

// Get ONLY deposits with "STRIPE" in description
$result = $mysqli->query("
    SELECT *
    FROM wp_swca_bank_transactions
    WHERE credit > 0
    AND description LIKE '%STRIPE%'
    AND id NOT IN (SELECT bank_transaction_id FROM swca_transaction_matches WHERE bank_transaction_id IS NOT NULL)
    ORDER BY post_date DESC
");

$stripe_deposits = [];
while ($row = $result->fetch_object()) {
    $stripe_deposits[] = $row;
}

echo sprintf("Found %d unmatched Stripe deposits\n\n", count($stripe_deposits));
echo sprintf("%-5s %-12s %10s %-60s\n", "ID", "Date", "Amount", "Description");
echo str_repeat("-", 95) . "\n";

foreach ($stripe_deposits as $deposit) {
    echo sprintf("%-5d %-12s %10.2f %-60s\n",
        $deposit->id,
        $deposit->post_date,
        floatval($deposit->credit),
        substr($deposit->description, 0, 60)
    );
}

// Now search for Stripe payouts matching these deposits
echo "\n\n=== MATCHING STRIPE PAYOUTS TO BANK DEPOSITS ===\n\n";

foreach ($stripe_deposits as $bank) {
    echo sprintf("\nBank #%d | %s | $%.2f\n", $bank->id, $bank->post_date, floatval($bank->credit));
    echo str_repeat("-", 80) . "\n";

    // Search for Stripe payouts within ±5 days and ±$5
    $result = $mysqli->query(sprintf("
        SELECT
            payout_arrival_date,
            COUNT(*) as charge_count,
            SUM(net_amount) as payout_total,
            SUM(stripe_fee) as total_fees,
            GROUP_CONCAT(CONCAT('$', ROUND(net_amount, 2)) ORDER BY net_amount DESC SEPARATOR ', ') as amounts
        FROM swca_stripe_transactions
        WHERE payout_arrival_date BETWEEN DATE_SUB('%s', INTERVAL 5 DAY) AND DATE_ADD('%s', INTERVAL 5 DAY)
        AND net_amount > 0
        GROUP BY payout_arrival_date
        HAVING ABS(payout_total - %.2f) < 5.00
        ORDER BY ABS(payout_total - %.2f) ASC, ABS(DATEDIFF(payout_arrival_date, '%s')) ASC
        LIMIT 5
    ", $bank->post_date, $bank->post_date, floatval($bank->credit), floatval($bank->credit), $bank->post_date));

    if ($result->num_rows > 0) {
        echo "✓ FOUND MATCHES:\n";
        while ($row = $result->fetch_object()) {
            $diff_amount = abs(floatval($row->payout_total) - floatval($bank->credit));
            $diff_days = round((strtotime($row->payout_arrival_date) - strtotime($bank->post_date)) / 86400);

            $confidence = 'HIGH';
            if ($diff_amount > 1.00 || abs($diff_days) > 2) {
                $confidence = 'MEDIUM';
            }
            if ($diff_amount > 2.00 || abs($diff_days) > 3) {
                $confidence = 'LOW';
            }

            echo sprintf("  [%s] %s | %d charges | $%.2f | Diff: $%.2f (%d days)\n",
                $confidence,
                $row->payout_arrival_date,
                $row->charge_count,
                floatval($row->payout_total),
                $diff_amount,
                $diff_days
            );
            echo sprintf("  Breakdown: %s = $%.2f\n", $row->amounts, floatval($row->payout_total));
        }
    } else {
        echo "❌ NO MATCHES FOUND (within ±5 days, ±$5)\n";

        // Try wider search
        $result = $mysqli->query(sprintf("
            SELECT
                payout_arrival_date,
                SUM(net_amount) as payout_total
            FROM swca_stripe_transactions
            WHERE payout_arrival_date BETWEEN DATE_SUB('%s', INTERVAL 10 DAY) AND DATE_ADD('%s', INTERVAL 10 DAY)
            AND net_amount > 0
            GROUP BY payout_arrival_date
            ORDER BY ABS(payout_total - %.2f) ASC
            LIMIT 3
        ", $bank->post_date, $bank->post_date, floatval($bank->credit)));

        if ($result->num_rows > 0) {
            echo "  Nearby payouts (wider search):\n";
            while ($row = $result->fetch_object()) {
                echo sprintf("    %s | $%.2f (diff: $%.2f)\n",
                    $row->payout_arrival_date,
                    floatval($row->payout_total),
                    abs(floatval($row->payout_total) - floatval($bank->credit))
                );
            }
        }
    }
}

$mysqli->close();
echo "\n";
