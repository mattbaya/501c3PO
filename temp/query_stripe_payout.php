<?php
/**
 * Query Stripe API for Payout Details
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');

// Load Stripe integration functions
require_once('/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/stripe-integration.php');

echo "=== Querying Stripe API for Payout Details ===\n\n";

// Decrypt API key
$org_settings = get_option('five01c3po_organization_settings', array());
$encrypted_api_key = $org_settings['stripe_api_key_encrypted'] ?? '';
$passphrase_hash = $org_settings['stripe_passphrase_hash'] ?? '';

echo "Enter officer passphrase: ";
$passphrase = trim(fgets(STDIN));

if (empty($passphrase)) {
    echo "Error: Passphrase required\n";
    exit(1);
}

if (!five01c3po_verify_officer_passphrase($passphrase, $passphrase_hash)) {
    echo "Error: Invalid passphrase\n";
    exit(1);
}

$api_key = five01c3po_decrypt_stripe_key($encrypted_api_key, $passphrase);

// Get the payout ID from our database
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

$payout = $mysqli->query("
    SELECT source_id
    FROM swca_stripe_balance_transactions
    WHERE amount = -49.28
      AND available_on = '2025-07-01'
      AND txn_type = 'payout'
    LIMIT 1
")->fetch_assoc();

$payout_id = $payout['source_id'];
echo "Payout ID: $payout_id\n\n";

// Query Stripe for this payout's details
echo "Fetching payout from Stripe API...\n";
$payout_data = five01c3po_stripe_api_call("payouts/$payout_id", $api_key);

if (!$payout_data) {
    echo "Failed to fetch payout\n";
    exit(1);
}

echo "\n=== Payout Information ===\n";
printf("Amount: $%s\n", number_format($payout_data['amount'] / 100, 2));
printf("Arrival Date: %s\n", date('Y-m-d', $payout_data['arrival_date']));
printf("Status: %s\n", $payout_data['status']);
printf("Type: %s\n\n", $payout_data['type'] ?? 'standard');

// Get all balance transactions for this payout
echo "Fetching balance transactions for this payout...\n";
$balance_txns_data = five01c3po_stripe_api_call("balance_transactions?payout=$payout_id&limit=100", $api_key);

if (!$balance_txns_data || !isset($balance_txns_data['data'])) {
    echo "Failed to fetch balance transactions\n";
    exit(1);
}

$transactions = $balance_txns_data['data'];
echo "\nFound " . count($transactions) . " transactions in this payout:\n\n";

$total_amount = 0;
$total_fee = 0;
$total_net = 0;

foreach ($transactions as $txn) {
    $amount = $txn['amount'] / 100;
    $fee = $txn['fee'] / 100;
    $net = $txn['net'] / 100;

    printf("%-20s $%-10s (Fee: $%-8s) Net: $%-10s\n",
        $txn['type'],
        number_format($amount, 2),
        number_format($fee, 2),
        number_format($net, 2)
    );
    printf("  %s\n", $txn['description'] ?? 'No description');

    if (isset($txn['source']) && is_string($txn['source'])) {
        printf("  Source: %s\n", $txn['source']);
    }

    echo "\n";

    $total_amount += $amount;
    $total_fee += $fee;
    $total_net += $net;
}

echo "=== Summary ===\n";
printf("Total Amount: $%s\n", number_format($total_amount, 2));
printf("Total Fees: $%s\n", number_format($total_fee, 2));
printf("Total Net: $%s\n", number_format($total_net, 2));
printf("Payout Amount from API: $%s\n", number_format($payout_data['amount'] / 100, 2));
printf("Bank Deposit: $49.28\n\n");

if (abs($total_net - 49.28) < 0.01) {
    echo "✅ Net matches bank deposit!\n";
} else {
    printf("⚠️  Mismatch: $%s\n", number_format($total_net - 49.28, 2));
}

$mysqli->close();
?>
