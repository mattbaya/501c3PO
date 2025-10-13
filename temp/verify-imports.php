<?php
/**
 * Verify all imported bank transactions
 */

require_once('/home/swca/public_html/wp-load.php');

global $wpdb;

echo "Bank Transaction Data Verification\n";
echo str_repeat("=", 80) . "\n\n";

// Overall counts
$total_count = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions");
echo "📊 TOTAL TRANSACTIONS: $total_count\n\n";

// Yearly breakdown
echo "📅 YEARLY BREAKDOWN:\n";
$yearly = $wpdb->get_results("
    SELECT
        YEAR(post_date) as year,
        COUNT(*) as count,
        MIN(post_date) as first_date,
        MAX(post_date) as last_date,
        SUM(credit) as total_credits,
        SUM(debit) as total_debits
    FROM wp_swca_bank_transactions
    GROUP BY YEAR(post_date)
    ORDER BY year
");

foreach ($yearly as $year_data) {
    echo "  {$year_data->year}:\n";
    echo "    - Transactions: {$year_data->count}\n";
    echo "    - Date Range: {$year_data->first_date} to {$year_data->last_date}\n";
    echo "    - Total Credits: $" . number_format($year_data->total_credits, 2) . "\n";
    echo "    - Total Debits: $" . number_format($year_data->total_debits, 2) . "\n";
    echo "    - Net: $" . number_format($year_data->total_credits - $year_data->total_debits, 2) . "\n\n";
}

// 2024 monthly breakdown
echo "📋 2024 MONTHLY BREAKDOWN:\n";
$monthly_2024 = $wpdb->get_results("
    SELECT
        DATE_FORMAT(post_date, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(credit) as credits,
        SUM(debit) as debits
    FROM wp_swca_bank_transactions
    WHERE YEAR(post_date) = 2024
    GROUP BY DATE_FORMAT(post_date, '%Y-%m')
    ORDER BY month
");

foreach ($monthly_2024 as $month_data) {
    $net = $month_data->credits - $month_data->debits;
    echo "  {$month_data->month}: {$month_data->count} transactions | ";
    echo "Credits: $" . number_format($month_data->credits, 2) . " | ";
    echo "Debits: $" . number_format($month_data->debits, 2) . " | ";
    echo "Net: $" . number_format($net, 2) . "\n";
}

// 2025 monthly breakdown
echo "\n📋 2025 MONTHLY BREAKDOWN:\n";
$monthly_2025 = $wpdb->get_results("
    SELECT
        DATE_FORMAT(post_date, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(credit) as credits,
        SUM(debit) as debits
    FROM wp_swca_bank_transactions
    WHERE YEAR(post_date) = 2025
    GROUP BY DATE_FORMAT(post_date, '%Y-%m')
    ORDER BY month
");

foreach ($monthly_2025 as $month_data) {
    $net = $month_data->credits - $month_data->debits;
    echo "  {$month_data->month}: {$month_data->count} transactions | ";
    echo "Credits: $" . number_format($month_data->credits, 2) . " | ";
    echo "Debits: $" . number_format($month_data->debits, 2) . " | ";
    echo "Net: $" . number_format($net, 2) . "\n";
}

// Balance information
echo "\n💰 BALANCE INFORMATION:\n";
$balance_info = $wpdb->get_row("
    SELECT
        MIN(post_date) as earliest_date,
        MAX(post_date) as latest_date,
        (SELECT balance FROM wp_swca_bank_transactions ORDER BY post_date ASC, id ASC LIMIT 1) as first_balance,
        (SELECT balance FROM wp_swca_bank_transactions ORDER BY post_date DESC, id DESC LIMIT 1) as latest_balance
    FROM wp_swca_bank_transactions
");

echo "  Earliest Transaction: {$balance_info->earliest_date} (Balance: $" . number_format($balance_info->first_balance, 2) . ")\n";
echo "  Latest Transaction: {$balance_info->latest_date} (Balance: $" . number_format($balance_info->latest_balance, 2) . ")\n";

// Check for missing balances
$missing_balances = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE balance = 0 OR balance IS NULL");
if ($missing_balances > 0) {
    echo "\n⚠️  WARNING: $missing_balances transactions have missing or zero balance values\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "✅ Verification Complete!\n";
