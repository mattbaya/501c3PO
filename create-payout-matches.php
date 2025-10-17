<?php
/**
 * Create matches based on payout_arrival_date after Stripe sync
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  CREATING PAYOUT MATCHES AFTER STRIPE SYNC\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Get all unmatched bank deposits
$unmatched_banks = $mysqli->query("
    SELECT id, credit, post_date
    FROM wp_swca_bank_transactions
    WHERE credit > 0
    AND description LIKE '%STRIPE%'
    AND id NOT IN (
        SELECT DISTINCT bank_transaction_id
        FROM swca_c3_transaction_matches
        WHERE bank_transaction_id IS NOT NULL
    )
    ORDER BY post_date
");

echo "Found " . $unmatched_banks->num_rows . " unmatched bank deposits\n\n";

$created = 0;

while ($bank = $unmatched_banks->fetch_assoc()) {
    $bank_amount = floatval($bank['credit']);
    $bank_date = $bank['post_date'];

    echo sprintf("Bank #%d: $%.2f on %s\n", $bank['id'], $bank_amount, $bank_date);

    // Look for payout in balance_transactions that matches this date and amount
    $payout = $mysqli->query("
        SELECT
            balance_txn_id,
            source_id as payout_id,
            ABS(net) as payout_amount,
            available_on
        FROM swca_c3_stripe_balance_transactions
        WHERE txn_type = 'payout'
        AND DATE(available_on) = DATE('$bank_date')
        AND ABS(ABS(net) - $bank_amount) <= 0.50
        ORDER BY ABS(ABS(net) - $bank_amount) ASC
        LIMIT 1
    ")->fetch_assoc();

    if (!$payout) {
        echo "  ❌ No matching payout found\n\n";
        continue;
    }

    echo sprintf("  ✓ Found payout: %s ($%.2f)\n",
        substr($payout['payout_id'], 0, 30),
        $payout['payout_amount']
    );

    // Get all charge/payment records from balance_transactions for this payout
    $balance_charges = $mysqli->query("
        SELECT source_id as charge_id, amount, net
        FROM swca_c3_stripe_balance_transactions
        WHERE payout_id = '{$payout['payout_id']}'
        AND txn_type IN ('charge', 'payment')
        ORDER BY balance_txn_id
    ");

    if (!$balance_charges || $balance_charges->num_rows == 0) {
        echo "  ⚠️ No charges found for this payout\n\n";
        continue;
    }

    echo "  Found " . $balance_charges->num_rows . " charges in payout\n";

    $idx = 0;
    while ($bal_charge = $balance_charges->fetch_assoc()) {
        // Find this charge in stripe_transactions by stripe_charge_id
        $charge = $mysqli->query("
            SELECT id, amount, stripe_charge_id, net_amount
            FROM swca_c3_stripe_transactions
            WHERE stripe_charge_id = '{$bal_charge['charge_id']}'
        ")->fetch_assoc();

        if (!$charge) {
            echo "  ⚠️ Charge {$bal_charge['charge_id']} not found in stripe_transactions\n";
            continue;
        }
        $match_type = ($idx === 0) ? 'bank_stripe_payout' : 'bank_stripe_payout_part';

        // Check if already matched
        $existing = $mysqli->query("
            SELECT id FROM swca_c3_transaction_matches
            WHERE stripe_transaction_id = {$charge['id']}
            AND bank_transaction_id = {$bank['id']}
        ");

        if ($existing && $existing->num_rows > 0) {
            continue;
        }

        // Insert match
        $stmt = $mysqli->prepare("
            INSERT INTO swca_c3_transaction_matches
            (stripe_transaction_id, bank_transaction_id, match_type, match_confidence, notes, matched_by, matched_at)
            VALUES (?, ?, ?, 'auto_high', ?, 1, NOW())
        ");

        $notes = sprintf(
            'Payout %s: Bank $%.2f on %s',
            $payout['payout_id'],
            $bank_amount,
            $bank_date
        );

        $stmt->bind_param('iiss',
            $charge['id'],
            $bank['id'],
            $match_type,
            $notes
        );

        if ($stmt->execute()) {
            $created++;
            if ($idx == 0) {
                echo "    ✓ Created match for Stripe #{$charge['id']}\n";
            }
        }

        $stmt->close();
        $idx++;
    }

    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  FINAL STATUS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$total = $mysqli->query("SELECT COUNT(*) as cnt FROM wp_swca_bank_transactions WHERE credit > 0 AND description LIKE '%STRIPE%'")->fetch_assoc()['cnt'];
$matched = $mysqli->query("SELECT COUNT(DISTINCT bank_transaction_id) as cnt FROM swca_c3_transaction_matches WHERE bank_transaction_id IS NOT NULL")->fetch_assoc()['cnt'];
$rate = round(($matched / $total) * 100, 1);

echo "Total Stripe deposits: $total\n";
echo "Matched: $matched\n";
echo "Match rate: $rate%\n";
echo "New matches created: $created\n\n";

if ($rate >= 100) {
    echo "🎉 100% MATCHING ACHIEVED!\n";
} else {
    echo "⚠️  Still have " . ($total - $matched) . " unmatched\n";
}

$mysqli->close();
?>
