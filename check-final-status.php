<?php
/**
 * Check Final Matching Status
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  FINAL MATCHING STATUS CHECK\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Total Stripe deposits
$total_stripe_deposits = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM wp_swca_bank_transactions
    WHERE credit > 0 AND description LIKE '%STRIPE%'
")->fetch_assoc()['cnt'];

// Matched Stripe deposits
$matched_deposits = $mysqli->query("
    SELECT COUNT(DISTINCT bank_transaction_id) as cnt
    FROM swca_c3_transaction_matches
    WHERE bank_transaction_id IS NOT NULL
")->fetch_assoc()['cnt'];

$unmatched = $total_stripe_deposits - $matched_deposits;
$match_rate = $total_stripe_deposits > 0 ? round(($matched_deposits / $total_stripe_deposits) * 100, 1) : 0;

echo "OVERALL STATUS:\n";
echo "─────────────────────────────────────────────────────────────\n";
echo "Total Stripe bank deposits: $total_stripe_deposits\n";
echo "Successfully matched: $matched_deposits\n";
echo "Unmatched: $unmatched\n";
echo "Match rate: $match_rate%\n\n";

if ($match_rate >= 100) {
    echo "🎉 100% MATCHING ACHIEVED!\n\n";
} elseif ($match_rate >= 95) {
    echo "✅ Excellent matching rate (>95%)\n\n";
} else {
    echo "⚠️  Still need improvement\n\n";
}

// Check specific bank transactions
echo "SPECIFIC TRANSACTIONS:\n";
echo "─────────────────────────────────────────────────────────────\n\n";

$bank_ids = [7, 9, 13, 15, 18, 31, 32];

foreach ($bank_ids as $bank_id) {
    $bank = $mysqli->query("
        SELECT id, post_date, credit, description
        FROM wp_swca_bank_transactions
        WHERE id = $bank_id
    ")->fetch_assoc();

    if (!$bank) {
        echo "Bank #$bank_id: DELETED\n\n";
        continue;
    }

    $match_check = $mysqli->query("
        SELECT
            m.id as match_id,
            m.stripe_transaction_id,
            m.confidence_level,
            s.amount as stripe_amount,
            s.payout_arrival_date
        FROM swca_c3_transaction_matches m
        LEFT JOIN swca_c3_stripe_transactions s ON m.stripe_transaction_id = s.id
        WHERE m.bank_transaction_id = $bank_id
    ");

    $is_matched = $match_check && $match_check->num_rows > 0;

    echo sprintf("Bank #%d: $%.2f on %s\n",
        $bank['id'],
        $bank['credit'],
        $bank['post_date']
    );

    if ($is_matched) {
        $match = $match_check->fetch_assoc();
        echo sprintf("  ✓ MATCHED to Stripe #%d (Match #%d, confidence: %s)\n",
            $match['stripe_transaction_id'],
            $match['match_id'],
            $match['confidence_level']
        );
        echo sprintf("    Stripe amount: $%.2f, Payout date: %s\n",
            $match['stripe_amount'],
            $match['payout_arrival_date'] ?: 'NULL'
        );
    } else {
        echo "  ✗ NOT MATCHED\n";
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  UNMATCHED DEPOSITS BREAKDOWN\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$unmatched_list = $mysqli->query("
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
    LIMIT 10
");

if ($unmatched_list && $unmatched_list->num_rows > 0) {
    echo "Unmatched deposits:\n\n";
    while ($row = $unmatched_list->fetch_assoc()) {
        echo sprintf("  Bank #%d: $%.2f on %s\n",
            $row['id'],
            $row['credit'],
            $row['post_date']
        );
    }
} else {
    echo "No unmatched deposits!\n";
}

$mysqli->close();
?>
