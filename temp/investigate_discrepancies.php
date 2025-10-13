<?php
/**
 * Investigate Specific Discrepancies
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');

$dates_to_check = array('2025-08-27', '2025-08-12', '2025-03-10');

foreach ($dates_to_check as $date) {
    echo str_repeat("=", 70) . "\n";
    echo "=== Investigating $date ===\n";
    echo str_repeat("=", 70) . "\n\n";

    global $wpdb;

    // Get bank transaction
    $bank = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM wp_swca_bank_transactions WHERE post_date = %s AND credit > 0",
        $date
    ));

    if (!$bank) {
        echo "No bank transaction found\n\n";
        continue;
    }

    printf("Bank Transaction:\n");
    printf("  Amount: $%s\n", number_format($bank->credit, 2));
    printf("  Description: %s\n\n", $bank->description);

    // Get Stripe matches
    $matches = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM swca_transaction_matches
         WHERE bank_transaction_id = %d
         AND stripe_transaction_id IS NOT NULL",
        $bank->id
    ));

    printf("Stripe Matches: %d\n", count($matches));

    foreach ($matches as $match) {
        $stripe = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM swca_stripe_transactions WHERE id = %d",
            $match->stripe_transaction_id
        ));

        printf("  - Stripe #%d: $%s (fee: $%s) | %s | Payout: %s\n",
            $stripe->id,
            number_format($stripe->amount, 2),
            number_format($stripe->stripe_fee, 2),
            $stripe->payment_date,
            $stripe->payout_arrival_date
        );
    }

    // Get payout details
    $first_match = $matches[0];
    $first_stripe = $wpdb->get_row($wpdb->prepare(
        "SELECT payout_arrival_date FROM swca_stripe_transactions WHERE id = %d",
        $first_match->stripe_transaction_id
    ));

    $payout_arrival_date = $first_stripe->payout_arrival_date;

    echo "\nPayout Arrival Date: $payout_arrival_date\n";

    // Get payout_id
    $bal_txn = $wpdb->get_row($wpdb->prepare(
        "SELECT payout_id FROM swca_stripe_balance_transactions
         WHERE source_type = 'charge'
         AND available_on = %s
         AND payout_id IS NOT NULL
         LIMIT 1",
        $payout_arrival_date
    ));

    $payout_id = $bal_txn->payout_id ?? null;

    printf("Payout ID: %s\n\n", $payout_id ?: 'NULL');

    if ($payout_id) {
        echo "Balance Transactions in Payout:\n";

        $balance_txns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM swca_stripe_balance_transactions
             WHERE payout_id = %s
             ORDER BY
                CASE txn_type
                    WHEN 'payment' THEN 1
                    WHEN 'charge' THEN 1
                    WHEN 'stripe_fee' THEN 2
                    WHEN 'adjustment' THEN 3
                    WHEN 'payout' THEN 4
                    ELSE 5
                END,
                created_at",
            $payout_id
        ));

        $total = 0;
        foreach ($balance_txns as $txn) {
            if ($txn->txn_type == 'payout') {
                printf("  [PAYOUT] $%s\n", number_format($txn->amount, 2));
                continue;
            }

            $total += $txn->net;

            printf("  %-15s | $%-8s (fee: $%-6s) net: $%-8s | %s | %s\n",
                $txn->txn_type,
                number_format($txn->amount, 2),
                number_format($txn->fee, 2),
                number_format($txn->net, 2),
                $txn->available_on,
                substr($txn->description, 0, 40)
            );
        }

        echo "\nCalculation:\n";
        printf("  Sum of balance transaction nets: $%s\n", number_format($total, 2));
        printf("  Bank deposit: $%s\n", number_format($bank->credit, 2));
        printf("  Difference: $%s\n\n", number_format(abs($total - $bank->credit), 2));
    }

    echo "\n";
}
?>
