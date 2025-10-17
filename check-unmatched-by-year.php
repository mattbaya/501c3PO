<?php
/**
 * Check unmatched Stripe transactions by year
 */

// Database credentials
$host = 'localhost';
$user = 'swca_swca2019';
$pass = '5Corners!';
$dbname = 'swca_swca2019';

// Connect to database
$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== UNMATCHED STRIPE TRANSACTIONS BY YEAR ===\n\n";

// Count by year
$result = $mysqli->query("
SELECT
    YEAR(s.created) as year,
    COUNT(*) as count,
    MIN(s.created) as earliest,
    MAX(s.created) as latest,
    SUM(s.amount) as total_amount
FROM swca_c3_stripe_transactions s
LEFT JOIN swca_c3_transaction_matches m
    ON m.stripe_transaction_id = s.id
    AND m.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
WHERE m.id IS NULL
GROUP BY YEAR(s.created)
ORDER BY year ASC
");

$pre_2024_count = 0;
$pre_2024_amount = 0;

while ($row = $result->fetch_assoc()) {
    echo "Year {$row['year']}: {$row['count']} transactions\n";
    echo "  Date Range: {$row['earliest']} to {$row['latest']}\n";
    echo "  Total Amount: $" . number_format($row['total_amount'], 2) . "\n\n";

    if ($row['year'] < 2024) {
        $pre_2024_count += $row['count'];
        $pre_2024_amount += $row['total_amount'];
    }
}

echo str_repeat("=", 60) . "\n";
echo "BEFORE 2024: {$pre_2024_count} unmatched transactions\n";
echo "BEFORE 2024 Total: $" . number_format($pre_2024_amount, 2) . "\n\n";

// Show a few examples from before 2024
if ($pre_2024_count > 0) {
    echo "=== EXAMPLES FROM BEFORE 2024 ===\n";
    $examples = $mysqli->query("
    SELECT s.id, s.created, s.amount, s.customer_name, s.customer_email
    FROM swca_c3_stripe_transactions s
    LEFT JOIN swca_c3_transaction_matches m ON m.stripe_transaction_id = s.id
    WHERE m.id IS NULL AND YEAR(s.created) < 2024
    ORDER BY s.created DESC
    LIMIT 10
    ");

    while ($row = $examples->fetch_assoc()) {
        echo "ID {$row['id']}: {$row['created']} - \${$row['amount']} - {$row['customer_name']} ({$row['customer_email']})\n";
    }
}

$mysqli->close();
