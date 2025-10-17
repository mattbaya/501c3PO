<?php
/**
 * Automatically Calculate Missing Running Balances
 * Calculates balances for transactions that don't have them yet
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== AUTO-CALCULATE MISSING BALANCES ===\n\n";

// Step 1: Check current status
$total = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions");
$with_balance = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE balance != 0");
$without_balance = $total - $with_balance;

echo "Current Status:\n";
echo "  Total transactions: $total\n";
echo "  With balances: $with_balance\n";
echo "  Missing balances: $without_balance\n\n";

if ($without_balance == 0) {
    echo "✓ All transactions already have calculated balances!\n";
    exit(0);
}

// Step 2: Find the last transaction with a calculated balance
$last_with_balance = $wpdb->get_row("
    SELECT id, post_date, balance, description
    FROM wp_swca_bank_transactions
    WHERE balance != 0
    ORDER BY post_date DESC, id DESC
    LIMIT 1
");

if ($last_with_balance) {
    echo "Last calculated balance:\n";
    echo "  Date: {$last_with_balance->post_date}\n";
    echo "  Balance: $" . number_format($last_with_balance->balance, 2) . "\n";
    echo "  Description: {$last_with_balance->description}\n\n";

    $starting_balance = $last_with_balance->balance;
    $starting_date = $last_with_balance->post_date;
} else {
    // No balances calculated yet - would need manual starting balance
    echo "⚠️  No existing balances found. Please run Calculate Balances from the admin interface\n";
    echo "   to enter your starting balance.\n";
    exit(1);
}

// Step 3: Get all transactions AFTER the last calculated balance
$transactions = $wpdb->get_results($wpdb->prepare("
    SELECT id, post_date, description, debit, credit, balance
    FROM wp_swca_bank_transactions
    WHERE post_date > %s OR (post_date = %s AND id > %d)
    ORDER BY post_date ASC, id ASC
", $starting_date, $starting_date, $last_with_balance->id));

if (empty($transactions)) {
    echo "✓ No new transactions to calculate!\n";
    exit(0);
}

echo "Found " . count($transactions) . " transactions to calculate\n";
echo "Calculating balances...\n\n";

// Step 4: Calculate running balances
$running_balance = $starting_balance;
$monthly_data = array();
$updated_count = 0;

foreach ($transactions as $txn) {
    $running_balance += floatval($txn->credit);
    $running_balance -= floatval($txn->debit);

    // Update transaction balance
    $result = $wpdb->update(
        'wp_swca_bank_transactions',
        array('balance' => $running_balance),
        array('id' => $txn->id),
        array('%f'),
        array('%d')
    );

    if ($result !== false) {
        $updated_count++;
        echo "  ✓ {$txn->post_date} - {$txn->description} = $" . number_format($running_balance, 2) . "\n";
    }

    // Track monthly data for statement creation
    $month_key = date('Y-m', strtotime($txn->post_date));
    if (!isset($monthly_data[$month_key])) {
        $monthly_data[$month_key] = array(
            'start_date' => date('Y-m-01', strtotime($txn->post_date)),
            'end_date' => date('Y-m-t', strtotime($txn->post_date)),
            'starting_balance' => $running_balance - floatval($txn->credit) + floatval($txn->debit),
            'credits' => 0,
            'debits' => 0,
            'transaction_count' => 0
        );
    }

    $monthly_data[$month_key]['credits'] += floatval($txn->credit);
    $monthly_data[$month_key]['debits'] += floatval($txn->debit);
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

    $result = $wpdb->query($wpdb->prepare("
        INSERT INTO wp_swca_bank_statements
        (statement_period_start, statement_period_end, starting_balance, ending_balance, total_credits, total_debits, notes)
        VALUES (%s, %s, %f, %f, %f, %f, %s)
        ON DUPLICATE KEY UPDATE
        ending_balance = VALUES(ending_balance),
        total_credits = total_credits + VALUES(total_credits),
        total_debits = total_debits + VALUES(total_debits),
        notes = CONCAT(notes, ' | ', VALUES(notes))
    ",
        $data['start_date'],
        $data['end_date'],
        $data['starting_balance'],
        $data['ending_balance'],
        $data['credits'],
        $data['debits'],
        $notes
    ));

    if ($result) {
        $statements_updated++;
        echo "  ✓ " . date('M Y', strtotime($data['start_date'])) .
             " - Ending balance: $" . number_format($data['ending_balance'], 2) . "\n";
    }
}

echo "\n✓ Updated $statements_updated monthly statements\n\n";

// Step 6: Final verification
$final_with_balance = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE balance != 0");
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
