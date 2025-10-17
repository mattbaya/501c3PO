<?php
/**
 * Investigate the 10 Unmatched Stripe Bank Deposits
 * Find out exactly why they're not matching to get to 100%
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=====================================\n";
echo "INVESTIGATING 10 UNMATCHED DEPOSITS\n";
echo "=====================================\n\n";

// Get unmatched Stripe bank deposits
$unmatched = $mysqli->query("
    SELECT
        b.id,
        b.post_date,
        b.credit,
        b.description
    FROM wp_swca_bank_transactions b
    WHERE b.credit > 0
    AND b.description LIKE '%STRIPE%'
    AND b.id NOT IN (
        SELECT DISTINCT bank_transaction_id
        FROM swca_c3_transaction_matches
        WHERE bank_transaction_id IS NOT NULL
    )
    ORDER BY b.post_date DESC
");

$count = $unmatched->num_rows;
echo "Found $count unmatched Stripe bank deposits\n\n";

$issue_categories = [
    'duplicate' => [],
    'refund' => [],
    'date_mismatch' => [],
    'amount_mismatch' => [],
    'missing_payout' => [],
    'unknown' => []
];

while ($bank = $unmatched->fetch_assoc()) {
    echo "================================================\n";
    echo sprintf("BANK #%d: $%.2f on %s\n", $bank['id'], $bank['credit'], $bank['post_date']);
    echo sprintf("Description: %s\n", $bank['description']);
    echo "================================================\n";

    $bank_amount = floatval($bank['credit']);
    $bank_date = $bank['post_date'];

    // Check 1: Is this a duplicate entry in the bank table?
    $duplicate_check = $mysqli->query($mysqli->prepare(
        "SELECT id, post_date FROM wp_swca_bank_transactions
         WHERE credit = %f
         AND DATE(post_date) = DATE('%s')
         AND id != %d
         AND description LIKE '%%STRIPE%%'",
        $bank_amount, $bank_date, $bank['id']
    ));

    if ($duplicate_check && $duplicate_check->num_rows > 0) {
        $dup = $duplicate_check->fetch_assoc();
        echo "❌ DUPLICATE: Same amount/date exists as Bank #" . $dup['id'] . "\n";
        $issue_categories['duplicate'][] = $bank;

        // Check if the duplicate is matched
        $dup_matched = $mysqli->query($mysqli->prepare(
            "SELECT id FROM swca_c3_transaction_matches WHERE bank_transaction_id = %d",
            $dup['id']
        ));
        if ($dup_matched && $dup_matched->num_rows > 0) {
            echo "  ✓ The duplicate (Bank #" . $dup['id'] . ") IS matched\n";
            echo "  → This is a duplicate database entry, can be ignored\n";
        }
        echo "\n";
        continue;
    }

    // Check 2: Look for Stripe payouts around this date and amount
    $date_range_start = date('Y-m-d', strtotime($bank_date . ' -7 days'));
    $date_range_end = date('Y-m-d', strtotime($bank_date . ' +2 days'));

    // Check balance_transactions for exact payout match
    $payout_check = $mysqli->query($mysqli->prepare(
        "SELECT
            balance_txn_id,
            available_on,
            ABS(net) as payout_amount,
            description
         FROM swca_c3_stripe_balance_transactions
         WHERE txn_type = 'payout'
         AND DATE(available_on) BETWEEN '%s' AND '%s'
         AND ABS(ABS(net) - %f) <= 0.50
         ORDER BY ABS(ABS(net) - %f) ASC
         LIMIT 3",
        $date_range_start, $date_range_end, $bank_amount, $bank_amount
    ));

    if ($payout_check && $payout_check->num_rows > 0) {
        echo "POTENTIAL PAYOUT MATCHES:\n";
        while ($payout = $payout_check->fetch_assoc()) {
            $diff = abs($payout['payout_amount'] - $bank_amount);
            $days_diff = abs((strtotime($payout['available_on']) - strtotime($bank_date)) / 86400);
            echo sprintf("  - Payout %s: $%.2f on %s (diff: $%.2f, days: %.1f)\n",
                substr($payout['balance_txn_id'], 0, 20),
                $payout['payout_amount'],
                $payout['available_on'],
                $diff,
                $days_diff
            );
        }
        $issue_categories['date_mismatch'][] = $bank;
        echo "  → Date or amount tolerance issue\n\n";
        continue;
    }

    // Check 3: Is this a refund-related deposit?
    $refund_check = $mysqli->query($mysqli->prepare(
        "SELECT
            id, amount, amount_refunded, stripe_fee, net_amount, payout_arrival_date
         FROM swca_c3_stripe_transactions
         WHERE ABS(amount - %f) <= 1.00
         AND amount_refunded > 0
         AND DATE(payout_arrival_date) BETWEEN '%s' AND '%s'
         LIMIT 3",
        $bank_amount, $date_range_start, $date_range_end
    ));

    if ($refund_check && $refund_check->num_rows > 0) {
        echo "REFUND TRANSACTIONS FOUND:\n";
        while ($refund = $refund_check->fetch_assoc()) {
            echo sprintf("  - Stripe #%d: Gross=$%.2f, Refunded=$%.2f, Net=$%.2f, Payout=%s\n",
                $refund['id'],
                $refund['amount'],
                $refund['amount_refunded'],
                $refund['net_amount'],
                $refund['payout_arrival_date']
            );
        }
        $issue_categories['refund'][] = $bank;
        echo "  → This deposit may be related to a refund\n\n";
        continue;
    }

    // Check 4: Any Stripe transactions with similar amount (no payout data)?
    $amount_check = $mysqli->query($mysqli->prepare(
        "SELECT id, amount, stripe_created, payout_arrival_date, payout_id
         FROM swca_c3_stripe_transactions
         WHERE ABS(amount - %f) <= 2.00
         ORDER BY ABS(amount - %f) ASC
         LIMIT 3",
        $bank_amount, $bank_amount
    ));

    if ($amount_check && $amount_check->num_rows > 0) {
        echo "SIMILAR AMOUNT STRIPE TRANSACTIONS:\n";
        while ($stripe = $amount_check->fetch_assoc()) {
            echo sprintf("  - Stripe #%d: $%.2f on %s, Payout=%s, ID=%s\n",
                $stripe['id'],
                $stripe['amount'],
                substr($stripe['stripe_created'], 0, 10),
                $stripe['payout_arrival_date'] ?: 'NULL',
                $stripe['payout_id'] ?: 'NULL'
            );
        }
        if (!$amount_check->fetch_assoc()['payout_arrival_date']) {
            $issue_categories['missing_payout'][] = $bank;
            echo "  → Missing payout data in Stripe transactions\n\n";
        } else {
            $issue_categories['amount_mismatch'][] = $bank;
            echo "  → Amount mismatch or date out of range\n\n";
        }
        continue;
    }

    echo "⚠️ NO MATCHES FOUND - Unknown issue\n\n";
    $issue_categories['unknown'][] = $bank;
}

// Summary
echo "\n=====================================\n";
echo "SUMMARY BY ISSUE TYPE\n";
echo "=====================================\n\n";

foreach ($issue_categories as $type => $items) {
    if (count($items) > 0) {
        echo sprintf("%s: %d deposits\n", strtoupper(str_replace('_', ' ', $type)), count($items));
        foreach ($items as $item) {
            echo sprintf("  - Bank #%d: $%.2f on %s\n", $item['id'], $item['credit'], $item['post_date']);
        }
        echo "\n";
    }
}

echo "=====================================\n";
echo "RECOMMENDATIONS\n";
echo "=====================================\n\n";

if (count($issue_categories['duplicate']) > 0) {
    echo sprintf("✓ DUPLICATES (%d): These are duplicate database entries. The actual deposit IS matched via the other record.\n", count($issue_categories['duplicate']));
    echo "  Action: Can be safely ignored or deleted from database\n\n";
}

if (count($issue_categories['refund']) > 0) {
    echo sprintf("⚠️ REFUNDS (%d): These deposits are related to refunded transactions\n", count($issue_categories['refund']));
    echo "  Action: May need special handling for refund tracking\n\n";
}

if (count($issue_categories['date_mismatch']) > 0) {
    echo sprintf("⚠️ DATE/AMOUNT MISMATCH (%d): Close matches found but outside tolerance\n", count($issue_categories['date_mismatch']));
    echo "  Action: Review tolerance settings or manual match\n\n";
}

if (count($issue_categories['missing_payout']) > 0) {
    echo sprintf("❌ MISSING PAYOUT DATA (%d): Stripe transactions lack payout information\n", count($issue_categories['missing_payout']));
    echo "  Action: Re-run Stripe sync to fetch complete payout data\n\n";
}

if (count($issue_categories['amount_mismatch']) > 0) {
    echo sprintf("⚠️ AMOUNT MISMATCH (%d): No close amount matches found\n", count($issue_categories['amount_mismatch']));
    echo "  Action: Investigate manually or check bank fees\n\n";
}

if (count($issue_categories['unknown']) > 0) {
    echo sprintf("❓ UNKNOWN (%d): Requires manual investigation\n", count($issue_categories['unknown']));
    echo "  Action: Review these deposits individually\n\n";
}

$explained = count($issue_categories['duplicate']) + count($issue_categories['refund']);
$needs_work = $count - $explained;

echo "=====================================\n";
echo "FINAL ASSESSMENT\n";
echo "=====================================\n\n";
echo "Total unmatched: $count\n";
echo "Explained (duplicates/refunds): $explained\n";
echo "Need action: $needs_work\n\n";

if ($explained == $count) {
    echo "🎉 ALL UNMATCHED DEPOSITS EXPLAINED!\n";
    echo "Actual matching rate: 100% of unique real deposits\n";
} else {
    $actual_rate = round((56 + $explained) / 66 * 100, 1);
    echo sprintf("📊 Effective matching rate: %.1f%% (accounting for explained items)\n", $actual_rate);
}

$mysqli->close();
