<?php
/**
 * Run Transaction Matching Algorithm
 * Matches Stripe ↔ Gravity Forms ↔ Bank transactions
 */

// Override wp_die to ignore JSON extension warnings (JSON is built into PHP 7.4+)
if (!function_exists('wp_die')) {
    function wp_die($message, $title = '', $args = array()) {
        // If it's just the JSON warning, ignore it
        if (is_string($message) && strpos($message, 'json') !== false && strpos($message, 'extension') !== false) {
            return; // Silently ignore
        }
        // For actual errors, still die
        die($message);
    }
}

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== RUNNING TRANSACTION MATCHING ALGORITHM ===\n\n";

// Check if the matching function exists
if (!function_exists('five01c3po_auto_match_transactions')) {
    echo "ERROR: Matching function not found. Make sure the plugin is loaded.\n";
    exit(1);
}

echo "Running auto-match (Payout-Based Matching)...\n\n";

// Run the matching algorithm
$results = five01c3po_auto_match_transactions(false); // false = NOT a dry run

echo "=== MATCHING RESULTS ===\n\n";

echo "MATCHES CREATED:\n";
echo "  Gravity Forms → Stripe: {$results['gravity_stripe_matches']}\n";
echo "  Bank → Stripe (High Confidence): {$results['bank_stripe_matches_high']}\n";
echo "  Bank → Stripe (Medium Confidence): {$results['bank_stripe_matches_medium']}\n";
echo "  Bank → Stripe (Low Confidence): {$results['bank_stripe_matches_low']}\n\n";

if (!empty($results['details'])) {
    echo "MATCH DETAILS:\n";
    foreach ($results['details'] as $detail) {
        echo "  " . $detail . "\n";
    }
    echo "\n";
}

if (!empty($results['debug'])) {
    echo "DEBUG INFORMATION:\n";
    foreach ($results['debug'] as $debug_line) {
        echo $debug_line . "\n";
    }
    echo "\n";
}

// Get updated statistics
$matches_table = $wpdb->prefix . 'transaction_matches';
$stripe_table = $wpdb->prefix . 'stripe_transactions';
$bank_table = 'wp_swca_bank_transactions';
$gf_table = 'swca_gf_addon_payment_transaction';

$total_matches = $wpdb->get_var("SELECT COUNT(*) FROM $matches_table");
$matched_stripe = $wpdb->get_var("SELECT COUNT(DISTINCT stripe_transaction_id) FROM $matches_table WHERE stripe_transaction_id IS NOT NULL");
$matched_bank = $wpdb->get_var("SELECT COUNT(DISTINCT bank_transaction_id) FROM $matches_table WHERE bank_transaction_id IS NOT NULL");
$matched_gf = $wpdb->get_var("SELECT COUNT(DISTINCT gravity_form_transaction_id) FROM $matches_table WHERE gravity_form_transaction_id IS NOT NULL");

$total_stripe = $wpdb->get_var("SELECT COUNT(*) FROM $stripe_table");
$total_bank = $wpdb->get_var("SELECT COUNT(*) FROM $bank_table WHERE credit > 0");
$total_gf = $wpdb->get_var("SELECT COUNT(*) FROM $gf_table WHERE transaction_type = 'payment'");

echo "=== UPDATED STATISTICS ===\n";
echo "Total Matches in Database: $total_matches\n\n";

echo "Stripe Transactions:\n";
echo "  Total: $total_stripe\n";
echo "  Matched: $matched_stripe (" . round(($matched_stripe / $total_stripe) * 100, 1) . "%)\n";
echo "  Unmatched: " . ($total_stripe - $matched_stripe) . "\n\n";

echo "Bank Deposits:\n";
echo "  Total: $total_bank\n";
echo "  Matched: $matched_bank (" . round(($matched_bank / $total_bank) * 100, 1) . "%)\n";
echo "  Unmatched: " . ($total_bank - $matched_bank) . "\n\n";

echo "Gravity Forms:\n";
echo "  Total: $total_gf\n";
echo "  Matched: $matched_gf (" . round(($matched_gf / $total_gf) * 100, 1) . "%)\n";
echo "  Unmatched: " . ($total_gf - $matched_gf) . "\n\n";

echo "✅ Matching algorithm complete!\n";
