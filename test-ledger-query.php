<?php
/**
 * Test Transaction Ledger Query with Unmatched Stripe
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once('/home/swca/public_html/wp-load.php');

global $wpdb;

// Use new c3_ table naming convention
$stripe_table = $wpdb->prefix . 'c3_stripe_transactions';
$gf_table = $wpdb->prefix . 'c3_gf_payment_transaction';
$bank_table = $wpdb->prefix . 'c3_bank_transactions';
$matches_table = $wpdb->prefix . 'c3_transaction_matches';

echo "=== TESTING TRANSACTION LEDGER WITH UNMATCHED STRIPE ===\n\n";

// Count unmatched Stripe transactions
$unmatched_count = $wpdb->get_var("
    SELECT COUNT(*)
    FROM $stripe_table s
    LEFT JOIN $matches_table m ON m.stripe_transaction_id = s.id AND m.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
    WHERE m.id IS NULL
");

echo "Unmatched Stripe Transactions: $unmatched_count\n";

// Count matched Stripe transactions
$matched_count = $wpdb->get_var("
    SELECT COUNT(DISTINCT s.id)
    FROM $stripe_table s
    INNER JOIN $matches_table m ON m.stripe_transaction_id = s.id AND m.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
");

echo "Matched Stripe Transactions: $matched_count\n";

// Count bank transactions
$bank_count = $wpdb->get_var("SELECT COUNT(*) FROM $bank_table");
echo "Bank Transactions: $bank_count\n\n";

// Get a few recent unmatched Stripe transactions
echo "=== RECENT UNMATCHED STRIPE TRANSACTIONS ===\n";
$recent_unmatched = $wpdb->get_results("
    SELECT s.id, DATE(s.charge_created) as date, s.amount, s.customer_name, s.customer_email
    FROM $stripe_table s
    LEFT JOIN $matches_table m ON m.stripe_transaction_id = s.id AND m.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
    WHERE m.id IS NULL
    ORDER BY s.charge_created DESC
    LIMIT 5
");

foreach ($recent_unmatched as $txn) {
    echo "ID: {$txn->id} | Date: {$txn->date} | Amount: \${$txn->amount} | Customer: {$txn->customer_name} ({$txn->customer_email})\n";
}

echo "\n=== TEST COMPLETE ===\n";
