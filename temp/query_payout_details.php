<?php
/**
 * Query a specific payout to see what balance transactions it includes
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');
require_once('/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/stripe-integration.php');

echo "=== Querying Specific Payout Details ===\n\n";

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

// Find the Sept 25 payout in the database
global $wpdb;
$payout_txn = $wpdb->get_row("
    SELECT * FROM swca_stripe_balance_transactions
    WHERE txn_type = 'payout'
      AND amount = -4.51
      AND available_on = '2025-09-25'
    LIMIT 1
");

if (!$payout_txn) {
    echo "Payout transaction not found in database\n";
    exit;
}

printf("Found payout transaction:\n");
printf("  Balance TXN ID: %s\n", $payout_txn->balance_txn_id);
printf("  Source ID: %s\n", $payout_txn->source_id);
printf("  Amount: $%s\n", number_format($payout_txn->amount, 2));
printf("  Available on: %s\n\n", $payout_txn->available_on);

// Query the payout object from Stripe
$payout_id = $payout_txn->source_id;
echo "Querying Stripe API for payout: $payout_id\n\n";

$payout_data = five01c3po_stripe_api_call("payouts/$payout_id", $api_key);

if (!$payout_data) {
    echo "Failed to fetch payout from Stripe\n";
    exit;
}

echo "=== Payout Information ===\n";
printf("ID: %s\n", $payout_data['id']);
printf("Amount: $%s\n", number_format($payout_data['amount'] / 100, 2));
printf("Arrival Date: %s\n", date('Y-m-d', $payout_data['arrival_date']));
printf("Created: %s\n", date('Y-m-d H:i:s', $payout_data['created']));
printf("Status: %s\n", $payout_data['status']);
printf("Type: %s\n\n", $payout_data['type'] ?? 'standard');

// Now query balance transactions FOR this payout
echo "=== Querying Balance Transactions for this Payout ===\n\n";

$balance_txns = five01c3po_stripe_api_call("balance_transactions?payout=$payout_id&limit=100", $api_key);

if (!$balance_txns || !isset($balance_txns['data'])) {
    echo "Failed to fetch balance transactions\n";
    exit;
}

printf("Found %d balance transactions in this payout:\n\n", count($balance_txns['data']));

$total_net = 0;
foreach ($balance_txns['data'] as $txn) {
    $amount = $txn['amount'] / 100;
    $fee = $txn['fee'] / 100;
    $net = $txn['net'] / 100;

    $total_net += $net;

    printf("%-20s | $%-10s (fee: $%-8s) net: $%-10s | Available: %s\n",
        $txn['type'],
        number_format($amount, 2),
        number_format($fee, 2),
        number_format($net, 2),
        date('Y-m-d', $txn['available_on'])
    );
    if ($txn['description']) {
        printf("  %s\n", $txn['description']);
    }
}

echo "\n=== Summary ===\n";
printf("Total Net: $%s\n", number_format($total_net, 2));
printf("Payout Amount: $%s\n", number_format($payout_data['amount'] / 100, 2));

if (abs($total_net - ($payout_data['amount'] / 100)) < 0.01) {
    echo "✅ Totals match!\n";
} else {
    printf("⚠️  Difference: $%s\n", number_format(abs($total_net - ($payout_data['amount'] / 100)), 2));
}
?>
