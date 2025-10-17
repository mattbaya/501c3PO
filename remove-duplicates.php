<?php
/**
 * Remove Duplicate Bank Transactions
 * Auto-generated deletion script
 *
 * Deletes 3 duplicate bank transactions that were re-imported:
 * - ID 202 (Sep 25, $4.51) - duplicate of ID 1
 * - ID 201 (Sep 19, $383.44 debit) - duplicate of ID 5
 * - ID 198 (Sep 4, $134.75) - duplicate of ID 11
 */

require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== REMOVING DUPLICATE BANK TRANSACTIONS ===\n\n";

$deleted_count = 0;
$deleted_ids = [];

// Get details before deletion
$duplicates = [
    ['id' => 202, 'date' => '2025-09-25', 'amount' => '$4.51', 'keeping' => 1],
    ['id' => 201, 'date' => '2025-09-19', 'amount' => '-$383.44', 'keeping' => 5],
    ['id' => 198, 'date' => '2025-09-04', 'amount' => '$134.75', 'keeping' => 11]
];

foreach ($duplicates as $dup) {
    $txn = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_swca_bank_transactions WHERE id = %d", $dup['id']));
    if ($txn) {
        echo "Deleting ID {$dup['id']}: {$dup['date']} {$dup['amount']}\n";
        echo "  Description: {$txn->description}\n";
        echo "  Imported: {$txn->imported_date}\n";
        echo "  (Keeping ID {$dup['keeping']} as the original)\n\n";

        $result = $wpdb->delete('wp_swca_bank_transactions', ['id' => $dup['id']]);
        if ($result) {
            $deleted_ids[] = $dup['id'];
            $deleted_count++;
        } else {
            echo "  ERROR: Failed to delete ID {$dup['id']}\n\n";
        }
    } else {
        echo "ID {$dup['id']} does not exist (already deleted?)\n\n";
    }
}

echo str_repeat("=", 80) . "\n";
echo "SUMMARY:\n";
echo "Deleted $deleted_count duplicate bank transactions\n";
echo "IDs removed: " . implode(', ', $deleted_ids) . "\n";

// Count remaining transactions
$total = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions");
echo "Total bank transactions remaining: $total\n";

echo "\nNEXT STEPS:\n";
echo "1. Recalculate running balances\n";
echo "2. Add unique constraint to prevent future duplicates\n";
echo "3. Update bank import code to check for existing transactions\n";
