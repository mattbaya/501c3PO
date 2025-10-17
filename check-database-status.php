<?php
/**
 * Database Status Check Script
 * Checks bank transactions, Stripe data, and matching status
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== DATABASE STATUS CHECK ===\n\n";

// 1. Bank Transactions
echo "1. BANK TRANSACTIONS:\n";
$bank_count = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions");
$bank_with_balance = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE balance != 0");
$bank_dates = $wpdb->get_row("SELECT MIN(date) as earliest, MAX(date) as latest FROM wp_swca_bank_transactions");

echo "   Total transactions: $bank_count\n";
echo "   With calculated balance: $bank_with_balance\n";
echo "   Date range: {$bank_dates->earliest} to {$bank_dates->latest}\n";

// Check for recent transactions
$recent_bank = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE date >= '2025-10-01'");
echo "   October 2025 transactions: $recent_bank\n\n";

// 2. Stripe Transactions
echo "2. STRIPE TRANSACTIONS:\n";
$stripe_count = $wpdb->get_var("SELECT COUNT(*) FROM swca_stripe_transactions");
$stripe_dates = $wpdb->get_row("SELECT MIN(charge_date) as earliest, MAX(charge_date) as latest FROM swca_stripe_transactions");

echo "   Total transactions: $stripe_count\n";
echo "   Date range: {$stripe_dates->earliest} to {$stripe_dates->latest}\n";

// Check for recent Stripe transactions
$recent_stripe = $wpdb->get_var("SELECT COUNT(*) FROM swca_stripe_transactions WHERE charge_date >= '2025-10-01'");
echo "   October 2025 transactions: $recent_stripe\n\n";

// 3. Transaction Matches
echo "3. TRANSACTION MATCHING:\n";
$total_matches = $wpdb->get_var("SELECT COUNT(*) FROM swca_transaction_matches");
$stripe_to_gf = $wpdb->get_var("SELECT COUNT(*) FROM swca_transaction_matches WHERE match_type = 'stripe_to_gf'");
$bank_to_stripe = $wpdb->get_var("SELECT COUNT(*) FROM swca_transaction_matches WHERE match_type = 'bank_to_stripe_payout'");

echo "   Total matches: $total_matches\n";
echo "   Stripe → Gravity Forms: $stripe_to_gf\n";
echo "   Bank → Stripe Payouts: $bank_to_stripe\n\n";

// 4. Unmatched Bank Deposits (Stripe only)
echo "4. UNMATCHED BANK DEPOSITS (Stripe ACH only):\n";
$unmatched_query = "
    SELECT b.id, b.date, b.description, b.amount
    FROM wp_swca_bank_transactions b
    LEFT JOIN swca_transaction_matches m ON b.id = m.bank_transaction_id
    WHERE b.amount > 0
    AND b.description LIKE '%STRIPE%'
    AND m.id IS NULL
    ORDER BY b.date DESC
    LIMIT 10
";
$unmatched = $wpdb->get_results($unmatched_query);

if ($unmatched) {
    echo "   Found " . count($unmatched) . " unmatched Stripe deposits:\n";
    foreach ($unmatched as $txn) {
        echo "   - {$txn->date}: \${$txn->amount} - {$txn->description}\n";
    }
} else {
    echo "   ✓ All Stripe bank deposits are matched!\n";
}

echo "\n";

// 5. Bank Statement Summary
echo "5. BANK STATEMENTS:\n";
$statement_count = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_statements");
if ($statement_count > 0) {
    $latest_stmt = $wpdb->get_row("SELECT * FROM wp_swca_bank_statements ORDER BY statement_period_end DESC LIMIT 1");
    echo "   Total statements: $statement_count\n";
    echo "   Latest statement: {$latest_stmt->statement_period_start} to {$latest_stmt->statement_period_end}\n";
    echo "   Ending balance: \${$latest_stmt->ending_balance}\n";
} else {
    echo "   ⚠ No monthly statements created yet\n";
    echo "   (Run 'Calculate Balances' to create statements)\n";
}

echo "\n=== END STATUS CHECK ===\n";
