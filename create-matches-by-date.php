<?php
/**
 * Create matches by matching payout_arrival_date to bank dates
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  CREATING MATCHES BY PAYOUT DATE\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$test_banks = [7, 9, 13, 15, 18, 31, 32];
$created = 0;
$skipped = 0;

foreach ($test_banks as $bank_id) {
    $bank = $mysqli->query("
        SELECT id, credit, post_date, description
        FROM wp_swca_bank_transactions
        WHERE id = $bank_id
    ")->fetch_assoc();

    if (!$bank) {
        echo "Bank #$bank_id: NOT FOUND\n\n";
        continue;
    }

    echo sprintf("Bank #%d: $%.2f on %s\n", $bank['id'], $bank['credit'], $bank['post_date']);
    $bank_amount = floatval($bank['credit']);

    // Find charges with matching payout date
    $charges = $mysqli->query("
        SELECT
            id,
            stripe_charge_id,
            amount,
            payout_id,
            payout_arrival_date
        FROM swca_c3_stripe_transactions
        WHERE DATE(payout_arrival_date) = DATE('{$bank['post_date']}')
        AND net_amount > 0
        ORDER BY id
    ");

    if (!$charges || $charges->num_rows == 0) {
        echo "  ❌ No charges found with payout date = {$bank['post_date']}\n\n";
        continue;
    }

    $charge_list = [];
    $total_net = 0;

    while ($charge = $charges->fetch_assoc()) {
        $charge_list[] = $charge;
        // Calculate net from charge table
        $net_query = $mysqli->query("
            SELECT (amount - stripe_fee - amount_refunded) as net
            FROM swca_c3_stripe_transactions
            WHERE id = {$charge['id']}
        ");
        $net = $net_query->fetch_assoc()['net'];
        $total_net += $net;
    }

    $diff = abs($total_net - $bank_amount);

    echo sprintf("  Found %d charges, total net: $%.2f, diff: $%.2f\n",
        count($charge_list),
        $total_net,
        $diff
    );

    if ($diff > 1.00) {
        echo "  ⚠️ Amount difference too large, skipping\n\n";
        continue;
    }

    // Create matches
    foreach ($charge_list as $idx => $charge) {
        // Check if already matched
        $existing = $mysqli->query("
            SELECT id FROM swca_c3_transaction_matches
            WHERE stripe_transaction_id = {$charge['id']}
            AND bank_transaction_id = $bank_id
        ");

        if ($existing && $existing->num_rows > 0) {
            $skipped++;
            continue;
        }

        $match_type = ($idx === 0) ? 'bank_stripe_payout' : 'bank_stripe_payout_part';

        $stmt = $mysqli->prepare("
            INSERT INTO swca_c3_transaction_matches
            (stripe_transaction_id, bank_transaction_id, match_type, match_confidence, notes, matched_by, matched_at)
            VALUES (?, ?, ?, 'auto_high', ?, 1, NOW())
        ");

        $notes = sprintf(
            'Matched by payout date: Bank $%.2f on %s = %d charges ($%.2f total)',
            $bank_amount,
            $bank['post_date'],
            count($charge_list),
            $total_net
        );

        $stmt->bind_param('iiss',
            $charge['id'],
            $bank_id,
            $match_type,
            $notes
        );

        if ($stmt->execute()) {
            if ($idx == 0) {
                echo "  ✓ Created match for Stripe #{$charge['id']} (main)\n";
            }
            $created++;
        } else {
            echo "  ❌ Error: " . $stmt->error . "\n";
        }

        $stmt->close();
    }

    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Match records created: $created\n";
echo "Already existed (skipped): $skipped\n\n";

// Check final status
$total_stripe_deposits = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM wp_swca_bank_transactions
    WHERE credit > 0 AND description LIKE '%STRIPE%'
")->fetch_assoc()['cnt'];

$matched_deposits = $mysqli->query("
    SELECT COUNT(DISTINCT bank_transaction_id) as cnt
    FROM swca_c3_transaction_matches
    WHERE bank_transaction_id IS NOT NULL
")->fetch_assoc()['cnt'];

$match_rate = $total_stripe_deposits > 0 ? round(($matched_deposits / $total_stripe_deposits) * 100, 1) : 0;

echo "FINAL MATCHING STATUS:\n";
echo "  Total Stripe deposits: $total_stripe_deposits\n";
echo "  Matched: $matched_deposits\n";
echo "  Unmatched: " . ($total_stripe_deposits - $matched_deposits) . "\n";
echo "  Match rate: $match_rate%\n\n";

if ($match_rate >= 100) {
    echo "🎉 100% MATCHING ACHIEVED!\n";
} else if ($match_rate >= 95) {
    echo "✅ 95%+ achieved! Almost there!\n";
} else {
    echo "⚠️  Still need improvement\n";
}

$mysqli->close();
?>
