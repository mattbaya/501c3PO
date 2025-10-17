<?php
/**
 * Investigate why Bank #7, #9, #13 aren't matching
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  INVESTIGATING BANK #7, #9, #13 - WHY NO MATCHES?\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$bank_ids = [7, 9, 13];

foreach ($bank_ids as $bank_id) {
    echo "─────────────────────────────────────────────────────────────\n";

    // Get bank transaction details
    $bank = $mysqli->query("
        SELECT id, post_date, credit, description
        FROM wp_swca_bank_transactions
        WHERE id = $bank_id
    ")->fetch_assoc();

    if (!$bank) {
        echo "Bank #$bank_id: NOT FOUND\n\n";
        continue;
    }

    echo sprintf("BANK #%d: $%.2f on %s\n", $bank['id'], $bank['credit'], $bank['post_date']);
    echo "Description: {$bank['description']}\n\n";

    $bank_amount = floatval($bank['credit']);
    $bank_date = $bank['post_date'];

    // Search for Stripe payouts around this date/amount
    $date_range_start = date('Y-m-d', strtotime($bank_date . ' -7 days'));
    $date_range_end = date('Y-m-d', strtotime($bank_date . ' +2 days'));

    echo "Searching for Stripe payouts:\n";
    echo "  Date range: $date_range_start to $date_range_end\n";
    echo "  Amount range: $" . ($bank_amount - 0.50) . " to $" . ($bank_amount + 0.50) . "\n\n";

    // Check balance_transactions table for payouts
    $payouts = $mysqli->query("
        SELECT
            balance_txn_id,
            available_on,
            ABS(net) as payout_amount,
            description,
            payout_id
        FROM swca_c3_stripe_balance_transactions
        WHERE txn_type = 'payout'
        AND DATE(available_on) BETWEEN '$date_range_start' AND '$date_range_end'
        AND ABS(ABS(net) - $bank_amount) <= 0.50
        ORDER BY ABS(ABS(net) - $bank_amount) ASC
        LIMIT 5
    ");

    if ($payouts && $payouts->num_rows > 0) {
        echo "FOUND POTENTIAL PAYOUT MATCHES:\n";
        while ($payout = $payouts->fetch_assoc()) {
            $diff = abs($payout['payout_amount'] - $bank_amount);
            $days_diff = abs((strtotime($payout['available_on']) - strtotime($bank_date)) / 86400);

            echo sprintf("  • %s: $%.2f on %s (diff: $%.2f, days: %.1f)\n",
                substr($payout['balance_txn_id'], 0, 25),
                $payout['payout_amount'],
                $payout['available_on'],
                $diff,
                $days_diff
            );

            if ($payout['payout_id']) {
                echo "    Payout ID: {$payout['payout_id']}\n";
            }
        }
        echo "\n";
    } else {
        echo "❌ NO PAYOUT MATCHES FOUND in balance_transactions table\n\n";
    }

    // Also check if ANY charges exist with similar amounts around this date
    echo "Checking for individual Stripe charges with similar amounts:\n";
    $charges = $mysqli->query("
        SELECT
            id,
            stripe_charge_id,
            amount,
            stripe_created,
            payout_arrival_date,
            payout_id
        FROM swca_c3_stripe_transactions
        WHERE ABS(amount - $bank_amount) <= 5.00
        AND DATE(stripe_created) BETWEEN '$date_range_start' AND '$date_range_end'
        ORDER BY ABS(amount - $bank_amount) ASC
        LIMIT 5
    ");

    if ($charges && $charges->num_rows > 0) {
        echo "FOUND SIMILAR CHARGES:\n";
        while ($charge = $charges->fetch_assoc()) {
            echo sprintf("  • Stripe #%d (%s): $%.2f on %s\n",
                $charge['id'],
                substr($charge['stripe_charge_id'], 0, 20),
                $charge['amount'],
                $charge['stripe_created']
            );
            echo "    Payout date: " . ($charge['payout_arrival_date'] ?: 'NULL') . "\n";
            echo "    Payout ID: " . ($charge['payout_id'] ?: 'NULL') . "\n";
        }
        echo "\n";
    } else {
        echo "No individual charges found\n\n";
    }

    // Check if this bank transaction is already matched
    $match = $mysqli->query("
        SELECT
            m.id as match_id,
            m.stripe_transaction_id,
            m.confidence_level,
            s.amount,
            s.payout_arrival_date
        FROM swca_c3_transaction_matches m
        LEFT JOIN swca_c3_stripe_transactions s ON m.stripe_transaction_id = s.id
        WHERE m.bank_transaction_id = $bank_id
    ");

    if ($match && $match->num_rows > 0) {
        echo "✓ ALREADY MATCHED:\n";
        while ($m = $match->fetch_assoc()) {
            echo sprintf("  Match #%d → Stripe #%d ($%.2f, payout: %s)\n",
                $m['match_id'],
                $m['stripe_transaction_id'],
                $m['amount'],
                $m['payout_arrival_date'] ?: 'NULL'
            );
        }
        echo "\n";
    } else {
        echo "✗ NOT MATCHED\n\n";
    }

    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Possible reasons for no matches:\n";
echo "1. Missing payout data in balance_transactions table\n";
echo "2. Date mismatch (bank shows different date than Stripe)\n";
echo "3. Amount mismatch (fees/rounding differences)\n";
echo "4. These deposits are from multiple small charges (need grouping)\n";
echo "5. Stripe sync didn't capture the payout records for these dates\n";

$mysqli->close();
?>
