<?php
/**
 * Find Exact Duplicate Bank Transactions
 * Same date + amount + description = duplicate
 */

require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== FINDING EXACT DUPLICATE BANK TRANSACTIONS ===\n\n";

// Find duplicates by date + amount + description
$duplicates = $wpdb->get_results("
    SELECT
        post_date,
        credit,
        debit,
        description,
        COUNT(*) as duplicate_count,
        GROUP_CONCAT(id ORDER BY id) as duplicate_ids,
        GROUP_CONCAT(imported_date ORDER BY id) as import_dates
    FROM wp_swca_bank_transactions
    GROUP BY post_date, credit, debit, description
    HAVING duplicate_count > 1
    ORDER BY post_date DESC, credit DESC
", ARRAY_A);

echo "Found " . count($duplicates) . " sets of duplicate transactions:\n\n";

$total_dupes = 0;
foreach ($duplicates as $dup) {
    $ids = explode(',', $dup['duplicate_ids']);
    $import_dates = explode(',', $dup['import_dates']);
    $dupes_to_delete = count($ids) - 1; // Keep first, delete rest
    $total_dupes += $dupes_to_delete;

    echo "Date: {$dup['post_date']}\n";
    echo "Amount: " . ($dup['credit'] > 0 ? "+\${$dup['credit']}" : "-\${$dup['debit']}") . "\n";
    echo "Description: " . substr($dup['description'], 0, 60) . "\n";
    echo "Duplicate Count: {$dup['duplicate_count']} records\n";
    echo "IDs: {$dup['duplicate_ids']}\n";
    echo "Import Dates:\n";
    foreach ($ids as $idx => $id) {
        echo "  ID $id: {$import_dates[$idx]}\n";
    }
    echo "Action: Keep ID {$ids[0]}, DELETE " . $dupes_to_delete . " duplicate(s)\n";
    echo str_repeat("-", 80) . "\n\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total duplicate sets: " . count($duplicates) . "\n";
echo "Total records to DELETE: $total_dupes\n";
echo "This will reduce table from " . $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions") . " to " . ($wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions") - $total_dupes) . " records\n";

echo "\n=== NEXT STEP ===\n";
echo "Run fix-duplicates.php to automatically remove these duplicates\n";
