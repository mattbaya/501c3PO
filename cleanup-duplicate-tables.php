<?php
/**
 * Cleanup Duplicate Tables - Remove wp_swca_ prefix tables
 * These are duplicates of the c3_ tables that the ledger actually uses
 */

require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== CLEANING UP DUPLICATE TABLES ===\n\n";

// Tables to DROP (wp_swca_ prefix - these are NOT used by the ledger)
$tables_to_drop = [
    'wp_swca_bank_transactions',      // Duplicate of swca_c3_bank_transactions
    'wp_swca_bank_statements',        // Duplicate of swca_c3_bank_statements
    'wp_swca_agendas',                // Empty
    'wp_swca_committee_reports',      // Empty
    'wp_swca_committees',             // Has 6 rows - check if needed
    'wp_swca_documents',              // Empty
    'wp_swca_drive_folders',          // Has 19 rows - check if needed
    'wp_swca_email_templates',        // Has 4 rows - check if needed
    'wp_swca_emails',                 // Empty
    'wp_swca_events',                 // Empty
    'wp_swca_financial_reports',      // Empty
    'wp_swca_minutes',                // Empty
    'wp_swca_volunteer_slots',        // Empty
];

echo "Tables to drop:\n";
foreach ($tables_to_drop as $table) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    if ($count > 0) {
        echo "  ⚠️ $table ($count rows) - ";

        // Check if there's a c3_ equivalent
        $c3_table = str_replace('wp_swca_', 'swca_c3_', $table);
        $c3_exists = $wpdb->get_var("SHOW TABLES LIKE '$c3_table'");

        if ($c3_exists) {
            $c3_count = $wpdb->get_var("SELECT COUNT(*) FROM $c3_table");
            echo "HAS c3_ equivalent with $c3_count rows - SAFE TO DROP\n";
            $wpdb->query("DROP TABLE $table");
        } else {
            echo "NO c3_ equivalent - SKIPPING (manual review needed)\n";
        }
    } else {
        echo "  ✓ $table (empty) - DROPPING\n";
        $wpdb->query("DROP TABLE $table");
    }
}

echo "\n=== LEGACY swca_ TRANSACTION TABLES ===\n";

// Check legacy transaction tables
$legacy_tables = [
    'swca_stripe_transactions',
    'swca_stripe_balance_transactions',
    'swca_transaction_matches',
    'swca_gf_addon_payment_transaction'
];

foreach ($legacy_tables as $table) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $c3_table = str_replace('swca_', 'swca_c3_', $table);
    $c3_count = $wpdb->get_var("SELECT COUNT(*) FROM $c3_table");

    echo "$table: $count rows\n";
    echo "  → c3_ version: $c3_count rows\n";

    if ($c3_count >= $count) {
        echo "  ✓ c3_ version has same or more rows - OLD TABLE CAN BE DROPPED\n";
    } else {
        echo "  ⚠️ c3_ version has FEWER rows - INVESTIGATE!\n";
    }
}

echo "\n✅ Cleanup complete\n";
echo "Check code for references to dropped tables\n";
