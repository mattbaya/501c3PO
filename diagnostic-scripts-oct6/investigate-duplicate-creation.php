<?php
/**
 * Investigate how duplicate match records were created
 */

$host = 'localhost';
$database = 'swca_swca2019';
$username = 'swca_swca2019';
$password = '5Corners!';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Investigating duplicate match creation...\n\n";

// Check if matches table has timestamps
echo "1. Checking table structure for timestamps:\n";
echo str_repeat("=", 100) . "\n";

$structure = $mysqli->query("DESCRIBE swca_transaction_matches");
echo "Columns in swca_transaction_matches:\n";
while ($row = $structure->fetch_assoc()) {
    echo "  - {$row['Field']} ({$row['Type']})\n";
}

echo "\n";

// Look at all match records for the affected transactions
echo "2. All match records for affected Stripe transactions:\n";
echo str_repeat("=", 100) . "\n";

$query = "
    SELECT
        id as match_id,
        stripe_transaction_id,
        bank_transaction_id,
        gravity_form_transaction_id,
        match_type,
        match_confidence,
        notes,
        created_at
    FROM swca_transaction_matches
    WHERE stripe_transaction_id IN (1, 39)
    ORDER BY stripe_transaction_id, created_at, id
";

$result = $mysqli->query($query);

if ($result->num_rows > 0) {
    printf("%-10s %-15s %-15s %-15s %-30s %-20s %-20s %s\n",
        "Match ID", "Stripe ID", "Bank ID", "GF ID", "Match Type", "Confidence", "Created", "Notes");
    echo str_repeat("-", 150) . "\n";

    while ($row = $result->fetch_assoc()) {
        printf("%-10s %-15s %-15s %-15s %-30s %-20s %-20s %s\n",
            $row['match_id'],
            $row['stripe_transaction_id'],
            $row['bank_transaction_id'] ?: 'NULL',
            $row['gravity_form_transaction_id'] ?: 'NULL',
            $row['match_type'],
            $row['match_confidence'],
            $row['created_at'] ?: 'NO TIMESTAMP',
            substr($row['notes'] ?: '', 0, 40)
        );
    }
}

echo "\n";

// Check for patterns in all bank matches
echo "3. Pattern analysis - All bank matches (looking for duplicates):\n";
echo str_repeat("=", 100) . "\n";

$pattern_query = "
    SELECT
        match_type,
        match_confidence,
        notes,
        COUNT(*) as count,
        COUNT(DISTINCT stripe_transaction_id) as unique_stripe,
        COUNT(DISTINCT bank_transaction_id) as unique_bank,
        MIN(created_at) as first_created,
        MAX(created_at) as last_created
    FROM swca_transaction_matches
    WHERE match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
    GROUP BY match_type, match_confidence, notes
    ORDER BY count DESC
";

$result = $mysqli->query($pattern_query);

if ($result->num_rows > 0) {
    printf("%-30s %-20s %-10s %-15s %-15s %-20s %-20s\n",
        "Match Type", "Confidence", "Count", "Unique Stripe", "Unique Bank", "First Created", "Last Created");
    echo str_repeat("-", 150) . "\n";

    while ($row = $result->fetch_assoc()) {
        printf("%-30s %-20s %-10s %-15s %-15s %-20s %-20s\n",
            $row['match_type'],
            $row['match_confidence'],
            $row['count'],
            $row['unique_stripe'],
            $row['unique_bank'],
            $row['first_created'] ?: 'NO TIMESTAMP',
            $row['last_created'] ?: 'NO TIMESTAMP'
        );
    }
}

echo "\n";

// Check matching scripts/code
echo "4. Checking for matching algorithm code:\n";
echo str_repeat("=", 100) . "\n";

$matching_files = [
    '/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/transaction-matching.php',
    '/home/swca/scripts/501c3PO/includes/features/transaction-matching.php'
];

foreach ($matching_files as $file) {
    if (file_exists($file)) {
        echo "Found: $file\n";

        // Check if it has duplicate prevention
        $content = file_get_contents($file);

        if (strpos($content, 'INSERT INTO') !== false && strpos($content, 'transaction_matches') !== false) {
            echo "  ✓ Contains INSERT logic for transaction_matches\n";

            // Check for duplicate prevention
            if (strpos($content, 'ON DUPLICATE KEY') !== false) {
                echo "  ✓ Has ON DUPLICATE KEY UPDATE (prevents duplicates)\n";
            } elseif (strpos($content, 'WHERE NOT EXISTS') !== false || strpos($content, 'IGNORE') !== false) {
                echo "  ✓ Has duplicate prevention logic\n";
            } else {
                echo "  ⚠ WARNING: No obvious duplicate prevention in INSERT statements!\n";
            }
        }
    }
}

$mysqli->close();

echo "\n" . str_repeat("=", 100) . "\n";
echo "INVESTIGATION COMPLETE\n";
