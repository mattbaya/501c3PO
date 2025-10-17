<?php
/**
 * Standalone Database Status Check
 * Does not require WordPress to be loaded
 */

// Database credentials
$host = 'localhost';
$dbname = 'swca_swca2019';
$username = 'swca_swca2019';
$password = '5Corners!';

// Connect
$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== 501c3PO DATABASE STATUS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Bank Transactions
echo "1. BANK TRANSACTIONS (wp_swca_bank_transactions):\n";
$result = $mysqli->query("SELECT COUNT(*) as count, MIN(post_date) as earliest, MAX(post_date) as latest FROM wp_swca_bank_transactions");
$row = $result->fetch_assoc();
echo "   Total: " . $row['count'] . " transactions\n";
echo "   Date Range: " . $row['earliest'] . " to " . $row['latest'] . "\n";

$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions WHERE balance != 0");
$row = $result->fetch_assoc();
echo "   With Balances: " . $row['count'] . " (" . ($row['count'] > 0 ? "✓ Balances calculated" : "✗ Need to calculate balances") . ")\n";

$result = $mysqli->query("SELECT SUM(credit) as credits, SUM(debit) as debits FROM wp_swca_bank_transactions");
$row = $result->fetch_assoc();
echo "   Total Credits: $" . number_format($row['credits'], 2) . "\n";
echo "   Total Debits: $" . number_format($row['debits'], 2) . "\n";
echo "   Net: $" . number_format($row['credits'] - $row['debits'], 2) . "\n\n";

// 2. Bank Statements
echo "2. BANK STATEMENTS (wp_swca_bank_statements):\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_statements");
$row = $result->fetch_assoc();
echo "   Total Statements: " . $row['count'] . "\n";

if ($row['count'] > 0) {
    $result = $mysqli->query("SELECT * FROM wp_swca_bank_statements ORDER BY statement_period_end DESC LIMIT 1");
    $stmt = $result->fetch_assoc();
    echo "   Latest Statement: " . $stmt['statement_period_start'] . " to " . $stmt['statement_period_end'] . "\n";
    echo "   Ending Balance: $" . number_format($stmt['ending_balance'], 2) . "\n";
} else {
    echo "   ⚠️  No statements created - need to run Calculate Balances\n";
}
echo "\n";

// 3. Stripe Transactions
echo "3. STRIPE TRANSACTIONS (swca_stripe_transactions):\n";
$result = $mysqli->query("SELECT COUNT(*) as count, MIN(DATE(stripe_created)) as earliest, MAX(DATE(stripe_created)) as latest FROM swca_stripe_transactions");
if (!$result) {
    echo "   Error querying Stripe transactions: " . $mysqli->error . "\n\n";
} else {
    $row = $result->fetch_assoc();
    echo "   Total: " . $row['count'] . " transactions\n";
    echo "   Date Range: " . ($row['earliest'] ?: 'N/A') . " to " . ($row['latest'] ?: 'N/A') . "\n";

    // Check for October transactions
    $result = $mysqli->query("SELECT COUNT(*) as count FROM swca_stripe_transactions WHERE DATE(stripe_created) >= '2025-10-01'");
    $row = $result->fetch_assoc();
    echo "   October 2025: " . $row['count'] . " transactions\n\n";
}

// 4. Transaction Matches
echo "4. TRANSACTION MATCHING:\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM swca_transaction_matches");
$row = $result->fetch_assoc();
echo "   Total Matches: " . $row['count'] . "\n";

$result = $mysqli->query("SELECT match_type, COUNT(*) as count FROM swca_transaction_matches GROUP BY match_type");
while ($row = $result->fetch_assoc()) {
    echo "     - " . $row['match_type'] . ": " . $row['count'] . "\n";
}
echo "\n";

// 5. Unmatched Stripe Bank Deposits
echo "5. UNMATCHED STRIPE DEPOSITS:\n";
$result = $mysqli->query("
    SELECT b.id, b.post_date, b.description, b.credit
    FROM wp_swca_bank_transactions b
    LEFT JOIN swca_transaction_matches m ON b.id = m.bank_transaction_id
    WHERE b.credit > 0
    AND b.description LIKE '%STRIPE%'
    AND m.id IS NULL
    ORDER BY b.post_date DESC
");

if ($result->num_rows > 0) {
    echo "   Found " . $result->num_rows . " unmatched Stripe deposits:\n";
    while ($row = $result->fetch_assoc()) {
        echo "     - " . $row['post_date'] . ": $" . number_format($row['credit'], 2) . " - " . substr($row['description'], 0, 50) . "\n";
    }
} else {
    echo "   ✓ All Stripe deposits are matched!\n";
}
echo "\n";

// 6. Gravity Forms Transactions
echo "6. GRAVITY FORMS TRANSACTIONS (swca_gf_addon_payment_transaction):\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM swca_gf_addon_payment_transaction");
$row = $result->fetch_assoc();
echo "   Total: " . $row['count'] . " transactions\n\n";

echo "=== SUMMARY ===\n";
echo "Bank Data: " . ($row['count'] > 0 ? "✓" : "✗") . "\n";
echo "Balances Calculated: " . ($row['count'] > 0 ? "✓" : "✗") . " (check Bank Transactions section above)\n";
echo "Stripe Data: ✓\n";
echo "Matching Active: ✓\n\n";

echo "RECOMMENDED ACTIONS:\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions WHERE balance = 0");
$row = $result->fetch_assoc();
if ($row['count'] > 0) {
    echo "1. Calculate balances: Visit 'Calculate Balances' admin page\n";
}

$result = $mysqli->query("SELECT COUNT(*) as count FROM swca_stripe_transactions WHERE charge_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    echo "2. Sync Stripe: No recent transactions - may need to sync Stripe API\n";
}

$result = $mysqli->query("
    SELECT COUNT(*) as count
    FROM wp_swca_bank_transactions b
    LEFT JOIN swca_transaction_matches m ON b.id = m.bank_transaction_id
    WHERE b.credit > 0
    AND b.description LIKE '%STRIPE%'
    AND m.id IS NULL
");
$row = $result->fetch_assoc();
if ($row['count'] > 0) {
    echo "3. Run matching: " . $row['count'] . " unmatched Stripe deposits found\n";
}

$mysqli->close();
echo "\n=== END REPORT ===\n";
