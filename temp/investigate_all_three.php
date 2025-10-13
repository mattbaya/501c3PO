<?php
/**
 * Complete Investigation: Multiple deposits, matching logic, and Stripe API
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');
require_once('/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/stripe-integration.php');

echo "=== PART 1: Check for Multiple Bank Deposits ===\n\n";

global $wpdb;

$problem_dates = array('2025-08-27', '2025-08-12');

foreach ($problem_dates as $date) {
    echo "Date: $date\n";

    // Get ALL bank transactions on this date
    $all_deposits = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM wp_swca_bank_transactions WHERE post_date = %s ORDER BY credit DESC",
        $date
    ));

    printf("  Found %d bank transaction(s):\n", count($all_deposits));

    foreach ($all_deposits as $deposit) {
        printf("    - $%s: %s\n",
            number_format($deposit->credit ?: $deposit->debit, 2),
            substr($deposit->description, 0, 60)
        );
    }
    echo "\n";
}

echo "\n=== PART 2: Review Transaction Matching Logic ===\n\n";

foreach ($problem_dates as $date) {
    echo "Date: $date\n";

    // Get the bank transaction
    $bank = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM wp_swca_bank_transactions WHERE post_date = %s AND credit > 0",
        $date
    ));

    if (!$bank) continue;

    // Get all matches for this bank transaction
    $matches = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, s.amount, s.stripe_fee, s.payout_arrival_date, s.customer_name
         FROM swca_transaction_matches m
         LEFT JOIN swca_stripe_transactions s ON s.id = m.stripe_transaction_id
         WHERE m.bank_transaction_id = %d
         ORDER BY s.payout_arrival_date",
        $bank->id
    ));

    printf("  Bank Transaction #%d: $%s\n", $bank->id, number_format($bank->credit, 2));
    printf("  Matched to %d Stripe transaction(s):\n", count($matches));

    foreach ($matches as $match) {
        printf("    - Stripe #%d: $%s (fee: $%s) | Payout: %s | %s | Match: %s\n",
            $match->stripe_transaction_id,
            number_format($match->amount, 2),
            number_format($match->stripe_fee, 2),
            $match->payout_arrival_date,
            substr($match->customer_name, 0, 20),
            $match->match_type
        );
    }
    echo "\n";
}

echo "\n=== PART 3: Query Stripe API for Actual Payouts ===\n\n";

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

foreach ($problem_dates as $date) {
    echo "Date: $date\n";

    // Get the bank transaction
    $bank = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM wp_swca_bank_transactions WHERE post_date = %s AND credit > 0",
        $date
    ));

    if (!$bank) continue;

    printf("  Bank deposit: $%s\n\n", number_format($bank->credit, 2));

    // Find payouts around this date
    $date_start = date('Y-m-d', strtotime($date . ' -5 days'));
    $date_end = date('Y-m-d', strtotime($date . ' +5 days'));

    $payouts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM swca_stripe_balance_transactions
         WHERE txn_type = 'payout'
         AND available_on BETWEEN %s AND %s
         ORDER BY available_on",
        $date_start, $date_end
    ));

    printf("  Payouts around this date (%s to %s):\n", $date_start, $date_end);

    foreach ($payouts as $payout) {
        $payout_id = $payout->source_id;

        // Query Stripe API for payout details
        $payout_data = five01c3po_stripe_api_call("payouts/$payout_id", $api_key);

        if ($payout_data) {
            $arrival_date = date('Y-m-d', $payout_data['arrival_date']);
            $payout_amount = $payout_data['amount'] / 100;

            printf("    - %s: $%s (ID: %s)\n",
                $arrival_date,
                number_format($payout_amount, 2),
                substr($payout_id, 0, 20)
            );

            // Check if this matches our bank amount
            if (abs($payout_amount - $bank->credit) < 0.01) {
                echo "      ✅ MATCHES BANK DEPOSIT!\n";
            }
        }
    }

    echo "\n";
}

echo "\n=== Summary of Findings ===\n\n";
echo "Aug 27: Bank received $50.00 but matched to $39.26 payout\n";
echo "Aug 12: Bank received $2.16 but matched to $127.37 payout\n";
echo "\nLikely cause: Incorrect transaction matching linking wrong payouts to bank deposits\n";
?>
