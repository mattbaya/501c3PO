<?php
/**
 * Find all duplicate match records
 */

$host = 'localhost';
$database = 'swca_swca2019';
$username = 'swca_swca2019';
$password = '5Corners!';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Finding all duplicate match records...\n\n";

$query = "
    SELECT
        stripe_transaction_id,
        bank_transaction_id,
        match_type,
        COUNT(*) as count,
        GROUP_CONCAT(id ORDER BY id) as match_ids
    FROM swca_transaction_matches
    WHERE match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
    GROUP BY stripe_transaction_id, bank_transaction_id, match_type
    HAVING count > 1
    ORDER BY stripe_transaction_id
";

$result = $mysqli->query($query);

if ($result->num_rows > 0) {
    echo "❌ DUPLICATE MATCHES FOUND:\n";
    echo str_repeat("=", 100) . "\n";
    printf("%-15s %-15s %-30s %-10s %s\n",
        "Stripe ID", "Bank ID", "Match Type", "Count", "Match Record IDs");
    echo str_repeat("-", 100) . "\n";

    $total_duplicates = 0;
    while ($row = $result->fetch_assoc()) {
        printf("%-15s %-15s %-30s %-10s %s\n",
            $row['stripe_transaction_id'],
            $row['bank_transaction_id'],
            $row['match_type'],
            $row['count'],
            $row['match_ids']
        );
        $total_duplicates++;
    }

    echo str_repeat("=", 100) . "\n";
    echo "Total affected Stripe transactions: $total_duplicates\n";
    echo "\nThis means {$total_duplicates} Stripe transactions will appear as duplicates in the ledger.\n";
} else {
    echo "✓ No duplicate match records found\n";
}

$mysqli->close();
