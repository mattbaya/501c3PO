<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "=== Finding Balance Transactions for July 1st Payout ===\n\n";

// First, get the charge ID from the Stripe transaction
$charge = $mysqli->query("
    SELECT stripe_charge_id, balance_transaction_id
    FROM swca_stripe_transactions
    WHERE payout_arrival_date = '2025-07-01'
      AND customer_email = 'dennymene@gmail.com'
")->fetch_assoc();

if (!$charge) {
    echo "No charge found\n";
    exit;
}

echo "Charge ID: " . $charge['stripe_charge_id'] . "\n";
echo "Balance Txn ID: " . $charge['balance_transaction_id'] . "\n\n";

// Get the balance transaction for this charge
echo "Step 1: Balance transaction for the charge itself:\n";
$charge_balance = $mysqli->query("
    SELECT *
    FROM swca_stripe_balance_transactions
    WHERE source_id = '" . $mysqli->real_escape_string($charge['stripe_charge_id']) . "'
");

if ($charge_balance->num_rows > 0) {
    while ($txn = $charge_balance->fetch_assoc()) {
        printf("  Type: %s\n", $txn['txn_type']);
        printf("  Amount: $%s, Fee: $%s, Net: $%s\n",
            number_format($txn['amount'], 2),
            number_format($txn['fee'], 2),
            number_format($txn['net'], 2)
        );
        printf("  Description: %s\n", $txn['description']);
        printf("  Available On: %s\n\n", $txn['available_on']);
    }
}

// Look for any fees or adjustments around that date
echo "Step 2: Looking for fees/adjustments around June 27 - July 1:\n\n";
$related_txns = $mysqli->query("
    SELECT *
    FROM swca_stripe_balance_transactions
    WHERE available_on BETWEEN '2025-06-27' AND '2025-07-01'
    ORDER BY created_at
");

if ($related_txns->num_rows > 0) {
    $total_net = 0;
    while ($txn = $related_txns->fetch_assoc()) {
        printf("Type: %-15s Amount: $%-8s Fee: $%-8s Net: $%-8s\n",
            $txn['txn_type'],
            number_format($txn['amount'], 2),
            number_format($txn['fee'], 2),
            number_format($txn['net'], 2)
        );
        printf("  Description: %s\n", $txn['description']);
        printf("  Available: %s\n\n", $txn['available_on']);

        if ($txn['available_on'] == '2025-07-01') {
            $total_net += $txn['net'];
        }
    }

    echo "=== Total Net for July 1st ===\n";
    echo "Sum of all transactions available on 2025-07-01: $" . number_format($total_net, 2) . "\n";
    echo "Bank Deposit: $49.28\n";
    echo "Difference: $" . number_format($total_net - 49.28, 2) . "\n";
} else {
    echo "No balance transactions found in that date range\n";
}

$mysqli->close();
?>
