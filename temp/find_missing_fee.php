<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "=== Finding the Missing 72 Cents ===\n\n";

// The payout arrived on July 1, 2025
// Let's find all balance transactions for that payout

echo "Step 1: Find the payout ID...\n";
$payout_result = $mysqli->query("
    SELECT DISTINCT payout_id
    FROM swca_stripe_transactions
    WHERE payout_arrival_date = '2025-07-01'
      AND customer_email = 'dennymene@gmail.com'
");

if ($payout_result->num_rows == 0) {
    echo "No payout found\n";
    exit;
}

$payout_row = $payout_result->fetch_assoc();
$payout_id = $payout_row['payout_id'];
echo "Payout ID: $payout_id\n\n";

echo "Step 2: Get ALL balance transactions for this payout...\n\n";

$balance_txns = $mysqli->query("
    SELECT
        stripe_balance_txn_id,
        type,
        amount,
        fee,
        net,
        description,
        source_id,
        created
    FROM swca_stripe_balance_transactions
    WHERE payout_id = '$payout_id'
    ORDER BY created
");

if ($balance_txns->num_rows == 0) {
    echo "No balance transactions found for this payout\n";
    exit;
}

echo "Found " . $balance_txns->num_rows . " balance transactions:\n\n";

$total_net = 0;
while ($txn = $balance_txns->fetch_assoc()) {
    printf("Type: %-20s Amount: $%-8s Fee: $%-8s Net: $%-8s\n",
        $txn['type'],
        number_format($txn['amount'], 2),
        number_format($txn['fee'], 2),
        number_format($txn['net'], 2)
    );
    printf("  Description: %s\n", $txn['description']);
    printf("  Source: %s\n", $txn['source_id']);
    printf("  Created: %s\n\n", $txn['created']);

    $total_net += $txn['net'];
}

echo "=== Summary ===\n";
echo "Total Net from ALL Balance Transactions: $" . number_format($total_net, 2) . "\n";
echo "Bank Deposit: $49.28\n";
echo "Difference: $" . number_format($total_net - 49.28, 2) . "\n";

$mysqli->close();
?>
