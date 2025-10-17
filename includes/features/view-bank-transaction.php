<?php
/**
 * View Bank Transaction Details
 * Shows complete details for a single bank transaction
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Bank Transaction Viewer menu (hidden)
 */
add_action('admin_menu', 'five01c3po_add_bank_viewer_menu', 99);

function five01c3po_add_bank_viewer_menu() {
    // Hidden menu - only accessible via direct link
    add_submenu_page(
        null, // No parent menu = hidden
        'View Bank Transaction',
        'View Bank Transaction',
        'manage_options',
        '501c3PO-view-bank-transaction',
        'five01c3po_view_bank_transaction_page'
    );
}

/**
 * View Bank Transaction page
 */
function five01c3po_view_bank_transaction_page() {
    global $wpdb;

    $bank_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$bank_id) {
        echo '<div class="wrap"><h1>Error</h1><p>No transaction ID provided.</p></div>';
        return;
    }

    $bank_table = $wpdb->prefix . 'c3_bank_transactions';
    $matches_table = $wpdb->prefix . 'c3_transaction_matches';
    $stripe_table = $wpdb->prefix . 'c3_stripe_transactions';

    $txn = $wpdb->get_row($wpdb->prepare("
        SELECT b.*
        FROM $bank_table b
        WHERE b.id = %d
    ", $bank_id));

    if (!$txn) {
        echo '<div class="wrap"><h1>Error</h1><p>Bank transaction not found.</p></div>';
        return;
    }

    // Get all matched Stripe transactions
    $stripe_matches = $wpdb->get_results($wpdb->prepare("
        SELECT
            s.id,
            s.stripe_charge_id,
            s.customer_email,
            s.amount,
            s.stripe_fee,
            s.amount_refunded,
            (s.amount - s.stripe_fee - s.amount_refunded) as net_amount,
            s.stripe_created,
            m.match_type,
            m.match_confidence
        FROM $matches_table m
        JOIN $stripe_table s ON s.id = m.stripe_transaction_id
        WHERE m.bank_transaction_id = %d
        ORDER BY s.stripe_created DESC
    ", $bank_id));

    ?>
    <div class="wrap">
        <h1>🏦 Bank Transaction Details</h1>
        <p class="description">
            <a href="<?php echo admin_url('admin.php?page=501c3PO-transaction-ledger'); ?>">&larr; Back to Transaction Ledger</a>
        </p>

        <div class="card" style="max-width: 900px;">
            <h2>Bank Transaction Information</h2>
            <table class="form-table">
                <tr>
                    <th scope="row" style="width: 250px;">Database ID</th>
                    <td><strong>#<?php echo esc_html($txn->id); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Account Number</th>
                    <td><code><?php echo esc_html($txn->account_number); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Post Date</th>
                    <td><strong><?php echo date('F j, Y', strtotime($txn->post_date)); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Description</th>
                    <td><?php echo esc_html($txn->description); ?></td>
                </tr>
                <tr>
                    <th scope="row">Check Number</th>
                    <td><?php echo esc_html($txn->check_number ?: 'N/A'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td><?php echo esc_html($txn->status); ?></td>
                </tr>
            </table>
        </div>

        <div class="card" style="max-width: 900px; margin-top: 20px;">
            <h2>Financial Details</h2>
            <table class="form-table">
                <tr>
                    <th scope="row" style="width: 250px;">Credit (Money In)</th>
                    <td style="color: #00a32a; font-size: 18px;">
                        <?php if ($txn->credit > 0): ?>
                            <strong>+$<?php echo number_format($txn->credit, 2); ?></strong>
                        <?php else: ?>
                            <span style="color: #999;">$0.00</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Debit (Money Out)</th>
                    <td style="color: #dc3545; font-size: 18px;">
                        <?php if ($txn->debit > 0): ?>
                            <strong>-$<?php echo number_format($txn->debit, 2); ?></strong>
                        <?php else: ?>
                            <span style="color: #999;">$0.00</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Account Balance After</th>
                    <td><strong>$<?php echo number_format($txn->balance, 2); ?></strong></td>
                </tr>
            </table>
        </div>

        <?php if (!empty($stripe_matches)): ?>
        <div class="card" style="max-width: 900px; margin-top: 20px;">
            <h2>Matched Stripe Transactions (<?php echo count($stripe_matches); ?>)</h2>
            <p>This bank deposit contains the following Stripe payouts:</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Stripe ID</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: right;">Fee</th>
                        <th style="text-align: right;">Net</th>
                        <th>Match Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_net = 0;
                    foreach ($stripe_matches as $stripe):
                        $total_net += floatval($stripe->net_amount);
                    ?>
                    <tr>
                        <td><code><?php echo esc_html(substr($stripe->stripe_charge_id, 0, 20)); ?>...</code></td>
                        <td><?php echo date('M j, Y', strtotime($stripe->stripe_created)); ?></td>
                        <td><?php echo esc_html(substr($stripe->customer_email, 0, 30)); ?></td>
                        <td style="text-align: right;">$<?php echo number_format($stripe->amount, 2); ?></td>
                        <td style="text-align: right; color: #dc3545;">-$<?php echo number_format($stripe->stripe_fee, 2); ?></td>
                        <td style="text-align: right;"><strong>$<?php echo number_format($stripe->net_amount, 2); ?></strong></td>
                        <td>
                            <span style="background: <?php echo $stripe->match_confidence === 'auto_high' ? '#00a32a' : '#dba617'; ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                <?php echo esc_html(str_replace('_', ' ', $stripe->match_type)); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=501c3PO-view-stripe-transaction&id=' . $stripe->id); ?>" class="button button-small">View Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f0f0f0; font-weight: bold;">
                        <td colspan="5" style="text-align: right;">Total Net from Stripe:</td>
                        <td style="text-align: right;"><strong>$<?php echo number_format($total_net, 2); ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr style="background: #fff3cd;">
                        <td colspan="5" style="text-align: right;"><strong>Bank Deposit Amount:</strong></td>
                        <td style="text-align: right;"><strong>$<?php echo number_format($txn->credit, 2); ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr style="background: <?php echo abs($total_net - $txn->credit) < 0.01 ? '#d4edda' : '#f8d7da'; ?>;">
                        <td colspan="5" style="text-align: right;"><strong>Difference:</strong></td>
                        <td style="text-align: right;">
                            <strong style="color: <?php echo abs($total_net - $txn->credit) < 0.01 ? '#00a32a' : '#dc3545'; ?>;">
                                $<?php echo number_format(abs($total_net - $txn->credit), 2); ?>
                                <?php if (abs($total_net - $txn->credit) < 0.01): ?>
                                    ✓
                                <?php endif; ?>
                            </strong>
                        </td>
                        <td colspan="2">
                            <?php if (abs($total_net - $txn->credit) < 0.01): ?>
                                <span style="color: #00a32a;">✓ Matches perfectly</span>
                            <?php else: ?>
                                <span style="color: #dc3545;">⚠ Amounts don't match</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="notice notice-warning" style="max-width: 900px; margin-top: 20px;">
            <p><strong>⚠️ No Stripe Matches</strong><br>This bank transaction has not been matched to any Stripe payouts.</p>
            <?php if (stripos($txn->description, 'STRIPE') === false): ?>
                <p><em>Note: This transaction does not contain "STRIPE" in the description - it may be a cash/check deposit or other non-Stripe income.</em></p>
            <?php endif; ?>
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
