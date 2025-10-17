<?php
/**
 * Recalculate ALL Balances from Scratch
 * This is necessary because transactions were imported out of order
 */

$host = 'localhost';
$dbname = 'swca_swca2019';
$username = 'swca_swca2019';
$password = '5Corners!';

$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== RECALCULATE ALL BALANCES FROM SCRATCH ===\n\n";

// Step 1: Find the earliest date with a calculated balance
$result = $mysqli->query("
    SELECT id, post_date, balance, description, debit, credit
    FROM wp_swca_bank_transactions
    WHERE balance != 0
    ORDER BY post_date ASC, id ASC
    LIMIT 1
");

$first_with_balance = $result->fetch_assoc();

if (!$first_with_balance) {
    echo "ERROR: No transactions have calculated balances. Please use the Calculate Balances admin page.\n";
    $mysqli->close();
    exit(1);
}

echo "First transaction with calculated balance:\n";
echo "  Date: {$first_with_balance['post_date']}\n";
echo "  Balance AFTER: $" . number_format($first_with_balance['balance'], 2) . "\n";
echo "  This transaction: Debit: {$first_with_balance['debit']}, Credit: {$first_with_balance['credit']}\n\n";

// Work backwards to find the starting balance
$balance_before = $first_with_balance['balance'] - $first_with_balance['credit'] + $first_with_balance['debit'];

echo "Calculated starting balance: $" . number_format($balance_before, 2) . "\n";
echo "(This is the balance BEFORE the first transaction)\n\n";

// Step 2: Get absolutely first transaction in database
$result = $mysqli->query("
    SELECT post_date, description
    FROM wp_swca_bank_transactions
    ORDER BY post_date ASC, id ASC
    LIMIT 1
");

$very_first = $result->fetch_assoc();
echo "Earliest transaction in database: {$very_first['post_date']} - {$very_first['description']}\n\n";

// Step 3: Clear all balances
echo "Clearing all existing balances...\n";
$mysqli->query("UPDATE wp_swca_bank_transactions SET balance = 0");
echo "✓ All balances cleared\n\n";

// Step 4: Clear auto-generated statements
echo "Clearing auto-generated statements...\n";
$mysqli->query("DELETE FROM wp_swca_bank_statements WHERE notes LIKE 'Auto-%'");
echo "✓ Auto-generated statements cleared\n\n";

// Step 5: Recalculate from scratch
echo "Recalculating all balances from {$very_first['post_date']}...\n";
echo "Starting balance: $" . number_format($balance_before, 2) . "\n\n";

$running_balance = $balance_before;
$monthly_data = array();
$updated_count = 0;

// Get ALL transactions in chronological order
$result = $mysqli->query("
    SELECT id, post_date, description, debit, credit
    FROM wp_swca_bank_transactions
    ORDER BY post_date ASC, id ASC
");

$transactions = array();
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

echo "Processing " . count($transactions) . " transactions...\n\n";

foreach ($transactions as $txn) {
    $running_balance += floatval($txn['credit']);
    $running_balance -= floatval($txn['debit']);

    // Update transaction balance
    $update_stmt = $mysqli->prepare("UPDATE wp_swca_bank_transactions SET balance = ? WHERE id = ?");
    $update_stmt->bind_param('di', $running_balance, $txn['id']);

    if ($update_stmt->execute()) {
        $updated_count++;
        if ($updated_count % 20 == 0) {
            echo "  Processed $updated_count transactions... Current: {$txn['post_date']} = $" . number_format($running_balance, 2) . "\n";
        }
    }

    $update_stmt->close();

    // Track monthly data
    $month_key = date('Y-m', strtotime($txn['post_date']));
    if (!isset($monthly_data[$month_key])) {
        $monthly_data[$month_key] = array(
            'start_date' => date('Y-m-01', strtotime($txn['post_date'])),
            'end_date' => date('Y-m-t', strtotime($txn['post_date'])),
            'starting_balance' => $running_balance - floatval($txn['credit']) + floatval($txn['debit']),
            'credits' => 0,
            'debits' => 0,
            'transaction_count' => 0
        );
    }

    $monthly_data[$month_key]['credits'] += floatval($txn['credit']);
    $monthly_data[$month_key]['debits'] += floatval($txn['debit']);
    $monthly_data[$month_key]['ending_balance'] = $running_balance;
    $monthly_data[$month_key]['transaction_count']++;
}

echo "\n✓ Updated balances for $updated_count transactions\n\n";

// Step 6: Create monthly statement records
echo "Creating monthly statements...\n";
$statements_created = 0;

foreach ($monthly_data as $month => $data) {
    $notes = sprintf(
        "Auto-calculated from %d transactions. Credits: $%s, Debits: $%s",
        $data['transaction_count'],
        number_format($data['credits'], 2),
        number_format($data['debits'], 2)
    );

    $stmt = $mysqli->prepare("
        INSERT INTO wp_swca_bank_statements
        (statement_period_start, statement_period_end, starting_balance, ending_balance, total_credits, total_debits, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param('ssdddds',
        $data['start_date'],
        $data['end_date'],
        $data['starting_balance'],
        $data['ending_balance'],
        $data['credits'],
        $data['debits'],
        $notes
    );

    if ($stmt->execute()) {
        $statements_created++;
        echo "  ✓ " . date('M Y', strtotime($data['start_date'])) .
             ": $" . number_format($data['starting_balance'], 2) .
             " → $" . number_format($data['ending_balance'], 2) . "\n";
    }

    $stmt->close();
}

echo "\n✓ Created $statements_created monthly statements\n\n";

// Step 7: Final verification
$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions WHERE balance != 0");
$final_with_balance = $result->fetch_assoc()['count'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions");
$total = $result->fetch_assoc()['count'];

$final_without = $total - $final_with_balance;

echo "=== FINAL STATUS ===\n";
echo "Total transactions: $total\n";
echo "With balances: $final_with_balance\n";
echo "Missing balances: $final_without\n";

if ($final_without == 0) {
    echo "\n✅ SUCCESS! All $total transactions now have calculated balances.\n";
    echo "Final balance: $" . number_format($running_balance, 2) . "\n";
} else {
    echo "\n⚠️  $final_without transactions still missing balances\n";
}

echo "\n=== COMPLETE ===\n";

$mysqli->close();
