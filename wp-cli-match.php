<?php
// Load matching function
require_once('/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/transaction-matching.php');

echo "Running Transaction Matching Algorithm...\n\n";

$results = five01c3po_auto_match_transactions(false);

echo "RESULTS:\n";
echo "========\n";
echo "GF → Stripe: {$results['gravity_stripe_matches']} matches\n";
echo "Bank → Stripe (High): {$results['bank_stripe_matches_high']} matches\n";
echo "Bank → Stripe (Medium): {$results['bank_stripe_matches_medium']} matches\n\n";

// Check final rates
global $wpdb;
$total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}c3_stripe_transactions");
$bank_matched = $wpdb->get_var("SELECT COUNT(DISTINCT stripe_transaction_id) FROM {$wpdb->prefix}c3_transaction_matches WHERE bank_transaction_id IS NOT NULL");
$gf_matched = $wpdb->get_var("SELECT COUNT(DISTINCT stripe_transaction_id) FROM {$wpdb->prefix}c3_transaction_matches WHERE gravity_form_transaction_id IS NOT NULL");

$bank_rate = $total > 0 ? round(($bank_matched / $total) * 100, 1) : 0;
$gf_rate = $total > 0 ? round(($gf_matched / $total) * 100, 1) : 0;

echo "Final Matching Rates:\n";
echo "  GF → Stripe: {$gf_matched} / {$total} ({$gf_rate}%)\n";
echo "  Bank → Stripe: {$bank_matched} / {$total} ({$bank_rate}%)\n\n";

if ($bank_rate >= 90) {
    echo "🎉 SUCCESS! Bank matching is {$bank_rate}%\n";
} else if ($bank_rate >= 70) {
    echo "✅ GOOD! Bank matching improved to {$bank_rate}%\n";
} else if ($bank_rate >= 50) {
    echo "📈 IMPROVED! Bank matching is now {$bank_rate}%\n";
} else {
    echo "⚠️  Bank matching at {$bank_rate}% - needs investigation\n";
}
?>
