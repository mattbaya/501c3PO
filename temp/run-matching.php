<?php
/**
 * Web-accessible script to run matching algorithm
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once('../../../../wp-load.php');

// Load matching function
require_once('../includes/features/transaction-matching.php');

header('Content-Type: text/plain');

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
$bank_rate = $total > 0 ? round(($bank_matched / $total) * 100, 1) : 0;

echo "Final bank matching rate: {$bank_rate}%\n";
?>
