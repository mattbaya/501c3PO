<?php
/**
 * Check which transactions are missing balances
 */

$host = 'localhost';
$dbname = 'swca_swca2019';
$username = 'swca_swca2019';
$password = '5Corners!';

$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== MISSING BALANCES INVESTIGATION ===\n\n";

// Get all transactions without balances
$result = $mysqli->query("
    SELECT id, post_date, description, debit, credit, balance
    FROM wp_swca_bank_transactions
    WHERE balance = 0
    ORDER BY post_date ASC, id ASC
    LIMIT 10
");

echo "First 10 transactions with balance = 0:\n";
while ($row = $result->fetch_assoc()) {
    echo "  ID: {$row['id']}, Date: {$row['post_date']}, Desc: " . substr($row['description'], 0, 30) .
         ", Debit: {$row['debit']}, Credit: {$row['credit']}\n";
}

echo "\n";

// Get last 10 transactions without balances
$result = $mysqli->query("
    SELECT id, post_date, description, debit, credit, balance
    FROM wp_swca_bank_transactions
    WHERE balance = 0
    ORDER BY post_date DESC, id DESC
    LIMIT 10
");

echo "Last 10 transactions with balance = 0:\n";
while ($row = $result->fetch_assoc()) {
    echo "  ID: {$row['id']}, Date: {$row['post_date']}, Desc: " . substr($row['description'], 0, 30) .
         ", Debit: {$row['debit']}, Credit: {$row['credit']}\n";
}

echo "\n";

// Check date range of transactions without balances
$result = $mysqli->query("
    SELECT MIN(post_date) as earliest, MAX(post_date) as latest, COUNT(*) as count
    FROM wp_swca_bank_transactions
    WHERE balance = 0
");

$row = $result->fetch_assoc();
echo "Transactions with balance = 0:\n";
echo "  Count: {$row['count']}\n";
echo "  Earliest: {$row['earliest']}\n";
echo "  Latest: {$row['latest']}\n";

echo "\n";

// Check if these are transactions where both debit AND credit are 0
$result = $mysqli->query("
    SELECT COUNT(*) as count
    FROM wp_swca_bank_transactions
    WHERE balance = 0 AND debit = 0 AND credit = 0
");

$row = $result->fetch_assoc();
echo "Transactions where debit=0 AND credit=0 AND balance=0: {$row['count']}\n";

$mysqli->close();
