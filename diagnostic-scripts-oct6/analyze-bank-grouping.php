#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== ANALYZING BANK DEPOSIT GROUPING ===\n\n";

// Group unmatched bank deposits by date
echo "Unmatched bank deposits grouped by date:\n";
echo str_repeat("-", 100) . "\n";

$result = $mysqli->query("
    SELECT
        post_date,
        COUNT(*) as deposit_count,
        SUM(credit) as total_credit,
        GROUP_CONCAT(CONCAT('#', id, ': $', ROUND(credit, 2)) ORDER BY credit DESC SEPARATOR ', ') as deposits,
        GROUP_CONCAT(SUBSTRING(description, 1, 30) ORDER BY credit DESC SEPARATOR ' | ') as descriptions
    FROM wp_swca_bank_transactions
    WHERE credit > 0
    AND id NOT IN (SELECT bank_transaction_id FROM swca_transaction_matches WHERE bank_transaction_id IS NOT NULL)
    GROUP BY post_date
    HAVING deposit_count > 1 OR total_credit > 100
    ORDER BY post_date DESC
    LIMIT 20
");

while ($row = $result->fetch_object()) {
    echo sprintf("\n%s | %d deposits | Total: $%.2f\n",
        $row->post_date,
        $row->deposit_count,
        floatval($row->total_credit)
    );
    echo "  Deposits: " . $row->deposits . "\n";
    echo "  Descriptions: " . substr($row->descriptions, 0, 80) . "\n";

    // Look for matching Stripe payout
    $sql = sprintf("
        SELECT
            payout_arrival_date,
            COUNT(*) as charge_count,
            SUM(net_amount) as payout_total,
            GROUP_CONCAT(CONCAT('$', ROUND(net_amount, 2)) ORDER BY net_amount DESC SEPARATOR ', ') as individual_amounts
        FROM swca_stripe_transactions
        WHERE payout_arrival_date BETWEEN DATE_SUB('%s', INTERVAL 3 DAY) AND DATE_ADD('%s', INTERVAL 3 DAY)
        AND net_amount > 0
        GROUP BY payout_arrival_date
        HAVING ABS(payout_total - %.2f) < 5.00
        ORDER BY ABS(payout_total - %.2f) ASC
        LIMIT 3
    ", $row->post_date, $row->post_date, floatval($row->total_credit), floatval($row->total_credit));

    $stripe_result = $mysqli->query($sql);
    if ($stripe_result->num_rows > 0) {
        echo "  ✓ POSSIBLE STRIPE MATCHES:\n";
        while ($stripe = $stripe_result->fetch_object()) {
            $diff = abs(floatval($stripe->payout_total) - floatval($row->total_credit));
            $days = round((strtotime($stripe->payout_arrival_date) - strtotime($row->post_date)) / 86400);
            echo sprintf("    %s | %d charges | $%.2f | Diff: $%.2f | %d days\n",
                $stripe->payout_arrival_date,
                $stripe->charge_count,
                floatval($stripe->payout_total),
                $diff,
                $days
            );
            echo "    Amounts: " . $stripe->individual_amounts . "\n";
        }
    } else {
        echo "  ❌ No matching Stripe payouts found\n";
    }
}

// Check for Stripe payouts that might be split across multiple bank deposits
echo "\n\n=== LARGE STRIPE PAYOUTS (looking for split deposits) ===\n\n";

$result = $mysqli->query("
    SELECT
        payout_arrival_date,
        COUNT(*) as charge_count,
        SUM(net_amount) as payout_total,
        MIN(stripe_created) as first_charge,
        MAX(stripe_created) as last_charge
    FROM swca_stripe_transactions
    WHERE net_amount > 0
    AND payout_arrival_date >= '2025-01-01'
    AND id NOT IN (SELECT stripe_transaction_id FROM swca_transaction_matches WHERE stripe_transaction_id IS NOT NULL)
    GROUP BY payout_arrival_date
    HAVING payout_total > 100
    ORDER BY payout_total DESC
    LIMIT 10
");

echo sprintf("%-15s %7s %12s %-25s\n", "Payout Date", "Charges", "Total", "Charge Date Range");
echo str_repeat("-", 75) . "\n";

while ($row = $result->fetch_object()) {
    echo sprintf("%-15s %7d %12.2f %s to %s\n",
        $row->payout_arrival_date,
        $row->charge_count,
        floatval($row->payout_total),
        substr($row->first_charge, 0, 10),
        substr($row->last_charge, 0, 10)
    );

    // Look for combinations of bank deposits around this date
    $bank_result = $mysqli->query(sprintf("
        SELECT
            post_date,
            GROUP_CONCAT(CONCAT('$', ROUND(credit, 2)) ORDER BY credit DESC SEPARATOR ' + ') as amounts,
            SUM(credit) as total
        FROM wp_swca_bank_transactions
        WHERE post_date BETWEEN DATE_SUB('%s', INTERVAL 3 DAY) AND DATE_ADD('%s', INTERVAL 3 DAY)
        AND credit > 0
        AND id NOT IN (SELECT bank_transaction_id FROM swca_transaction_matches WHERE bank_transaction_id IS NOT NULL)
        GROUP BY post_date
        HAVING ABS(total - %.2f) < 5.00
    ", $row->payout_arrival_date, $row->payout_arrival_date, floatval($row->payout_total)));

    if ($bank_result->num_rows > 0) {
        while ($bank = $bank_result->fetch_object()) {
            echo sprintf("  → Bank match: %s | %s = $%.2f\n",
                $bank->post_date,
                $bank->amounts,
                floatval($bank->total)
            );
        }
    }
}

$mysqli->close();
echo "\n";
