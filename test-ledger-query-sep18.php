<?php
/**
 * Test Ledger Query for Sep 18, 2025 Specifically
 */

require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== TESTING LEDGER QUERY FOR SEP 18, 2025 ===\n\n";

// Use same table names as ledger
$stripe_table = $wpdb->prefix . 'c3_stripe_transactions';
$gf_table = $wpdb->prefix . 'c3_gf_payment_transaction';
$bank_table = $wpdb->prefix . 'c3_bank_transactions';
$matches_table = $wpdb->prefix . 'c3_transaction_matches';

// Simplified query just for Sep 18
$query = "
    SELECT
        b.id as bank_id,
        b.post_date,
        b.credit,
        b.description,
        GROUP_CONCAT(DISTINCT m_stripe.id ORDER BY m_stripe.id) as match_record_ids,
        GROUP_CONCAT(DISTINCT m_stripe.stripe_transaction_id ORDER BY m_stripe.stripe_transaction_id) as matched_stripe_ids,
        COUNT(DISTINCT m_stripe.id) as match_record_count,
        COUNT(DISTINCT m_stripe.stripe_transaction_id) as stripe_match_count,
        GROUP_CONCAT(DISTINCT s.customer_email ORDER BY s.id) as customer_emails,
        GROUP_CONCAT(DISTINCT s.customer_name ORDER BY s.id) as customer_names
    FROM $bank_table b
    LEFT JOIN $matches_table m_stripe
        ON m_stripe.bank_transaction_id = b.id
        AND m_stripe.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
    LEFT JOIN $stripe_table s ON s.id = m_stripe.stripe_transaction_id
    WHERE b.post_date = '2025-09-18'
    GROUP BY b.id
    ORDER BY b.id
";

$results = $wpdb->get_results($query);

echo "Query Results for Sep 18, 2025:\n";
echo str_repeat("=", 100) . "\n\n";

foreach ($results as $row) {
    echo "Bank ID: {$row->bank_id}\n";
    echo "Date: {$row->post_date}\n";
    echo "Amount: \${$row->credit}\n";
    echo "Description: {$row->description}\n";
    echo "Match Record IDs: " . ($row->match_record_ids ?: 'NONE') . "\n";
    echo "Match Record Count: {$row->match_record_count}\n";
    echo "Stripe Transaction IDs: " . ($row->matched_stripe_ids ?: 'NONE') . "\n";
    echo "Stripe Match Count: {$row->stripe_match_count}\n";
    echo "Customer Emails: " . ($row->customer_emails ?: 'NONE') . "\n";
    echo "Customer Names: " . ($row->customer_names ?: 'NONE') . "\n";
    echo str_repeat("-", 100) . "\n\n";
}

echo "Total rows returned: " . count($results) . "\n";
echo "\nIf this shows 1 row, the duplicate is NOT from the database or GROUP BY.\n";
echo "If this shows 2+ rows, we have a real duplicate issue.\n";

// Also check if there's an unmatched Stripe transaction that might create a duplicate
echo "\n\n=== CHECKING FOR UNMATCHED STRIPE ON SEP 18 ===\n\n";

$unmatched_query = "
    SELECT s.id, s.stripe_created, s.amount, s.customer_email, m.id as has_match
    FROM $stripe_table s
    LEFT JOIN $matches_table m ON m.stripe_transaction_id = s.id
        AND m.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
    WHERE DATE(s.stripe_created) = '2025-09-18'
";

$unmatched = $wpdb->get_results($unmatched_query);

echo "Stripe transactions created on Sep 18:\n";
foreach ($unmatched as $u) {
    echo "Stripe ID: {$u->id}, Amount: \${$u->amount}, Email: {$u->customer_email}, ";
    echo "Has Bank Match: " . ($u->has_match ? "YES (ID {$u->has_match})" : "NO") . "\n";
}

echo "\nTotal Stripe transactions on Sep 18: " . count($unmatched) . "\n";
