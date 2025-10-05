<?php
/**
 * Smart Transaction Matching Algorithm
 * Based on analysis of actual SWCA transaction patterns
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Intelligent matching based on observed patterns
 */
function five01c3po_smart_match_transactions() {
    global $wpdb;

    $results = array(
        'gf_stripe_matches' => 0,
        'bank_payout_matches' => 0,
        'bank_non_stripe' => 0,
        'refunded_stripe_identified' => 0,
        'details' => array(),
        'debug' => array()
    );

    $matches_table = $wpdb->prefix . 'transaction_matches';
    $stripe_table = $wpdb->prefix . 'stripe_transactions';
    $bank_table = 'wp_swca_bank_transactions'; // Use actual table with data
    $gf_table = 'swca_gf_addon_payment_transaction';

    // PATTERN 1: Identify refunded Stripe transactions (net_amount = 0)
    $results['debug'][] = "PATTERN 1: Identifying refunded Stripe transactions...";
    $refunded = $wpdb->get_results("
        SELECT id, stripe_charge_id, amount, net_amount, stripe_fee
        FROM $stripe_table
        WHERE net_amount = 0 AND amount > 0
    ");
    $results['refunded_stripe_identified'] = count($refunded);
    $results['debug'][] = "Found " . count($refunded) . " refunded Stripe transactions (net = $0.00)";

    // PATTERN 2: Match bank → Stripe payouts by date range and amount sum
    $results['debug'][] = "\nPATTERN 2: Matching bank deposits to Stripe payouts...";

    $bank_stripe_deposits = $wpdb->get_results("
        SELECT id, post_date, description, credit
        FROM $bank_table
        WHERE credit > 0
        AND description LIKE '%STRIPE%'
        ORDER BY post_date ASC
    ");

    $results['debug'][] = "Found " . count($bank_stripe_deposits) . " bank deposits from Stripe";

    // For each Stripe bank deposit, find matching Stripe charges
    $previous_bank_date = null;
    foreach ($bank_stripe_deposits as $bank) {
        $bank_amount = floatval($bank->credit);
        $bank_date = $bank->post_date;

        // Extract Stripe payout ID if present (ST-XXXXXX)
        $payout_id = null;
        if (preg_match('/ST-([A-Z0-9]+)/', $bank->description, $matches)) {
            $payout_id = 'ST-' . $matches[1];
        }

        // Date range: Between previous bank deposit and this one
        // (Stripe batches charges and pays out periodically)
        $start_date = $previous_bank_date ? date('Y-m-d', strtotime($previous_bank_date . ' +1 day')) : date('Y-m-d', strtotime($bank_date . ' -14 days'));
        $end_date = $bank_date;

        // Find all Stripe charges in this date range with non-zero net
        $stripe_in_range = $wpdb->get_results($wpdb->prepare("
            SELECT id, stripe_created, amount, net_amount, stripe_fee, customer_email
            FROM $stripe_table
            WHERE stripe_created >= %s
            AND stripe_created <= %s
            AND net_amount > 0
            ORDER BY stripe_created ASC
        ", $start_date, $end_date));

        if (empty($stripe_in_range)) {
            $results['debug'][] = sprintf(
                "Bank #%d (%s, $%.2f): No Stripe charges found in range %s to %s",
                $bank->id,
                $bank_date,
                $bank_amount,
                $start_date,
                $end_date
            );
            continue;
        }

        // Sum the net amounts
        $stripe_total = 0;
        $stripe_ids = array();
        foreach ($stripe_in_range as $stripe) {
            $stripe_total += floatval($stripe->net_amount);
            $stripe_ids[] = $stripe->id;
        }

        // Check if sum matches bank amount (within $0.50)
        $difference = abs($stripe_total - $bank_amount);

        if ($difference <= 0.50) {
            // MATCH FOUND!
            $confidence = $difference <= 0.05 ? 'high' : 'medium';

            $results['details'][] = sprintf(
                "✓ Bank #%d ($%.2f on %s) matched to %d Stripe charges ($%.2f) | Diff: $%.2f | Payout ID: %s",
                $bank->id,
                $bank_amount,
                $bank_date,
                count($stripe_ids),
                $stripe_total,
                $difference,
                $payout_id ?: 'not-found'
            );

            // Create matches for each Stripe charge in this payout
            foreach ($stripe_ids as $stripe_id) {
                $wpdb->insert($matches_table, array(
                    'stripe_transaction_id' => $stripe_id,
                    'bank_transaction_id' => $bank->id,
                    'match_type' => 'bank_stripe_payout',
                    'match_confidence' => 'auto_' . $confidence,
                    'notes' => sprintf(
                        'Part of payout batch: %d charges totaling $%.2f deposited on %s (Payout: %s)',
                        count($stripe_ids),
                        $stripe_total,
                        $bank_date,
                        $payout_id ?: 'unknown'
                    ),
                    'matched_by' => get_current_user_id()
                ));
            }

            $results['bank_payout_matches']++;
        } else {
            $results['debug'][] = sprintf(
                "Bank #%d ($%.2f): Found %d charges ($%.2f) but difference too large: $%.2f",
                $bank->id,
                $bank_amount,
                count($stripe_ids),
                $stripe_total,
                $difference
            );
        }

        $previous_bank_date = $bank_date;
    }

    // PATTERN 3: Identify non-Stripe bank deposits
    $results['debug'][] = "\nPATTERN 3: Identifying non-Stripe bank deposits...";
    $non_stripe = $wpdb->get_results("
        SELECT id, post_date, description, credit
        FROM $bank_table
        WHERE credit > 0
        AND description NOT LIKE '%STRIPE%'
        ORDER BY post_date DESC
        LIMIT 20
    ");

    $results['bank_non_stripe'] = count($non_stripe);
    foreach ($non_stripe as $ns) {
        $results['details'][] = sprintf(
            "ℹ Bank #%d ($%.2f on %s): Non-Stripe deposit - %s",
            $ns->id,
            floatval($ns->credit),
            $ns->post_date,
            substr($ns->description, 0, 50)
        );
    }

    // PATTERN 4: Gravity Forms → Stripe matching
    // (Will implement after checking why GF query returns 0 results)
    $results['debug'][] = "\nPATTERN 4: Gravity Forms matching...";
    $gf_count = $wpdb->get_var("SELECT COUNT(*) FROM $gf_table WHERE transaction_type = 'payment'");
    $results['debug'][] = "Gravity Forms payments in DB: $gf_count";

    if ($gf_count > 0) {
        // Try matching GF to Stripe
        $gf_txns = $wpdb->get_results("
            SELECT id, transaction_id, date_created, amount
            FROM $gf_table
            WHERE transaction_type = 'payment'
            LIMIT 20
        ");

        foreach ($gf_txns as $gf) {
            // Try to match by transaction_id (might be Stripe charge ID)
            if (!empty($gf->transaction_id)) {
                $stripe_match = $wpdb->get_row($wpdb->prepare("
                    SELECT id, stripe_charge_id, amount
                    FROM $stripe_table
                    WHERE stripe_charge_id = %s
                    LIMIT 1
                ", $gf->transaction_id));

                if ($stripe_match) {
                    $wpdb->insert($matches_table, array(
                        'stripe_transaction_id' => $stripe_match->id,
                        'gravity_form_transaction_id' => $gf->id,
                        'match_type' => 'gf_stripe_txn_id',
                        'match_confidence' => 'auto_high',
                        'notes' => 'Matched by Stripe charge ID: ' . $gf->transaction_id,
                        'matched_by' => get_current_user_id()
                    ));

                    $results['gf_stripe_matches']++;
                    $results['details'][] = sprintf(
                        "✓ GF #%d matched to Stripe #%d by txn ID: %s",
                        $gf->id,
                        $stripe_match->id,
                        $gf->transaction_id
                    );
                }
            }
        }
    }

    return $results;
}
?>
