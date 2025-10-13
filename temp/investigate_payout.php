<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "=== Investigating Payout with 72 cent discrepancy ===\n\n";

// First, find the payout ID from June 27, 2025 with email dennymene@gmail.com
$payout = $mysqli->query("
    SELECT payout_id, payout_arrival_date
    FROM swca_stripe_transactions
    WHERE customer_email = 'dennymene@gmail.com'
      AND DATE(stripe_created) = '2025-06-27'
    LIMIT 1
")->fetch_assoc();

if (!$payout) {
    // Try searching for any payout around that date with amount $51.80
    $payout = $mysqli->query("
        SELECT payout_id, payout_arrival_date
        FROM swca_stripe_transactions
        WHERE amount = 51.80
          AND DATE(stripe_created) BETWEEN '2025-06-26' AND '2025-06-28'
        LIMIT 1
    ")->fetch_assoc();
}

if (!$payout) {
    echo "Payout not found!\n";
    exit;
}

echo "Payout ID: " . $payout['payout_id'] . "\n";
echo "Arrival Date: " . $payout['payout_arrival_date'] . "\n\n";

// Get ALL transactions in this payout
$transactions = $mysqli->query("
    SELECT
        id,
        stripe_charge_id,
        amount,
        stripe_fee,
        amount_refunded,
        (amount - stripe_fee - amount_refunded) as net_amount,
        customer_name,
        customer_email,
        description,
        stripe_created
    FROM swca_stripe_transactions
    WHERE payout_id = '" . $mysqli->real_escape_string($payout['payout_id']) . "'
    ORDER BY stripe_created
");

echo "=== All Transactions in This Payout ===\n\n";

$total_amount = 0;
$total_fees = 0;
$total_refunded = 0;
$total_net = 0;
$count = 0;

while ($txn = $transactions->fetch_assoc()) {
    $count++;
    $net = $txn['amount'] - $txn['stripe_fee'] - $txn['amount_refunded'];

    printf("#%d - Stripe ID: %d\n", $count, $txn['id']);
    printf("  Charge: %s\n", $txn['stripe_charge_id']);
    printf("  Customer: %s (%s)\n", $txn['customer_name'], $txn['customer_email']);
    printf("  Date: %s\n", $txn['stripe_created']);
    printf("  Amount: $%s\n", number_format($txn['amount'], 2));
    printf("  Fee: -$%s\n", number_format($txn['stripe_fee'], 2));
    if ($txn['amount_refunded'] > 0) {
        printf("  Refunded: -$%s\n", number_format($txn['amount_refunded'], 2));
    }
    printf("  Net: $%s\n", number_format($net, 2));
    printf("  Description: %s\n\n", $txn['description']);

    $total_amount += $txn['amount'];
    $total_fees += $txn['stripe_fee'];
    $total_refunded += $txn['amount_refunded'];
    $total_net += $net;
}

echo "=== Summary ===\n";
echo "Total Transactions: $count\n";
echo "Total Amount: $" . number_format($total_amount, 2) . "\n";
echo "Total Fees: -$" . number_format($total_fees, 2) . "\n";
if ($total_refunded > 0) {
    echo "Total Refunded: -$" . number_format($total_refunded, 2) . "\n";
}
echo "Total Net (should match bank): $" . number_format($total_net, 2) . "\n\n";

// Check what's matched
echo "=== Match Status ===\n";
$matched = $mysqli->query("
    SELECT COUNT(*) as matched_count
    FROM swca_transaction_matches
    WHERE stripe_transaction_id IN (
        SELECT id FROM swca_stripe_transactions
        WHERE payout_id = '" . $mysqli->real_escape_string($payout['payout_id']) . "'
    )
    AND match_type LIKE '%bank%'
");

$matched_row = $matched->fetch_assoc();
echo "Matched to bank: " . $matched_row['matched_count'] . " out of $count transactions\n";

if ($matched_row['matched_count'] < $count) {
    echo "\n⚠️  WARNING: Not all transactions in this payout are matched!\n";
    echo "This could explain the discrepancy.\n";
}

$mysqli->close();
?>
