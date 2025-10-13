<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "=== Detailed July 1st Payout Analysis ===\n\n";

// Find the actual payout record
$payout_txn = $mysqli->query("
    SELECT *
    FROM swca_stripe_balance_transactions
    WHERE txn_type = 'payout'
      AND amount = -49.28
      AND available_on = '2025-07-01'
")->fetch_assoc();

if (!$payout_txn) {
    echo "Payout transaction not found\n";
    exit;
}

echo "Payout Transaction:\n";
printf("  ID: %s\n", $payout_txn['balance_txn_id']);
printf("  Source: %s\n", $payout_txn['source_id']);
printf("  Amount: $%s\n", number_format($payout_txn['amount'], 2));
printf("  Date: %s\n\n", $payout_txn['available_on']);

$payout_id = $payout_txn['source_id'];

// Find all balance transactions that were included in this payout
// This is done by looking at the payout_id field in balance_transactions
echo "=== All Transactions in Payout $payout_id ===\n\n";

$payout_contents = $mysqli->query("
    SELECT *
    FROM swca_stripe_balance_transactions
    WHERE payout_id = '$payout_id'
       OR (source_id = '$payout_id' AND txn_type = 'payout')
    ORDER BY created_at
");

if ($payout_contents->num_rows == 0) {
    echo "No transactions found with payout_id = $payout_id\n";
    echo "This payout may not have payout_id populated correctly.\n\n";

    // Stripe groups payouts by available_on date, so let's find by that
    echo "Searching by available_on date instead...\n\n";

    $by_date = $mysqli->query("
        SELECT *
        FROM swca_stripe_balance_transactions
        WHERE available_on = '2025-07-01'
          AND txn_type != 'payout'
        ORDER BY created_at
    ");

    if ($by_date->num_rows > 0) {
        echo "Transactions available on 2025-07-01:\n\n";
        $total_net = 0;

        while ($txn = $by_date->fetch_assoc()) {
            printf("%-20s $%-10s (Fee: $%-8s) Net: $%-10s\n",
                $txn['txn_type'],
                number_format($txn['amount'], 2),
                number_format($txn['fee'], 2),
                number_format($txn['net'], 2)
            );
            printf("  %s\n", $txn['description']);
            printf("  Source: %s\n\n", $txn['source_id']);

            $total_net += $txn['net'];
        }

        echo "=== Calculation ===\n";
        printf("Sum of all non-payout transactions (net): $%s\n", number_format($total_net, 2));
        printf("Actual bank deposit: $49.28\n");
        printf("Difference: $%s\n", number_format($total_net - 49.28, 2));
    }

} else {
    $total_net = 0;

    while ($txn = $payout_contents->fetch_assoc()) {
        printf("%-20s $%-10s (Fee: $%-8s) Net: $%-10s\n",
            $txn['txn_type'],
            number_format($txn['amount'], 2),
            number_format($txn['fee'], 2),
            number_format($txn['net'], 2)
        );
        printf("  %s\n\n", $txn['description']);

        if ($txn['txn_type'] != 'payout') {
            $total_net += $txn['net'];
        }
    }

    echo "=== Calculation ===\n";
    printf("Sum of all transactions in payout: $%s\n", number_format($total_net, 2));
    printf("Actual bank deposit: $49.28\n");
    printf("Difference: $%s\n", number_format($total_net - 49.28, 2));
}

$mysqli->close();
?>
