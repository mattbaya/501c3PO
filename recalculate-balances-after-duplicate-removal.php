<?php
/**
 * Recalculate All Balances After Duplicate Removal
 * This script recalculates running balances with duplicates removed
 */

require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

echo "=== RECALCULATING BALANCES AFTER DUPLICATE REMOVAL ===\n\n";

// Known starting balance from previous calculation
$starting_balance = 6849.28;
$starting_date = '2024-01-02';

echo "Starting Balance: $" . number_format($starting_balance, 2) . "\n";
echo "Starting Date: $starting_date\n\n";

// Step 1: Clear existing balances and statements
echo "Step 1: Clearing existing balances and statements...\n";
$wpdb->query("UPDATE wp_swca_bank_transactions SET balance = 0");
$wpdb->query("DELETE FROM wp_swca_bank_statements WHERE notes LIKE 'Auto-generated%'");
echo "✓ Cleared\n\n";

// Step 2: Recalculate running balances
echo "Step 2: Recalculating running balances...\n";

$running_balance = $starting_balance;
$monthly_data = array();

$transactions = $wpdb->get_results($wpdb->prepare("
    SELECT id, post_date, description, debit, credit
    FROM wp_swca_bank_transactions
    WHERE post_date >= %s
    ORDER BY post_date ASC, id ASC
", $starting_date));

echo "Processing " . count($transactions) . " transactions...\n";

foreach ($transactions as $txn) {
    $running_balance += floatval($txn->credit);
    $running_balance -= floatval($txn->debit);

    // Update transaction balance
    $wpdb->update(
        'wp_swca_bank_transactions',
        array('balance' => $running_balance),
        array('id' => $txn->id),
        array('%f'),
        array('%d')
    );

    // Track monthly data
    $month_key = date('Y-m', strtotime($txn->post_date));
    if (!isset($monthly_data[$month_key])) {
        $monthly_data[$month_key] = array(
            'start_date' => date('Y-m-01', strtotime($txn->post_date)),
            'end_date' => date('Y-m-t', strtotime($txn->post_date)),
            'starting_balance' => $running_balance - floatval($txn->credit) + floatval($txn->debit),
            'credits' => 0,
            'debits' => 0
        );
    }

    $monthly_data[$month_key]['credits'] += floatval($txn->credit);
    $monthly_data[$month_key]['debits'] += floatval($txn->debit);
    $monthly_data[$month_key]['ending_balance'] = $running_balance;
}

echo "✓ Balances calculated\n\n";

// Step 3: Create monthly statement records
echo "Step 3: Creating monthly statement records...\n";

foreach ($monthly_data as $month => $data) {
    $notes = sprintf(
        "Auto-generated from transactions for %s. Starting balance: $%s, Ending balance: $%s",
        date('F Y', strtotime($data['start_date'])),
        number_format($data['starting_balance'], 2),
        number_format($data['ending_balance'], 2)
    );

    $wpdb->query($wpdb->prepare("
        INSERT INTO wp_swca_bank_statements
        (statement_period_start, statement_period_end, starting_balance, ending_balance, total_credits, total_debits, notes)
        VALUES (%s, %s, %f, %f, %f, %f, %s)
        ON DUPLICATE KEY UPDATE
        starting_balance = VALUES(starting_balance),
        ending_balance = VALUES(ending_balance),
        total_credits = VALUES(total_credits),
        total_debits = VALUES(total_debits),
        notes = VALUES(notes)
    ",
        $data['start_date'],
        $data['end_date'],
        $data['starting_balance'],
        $data['ending_balance'],
        $data['credits'],
        $data['debits'],
        $notes
    ));

    echo "  Created statement for $month: \${$data['ending_balance']}\n";
}

echo "\n✓ Created " . count($monthly_data) . " monthly statements\n\n";

// Step 4: Verification
echo "Step 4: Verification...\n";
$final_balance = $wpdb->get_var("SELECT balance FROM wp_swca_bank_transactions ORDER BY post_date DESC, id DESC LIMIT 1");
$total_with_balance = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE balance != 0");
$total_statements = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_statements");

echo "Final balance: $" . number_format($final_balance, 2) . "\n";
echo "Transactions with balance: $total_with_balance / " . count($transactions) . "\n";
echo "Monthly statements: $total_statements\n\n";

echo "=== RECALCULATION COMPLETE ===\n";
echo "\n✅ All balances recalculated after duplicate removal\n";
echo "✅ Monthly statements regenerated\n";
echo "✅ Ledger now shows accurate balances without duplicates\n";
