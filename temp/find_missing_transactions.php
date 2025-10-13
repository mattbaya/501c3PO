<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "=== Finding Missing Transactions for $49.28 Payout ===\n\n";

// The payout arrived on 2025-07-01 with ID 800948598
// Stripe typically pays out 2 days after the charge, so charges would be June 27-29

echo "Looking for Stripe charges around June 27-29, 2025...\n\n";

// Find all Stripe transactions around that date that might be in this payout
$possible = $mysqli->query("
    SELECT
        id,
        stripe_charge_id,
        stripe_created,
        amount,
        stripe_fee,
        amount_refunded,
        (amount - stripe_fee - amount_refunded) as net,
        customer_name,
        customer_email,
        payout_arrival_date,
        payout_id
    FROM swca_stripe_transactions
    WHERE DATE(stripe_created) BETWEEN '2025-06-25' AND '2025-06-30'
       OR payout_arrival_date = '2025-07-01'
    ORDER BY stripe_created
");

echo "Found " . $possible->num_rows . " possible transactions:\n\n";

$total_net = 0;
$matched_ids = array();

// Get IDs that are already matched to bank transaction 40
$matched_result = $mysqli->query("
    SELECT stripe_transaction_id
    FROM swca_transaction_matches
    WHERE bank_transaction_id = 40
");
while ($row = $matched_result->fetch_assoc()) {
    $matched_ids[] = $row['stripe_transaction_id'];
}

while ($txn = $possible->fetch_assoc()) {
    $is_matched = in_array($txn['id'], $matched_ids);
    $status = $is_matched ? " ✓ MATCHED" : " ❌ NOT MATCHED";

    printf("ID %d%s\n", $txn['id'], $status);
    printf("  Charge: %s\n", $txn['stripe_charge_id']);
    printf("  Customer: %s (%s)\n", $txn['customer_name'], $txn['customer_email']);
    printf("  Date: %s\n", $txn['stripe_created']);
    printf("  Amount: $%s, Fee: $%s, Net: $%s\n",
        number_format($txn['amount'], 2),
        number_format($txn['stripe_fee'], 2),
        number_format($txn['net'], 2)
    );
    if ($txn['payout_arrival_date']) {
        printf("  Payout Arrival: %s\n", $txn['payout_arrival_date']);
    }
    if ($txn['payout_id']) {
        printf("  Payout ID: %s\n", $txn['payout_id']);
    }
    echo "\n";

    if ($txn['payout_arrival_date'] == '2025-07-01' || $is_matched) {
        $total_net += $txn['net'];
    }
}

echo "=== Analysis ===\n";
echo "If we include all transactions with payout_arrival_date = 2025-07-01:\n";
echo "Total Net: $" . number_format($total_net, 2) . "\n";
echo "Bank Deposit: $49.28\n";
echo "Difference: $" . number_format($total_net - 49.28, 2) . "\n";

$mysqli->close();
?>
