<?php
/**
 * Run the transaction matching algorithm directly
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once('/home/swca/public_html/wp-load.php');

echo "═══════════════════════════════════════════════════════════════\n";
echo "  RUNNING TRANSACTION MATCHING ALGORITHM\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Load the matching function
require_once('/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/transaction-matching.php');

// Run the matching algorithm
echo "Starting auto-match...\n\n";
$results = five01c3po_auto_match_transactions(false);

echo "═══════════════════════════════════════════════════════════════\n";
echo "  RESULTS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "✅ Gravity Forms → Stripe: {$results['gravity_stripe_matches']} matches\n";
echo "✅ Bank → Stripe (High Confidence): {$results['bank_stripe_matches_high']} matches\n";
echo "⚠️  Bank → Stripe (Medium Confidence): {$results['bank_stripe_matches_medium']} matches\n\n";

if (!empty($results['debug'])) {
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  DEBUG INFORMATION\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    foreach ($results['debug'] as $line) {
        echo $line . "\n";
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  VERIFICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

global $wpdb;

// Check final match rates
$total_stripe = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}c3_stripe_transactions");
$matched_stripe_gf = $wpdb->get_var("SELECT COUNT(DISTINCT stripe_transaction_id) FROM {$wpdb->prefix}c3_transaction_matches WHERE gravity_form_transaction_id IS NOT NULL");
$matched_stripe_bank = $wpdb->get_var("SELECT COUNT(DISTINCT stripe_transaction_id) FROM {$wpdb->prefix}c3_transaction_matches WHERE bank_transaction_id IS NOT NULL");

$gf_rate = $total_stripe > 0 ? round(($matched_stripe_gf / $total_stripe) * 100, 1) : 0;
$bank_rate = $total_stripe > 0 ? round(($matched_stripe_bank / $total_stripe) * 100, 1) : 0;

echo "Final Matching Rates:\n";
echo "  • Stripe → GF: {$matched_stripe_gf} / {$total_stripe} ({$gf_rate}%)\n";
echo "  • Stripe → Bank: {$matched_stripe_bank} / {$total_stripe} ({$bank_rate}%)\n\n";

if ($bank_rate >= 90) {
    echo "🎉 SUCCESS! Bank matching is now {$bank_rate}%\n";
} elseif ($bank_rate >= 70) {
    echo "✅ GOOD! Bank matching improved to {$bank_rate}%\n";
} else {
    echo "⚠️  Bank matching at {$bank_rate}% - may need investigation\n";
}

echo "\n";
?>
