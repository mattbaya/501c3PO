<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "=== Investigating $49.28 Bank Deposit ===\n\n";

// Find the bank transaction with $49.28
$bank = $mysqli->query("
    SELECT id, post_date, credit, description
    FROM wp_swca_bank_transactions
    WHERE credit = 49.28
    LIMIT 1
");

if ($bank->num_rows == 0) {
    echo "Bank transaction with $49.28 not found!\n";
    exit;
}

$bank_txn = $bank->fetch_assoc();
echo "Bank Transaction ID: " . $bank_txn['id'] . "\n";
echo "Date: " . $bank_txn['post_date'] . "\n";
echo "Amount: $" . number_format($bank_txn['credit'], 2) . "\n";
echo "Description: " . $bank_txn['description'] . "\n\n";

// Get all Stripe transactions matched to this bank transaction
echo "=== Stripe Transactions Matched to This Bank Deposit ===\n\n";

$matches = $mysqli->query("
    SELECT
        m.stripe_transaction_id,
        m.match_type,
        s.stripe_charge_id,
        s.amount,
        s.stripe_fee,
        s.amount_refunded,
        (s.amount - s.stripe_fee - s.amount_refunded) as net,
        s.customer_name,
        s.customer_email,
        s.stripe_created,
        s.description
    FROM swca_transaction_matches m
    JOIN swca_stripe_transactions s ON s.id = m.stripe_transaction_id
    WHERE m.bank_transaction_id = " . $bank_txn['id'] . "
    ORDER BY s.stripe_created
");

if ($matches->num_rows == 0) {
    echo "No Stripe transactions matched to this bank deposit!\n";
    exit;
}

$total_gross = 0;
$total_fees = 0;
$total_refunded = 0;
$total_net = 0;
$count = 0;

while ($match = $matches->fetch_assoc()) {
    $count++;
    printf("#%d - Stripe Transaction ID: %d\n", $count, $match['stripe_transaction_id']);
    printf("  Charge ID: %s\n", $match['stripe_charge_id']);
    printf("  Match Type: %s\n", $match['match_type']);
    printf("  Customer: %s (%s)\n", $match['customer_name'], $match['customer_email']);
    printf("  Date: %s\n", $match['stripe_created']);
    printf("  Amount: $%s\n", number_format($match['amount'], 2));
    printf("  Fee: -$%s\n", number_format($match['stripe_fee'], 2));
    if ($match['amount_refunded'] > 0) {
        printf("  Refunded: -$%s\n", number_format($match['amount_refunded'], 2));
    }
    printf("  Net: $%s\n", number_format($match['net'], 2));
    echo "\n";

    $total_gross += $match['amount'];
    $total_fees += $match['stripe_fee'];
    $total_refunded += $match['amount_refunded'];
    $total_net += $match['net'];
}

echo "=== Summary ===\n";
echo "Total Stripe Transactions Matched: $count\n";
echo "Total Gross Amount: $" . number_format($total_gross, 2) . "\n";
echo "Total Stripe Fees: -$" . number_format($total_fees, 2) . "\n";
if ($total_refunded > 0) {
    echo "Total Refunded: -$" . number_format($total_refunded, 2) . "\n";
}
echo "Total Net (expected in bank): $" . number_format($total_net, 2) . "\n";
echo "Actual Bank Deposit: $" . number_format($bank_txn['credit'], 2) . "\n";
$diff = $bank_txn['credit'] - $total_net;
echo "Difference: $" . number_format($diff, 2);
if (abs($diff) > 0.01) {
    echo " ⚠️  MISMATCH\n";
} else {
    echo " ✓ Match\n";
}

$mysqli->close();
?>
