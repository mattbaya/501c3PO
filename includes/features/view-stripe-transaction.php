<?php
/**
 * View Stripe Transaction Details
 * Shows complete details for a single Stripe transaction
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Stripe Transaction Viewer menu (hidden)
 */
add_action('admin_menu', 'five01c3po_add_stripe_viewer_menu', 99);

function five01c3po_add_stripe_viewer_menu() {
    // Hidden menu - only accessible via direct link
    add_submenu_page(
        null, // No parent menu = hidden
        'View Stripe Transaction',
        'View Stripe Transaction',
        'manage_options',
        '501c3PO-view-stripe-transaction',
        'five01c3po_view_stripe_transaction_page'
    );
}

/**
 * View Stripe Transaction page
 */
function five01c3po_view_stripe_transaction_page() {
    global $wpdb;

    $stripe_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$stripe_id) {
        echo '<div class="wrap"><h1>Error</h1><p>No transaction ID provided.</p></div>';
        return;
    }

    $stripe_table = 'swca_stripe_transactions';
    $matches_table = 'swca_transaction_matches';
    $gf_table = 'swca_gf_addon_payment_transaction';
    $bank_table = 'swca_c3_bank_transactions';

    $txn = $wpdb->get_row($wpdb->prepare("
        SELECT
            s.*,
            gf.id as gf_id,
            gf.date_created as gf_date,
            gf.amount as gf_amount,
            gf.lead_id,
            b.id as bank_id,
            b.post_date as bank_date,
            b.credit as bank_amount,
            b.description as bank_desc
        FROM $stripe_table s
        LEFT JOIN $matches_table m_gf ON m_gf.stripe_transaction_id = s.id AND m_gf.match_type = 'gravity_stripe'
        LEFT JOIN $gf_table gf ON gf.id = m_gf.gravity_form_transaction_id
        LEFT JOIN $matches_table m_bank ON m_bank.stripe_transaction_id = s.id
            AND m_bank.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
        LEFT JOIN $bank_table b ON b.id = m_bank.bank_transaction_id
        WHERE s.id = %d
    ", $stripe_id));

    if (!$txn) {
        echo '<div class="wrap"><h1>Error</h1><p>Transaction not found.</p></div>';
        return;
    }

    $net_amount = floatval($txn->amount) - floatval($txn->stripe_fee) - floatval($txn->amount_refunded);

    ?>
    <div class="wrap">
        <h1>💳 Stripe Transaction Details</h1>
        <p class="description">
            <a href="<?php echo admin_url('admin.php?page=501c3PO-transaction-ledger'); ?>">&larr; Back to Transaction Ledger</a>
        </p>

        <div class="card" style="max-width: 900px;">
            <h2>Transaction Information</h2>
            <table class="form-table">
                <tr>
                    <th scope="row" style="width: 250px;">Database ID</th>
                    <td><strong>#<?php echo esc_html($txn->id); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Stripe Charge ID</th>
                    <td>
                        <code><?php echo esc_html($txn->stripe_charge_id); ?></code>
                        <a href="https://dashboard.stripe.com/payments/<?php echo esc_attr($txn->stripe_charge_id); ?>" target="_blank" class="button button-small">View in Stripe Dashboard →</a>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Customer Name</th>
                    <td><?php echo esc_html($txn->customer_name ?: 'N/A'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Customer Email</th>
                    <td><?php echo esc_html($txn->customer_email ?: 'N/A'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Description</th>
                    <td><?php echo esc_html($txn->description ?: 'N/A'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Transaction Date</th>
                    <td><strong><?php echo date('F j, Y \a\t g:i a', strtotime($txn->stripe_created)); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td><span class="button button-small" style="background: <?php echo $txn->status === 'succeeded' ? '#00a32a' : '#dc3545'; ?>; color: white;"><?php echo esc_html(ucfirst($txn->status)); ?></span></td>
                </tr>
            </table>
        </div>

        <div class="card" style="max-width: 900px; margin-top: 20px;">
            <h2>Financial Details</h2>
            <table class="form-table">
                <tr>
                    <th scope="row" style="width: 250px;">Gross Amount</th>
                    <td><strong style="font-size: 18px;">$<?php echo number_format($txn->amount, 2); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Stripe Processing Fee</th>
                    <td style="color: #dc3545;">-$<?php echo number_format($txn->stripe_fee, 2); ?></td>
                </tr>
                <tr>
                    <th scope="row">Amount Refunded</th>
                    <td style="color: <?php echo $txn->amount_refunded > 0 ? '#dc3545' : '#999'; ?>;">
                        <?php if ($txn->amount_refunded > 0): ?>
                            <strong>-$<?php echo number_format($txn->amount_refunded, 2); ?></strong>
                            <a href="https://dashboard.stripe.com/charges/<?php echo esc_attr($txn->stripe_charge_id); ?>"
                               target="_blank"
                               class="button button-small"
                               style="margin-left: 10px;">
                                View Refund Details →
                            </a>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                Click to view refund date, time, and transaction IDs in Stripe Dashboard
                            </p>
                        <?php else: ?>
                            $0.00
                        <?php endif; ?>
                    </td>
                </tr>
                <tr style="border-top: 2px solid #000;">
                    <th scope="row"><strong>Net Amount</strong></th>
                    <td><strong style="font-size: 18px; color: <?php echo $net_amount >= 0 ? '#00a32a' : '#dc3545'; ?>;">$<?php echo number_format($net_amount, 2); ?></strong></td>
                </tr>
            </table>

            <p style="background: #f0f0f0; padding: 10px; border-radius: 4px; margin-top: 15px;">
                <strong>Calculation:</strong> <code>$<?php echo number_format($txn->amount, 2); ?> - $<?php echo number_format($txn->stripe_fee, 2); ?> - $<?php echo number_format($txn->amount_refunded, 2); ?> = $<?php echo number_format($net_amount, 2); ?></code>
            </p>
        </div>

        <div class="card" style="max-width: 900px; margin-top: 20px;">
            <h2>Payout Information</h2>
            <table class="form-table">
                <tr>
                    <th scope="row" style="width: 250px;">Payout ID</th>
                    <td>
                        <?php if ($txn->payout_id): ?>
                            <code><?php echo esc_html($txn->payout_id); ?></code>
                            <a href="https://dashboard.stripe.com/payouts/<?php echo esc_attr($txn->payout_id); ?>" target="_blank" class="button button-small">View Payout in Stripe →</a>
                        <?php else: ?>
                            <span style="color: #999;">Not yet paid out</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Payout Date</th>
                    <td><?php echo $txn->payout_date ? date('F j, Y', strtotime($txn->payout_date)) : '<span style="color: #999;">N/A</span>'; ?></td>
                </tr>
                <tr>
                    <th scope="row">Payout Arrival Date</th>
                    <td>
                        <?php if ($txn->payout_arrival_date): ?>
                            <strong><?php echo date('F j, Y', strtotime($txn->payout_arrival_date)); ?></strong>
                            (<?php echo round((strtotime($txn->payout_arrival_date) - strtotime($txn->stripe_created)) / 86400); ?> days after charge)
                        <?php else: ?>
                            <span style="color: #999;">N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Payout Status</th>
                    <td><?php echo esc_html($txn->payout_status ?: 'N/A'); ?></td>
                </tr>
            </table>
        </div>

        <?php if ($txn->bank_id): ?>
        <div class="card" style="max-width: 900px; margin-top: 20px;">
            <h2>Bank Deposit Match</h2>
            <table class="form-table">
                <tr>
                    <th scope="row" style="width: 250px;">Bank Transaction ID</th>
                    <td>
                        <strong>#<?php echo esc_html($txn->bank_id); ?></strong>
                        <a href="<?php echo admin_url('admin.php?page=501c3PO-view-bank-transaction&id=' . $txn->bank_id); ?>" class="button button-small">View Bank Transaction →</a>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Bank Deposit Date</th>
                    <td>
                        <strong><?php echo date('F j, Y', strtotime($txn->bank_date)); ?></strong>
                        <?php if ($txn->payout_arrival_date): ?>
                            (<?php echo abs(round((strtotime($txn->bank_date) - strtotime($txn->payout_arrival_date)) / 86400)); ?> days after payout)
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Bank Deposit Amount</th>
                    <td><strong>$<?php echo number_format($txn->bank_amount, 2); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Bank Description</th>
                    <td><?php echo esc_html($txn->bank_desc); ?></td>
                </tr>
            </table>
        </div>
        <?php else: ?>
        <div class="notice notice-warning" style="max-width: 900px; margin-top: 20px;">
            <p><strong>⚠️ No Bank Match</strong><br>This transaction has not been matched to a bank deposit yet.</p>
        </div>
        <?php endif; ?>

        <?php if ($txn->gf_id): ?>
        <div class="card" style="max-width: 900px; margin-top: 20px;">
            <h2>Gravity Forms Match</h2>
            <table class="form-table">
                <tr>
                    <th scope="row" style="width: 250px;">Gravity Forms Transaction ID</th>
                    <td><strong>#<?php echo esc_html($txn->gf_id); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Lead ID</th>
                    <td><?php echo esc_html($txn->lead_id); ?></td>
                </tr>
                <tr>
                    <th scope="row">GF Transaction Date</th>
                    <td><?php echo date('F j, Y \a\t g:i a', strtotime($txn->gf_date)); ?></td>
                </tr>
                <tr>
                    <th scope="row">GF Amount</th>
                    <td>$<?php echo number_format($txn->gf_amount, 2); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <div style="max-width: 900px; margin-top: 20px;">
            <a href="<?php echo admin_url('admin.php?page=501c3PO-transaction-ledger'); ?>" class="button">&larr; Back to Transaction Ledger</a>
        </div>
    </div>

    <style>
        .form-table th {
            padding-left: 0;
        }
        .card {
            padding: 20px;
        }
    </style>
    <?php
}
