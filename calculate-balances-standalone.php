<?php
/**
 * Standalone Balance Calculator
 * Does not require WordPress - uses direct database connection
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

echo "=== AUTO-CALCULATE MISSING BALANCES ===\n\n";

// Step 1: Check current status
$result = $mysqli->query("SELECT COUNT(*) as total FROM wp_swca_bank_transactions");
$total = $result->fetch_assoc()['total'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions WHERE balance != 0");
$with_balance = $result->fetch_assoc()['count'];

$without_balance = $total - $with_balance;

echo "Current Status:\n";
echo "  Total transactions: $total\n";
echo "  With balances: $with_balance\n";
echo "  Missing balances: $without_balance\n\n";

if ($without_balance == 0) {
    echo "✓ All transactions already have calculated balances!\n";
    $mysqli->close();
    exit(0);
}

// Step 2: Find the last transaction with a calculated balance
$result = $mysqli->query("
    SELECT id, post_date, balance, description
    FROM wp_swca_bank_transactions
    WHERE balance != 0
    ORDER BY post_date DESC, id DESC
    LIMIT 1
");

$last_with_balance = $result->fetch_assoc();

if ($last_with_balance) {
    echo "Last calculated balance:\n";
    echo "  Date: {$last_with_balance['post_date']}\n";
    echo "  Balance: $" . number_format($last_with_balance['balance'], 2) . "\n";
    echo "  Description: {$last_with_balance['description']}\n\n";

    $starting_balance = $last_with_balance['balance'];
    $starting_date = $last_with_balance['post_date'];
    $starting_id = $last_with_balance['id'];
} else {
    echo "⚠️  No existing balances found. Calculating from first transaction with balance = 0\n";

    // Get first transaction
    $result = $mysqli->query("SELECT id, post_date FROM wp_swca_bank_transactions ORDER BY post_date ASC, id ASC LIMIT 1");
    $first = $result->fetch_assoc();

    if (!$first) {
        echo "ERROR: No transactions found in database!\n";
        $mysqli->close();
        exit(1);
    }

    $starting_balance = 0;
    $starting_date = $first['post_date'];
    $starting_id = 0;
}

// Step 3: Get all transactions AFTER the last calculated balance
$stmt = $mysqli->prepare("
    SELECT id, post_date, description, debit, credit, balance
    FROM wp_swca_bank_transactions
    WHERE post_date > ? OR (post_date = ? AND id > ?)
    ORDER BY post_date ASC, id ASC
");

$stmt->bind_param('ssi', $starting_date, $starting_date, $starting_id);
$stmt->execute();
$result = $stmt->get_result();

$transactions = array();
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

if (empty($transactions)) {
    echo "✓ No new transactions to calculate!\n";
    $mysqli->close();
    exit(0);
}

echo "Found " . count($transactions) . " transactions to calculate\n";
echo "Calculating balances...\n\n";

// Step 4: Calculate running balances
$running_balance = $starting_balance;
$monthly_data = array();
$updated_count = 0;

foreach ($transactions as $txn) {
    $running_balance += floatval($txn['credit']);
    $running_balance -= floatval($txn['debit']);

    // Update transaction balance
    $update_stmt = $mysqli->prepare("UPDATE wp_swca_bank_transactions SET balance = ? WHERE id = ?");
    $update_stmt->bind_param('di', $running_balance, $txn['id']);

    if ($update_stmt->execute()) {
        $updated_count++;
        echo "  ✓ {$txn['post_date']} - " . substr($txn['description'], 0, 40) . " = $" . number_format($running_balance, 2) . "\n";
    } else {
        echo "  ✗ Failed to update transaction {$txn['id']}\n";
    }

    $update_stmt->close();

    // Track monthly data for statement creation
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

// Step 5: Update or create monthly statement records
echo "Updating monthly statements...\n";
$statements_updated = 0;

foreach ($monthly_data as $month => $data) {
    $notes = sprintf(
        "Auto-calculated %d transactions for %s. Credits: $%s, Debits: $%s",
        $data['transaction_count'],
        date('F Y', strtotime($data['start_date'])),
        number_format($data['credits'], 2),
        number_format($data['debits'], 2)
    );

    // Use INSERT ON DUPLICATE KEY UPDATE
    $stmt = $mysqli->prepare("
        INSERT INTO wp_swca_bank_statements
        (statement_period_start, statement_period_end, starting_balance, ending_balance, total_credits, total_debits, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        ending_balance = VALUES(ending_balance),
        total_credits = total_credits + VALUES(total_credits),
        total_debits = total_debits + VALUES(total_debits),
        notes = CONCAT(notes, ' | ', VALUES(notes))
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
        $statements_updated++;
        echo "  ✓ " . date('M Y', strtotime($data['start_date'])) .
             " - Ending balance: $" . number_format($data['ending_balance'], 2) . "\n";
    } else {
        echo "  ✗ Failed to update statement for " . date('M Y', strtotime($data['start_date'])) . "\n";
    }

    $stmt->close();
}

echo "\n✓ Updated $statements_updated monthly statements\n\n";

// Step 6: Final verification
$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions WHERE balance != 0");
$final_with_balance = $result->fetch_assoc()['count'];
$final_without = $total - $final_with_balance;

echo "=== FINAL STATUS ===\n";
echo "Total transactions: $total\n";
echo "With balances: $final_with_balance\n";
echo "Missing balances: $final_without\n";

if ($final_without == 0) {
    echo "\n✅ SUCCESS! All transactions now have calculated balances.\n";
} else {
    echo "\n⚠️  $final_without transactions still missing balances\n";
}

echo "\n=== COMPLETE ===\n";

$mysqli->close();
