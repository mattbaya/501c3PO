#!/usr/bin/env php
<?php
/**
 * Debug script to analyze why bank matching is finding so few matches
 * Standalone version - queries MySQL directly without WordPress
 */

$db_name = 'swca_swca2019';
$db_user = 'swca_swca2019';
$db_pass = '5Corners!';
$db_host = 'localhost';

// Connect to database
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$stripe_table = 'swca_stripe_transactions';  // NO wp_ prefix in production!
$bank_table = 'wp_swca_bank_transactions';
$matches_table = 'swca_transaction_matches';  // NO wp_ prefix in production!

echo "\n=== BANK MATCHING DIAGNOSTIC ===\n\n";

// Get sample unmatched bank transactions
echo "1. SAMPLE UNMATCHED BANK TRANSACTIONS:\n";
echo str_repeat("-", 100) . "\n";

$query = "
    SELECT *
    FROM $bank_table
    WHERE credit > 0
    AND id NOT IN (SELECT bank_transaction_id FROM $matches_table WHERE bank_transaction_id IS NOT NULL)
    ORDER BY post_date DESC
    LIMIT 5
";

$result = $mysqli->query($query);
$unmatched_bank = [];
if ($result) {
    while ($row = $result->fetch_object()) {
        $unmatched_bank[] = $row;
    }
}

foreach ($unmatched_bank as $bank) {
    echo sprintf(
        "Bank #%d | %s | $%.2f | %s\n",
        $bank->id,
        $bank->post_date,
        floatval($bank->credit),
        substr($bank->description, 0, 60)
    );

    // Look for payout groups in the date range
    $bank_date = $bank->post_date;
    $bank_amount = floatval($bank->credit);

    $sql = "
        SELECT
            payout_arrival_date,
            COUNT(*) as txn_count,
            SUM(net_amount) as payout_net_total,
            SUM(stripe_fee) as payout_fees_total
        FROM $stripe_table
        WHERE payout_arrival_date BETWEEN DATE_SUB('$bank_date', INTERVAL 2 DAY) AND DATE_ADD('$bank_date', INTERVAL 2 DAY)
        AND net_amount > 0
        GROUP BY payout_arrival_date
        HAVING payout_net_total > 0
        ORDER BY ABS(DATEDIFF(payout_arrival_date, '$bank_date')) ASC
        LIMIT 5
    ";

    $result = $mysqli->query($sql);
    if (!$result) {
        echo "  ⚠️  SQL Error: " . $mysqli->error . "\n";
        continue;
    }

    $payout_groups = [];
    while ($row = $result->fetch_object()) {
        $payout_groups[] = $row;
    }

    if (empty($payout_groups)) {
        echo "  ❌ No Stripe payout groups found in date range\n";
    } else {
        echo "  📊 Stripe payout groups nearby:\n";
        foreach ($payout_groups as $pg) {
            $amount_diff = abs(floatval($pg->payout_net_total) - $bank_amount);
            $days_diff = abs((strtotime($pg->payout_arrival_date) - strtotime($bank_date)) / 86400);

            $match_indicator = '';
            if ($amount_diff <= 1.00 && $days_diff <= 2) {
                $match_indicator = '✓ SHOULD MATCH';
            } elseif ($amount_diff > 1.00) {
                $match_indicator = sprintf('✗ Amount diff too large: $%.2f', $amount_diff);
            } else {
                $match_indicator = sprintf('✗ Date diff too large: %.1f days', $days_diff);
            }

            echo sprintf(
                "     %s | %d charges | $%.2f | Diff: $%.2f | Days: %.1f | %s\n",
                $pg->payout_arrival_date,
                $pg->txn_count,
                floatval($pg->payout_net_total),
                $amount_diff,
                $days_diff,
                $match_indicator
            );
        }
    }
    echo "\n";
}

// Check overall payout date distribution
echo "\n2. STRIPE PAYOUT DATE DISTRIBUTION:\n";
echo str_repeat("-", 100) . "\n";

$query = "
    SELECT
        payout_arrival_date,
        COUNT(*) as charge_count,
        SUM(net_amount) as total_net,
        MIN(stripe_created) as first_charge,
        MAX(stripe_created) as last_charge
    FROM $stripe_table
    WHERE payout_arrival_date IS NOT NULL
    GROUP BY payout_arrival_date
    ORDER BY payout_arrival_date DESC
    LIMIT 15
";

$result = $mysqli->query($query);
$payout_summary = [];
if ($result) {
    while ($row = $result->fetch_object()) {
        $payout_summary[] = $row;
    }
}

foreach ($payout_summary as $ps) {
    echo sprintf(
        "%s | %2d charges | $%8.2f | Range: %s to %s\n",
        $ps->payout_arrival_date,
        $ps->charge_count,
        floatval($ps->total_net),
        substr($ps->first_charge, 0, 10),
        substr($ps->last_charge, 0, 10)
    );
}

// Check if there are any NULL payout dates
echo "\n3. MISSING PAYOUT DATA:\n";
echo str_repeat("-", 100) . "\n";

$result = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM $stripe_table
    WHERE payout_arrival_date IS NULL
");
$row = $result->fetch_object();
$null_payouts = $row->cnt;

echo "Stripe transactions with NULL payout_arrival_date: $null_payouts\n";

if ($null_payouts > 0) {
    echo "⚠️ WARNING: Some Stripe transactions are missing payout dates. Run sync again.\n";
}

// Check bank transaction date range
echo "\n4. BANK TRANSACTION DATE RANGE:\n";
echo str_repeat("-", 100) . "\n";

$result = $mysqli->query("
    SELECT
        MIN(post_date) as first_date,
        MAX(post_date) as last_date,
        COUNT(*) as total_deposits,
        SUM(credit) as total_amount
    FROM $bank_table
    WHERE credit > 0
");
$bank_date_range = $result->fetch_object();

echo sprintf(
    "First deposit: %s | Last deposit: %s | Total: %d deposits | $%.2f\n",
    $bank_date_range->first_date,
    $bank_date_range->last_date,
    $bank_date_range->total_deposits,
    floatval($bank_date_range->total_amount)
);

// Check Stripe payout date range
echo "\n5. STRIPE PAYOUT DATE RANGE:\n";
echo str_repeat("-", 100) . "\n";

$result = $mysqli->query("
    SELECT
        MIN(payout_arrival_date) as first_payout,
        MAX(payout_arrival_date) as last_payout
    FROM $stripe_table
    WHERE payout_arrival_date IS NOT NULL
");
$stripe_payout_range = $result->fetch_object();

echo sprintf(
    "First payout: %s | Last payout: %s\n",
    $stripe_payout_range->first_payout,
    $stripe_payout_range->last_payout
);

// Compare date ranges
echo "\n6. DATE RANGE OVERLAP ANALYSIS:\n";
echo str_repeat("-", 100) . "\n";

$bank_start = strtotime($bank_date_range->first_date);
$bank_end = strtotime($bank_date_range->last_date);
$stripe_start = strtotime($stripe_payout_range->first_payout);
$stripe_end = strtotime($stripe_payout_range->last_payout);

if ($bank_start > $stripe_end || $stripe_start > $bank_end) {
    echo "❌ NO OVERLAP: Bank and Stripe payout dates don't overlap at all!\n";
    echo "   This explains why matching is failing.\n";
} else {
    $overlap_start = max($bank_start, $stripe_start);
    $overlap_end = min($bank_end, $stripe_end);
    echo sprintf(
        "✓ Overlap exists: %s to %s\n",
        date('Y-m-d', $overlap_start),
        date('Y-m-d', $overlap_end)
    );

    // Count transactions in overlap period
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as cnt
        FROM $bank_table
        WHERE credit > 0
        AND post_date BETWEEN ? AND ?
    ");
    $overlap_start_str = date('Y-m-d', $overlap_start);
    $overlap_end_str = date('Y-m-d', $overlap_end);
    $stmt->bind_param("ss", $overlap_start_str, $overlap_end_str);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_object();
    $bank_in_overlap = $row->cnt;

    $stmt = $mysqli->prepare("
        SELECT COUNT(DISTINCT payout_arrival_date) as cnt
        FROM $stripe_table
        WHERE payout_arrival_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $overlap_start_str, $overlap_end_str);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_object();
    $stripe_in_overlap = $row->cnt;

    echo sprintf(
        "   Bank deposits in overlap: %d\n   Stripe payout dates in overlap: %d\n",
        $bank_in_overlap,
        $stripe_in_overlap
    );
}

echo "\n=== END DIAGNOSTIC ===\n\n";

$mysqli->close();
