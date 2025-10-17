<?php
/**
 * Remove duplicate match records from swca_transaction_matches
 * Keeps the FIRST match record, removes subsequent duplicates
 */

$host = 'localhost';
$database = 'swca_swca2019';
$username = 'swca_swca2019';
$password = '5Corners!';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Removing duplicate match records...\n\n";

// Find all duplicates
$find_query = "
    SELECT
        stripe_transaction_id,
        bank_transaction_id,
        match_type,
        GROUP_CONCAT(id ORDER BY id) as match_ids,
        COUNT(*) as count
    FROM swca_transaction_matches
    WHERE match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
    GROUP BY stripe_transaction_id, bank_transaction_id, match_type
    HAVING count > 1
";

$result = $mysqli->query($find_query);

if ($result->num_rows > 0) {
    echo "Found " . $result->num_rows . " sets of duplicate matches:\n";
    echo str_repeat("=", 100) . "\n";

    $total_deleted = 0;

    while ($row = $result->fetch_assoc()) {
        $match_ids = explode(',', $row['match_ids']);
        $keep_id = array_shift($match_ids); // Keep the first one
        $delete_ids = $match_ids; // Delete the rest

        echo "Stripe #{$row['stripe_transaction_id']} → Bank #{$row['bank_transaction_id']} ({$row['match_type']}):\n";
        echo "  Keeping match ID: $keep_id\n";
        echo "  Deleting match IDs: " . implode(', ', $delete_ids) . "\n";

        // Delete the duplicates
        foreach ($delete_ids as $delete_id) {
            $delete_query = "DELETE FROM swca_transaction_matches WHERE id = " . intval($delete_id);
            if ($mysqli->query($delete_query)) {
                echo "  ✓ Deleted match ID $delete_id\n";
                $total_deleted++;
            } else {
                echo "  ✗ Error deleting match ID $delete_id: " . $mysqli->error . "\n";
            }
        }
        echo "\n";
    }

    echo str_repeat("=", 100) . "\n";
    echo "Total duplicate records removed: $total_deleted\n";
} else {
    echo "✓ No duplicate match records found\n";
}

// Verify cleanup
echo "\nVerifying cleanup...\n";
$verify_query = "
    SELECT COUNT(*) as count
    FROM (
        SELECT
            stripe_transaction_id,
            bank_transaction_id,
            match_type,
            COUNT(*) as cnt
        FROM swca_transaction_matches
        WHERE match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
        GROUP BY stripe_transaction_id, bank_transaction_id, match_type
        HAVING cnt > 1
    ) duplicates
";

$verify_result = $mysqli->query($verify_query);
$verify_row = $verify_result->fetch_assoc();

if ($verify_row['count'] == 0) {
    echo "✓ SUCCESS: No duplicate matches remain!\n";
} else {
    echo "⚠ WARNING: " . $verify_row['count'] . " duplicate sets still exist\n";
}

$mysqli->close();

echo "\nDONE - Refresh the Transaction Ledger to see the fix.\n";
