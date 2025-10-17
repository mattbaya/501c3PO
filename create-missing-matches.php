<?php
/**
 * Directly create the 3 missing match records for Bank #7, #9, #13
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  CREATING MISSING MATCH RECORDS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$matches_to_create = [
    [
        'bank_id' => 7,
        'bank_amount' => 34.28,
        'bank_date' => '2025-09-18',
        'payout_id' => 'po_1S89onJHWUaRCmpEDJd5EpB4'
    ],
    [
        'bank_id' => 9,
        'bank_amount' => 99.99,
        'bank_date' => '2025-09-12',
        'payout_id' => 'po_1S5yyGJHWUaRCmpEkRUUzyM4'
    ],
    [
        'bank_id' => 13,
        'bank_amount' => 48.55,
        'bank_date' => '2025-09-02',
        'payout_id' => 'po_1S2MLRJHWUaRCmpEXaunNlda'
    ]
];

$created = 0;
$skipped = 0;

foreach ($matches_to_create as $match_info) {
    echo "Processing Bank #{$match_info['bank_id']} (${$match_info['bank_amount']} on {$match_info['bank_date']})...\n";

    // Find Stripe charges for this payout
    $charges = $mysqli->query("
        SELECT id, amount, stripe_fee, amount_refunded
        FROM swca_c3_stripe_transactions
        WHERE payout_id = '{$match_info['payout_id']}'
        AND net_amount > 0
        ORDER BY id
    ");

    if (!$charges || $charges->num_rows == 0) {
        echo "  ❌ No Stripe charges found for payout {$match_info['payout_id']}\n\n";
        continue;
    }

    $charge_list = [];
    while ($charge = $charges->fetch_assoc()) {
        $charge_list[] = $charge;
    }

    echo "  Found " . count($charge_list) . " Stripe charges in this payout:\n";

    foreach ($charge_list as $idx => $charge) {
        echo sprintf("    Stripe #%d: $%.2f\n", $charge['id'], $charge['amount']);

        // Check if match already exists
        $existing = $mysqli->query("
            SELECT id FROM swca_c3_transaction_matches
            WHERE stripe_transaction_id = {$charge['id']}
            AND bank_transaction_id = {$match_info['bank_id']}
        ");

        if ($existing && $existing->num_rows > 0) {
            echo "      ⏭ Match already exists\n";
            $skipped++;
            continue;
        }

        // Determine match type (first charge is main, rest are parts)
        $match_type = ($idx === 0) ? 'bank_stripe_payout' : 'bank_stripe_payout_part';

        // Create the match
        $stmt = $mysqli->prepare("
            INSERT INTO swca_c3_transaction_matches
            (stripe_transaction_id, bank_transaction_id, match_type, match_confidence, notes, matched_by, matched_at)
            VALUES (?, ?, ?, 'auto_high', ?, 1, NOW())
        ");

        $notes = sprintf(
            'Payout %s → Bank $%.2f on %s (auto-matched)',
            $match_info['payout_id'],
            $match_info['bank_amount'],
            $match_info['bank_date']
        );

        $stmt->bind_param('iiss',
            $charge['id'],
            $match_info['bank_id'],
            $match_type,
            $notes
        );

        if ($stmt->execute()) {
            echo "      ✓ Created match record (Match #{$mysqli->insert_id})\n";
            $created++;
        } else {
            echo "      ❌ Error: " . $stmt->error . "\n";
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

echo "Current matching status:\n";
echo "  Total Stripe deposits: $total_stripe_deposits\n";
echo "  Matched: $matched_deposits\n";
echo "  Match rate: $match_rate%\n\n";

if ($match_rate >= 100) {
    echo "🎉 100% MATCHING ACHIEVED!\n";
} elseif ($match_rate >= 95) {
    echo "✅ Excellent! Very close to 100%\n";
} else {
    $unmatched = $total_stripe_deposits - $matched_deposits;
    echo "⚠️  Still have $unmatched unmatched deposits\n";
}

$mysqli->close();
?>
