<?php
/**
 * Grouped Transaction View - Shows all transactions with matches visually grouped
 */

if (!defined('ABSPATH')) exit;

function five01c3po_grouped_transactions_page() {
    global $wpdb;

    $stripe_table = $wpdb->prefix . 'c3_stripe_transactions';
    $gf_table = 'swca_gf_addon_payment_transaction';
    $bank_table = 'swca_c3_bank_transactions';
    $matches_table = $wpdb->prefix . 'c3_transaction_matches';

    ?>
    <div class="wrap">
        <h1>📊 Grouped Transaction View</h1>
        <p>All transactions from all sources, with matches visually grouped together.</p>

        <style>
            .txn-group {
                margin: 20px 0;
                border: 2px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
            }
            .txn-group.matched {
                border-color: #00a32a;
                background: #f0f9f4;
            }
            .txn-group.partial-match {
                border-color: #dba617;
                background: #fcf9e8;
            }
            .txn-group.unmatched {
                border-color: #d63638;
                background: #fcf0f1;
            }
            .txn-row {
                display: grid;
                grid-template-columns: 100px 150px 120px 200px 120px 1fr 80px;
                padding: 12px;
                border-bottom: 1px solid #e0e0e0;
                align-items: center;
            }
            .txn-row:last-child {
                border-bottom: none;
            }
            .txn-row.stripe-row {
                background: rgba(99, 91, 255, 0.08);
            }
            .txn-row.gf-row {
                background: rgba(255, 120, 0, 0.08);
            }
            .txn-row.bank-row {
                background: rgba(0, 163, 42, 0.08);
            }
            .source-badge {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: bold;
                color: white;
                text-align: center;
            }
            .badge-stripe { background: #635bff; }
            .badge-gf { background: #ff7800; }
            .badge-bank { background: #00a32a; }
            .match-indicator {
                font-size: 12px;
                color: #666;
            }
            .match-indicator.high { color: #00a32a; }
            .match-indicator.medium { color: #dba617; }
            .amount-column {
                text-align: right;
                font-weight: bold;
            }
            .date-column {
                font-family: monospace;
                font-size: 12px;
            }
            .fee-column {
                text-align: right;
                font-size: 12px;
                color: #d63638;
            }
            .txn-header {
                background: #f6f7f7;
                font-weight: bold;
                font-size: 11px;
                text-transform: uppercase;
                color: #666;
            }
            .group-summary {
                padding: 8px 12px;
                background: #fff;
                border-top: 2px solid #ddd;
                font-size: 12px;
                font-weight: bold;
            }
            .filters {
                background: #fff;
                padding: 15px;
                margin-bottom: 20px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .filters label {
                margin-right: 15px;
            }
        </style>

        <div class="filters">
            <form method="get">
                <input type="hidden" name="page" value="five01c3po-grouped-transactions">
                <label>
                    <input type="checkbox" name="show_matched" value="1" <?php checked(isset($_GET['show_matched']) || !isset($_GET['show_unmatched'])); ?>>
                    Show Matched
                </label>
                <label>
                    <input type="checkbox" name="show_unmatched" value="1" <?php checked(isset($_GET['show_unmatched'])); ?>>
                    Show Unmatched
                </label>
                <button type="submit" class="button">Apply Filter</button>
            </form>
        </div>

        <?php

        // Get all matches with their related transactions
        $matches = $wpdb->get_results("
            SELECT
                m.*,
                s.stripe_charge_id, s.stripe_created, s.amount as stripe_amount, s.net_amount as stripe_net,
                s.stripe_fee, s.customer_email, s.description as stripe_desc, s.status as stripe_status,
                s.payout_arrival_date, s.payout_status,
                g.transaction_id as gf_txn_id, g.date_created as gf_date, g.amount as gf_amount,
                g.lead_id, g.transaction_type as gf_type,
                b.post_date as bank_date, b.credit as bank_amount, b.description as bank_desc,
                b.debit as bank_debit
            FROM $matches_table m
            LEFT JOIN $stripe_table s ON m.stripe_transaction_id = s.id
            LEFT JOIN $gf_table g ON m.gravity_form_transaction_id = g.id
            LEFT JOIN $bank_table b ON m.bank_transaction_id = b.id
            ORDER BY
                COALESCE(s.stripe_created, g.date_created, b.post_date) DESC,
                m.id ASC
        ");

        $show_matched = isset($_GET['show_matched']) || !isset($_GET['show_unmatched']);
        $show_unmatched = isset($_GET['show_unmatched']);

        if ($show_matched && count($matches) > 0) {
            echo '<h2>✅ Matched Transactions (' . count($matches) . ' groups)</h2>';

            foreach ($matches as $match) {
                $match_class = ($match->match_confidence === 'auto_high') ? 'matched' : 'partial-match';
                $rows_html = '';
                $total_amount = 0;
                $total_fees = 0;
                $row_count = 0;

                // Stripe row
                if ($match->stripe_transaction_id) {
                    $time_diff = '';
                    if ($match->gf_date && $match->stripe_created) {
                        $diff = abs(strtotime($match->gf_date) - strtotime($match->stripe_created));
                        $time_diff = $diff . 's';
                    }

                    // Show payout info
                    $payout_info = '';
                    if ($match->payout_arrival_date) {
                        $payout_info = 'Payout: ' . $match->payout_arrival_date;
                    }

                    $rows_html .= sprintf(
                        '<div class="txn-row stripe-row">
                            <div><span class="source-badge badge-stripe">STRIPE</span></div>
                            <div class="date-column">%s<br><small style="color: #666;">%s</small></div>
                            <div class="amount-column">$%.2f</div>
                            <div>%s</div>
                            <div class="amount-column">$%.2f</div>
                            <div class="fee-column">-$%.2f</div>
                            <div>%s</div>
                        </div>',
                        date('Y-m-d H:i:s', strtotime($match->stripe_created)),
                        $payout_info,
                        floatval($match->stripe_amount),
                        substr($match->customer_email ?: 'N/A', 0, 30),
                        floatval($match->stripe_net),
                        floatval($match->stripe_fee),
                        $time_diff
                    );
                    $total_amount = floatval($match->stripe_amount);
                    $total_fees += floatval($match->stripe_fee);
                    $row_count++;
                }

                // Gravity Forms row
                if ($match->gravity_form_transaction_id) {
                    $rows_html .= sprintf(
                        '<div class="txn-row gf-row">
                            <div><span class="source-badge badge-gf">GRAVITY</span></div>
                            <div class="date-column">%s</div>
                            <div class="amount-column">$%.2f</div>
                            <div>Lead #%s</div>
                            <div colspan="3">%s</div>
                        </div>',
                        date('Y-m-d H:i:s', strtotime($match->gf_date)),
                        floatval($match->gf_amount),
                        $match->lead_id,
                        $match->gf_type
                    );
                    $row_count++;
                }

                // Bank row
                if ($match->bank_transaction_id) {
                    $bank_delay = '';
                    if ($match->stripe_created && $match->bank_date) {
                        $diff_days = round((strtotime($match->bank_date) - strtotime($match->stripe_created)) / 86400, 1);
                        $bank_delay = '+' . $diff_days . ' days';
                    }

                    $rows_html .= sprintf(
                        '<div class="txn-row bank-row">
                            <div><span class="source-badge badge-bank">BANK</span></div>
                            <div class="date-column">%s</div>
                            <div class="amount-column">$%.2f</div>
                            <div>%s</div>
                            <div colspan="3">%s</div>
                        </div>',
                        date('Y-m-d', strtotime($match->bank_date)),
                        floatval($match->bank_amount),
                        substr($match->bank_desc, 0, 40),
                        $bank_delay
                    );
                    $row_count++;
                }

                // Match summary row
                $confidence_icon = ($match->match_confidence === 'auto_high') ? '✓ High' : '⚠ Medium';
                $summary = sprintf(
                    '<div class="group-summary">
                        <span class="match-indicator %s">%s Confidence</span> |
                        %s | %d source%s | %s
                    </div>',
                    ($match->match_confidence === 'auto_high') ? 'high' : 'medium',
                    $confidence_icon,
                    $match->match_type,
                    $row_count,
                    ($row_count > 1) ? 's' : '',
                    $match->notes ?: ''
                );

                // Output the complete group
                echo '<div class="txn-group ' . $match_class . '">';
                echo '<div class="txn-row txn-header">
                    <div>Source</div>
                    <div>Date/Time</div>
                    <div>Amount</div>
                    <div>Details</div>
                    <div>Net</div>
                    <div>Fee</div>
                    <div>Timing</div>
                </div>';
                echo $rows_html;
                echo $summary;
                echo '</div>';
            }
        }

        if ($show_unmatched) {
            // Show unmatched Stripe transactions
            $unmatched_stripe = $wpdb->get_results("
                SELECT * FROM $stripe_table
                WHERE id NOT IN (SELECT stripe_transaction_id FROM $matches_table WHERE stripe_transaction_id IS NOT NULL)
                ORDER BY stripe_created DESC
                LIMIT 50
            ");

            if (count($unmatched_stripe) > 0) {
                echo '<h2>❌ Unmatched Stripe Transactions (' . count($unmatched_stripe) . ')</h2>';

                foreach ($unmatched_stripe as $txn) {
                    echo '<div class="txn-group unmatched">';
                    echo '<div class="txn-row txn-header">
                        <div>Source</div>
                        <div>Date/Time</div>
                        <div>Amount</div>
                        <div>Details</div>
                        <div>Net</div>
                        <div>Fee</div>
                        <div>Status</div>
                    </div>';
                    $payout_info = $txn->payout_arrival_date ? '<br><small>Payout: ' . $txn->payout_arrival_date . '</small>' : '';

                    echo sprintf(
                        '<div class="txn-row stripe-row">
                            <div><span class="source-badge badge-stripe">STRIPE</span></div>
                            <div class="date-column">%s%s</div>
                            <div class="amount-column">$%.2f</div>
                            <div>%s</div>
                            <div class="amount-column">$%.2f</div>
                            <div class="fee-column">-$%.2f</div>
                            <div>%s</div>
                        </div>',
                        date('Y-m-d H:i:s', strtotime($txn->stripe_created)),
                        $payout_info,
                        floatval($txn->amount),
                        substr($txn->customer_email ?: 'N/A', 0, 30),
                        floatval($txn->net_amount),
                        floatval($txn->stripe_fee),
                        $txn->status
                    );
                    echo '<div class="group-summary">No matching Gravity Forms or Bank record</div>';
                    echo '</div>';
                }
            }

            // Show unmatched Bank transactions
            $unmatched_bank = $wpdb->get_results("
                SELECT * FROM $bank_table
                WHERE credit > 0
                AND id NOT IN (SELECT bank_transaction_id FROM $matches_table WHERE bank_transaction_id IS NOT NULL)
                ORDER BY post_date DESC
                LIMIT 50
            ");

            if (count($unmatched_bank) > 0) {
                echo '<h2>❌ Unmatched Bank Deposits (' . count($unmatched_bank) . ')</h2>';

                foreach ($unmatched_bank as $txn) {
                    echo '<div class="txn-group unmatched">';
                    echo '<div class="txn-row txn-header">
                        <div>Source</div>
                        <div>Date</div>
                        <div>Amount</div>
                        <div colspan="4">Description</div>
                    </div>';
                    echo sprintf(
                        '<div class="txn-row bank-row">
                            <div><span class="source-badge badge-bank">BANK</span></div>
                            <div class="date-column">%s</div>
                            <div class="amount-column">$%.2f</div>
                            <div colspan="4">%s</div>
                        </div>',
                        date('Y-m-d', strtotime($txn->post_date)),
                        floatval($txn->credit),
                        $txn->description
                    );
                    echo '<div class="group-summary">No matching Stripe transaction found - likely batch payout needing manual review</div>';
                    echo '</div>';
                }
            }
        }

        ?>
    </div>
    <?php
}

// Add menu item
add_action('admin_menu', function() {
    add_submenu_page(
        'five01c3po-settings',
        'Grouped Transactions',
        'Grouped Transactions',
        'manage_options',
        'five01c3po-grouped-transactions',
        'five01c3po_grouped_transactions_page'
    );
}, 25);
