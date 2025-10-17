<?php
/**
 * Find Near-Duplicate Bank Transactions
 * Same date + amount but different descriptions/import dates
 */

require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== FINDING NEAR-DUPLICATE BANK TRANSACTIONS ===\n\n";

// Strategy: Find transactions with same date + amount, then analyze descriptions
$query = "
    SELECT
        post_date,
        credit,
        debit,
        GROUP_CONCAT(id ORDER BY id) as ids,
        GROUP_CONCAT(CONCAT('ID:', id, ' - ', SUBSTRING(description, 1, 80)) ORDER BY id SEPARATOR '\n  ') as descriptions,
        GROUP_CONCAT(imported_date ORDER BY id) as import_dates,
        COUNT(*) as count
    FROM wp_swca_bank_transactions
    WHERE (credit > 0 OR debit > 0)
    GROUP BY post_date, credit, debit
    HAVING count > 1
    ORDER BY post_date DESC
";

$results = $wpdb->get_results($query, ARRAY_A);

echo "Found " . count($results) . " date+amount combinations with multiple records:\n\n";

$total_to_delete = 0;
$confirmed_duplicates = [];

foreach ($results as $row) {
    $ids = explode(',', $row['ids']);
    $import_dates = explode(',', $row['import_dates']);

    echo str_repeat("=", 100) . "\n";
    echo "Date: {$row['post_date']}\n";
    echo "Amount: " . ($row['credit'] > 0 ? "+\${$row['credit']}" : "-\${$row['debit']}") . "\n";
    echo "Count: {$row['count']} records\n";
    echo "IDs: " . implode(', ', $ids) . "\n";
    echo "Descriptions:\n";
    echo "  " . $row['descriptions'] . "\n";
    echo "Import Dates:\n";
    foreach ($ids as $idx => $id) {
        echo "  ID $id: {$import_dates[$idx]}\n";
    }

    // Analyze if these are likely duplicates
    $unique_import_dates = array_unique($import_dates);
    if (count($unique_import_dates) > 1) {
        echo "\n⚠️  LIKELY DUPLICATE: Multiple import dates suggest re-import\n";
        echo "   Recommendation: Keep ID {$ids[0]} (earliest), DELETE " . (count($ids) - 1) . " others\n";
        $total_to_delete += (count($ids) - 1);
        $confirmed_duplicates[] = [
            'keep' => $ids[0],
            'delete' => array_slice($ids, 1),
            'date' => $row['post_date'],
            'amount' => $row['credit'] > 0 ? $row['credit'] : $row['debit']
        ];
    } else {
        echo "\n ℹ️  Same import date - might be legitimate (e.g., multiple checks same day)\n";
    }
    echo "\n";
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "=== SUMMARY ===\n";
echo "Total near-duplicate sets: " . count($results) . "\n";
echo "Confirmed duplicates (diff import dates): " . count($confirmed_duplicates) . "\n";
echo "Total records to DELETE: $total_to_delete\n\n";

// Generate DELETE commands
if (count($confirmed_duplicates) > 0) {
    echo "=== DELETION SCRIPT ===\n\n";
    echo "<?php\n";
    echo "// Auto-generated deletion script for duplicate bank transactions\n";
    echo "require_once('/home/swca/public_html/wp-load.php');\n";
    echo "global \$wpdb;\n\n";
    echo "\$deleted_count = 0;\n";
    echo "\$deleted_ids = [];\n\n";

    foreach ($confirmed_duplicates as $dup) {
        echo "// Delete duplicates for {$dup['date']} \${$dup['amount']}\n";
        foreach ($dup['delete'] as $del_id) {
            echo "\$wpdb->delete('wp_swca_bank_transactions', ['id' => $del_id]);\n";
            echo "\$deleted_ids[] = $del_id;\n";
            echo "\$deleted_count++;\n";
        }
        echo "\n";
    }

    echo "echo \"Deleted \$deleted_count duplicate bank transactions\\n\";\n";
    echo "echo \"IDs removed: \" . implode(', ', \$deleted_ids) . \"\\n\";\n";
    echo "?>\n";
}

echo "\n=== NEXT STEP ===\n";
echo "Save the deletion script above to remove-duplicates.php and run it\n";
