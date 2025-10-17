<?php
/**
 * Remove Duplicate Match Records
 * Keeps the oldest match record for each unique combination
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=====================================\n";
echo "DUPLICATE MATCH CLEANUP\n";
echo "=====================================\n\n";

// Find all duplicate match groups
$result = $mysqli->query("
    SELECT
        stripe_transaction_id,
        gravity_form_transaction_id,
        bank_transaction_id,
        COUNT(*) as count,
        GROUP_CONCAT(id ORDER BY id ASC) as all_ids,
        MIN(id) as keep_id
    FROM swca_c3_transaction_matches
    WHERE stripe_transaction_id IS NOT NULL
       OR gravity_form_transaction_id IS NOT NULL
       OR bank_transaction_id IS NOT NULL
    GROUP BY stripe_transaction_id, gravity_form_transaction_id, bank_transaction_id
    HAVING count > 1
");

$total_duplicates = 0;
$ids_to_delete = array();

echo "Found " . $result->num_rows . " duplicate match groups\n\n";

while ($row = $result->fetch_assoc()) {
    $all_ids = explode(',', $row['all_ids']);
    $keep_id = $row['keep_id'];

    echo sprintf("Stripe #%s / GF #%s / Bank #%s: %d records (keeping #%d)\n",
        $row['stripe_transaction_id'] ?: 'NULL',
        $row['gravity_form_transaction_id'] ?: 'NULL',
        $row['bank_transaction_id'] ?: 'NULL',
        $row['count'],
        $keep_id
    );

    // Mark all IDs except the one to keep for deletion
    foreach ($all_ids as $id) {
        if ($id != $keep_id) {
            $ids_to_delete[] = $id;
            $total_duplicates++;
            echo "  - Will delete match record #$id\n";
        }
    }
}

echo "\n=====================================\n";
echo sprintf("Total duplicate records to delete: %d\n", $total_duplicates);
echo "=====================================\n\n";

if ($total_duplicates > 0) {
    echo "Proceed with deletion? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim($line) == 'y') {
        $ids_list = implode(',', $ids_to_delete);
        $delete_sql = "DELETE FROM swca_c3_transaction_matches WHERE id IN ($ids_list)";

        if ($mysqli->query($delete_sql)) {
            echo "✓ Successfully deleted $total_duplicates duplicate match records\n";

            // Verify cleanup
            $remaining = $mysqli->query("SELECT COUNT(*) FROM swca_c3_transaction_matches")->fetch_row()[0];
            echo "✓ Remaining match records: $remaining\n";
        } else {
            echo "✗ Error deleting duplicates: " . $mysqli->error . "\n";
        }
    } else {
        echo "Deletion cancelled.\n";
    }
} else {
    echo "✓ No duplicates to clean up!\n";
}

$mysqli->close();
