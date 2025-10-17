<?php
/**
 * Investigate Duplicate Transactions in Ledger
 * CRITICAL BUG FIX - Oct 17, 2025
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== INVESTIGATING DUPLICATE TRANSACTIONS ===\n\n";

// Step 1: Check bank_transactions table structure
echo "Step 1: Bank Transactions Table Structure\n";
echo str_repeat("-", 60) . "\n";
$columns = $wpdb->get_results("DESCRIBE wp_swca_bank_transactions");
foreach ($columns as $col) {
    echo sprintf("%-20s %-15s\n", $col->Field, $col->Type);
}

// Step 2: Search for ID4270465600 specifically
echo "\n\nStep 2: Searching for Transaction ID4270465600\n";
echo str_repeat("-", 60) . "\n";
$results = $wpdb->get_results("
    SELECT * FROM wp_swca_bank_transactions
    WHERE description LIKE '%ID4270465600%'
    ORDER BY id
", ARRAY_A);

echo "Found " . count($results) . " rows with ID4270465600\n\n";
foreach ($results as $row) {
    echo "Bank Transaction ID: {$row['id']}\n";
    foreach ($row as $key => $value) {
        echo "  $key: $value\n";
    }
    echo "\n";
}

// Step 3: Check for ALL duplicates in bank_transactions
echo "\n\nStep 3: Finding ALL Duplicate Transaction IDs\n";
echo str_repeat("-", 60) . "\n";

// First, let's see what column contains the transaction ID
$sample = $wpdb->get_row("SELECT * FROM wp_swca_bank_transactions LIMIT 1", ARRAY_A);
echo "Sample row columns: " . implode(", ", array_keys($sample)) . "\n\n";

// Check for duplicates by description (which seems to contain the ID)
$duplicates = $wpdb->get_results("
    SELECT description, COUNT(*) as count, GROUP_CONCAT(id) as ids
    FROM wp_swca_bank_transactions
    GROUP BY description
    HAVING count > 1
    ORDER BY count DESC
", ARRAY_A);

echo "Found " . count($duplicates) . " duplicate descriptions:\n\n";
foreach ($duplicates as $dup) {
    echo "Description: {$dup['description']}\n";
    echo "  Count: {$dup['count']}\n";
    echo "  IDs: {$dup['ids']}\n\n";
}

// Step 4: Check the ledger query to see if it's creating duplicates
echo "\n\nStep 4: Understanding Ledger Query Logic\n";
echo str_repeat("-", 60) . "\n";

// Check if the issue is in the transaction_matches table
$match_check = $wpdb->get_results("
    SELECT
        bank_transaction_id,
        COUNT(*) as match_count,
        GROUP_CONCAT(id) as match_ids
    FROM swca_c3_transaction_matches
    WHERE bank_transaction_id IS NOT NULL
    GROUP BY bank_transaction_id
    HAVING match_count > 1
", ARRAY_A);

echo "Found " . count($match_check) . " bank transactions with multiple matches:\n\n";
foreach ($match_check as $m) {
    echo "Bank Transaction ID: {$m['bank_transaction_id']}\n";
    echo "  Match Count: {$m['match_count']}\n";
    echo "  Match IDs: {$m['match_ids']}\n\n";
}

// Step 5: Look at the specific bank transaction for Sep 18
echo "\n\nStep 5: Sep 18, 2025 Bank Transactions\n";
echo str_repeat("-", 60) . "\n";
$sep18 = $wpdb->get_results("
    SELECT * FROM wp_swca_bank_transactions
    WHERE date = '2025-09-18'
    ORDER BY id
", ARRAY_A);

echo "Found " . count($sep18) . " transactions on Sep 18:\n\n";
foreach ($sep18 as $row) {
    echo "ID: {$row['id']}, Amount: {$row['amount']}, Desc: {$row['description']}\n";
}

echo "\n=== INVESTIGATION COMPLETE ===\n";
