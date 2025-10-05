<?php
/**
 * Unified Transactions View
 * Combines Stripe, Bank, and Gravity Forms transaction data
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Unified Transactions menu
 */
add_action('admin_menu', 'five01c3po_add_unified_transactions_menu', 23);

function five01c3po_add_unified_transactions_menu() {
    add_submenu_page(
        'membership-management',
        'All Transactions',
        '📊 All Transactions',
        'manage_options',
        '501c3PO-unified-transactions',
        'five01c3po_unified_transactions_page'
    );
}

/**
 * Unified Transactions admin page
 */
function five01c3po_unified_transactions_page() {
    global $wpdb;

    // Get counts from each source
    $stripe_table = $wpdb->prefix . 'stripe_transactions';
    $bank_table = $wpdb->prefix . 'swca_bank_transactions';
    $gf_table = 'swca_gf_addon_payment_transaction';

    $stripe_count = $wpdb->get_var("SELECT COUNT(*) FROM $stripe_table");
    $bank_count = $wpdb->get_var("SELECT COUNT(*) FROM $bank_table");
    $gf_count = $wpdb->get_var("SELECT COUNT(*) FROM $gf_table WHERE transaction_type = 'payment'");

    ?>
    <div class="wrap">
        <h1>📊 All Transactions - Unified View</h1>

        <div class="card">
            <h2>Data Sources</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Records</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>💳 Stripe (API)</strong></td>
                        <td><?php echo number_format($stripe_count ?? 0); ?></td>
                        <td>Direct Stripe API synced transactions</td>
                    </tr>
                    <tr>
                        <td><strong>🏦 Bank Transactions</strong></td>
                        <td><?php echo number_format($bank_count ?? 0); ?></td>
                        <td>Imported bank CSV files</td>
                    </tr>
                    <tr>
                        <td><strong>📝 Gravity Forms Stripe</strong></td>
                        <td><?php echo number_format($gf_count ?? 0); ?></td>
                        <td>Historical Stripe payments via Gravity Forms</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>🔍 View Unified Transactions</h2>
            <p>Display all transactions from all sources on a public or private page using this shortcode:</p>
            <div style="background: #f0f0f0; padding: 15px; border-radius: 4px; font-family: monospace; margin: 15px 0;">
                [five01c3po_unified_transactions]
            </div>

            <h3>Shortcode Options</h3>
            <ul style="margin-left: 20px;">
                <li><code>[five01c3po_unified_transactions limit="100"]</code> - Limit number of transactions</li>
                <li><code>[five01c3po_unified_transactions source="stripe"]</code> - Show only Stripe</li>
                <li><code>[five01c3po_unified_transactions source="bank"]</code> - Show only bank transactions</li>
                <li><code>[five01c3po_unified_transactions source="gravity"]</code> - Show only Gravity Forms</li>
                <li><code>[five01c3po_unified_transactions start_date="2024-01-01"]</code> - Filter by date range</li>
                <li><code>[five01c3po_unified_transactions end_date="2024-12-31"]</code> - End date for range</li>
            </ul>

            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="button button-primary">
                    Create New Page with Shortcode
                </a>
            </p>
        </div>

        <div class="card">
            <h2>🔍 Preview (Last 20 transactions)</h2>
            <?php echo do_shortcode('[five01c3po_unified_transactions limit="20"]'); ?>
        </div>
    </div>
    <?php
}

/**
 * Unified Transactions Shortcode
 */
add_shortcode('five01c3po_unified_transactions', 'five01c3po_unified_transactions_shortcode');

function five01c3po_unified_transactions_shortcode($atts) {
    global $wpdb;

    // Parse attributes
    $atts = shortcode_atts(array(
        'limit' => 100,
        'source' => 'all', // all, stripe, bank, gravity
        'start_date' => '',
        'end_date' => '',
    ), $atts);

    $limit = intval($atts['limit']);
    $source = $atts['source'];

    // Build unified query
    $transactions = array();

    // 1. Stripe API transactions
    if ($source === 'all' || $source === 'stripe') {
        $stripe_table = $wpdb->prefix . 'stripe_transactions';
        $where = "1=1";

        if (!empty($atts['start_date'])) {
            $where .= $wpdb->prepare(" AND stripe_created >= %s", $atts['start_date']);
        }
        if (!empty($atts['end_date'])) {
            $where .= $wpdb->prepare(" AND stripe_created <= %s", $atts['end_date'] . ' 23:59:59');
        }

        $stripe_txns = $wpdb->get_results("
            SELECT
                'stripe' as source,
                stripe_created as date,
                customer_email as email,
                description,
                amount,
                amount_refunded,
                net_amount,
                stripe_fee as fee,
                status,
                customer_name as name
            FROM $stripe_table
            WHERE $where
            ORDER BY stripe_created DESC
        ");

        $transactions = array_merge($transactions, $stripe_txns);
    }

    // 2. Bank transactions
    if ($source === 'all' || $source === 'bank') {
        $bank_table = $wpdb->prefix . 'swca_bank_transactions';
        $where = "1=1";

        if (!empty($atts['start_date'])) {
            $where .= $wpdb->prepare(" AND post_date >= %s", $atts['start_date']);
        }
        if (!empty($atts['end_date'])) {
            $where .= $wpdb->prepare(" AND post_date <= %s", $atts['end_date']);
        }

        $bank_txns = $wpdb->get_results("
            SELECT
                'bank' as source,
                post_date as date,
                '' as email,
                description,
                credit as amount,
                0 as amount_refunded,
                (credit - debit) as net_amount,
                0 as fee,
                status,
                '' as name
            FROM $bank_table
            WHERE $where
            ORDER BY post_date DESC
        ");

        $transactions = array_merge($transactions, $bank_txns);
    }

    // 3. Gravity Forms Stripe transactions
    if ($source === 'all' || $source === 'gravity') {
        $gf_table = 'swca_gf_addon_payment_transaction';
        $where = "transaction_type = 'payment'";

        if (!empty($atts['start_date'])) {
            $where .= $wpdb->prepare(" AND date_created >= %s", $atts['start_date']);
        }
        if (!empty($atts['end_date'])) {
            $where .= $wpdb->prepare(" AND date_created <= %s", $atts['end_date'] . ' 23:59:59');
        }

        $gf_txns = $wpdb->get_results("
            SELECT
                'gravity' as source,
                date_created as date,
                '' as email,
                note as description,
                amount,
                0 as amount_refunded,
                amount as net_amount,
                0 as fee,
                'completed' as status,
                '' as name
            FROM $gf_table
            WHERE $where
            ORDER BY date_created DESC
        ");

        $transactions = array_merge($transactions, $gf_txns);
    }

    // Sort all transactions by date
    usort($transactions, function($a, $b) {
        return strtotime($b->date) - strtotime($a->date);
    });

    // Apply limit
    $transactions = array_slice($transactions, 0, $limit);

    if (empty($transactions)) {
        return '<p>No transactions found.</p>';
    }

    // Calculate totals
    $total_amount = 0;
    $total_net = 0;
    $total_fees = 0;
    foreach ($transactions as $txn) {
        $total_amount += floatval($txn->amount);
        $total_net += floatval($txn->net_amount);
        $total_fees += floatval($txn->fee);
    }

    // Build HTML output
    ob_start();
    ?>
    <div class="five01c3po-unified-transactions">
        <div class="transaction-summary" style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3 style="margin-top: 0;">Summary</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <strong>Total Transactions:</strong> <?php echo count($transactions); ?>
                </div>
                <div>
                    <strong>Total Amount:</strong> $<?php echo number_format($total_amount, 2); ?>
                </div>
                <div style="color: #d63638;">
                    <strong>Total Fees:</strong> $<?php echo number_format($total_fees, 2); ?>
                </div>
                <div style="color: #00a32a;">
                    <strong>Net Total:</strong> $<?php echo number_format($total_net, 2); ?>
                </div>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Email/Name</th>
                    <th style="text-align: right;">Amount</th>
                    <th style="text-align: right;">Refund</th>
                    <th style="text-align: right;">Fee</th>
                    <th style="text-align: right;">Net</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $txn):
                    $source_badge = '';
                    $source_color = '';
                    switch($txn->source) {
                        case 'stripe':
                            $source_badge = '💳 Stripe';
                            $source_color = '#635bff';
                            break;
                        case 'bank':
                            $source_badge = '🏦 Bank';
                            $source_color = '#007cba';
                            break;
                        case 'gravity':
                            $source_badge = '📝 GF Stripe';
                            $source_color = '#a34a9b';
                            break;
                    }
                ?>
                <tr>
                    <td><span style="background: <?php echo $source_color; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px;"><?php echo $source_badge; ?></span></td>
                    <td><?php echo esc_html(date('M d, Y', strtotime($txn->date))); ?></td>
                    <td><?php echo esc_html(substr($txn->description, 0, 50)); ?><?php echo strlen($txn->description) > 50 ? '...' : ''; ?></td>
                    <td><?php echo esc_html($txn->email ?: $txn->name); ?></td>
                    <td style="text-align: right; color: #00a32a;">
                        $<?php echo number_format($txn->amount, 2); ?>
                    </td>
                    <td style="text-align: right; color: #d63638;">
                        <?php echo $txn->amount_refunded > 0 ? '-$' . number_format($txn->amount_refunded, 2) : ''; ?>
                    </td>
                    <td style="text-align: right; color: #d63638;">
                        <?php echo $txn->fee > 0 ? '-$' . number_format($txn->fee, 2) : ''; ?>
                    </td>
                    <td style="text-align: right; font-weight: bold;">
                        $<?php echo number_format($txn->net_amount, 2); ?>
                    </td>
                    <td><?php echo esc_html($txn->status); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($transactions) >= $limit): ?>
        <p style="margin-top: 15px; font-style: italic; color: #666;">
            Showing <?php echo $limit; ?> most recent transactions. Increase limit parameter to show more.
        </p>
        <?php endif; ?>
    </div>

    <style>
        .five01c3po-unified-transactions table {
            border-collapse: collapse;
            width: 100%;
        }
        .five01c3po-unified-transactions th,
        .five01c3po-unified-transactions td {
            padding: 10px;
            text-align: left;
        }
        @media (max-width: 768px) {
            .transaction-summary > div {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    <?php

    return ob_get_clean();
}
