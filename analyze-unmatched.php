<?php
/**
 * Analyze Why Matching Isn't 100%
 * Shows exactly which transactions are unmatched and why
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=====================================\n";
echo "100% MATCHING ANALYSIS\n";
echo "=====================================\n\n";

// 1. STRIPE TRANSACTIONS
echo "1. STRIPE → GRAVITY FORMS MATCHING\n";
echo "-------------------------------------\n";

$total_stripe = $mysqli->query("SELECT COUNT(*) FROM swca_c3_stripe_transactions WHERE net_amount > 0")->fetch_row()[0];
$matched_stripe_gf = $mysqli->query("
    SELECT COUNT(DISTINCT stripe_transaction_id)
    FROM swca_c3_transaction_matches
    WHERE stripe_transaction_id IS NOT NULL
    AND gravity_form_transaction_id IS NOT NULL
")->fetch_row()[0];

$unmatched_stripe_gf = $total_stripe - $matched_stripe_gf;
$match_rate_gf = $total_stripe > 0 ? ($matched_stripe_gf / $total_stripe) * 100 : 0;

echo sprintf("Total Stripe charges (net > 0): %d\n", $total_stripe);
echo sprintf("Matched to Gravity Forms: %d (%.1f%%)\n", $matched_stripe_gf, $match_rate_gf);
echo sprintf("Unmatched: %d\n\n", $unmatched_stripe_gf);

if ($unmatched_stripe_gf > 0) {
    echo "UNMATCHED STRIPE TRANSACTIONS:\n";
    $result = $mysqli->query("
        SELECT
            s.id,
            s.stripe_id,
            s.amount,
            s.stripe_created,
            s.customer_email,
            s.customer_name
        FROM swca_c3_stripe_transactions s
        WHERE s.net_amount > 0
        AND s.id NOT IN (
            SELECT DISTINCT stripe_transaction_id
            FROM swca_c3_transaction_matches
            WHERE stripe_transaction_id IS NOT NULL
            AND gravity_form_transaction_id IS NOT NULL
        )
        ORDER BY s.stripe_created DESC
        LIMIT 10
    ");

    while ($row = $result->fetch_assoc()) {
        echo sprintf("  Stripe #%d (%s): $%.2f on %s - %s (%s)\n",
            $row['id'], $row['stripe_id'], $row['amount'],
            substr($row['stripe_created'], 0, 10),
            $row['customer_name'] ?: 'No name',
            $row['customer_email'] ?: 'No email'
        );
    }
    if ($unmatched_stripe_gf > 10) {
        echo sprintf("  ... and %d more\n", $unmatched_stripe_gf - 10);
    }
}

echo "\n2. STRIPE → BANK MATCHING\n";
echo "-------------------------------------\n";

$matched_stripe_bank = $mysqli->query("
    SELECT COUNT(DISTINCT stripe_transaction_id)
    FROM swca_c3_transaction_matches
    WHERE stripe_transaction_id IS NOT NULL
    AND bank_transaction_id IS NOT NULL
")->fetch_row()[0];

$unmatched_stripe_bank = $total_stripe - $matched_stripe_bank;
$match_rate_bank = $total_stripe > 0 ? ($matched_stripe_bank / $total_stripe) * 100 : 0;

echo sprintf("Total Stripe charges (net > 0): %d\n", $total_stripe);
echo sprintf("Matched to Bank: %d (%.1f%%)\n", $matched_stripe_bank, $match_rate_bank);
echo sprintf("Unmatched: %d\n\n", $unmatched_stripe_bank);

if ($unmatched_stripe_bank > 0) {
    echo "WHY AREN'T THESE MATCHED TO BANK?\n";

    // Check if they have payout data
    $result = $mysqli->query("
        SELECT
            s.id,
            s.stripe_id,
            s.amount,
            s.net_amount,
            s.stripe_created,
            s.payout_arrival_date,
            s.payout_id
        FROM swca_c3_stripe_transactions s
        WHERE s.net_amount > 0
        AND s.id NOT IN (
            SELECT DISTINCT stripe_transaction_id
            FROM swca_c3_transaction_matches
            WHERE stripe_transaction_id IS NOT NULL
            AND bank_transaction_id IS NOT NULL
        )
        ORDER BY s.stripe_created DESC
        LIMIT 10
    ");

    $no_payout_date = 0;
    $has_payout_date = 0;

    while ($row = $result->fetch_assoc()) {
        if (!$row['payout_arrival_date']) {
            $no_payout_date++;
            echo sprintf("  ⚠️ Stripe #%d: $%.2f on %s - NO PAYOUT DATE!\n",
                $row['id'], $row['net_amount'], substr($row['stripe_created'], 0, 10));
        } else {
            $has_payout_date++;
            echo sprintf("  ? Stripe #%d: $%.2f on %s → Payout %s (%s)\n",
                $row['id'], $row['net_amount'],
                substr($row['stripe_created'], 0, 10),
                $row['payout_arrival_date'],
                $row['payout_id'] ?: 'no payout ID'
            );
        }
    }

    echo sprintf("\n  Missing payout dates: %d\n", $no_payout_date);
    echo sprintf("  Have payout dates but not matched: %d\n", $has_payout_date);
}

echo "\n3. BANK DEPOSITS\n";
echo "-------------------------------------\n";

$total_bank = $mysqli->query("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE credit > 0")->fetch_row()[0];
$total_bank_stripe = $mysqli->query("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE credit > 0 AND description LIKE '%STRIPE%'")->fetch_row()[0];
$matched_bank = $mysqli->query("
    SELECT COUNT(DISTINCT bank_transaction_id)
    FROM swca_c3_transaction_matches
    WHERE bank_transaction_id IS NOT NULL
")->fetch_row()[0];

$unmatched_bank = $total_bank - $matched_bank;
$match_rate = $total_bank_stripe > 0 ? ($matched_bank / $total_bank_stripe) * 100 : 0;

echo sprintf("Total bank deposits: %d\n", $total_bank);
echo sprintf("Deposits with 'STRIPE' in description: %d\n", $total_bank_stripe);
echo sprintf("Matched to Stripe: %d (%.1f%% of Stripe deposits)\n", $matched_bank, $match_rate);
echo sprintf("Unmatched: %d\n\n", $unmatched_bank);

if ($unmatched_bank > 0) {
    echo "UNMATCHED BANK DEPOSITS:\n";
    $result = $mysqli->query("
        SELECT
            b.id,
            b.post_date,
            b.credit,
            b.description
        FROM wp_swca_bank_transactions b
        WHERE b.credit > 0
        AND b.id NOT IN (
            SELECT DISTINCT bank_transaction_id
            FROM swca_c3_transaction_matches
            WHERE bank_transaction_id IS NOT NULL
        )
        ORDER BY b.post_date DESC
        LIMIT 20
    ");

    $stripe_deposits = 0;
    $non_stripe_deposits = 0;

    while ($row = $result->fetch_assoc()) {
        $is_stripe = stripos($row['description'], 'STRIPE') !== false;
        if ($is_stripe) {
            $stripe_deposits++;
            echo sprintf("  ⚠️ STRIPE Bank #%d: $%.2f on %s - %s\n",
                $row['id'], $row['credit'], $row['post_date'], substr($row['description'], 0, 50));
        } else {
            $non_stripe_deposits++;
            echo sprintf("  ✓ Non-Stripe Bank #%d: $%.2f on %s - %s (cash/check/other)\n",
                $row['id'], $row['credit'], $row['post_date'], substr($row['description'], 0, 50));
        }
    }

    echo sprintf("\n  Unmatched Stripe deposits: %d\n", $stripe_deposits);
    echo sprintf("  Non-Stripe deposits (expected): %d\n", $non_stripe_deposits);
}

echo "\n=====================================\n";
echo "SUMMARY: What's Preventing 100%?\n";
echo "=====================================\n";
echo sprintf("GF → Stripe: %.1f%% (target: 100%%)\n", $match_rate_gf);
echo sprintf("Bank → Stripe: %.1f%% (target: 100%% of Stripe deposits)\n", $match_rate);
echo "\nNext Steps:\n";
echo "1. Check if missing GF data (form not submitted?)\n";
echo "2. Check if Stripe payout dates are missing/incorrect\n";
echo "3. Check if bank CSV imports are complete\n";
echo "4. Investigate date range mismatches\n";

$mysqli->close();
