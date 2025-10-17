<?php
/**
 * Check detailed matching status
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "=== TRANSACTION MATCHING STATUS ===\n\n";

// Total Stripe transactions
$result = $mysqli->query("SELECT COUNT(*) as count FROM swca_stripe_transactions");
$total_stripe = $result->fetch_assoc()['count'];

// Stripe matched to bank
$result = $mysqli->query("
    SELECT COUNT(DISTINCT stripe_transaction_id) as count
    FROM swca_transaction_matches
    WHERE stripe_transaction_id IS NOT NULL
");
$stripe_matched_to_bank = $result->fetch_assoc()['count'];

// Stripe matched to GF
$result = $mysqli->query("
    SELECT COUNT(DISTINCT stripe_transaction_id) as count
    FROM swca_transaction_matches
    WHERE match_type LIKE '%stripe%' AND gf_transaction_id IS NOT NULL
");
$stripe_matched_to_gf = $result->fetch_assoc()['count'];

echo "STRIPE TRANSACTIONS:\n";
echo "  Total: $total_stripe\n";
echo "  Matched to Bank: $stripe_matched_to_bank (" . round(($stripe_matched_to_bank / $total_stripe) * 100, 1) . "%)\n";
echo "  Matched to Gravity Forms: $stripe_matched_to_gf (" . round(($stripe_matched_to_gf / $total_stripe) * 100, 1) . "%)\n";
echo "  Unmatched: " . ($total_stripe - $stripe_matched_to_bank) . "\n\n";

// Bank deposits
$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions WHERE credit > 0");
$total_bank_deposits = $result->fetch_assoc()['count'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions WHERE credit > 0 AND description LIKE '%STRIPE%'");
$stripe_bank_deposits = $result->fetch_assoc()['count'];

$result = $mysqli->query("
    SELECT COUNT(DISTINCT b.id) as count
    FROM wp_swca_bank_transactions b
    INNER JOIN swca_transaction_matches m ON b.id = m.bank_transaction_id
    WHERE b.credit > 0 AND b.description LIKE '%STRIPE%'
");
$matched_stripe_deposits = $result->fetch_assoc()['count'];

echo "BANK DEPOSITS:\n";
echo "  Total deposits: $total_bank_deposits\n";
echo "  Stripe ACH transfers: $stripe_bank_deposits\n";
echo "  Stripe deposits matched: $matched_stripe_deposits (" . round(($matched_stripe_deposits / $stripe_bank_deposits) * 100, 1) . "%)\n";
echo "  Unmatched Stripe deposits: " . ($stripe_bank_deposits - $matched_stripe_deposits) . "\n\n";

// Match types breakdown
echo "MATCH TYPES:\n";
$result = $mysqli->query("SELECT match_type, COUNT(*) as count FROM swca_transaction_matches GROUP BY match_type ORDER BY count DESC");
while ($row = $result->fetch_assoc()) {
    echo "  {$row['match_type']}: {$row['count']}\n";
}

$mysqli->close();
?>
