<?php
/**
 * Transaction Ledger Feature
 * Complete money trail: Customer → Stripe → Bank
 * Shows every penny accounted for with fees, payouts, and bank deposits
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Transaction Ledger menu
 */
add_action('admin_menu', 'five01c3po_add_ledger_menu', 25);

/**
 * AJAX handler for updating bank transaction fields
 */
add_action('wp_ajax_update_bank_transaction_field', 'five01c3po_update_bank_transaction_field');

function five01c3po_update_bank_transaction_field() {
    check_ajax_referer('update_bank_transaction');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    global $wpdb;
    $bank_id = intval($_POST['bank_id']);
    $field = sanitize_text_field($_POST['field']);
    $value = sanitize_textarea_field($_POST['value']);

    $allowed_fields = array('notes', 'category', 'tags', 'recipient');
    if (!in_array($field, $allowed_fields)) {
        wp_send_json_error(array('message' => 'Invalid field'));
    }

    $result = $wpdb->update(
        'wp_swca_bank_transactions',
        array($field => $value),
        array('id' => $bank_id),
        array('%s'),
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success(array('message' => 'Updated successfully'));
    } else {
        wp_send_json_error(array('message' => 'Database update failed'));
    }
}

function five01c3po_add_ledger_menu() {
    add_submenu_page(
        'membership-management',
        'Transaction Ledger',
        '📒 Transaction Ledger',
        'manage_options',
        '501c3PO-transaction-ledger',
        'five01c3po_transaction_ledger_page'
    );
}

/**
 * Get complete ledger data
 */
function five01c3po_get_transaction_ledger($filters = array()) {
    global $wpdb;

    $stripe_table = 'swca_stripe_transactions';
    $gf_table = 'swca_gf_addon_payment_transaction';
    $bank_table = 'wp_swca_bank_transactions';
    $matches_table = 'swca_transaction_matches';

    // Build WHERE clause from filters (now based on bank transactions)
    $where_clauses = array("1=1");

    if (!empty($filters['date_from'])) {
        $where_clauses[] = $wpdb->prepare("b.post_date >= %s", $filters['date_from']);
    }
    if (!empty($filters['date_to'])) {
        $where_clauses[] = $wpdb->prepare("b.post_date <= %s", $filters['date_to']);
    }
    if (!empty($filters['min_amount'])) {
        $where_clauses[] = $wpdb->prepare("(b.credit >= %f OR b.debit >= %f)", $filters['min_amount'], $filters['min_amount']);
    }
    if (!empty($filters['search'])) {
        $search = '%' . $wpdb->esc_like($filters['search']) . '%';
        $where_clauses[] = $wpdb->prepare(
            "(b.description LIKE %s OR b.notes LIKE %s OR s.customer_email LIKE %s OR s.customer_name LIKE %s)",
            $search, $search, $search, $search
        );
    }
    if (!empty($filters['category'])) {
        $where_clauses[] = $wpdb->prepare("b.category = %s", $filters['category']);
    }

    $where_sql = implode(" AND ", $where_clauses);

    $query = "
        SELECT
            -- Bank Transaction Data (PRIMARY SOURCE)
            b.id as bank_id,
            b.post_date as transaction_date,
            b.description as bank_description,
            b.notes as bank_notes,
            b.category as bank_category,
            b.tags as bank_tags,
            b.check_number,
            b.recipient,
            b.debit as bank_debit,
            b.credit as bank_credit,
            b.status as bank_status,
            b.balance as bank_balance,

            -- Stripe Transaction Data (if matched)
            s.id as stripe_id,
            s.stripe_charge_id,
            s.stripe_created,
            s.amount as stripe_amount,
            s.stripe_fee,
            s.amount_refunded,
            (s.amount - s.stripe_fee - s.amount_refunded) as stripe_net_amount,
            s.status as stripe_status,
            s.description as stripe_description,
            s.customer_name,
            s.customer_email,

            -- Payout Information
            s.payout_id,
            s.payout_date,
            s.payout_arrival_date,
            s.payout_status,

            -- Gravity Forms Data (if matched)
            gf.id as gf_id,
            gf.date_created as gf_date,
            gf.amount as gf_amount,

            -- Match Information
            m_stripe.match_type,
            m_stripe.match_confidence,

            -- Transaction Type Indicator
            CASE
                WHEN s.id IS NOT NULL THEN 'stripe_matched'
                WHEN b.credit > 0 THEN 'bank_deposit'
                WHEN b.debit > 0 THEN 'bank_expense'
                ELSE 'unknown'
            END as transaction_type

        FROM $bank_table b

        -- Left join to Stripe matches
        LEFT JOIN (
            SELECT DISTINCT stripe_transaction_id, bank_transaction_id, match_type, match_confidence
            FROM $matches_table
            WHERE match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
            GROUP BY stripe_transaction_id, bank_transaction_id
        ) m_stripe ON m_stripe.bank_transaction_id = b.id
        LEFT JOIN $stripe_table s ON s.id = m_stripe.stripe_transaction_id

        -- Left join Gravity Forms (for amount verification)
        LEFT JOIN $matches_table m_gf ON m_gf.stripe_transaction_id = s.id AND m_gf.match_type = 'gravity_stripe'
        LEFT JOIN $gf_table gf ON gf.id = m_gf.gravity_form_transaction_id

        WHERE $where_sql

        ORDER BY b.post_date DESC, b.id DESC
    ";

    return $wpdb->get_results($query);
}

/**
 * Transaction Ledger page
 */
function five01c3po_transaction_ledger_page() {
    global $wpdb;

    // Handle filters
    $filters = array(
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'min_amount' => $_GET['min_amount'] ?? '',
        'search' => $_GET['search'] ?? '',
        'category' => $_GET['category'] ?? ''
    );

    // Handle AJAX updates for notes, category, tags
    if (isset($_POST['action']) && $_POST['action'] === 'update_bank_transaction') {
        check_admin_referer('update_bank_transaction');
        $bank_id = intval($_POST['bank_id']);
        $field = sanitize_text_field($_POST['field']);
        $value = sanitize_textarea_field($_POST['value']);

        $allowed_fields = array('notes', 'category', 'tags');
        if (in_array($field, $allowed_fields)) {
            $wpdb->update(
                'wp_swca_bank_transactions',
                array($field => $value),
                array('id' => $bank_id),
                array('%s'),
                array('%d')
            );
            wp_send_json_success(array('message' => 'Updated successfully'));
        }
        wp_send_json_error(array('message' => 'Invalid field'));
    }

    // Get latest bank statement
    $latest_statement = $wpdb->get_row("
        SELECT * FROM wp_swca_bank_statements
        ORDER BY statement_period_end DESC
        LIMIT 1
    ");

    // Get ledger data
    $transactions = five01c3po_get_transaction_ledger($filters);

    // Calculate totals
    $total_credits = 0;
    $total_debits = 0;
    $total_stripe_fees = 0;
    $stripe_matched_count = 0;
    $unmatched_count = 0;

    foreach ($transactions as $txn) {
        $total_credits += floatval($txn->bank_credit);
        $total_debits += floatval($txn->bank_debit);
        $total_stripe_fees += floatval($txn->stripe_fee);
        if ($txn->stripe_id) {
            $stripe_matched_count++;
        } else {
            $unmatched_count++;
        }
    }

    $net_balance = $total_credits - $total_debits;

    // Calculate current estimated balance
    if ($latest_statement) {
        // Start with statement ending balance
        $current_balance = floatval($latest_statement->ending_balance);

        // Add all transactions since statement end date
        $transactions_since_statement = $wpdb->get_results($wpdb->prepare("
            SELECT SUM(credit) as total_credits, SUM(debit) as total_debits
            FROM wp_swca_bank_transactions
            WHERE post_date > %s
        ", $latest_statement->statement_period_end));

        if ($transactions_since_statement) {
            $current_balance += floatval($transactions_since_statement[0]->total_credits);
            $current_balance -= floatval($transactions_since_statement[0]->total_debits);
        }

        // Check if balance is estimated (statement > 30 days old)
        $days_since_statement = (strtotime('now') - strtotime($latest_statement->statement_period_end)) / 86400;
        $balance_is_estimated = $days_since_statement > 30;
    } else {
        // No statement on file
        $current_balance = $net_balance;
        $balance_is_estimated = true;
    }

    ?>
    <div class="wrap">
        <h1>📒 Complete Transaction Ledger</h1>
        <p class="description">All bank transactions including Stripe payments, cash deposits, checks, and expenses</p>

        <!-- Bank Statement Reconciliation -->
        <?php if ($latest_statement): ?>
            <div class="notice <?php echo $balance_is_estimated ? 'notice-warning' : 'notice-success'; ?>" style="margin: 15px 0; padding: 15px; border-left-width: 4px;">
                <h3 style="margin-top: 0;">🏦 Bank Statement Reconciliation</h3>
                <table style="width: 100%; max-width: 700px;">
                    <tr>
                        <td style="padding: 5px 0;"><strong>Last Statement Date:</strong></td>
                        <td><?php echo date('F j, Y', strtotime($latest_statement->statement_period_end)); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Statement Ending Balance:</strong></td>
                        <td><strong>$<?php echo number_format($latest_statement->ending_balance, 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Transactions Since Statement:</strong></td>
                        <td>
                            <?php
                            $since_stmt = $wpdb->get_row($wpdb->prepare("
                                SELECT COUNT(*) as count, SUM(credit) as credits, SUM(debit) as debits
                                FROM wp_swca_bank_transactions
                                WHERE post_date > %s
                            ", $latest_statement->statement_period_end));
                            if ($since_stmt && $since_stmt->count > 0):
                                echo $since_stmt->count . ' transactions: ';
                                echo '<span style="color: #28a745;">+$' . number_format($since_stmt->credits, 2) . '</span>';
                                echo ' / ';
                                echo '<span style="color: #dc3545;">-$' . number_format($since_stmt->debits, 2) . '</span>';
                            else:
                                echo '<em>No transactions since statement</em>';
                            endif;
                            ?>
                        </td>
                    </tr>
                    <tr style="border-top: 2px solid #000;">
                        <td style="padding: 10px 0 5px 0;"><strong>Current Balance:</strong></td>
                        <td style="padding: 10px 0 5px 0;">
                            <strong style="font-size: 18px; color: <?php echo $current_balance >= 0 ? '#28a745' : '#dc3545'; ?>;">
                                $<?php echo number_format($current_balance, 2); ?>
                            </strong>
                            <?php if ($balance_is_estimated): ?>
                                <br><span style="color: #856404;">⚠️ ESTIMATED (<?php echo round($days_since_statement); ?> days since last statement)</span>
                            <?php else: ?>
                                <br><span style="color: #155724;">✓ Accurate (statement current)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        <?php else: ?>
            <div class="notice notice-warning" style="margin: 15px 0; padding: 15px;">
                <p><strong>⚠️ No Bank Statement Data</strong><br>
                To enable balance tracking and reconciliation, you need to calculate running balances.<br>
                See <code>/home/swca/public_html/CALCULATE_BALANCES_INSTRUCTIONS.md</code> for instructions.</p>
            </div>
        <?php endif; ?>

        <!-- Accounting Standards Notice -->
        <div class="notice notice-info" style="margin: 15px 0; padding: 10px;">
            <p><strong>📊 Bank Statement View:</strong> This ledger shows ALL bank transactions (credits and debits).
            Stripe-matched transactions include fee calculations. You can add notes, categories, and tags to any transaction for better organization.
            <br>Click any field in the Notes, Category, or Tags columns to edit.</p>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 20px;">
            <h2>Filters</h2>
            <form method="get" action="">
                <input type="hidden" name="page" value="501c3PO-transaction-ledger">
                <table class="form-table">
                    <tr>
                        <th>Date Range</th>
                        <td>
                            <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>" placeholder="From">
                            to
                            <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>" placeholder="To">
                        </td>
                    </tr>
                    <tr>
                        <th>Min Amount</th>
                        <td>
                            $<input type="number" name="min_amount" value="<?php echo esc_attr($filters['min_amount']); ?>"
                                   step="0.01" style="width: 100px;" placeholder="0.00">
                        </td>
                    </tr>
                    <tr>
                        <th>Search</th>
                        <td>
                            <input type="text" name="search" value="<?php echo esc_attr($filters['search']); ?>"
                                   class="regular-text" placeholder="Customer name or email">
                        </td>
                    </tr>
                </table>
                <?php submit_button('Apply Filters', 'secondary', 'submit', false); ?>
                <a href="?page=501c3PO-transaction-ledger" class="button">Clear Filters</a>
            </form>
        </div>

        <!-- Summary Stats -->
        <div class="card" style="margin-bottom: 20px;">
            <h2>Summary</h2>
            <table class="widefat" style="max-width: 600px;">
                <tr>
                    <th>Total Transactions</th>
                    <td><?php echo number_format(count($transactions)); ?></td>
                </tr>
                <tr>
                    <th>Stripe-Matched</th>
                    <td><?php echo number_format($stripe_matched_count); ?> (<?php echo count($transactions) > 0 ? round(($stripe_matched_count / count($transactions)) * 100, 1) : 0; ?>%)</td>
                </tr>
                <tr>
                    <th>Cash/Check/Other</th>
                    <td><?php echo number_format($unmatched_count); ?> (<?php echo count($transactions) > 0 ? round(($unmatched_count / count($transactions)) * 100, 1) : 0; ?>%)</td>
                </tr>
                <tr style="border-top: 1px solid #ddd;">
                    <th>Total Credits (Income)</th>
                    <td><strong style="color: #28a745;">+$<?php echo number_format($total_credits, 2); ?></strong></td>
                </tr>
                <tr>
                    <th>Total Debits (Expenses)</th>
                    <td style="color: #dc3545;">-$<?php echo number_format($total_debits, 2); ?></td>
                </tr>
                <tr>
                    <th>Total Stripe Fees</th>
                    <td style="color: #dc3545;">-$<?php echo number_format($total_stripe_fees, 2); ?></td>
                </tr>
                <tr style="border-top: 2px solid #000;">
                    <th>Net Balance</th>
                    <td><strong style="color: <?php echo $net_balance >= 0 ? '#28a745' : '#dc3545'; ?>;">
                        $<?php echo number_format($net_balance, 2); ?>
                    </strong></td>
                </tr>
            </table>
        </div>


        <!--Transaction Ledger -->
        <table class="wp-list-table widefat fixed striped" style="background: white;">
            <thead>
                <tr>
                    <th style="width: 90px;">Date</th>
                    <th style="width: 50px;">Type</th>
                    <th style="width: 90px; text-align: right;">Amount</th>
                    <th style="width: 150px;">Description</th>
                    <th style="width: 120px;">Recipient/Customer</th>
                    <th style="width: 120px;">Notes</th>
                    <th style="width: 80px;">Category</th>
                    <th style="width: 80px;">Tags</th>
                    <th style="width: 90px; text-align: right;">Balance</th>
                    <th style="width: 60px; text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px;">
                            No transactions found. Try adjusting your filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $txn): ?>
                        <?php
                        $is_credit = floatval($txn->bank_credit) > 0;
                        $is_debit = floatval($txn->bank_debit) > 0;
                        $amount = $is_credit ? $txn->bank_credit : $txn->bank_debit;
                        $has_stripe = !empty($txn->stripe_id);
                        $is_refunded = floatval($txn->amount_refunded) > 0;

                        // Row styling based on transaction type
                        if ($has_stripe && $is_refunded) {
                            $row_style = 'background: #fff3cd;';
                            $status_icon = '🔄';
                            $status_text = 'Refunded';
                        } elseif ($has_stripe) {
                            $row_style = 'background: #d4edda;';
                            $status_icon = '💳';
                            $status_text = 'Stripe';
                        } elseif ($is_credit) {
                            $row_style = 'background: #e7f3ff;';
                            $status_icon = '💵';
                            $status_text = 'Cash/Check';
                        } else {
                            $row_style = 'background: #ffe7e7;';
                            $status_icon = '📤';
                            $status_text = 'Expense';
                        }

                        $display_name = '';
                        if ($has_stripe) {
                            $display_name = $txn->customer_name ?? $txn->customer_email ?? '';
                        }
                        ?>
                        <tr style="<?php echo $row_style; ?>">
                            <td>
                                <strong><?php echo date('M j, Y', strtotime($txn->transaction_date)); ?></strong><br>
                                <small style="color: #666;"><?php echo date('l', strtotime($txn->transaction_date)); ?></small>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($is_credit): ?>
                                    <span style="color: #28a745; font-weight: bold;">CR</span>
                                <?php else: ?>
                                    <span style="color: #dc3545; font-weight: bold;">DR</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <strong style="color: <?php echo $is_credit ? '#28a745' : '#dc3545'; ?>;">
                                    <?php echo $is_credit ? '+' : '-'; ?>$<?php echo number_format($amount, 2); ?>
                                </strong>
                                <?php if ($has_stripe && $txn->stripe_fee > 0): ?>
                                    <br><small style="color: #999;">Fee: -$<?php echo number_format($txn->stripe_fee, 2); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html(substr($txn->bank_description, 0, 40)); ?></strong>
                                <?php if ($txn->check_number): ?>
                                    <br><small style="color: #999;">Check #<?php echo esc_html($txn->check_number); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="editable-cell" data-bank-id="<?php echo $txn->bank_id; ?>" data-field="recipient">
                                <?php if ($has_stripe): ?>
                                    <small style="color: #666;"><?php echo esc_html($display_name); ?></small>
                                <?php else: ?>
                                    <span class="cell-display"><?php echo esc_html($txn->recipient ?: '(click to add)'); ?></span>
                                    <input type="text" class="cell-editor" style="display: none; width: 100%;" value="<?php echo esc_attr($txn->recipient); ?>">
                                <?php endif; ?>
                            </td>
                            <td class="editable-cell" data-bank-id="<?php echo $txn->bank_id; ?>" data-field="notes">
                                <span class="cell-display"><?php echo esc_html($txn->bank_notes ?: '(click to add)'); ?></span>
                                <textarea class="cell-editor" style="display: none; width: 100%;" rows="2"><?php echo esc_textarea($txn->bank_notes); ?></textarea>
                            </td>
                            <td class="editable-cell" data-bank-id="<?php echo $txn->bank_id; ?>" data-field="category">
                                <span class="cell-display"><?php echo esc_html($txn->bank_category ?: '(click to add)'); ?></span>
                                <input type="text" class="cell-editor" style="display: none; width: 100%;" value="<?php echo esc_attr($txn->bank_category); ?>">
                            </td>
                            <td class="editable-cell" data-bank-id="<?php echo $txn->bank_id; ?>" data-field="tags">
                                <span class="cell-display"><?php echo esc_html($txn->bank_tags ?: '(click to add)'); ?></span>
                                <input type="text" class="cell-editor" style="display: none; width: 100%;" value="<?php echo esc_attr($txn->bank_tags); ?>">
                            </td>
                            <td style="text-align: right;">
                                <?php if (floatval($txn->bank_balance) != 0): ?>
                                    <strong>$<?php echo number_format($txn->bank_balance, 2); ?></strong>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;" title="<?php echo $status_text; ?>">
                                <span style="font-size: 20px;"><?php echo $status_icon; ?></span>
                                <?php if ($has_stripe): ?>
                                    <br><a href="<?php echo admin_url('admin.php?page=501c3PO-view-stripe-transaction&id=' . $txn->stripe_id); ?>"
                                          style="font-size: 11px;">Details</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Legend -->
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #007bff;">
            <h3 style="margin-top: 0;">Legend</h3>
            <table style="width: 100%; max-width: 800px;">
                <tr>
                    <td style="padding: 5px;"><span style="font-size: 20px;">💳</span> <strong>Stripe</strong></td>
                    <td>Payment processed through Stripe (matched to bank deposit)</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><span style="font-size: 20px;">💵</span> <strong>Cash/Check</strong></td>
                    <td>Direct deposit not from Stripe (cash, check, wire transfer, etc.)</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><span style="font-size: 20px;">📤</span> <strong>Expense</strong></td>
                    <td>Money out (checks written, ACH debits, fees, etc.)</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><span style="font-size: 20px;">🔄</span> <strong>Refunded</strong></td>
                    <td>Stripe transaction that was refunded</td>
                </tr>
            </table>
            <p style="margin-top: 15px;"><strong>CR</strong> = Credit (money in) | <strong>DR</strong> = Debit (money out)</p>
            <p><em>Click on Notes, Category, or Tags to edit. Press Enter to save, Esc to cancel.</em></p>
        </div>

        <!-- Export Options -->
        <div style="margin-top: 20px;">
            <p>
                <a href="#" class="button" onclick="window.print(); return false;">🖨 Print Ledger</a>
                <a href="#" class="button" onclick="alert('CSV export coming soon'); return false;">📊 Export to CSV</a>
            </p>
        </div>
    </div>

    <style>
        .editable-cell {
            cursor: pointer;
            padding: 8px;
        }
        .editable-cell:hover {
            background: #f0f0f0;
        }
        .cell-display {
            display: inline-block;
            min-height: 20px;
            width: 100%;
        }
        @media print {
            .wrap > h1 { page-break-after: avoid; }
            .card, .button { display: none; }
            table { page-break-inside: avoid; }
            .editable-cell { cursor: default !important; }
            .editable-cell:hover { background: transparent !important; }
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Inline editing for notes, category, tags
        $('.editable-cell').on('click', function() {
            var $cell = $(this);
            if ($cell.hasClass('editing')) return;

            $cell.addClass('editing');
            var $display = $cell.find('.cell-display');
            var $editor = $cell.find('.cell-editor');

            $display.hide();
            $editor.show().focus();

            // Handle save on Enter key
            $editor.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    saveEdit($cell);
                } else if (e.key === 'Escape') {
                    cancelEdit($cell);
                }
            });

            // Handle blur (clicking away)
            $editor.on('blur', function() {
                setTimeout(function() {
                    if ($cell.hasClass('editing')) {
                        saveEdit($cell);
                    }
                }, 200);
            });
        });

        function saveEdit($cell) {
            var $editor = $cell.find('.cell-editor');
            var $display = $cell.find('.cell-display');
            var newValue = $editor.val();
            var bankId = $cell.data('bank-id');
            var field = $cell.data('field');

            // Update display
            $display.text(newValue || '(click to add)');
            $display.show();
            $editor.hide();
            $cell.removeClass('editing');

            // Save to database
            $.post(ajaxurl, {
                action: 'update_bank_transaction_field',
                bank_id: bankId,
                field: field,
                value: newValue,
                _wpnonce: '<?php echo wp_create_nonce('update_bank_transaction'); ?>'
            }, function(response) {
                if (response.success) {
                    $cell.css('background', '#d4edda').delay(1000).queue(function() {
                        $(this).css('background', '').dequeue();
                    });
                } else {
                    alert('Error saving: ' + (response.data.message || 'Unknown error'));
                }
            });
        }

        function cancelEdit($cell) {
            var $display = $cell.find('.cell-display');
            var $editor = $cell.find('.cell-editor');

            $display.show();
            $editor.hide();
            $cell.removeClass('editing');
        }
    });
    </script>
    <?php
}
