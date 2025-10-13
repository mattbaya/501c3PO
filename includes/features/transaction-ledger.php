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
        $wpdb->prefix . 'c3_bank_transactions',
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

    // Use new c3_ table naming convention with proper prefix
    $stripe_table = $wpdb->prefix . 'c3_stripe_transactions';
    $gf_table = $wpdb->prefix . 'c3_gf_payment_transaction';
    $bank_table = $wpdb->prefix . 'c3_bank_transactions';
    $matches_table = $wpdb->prefix . 'c3_transaction_matches';

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

    // Build WHERE clause for unmatched Stripe transactions
    $stripe_where_clauses = array("1=1");

    if (!empty($filters['date_from'])) {
        $stripe_where_clauses[] = $wpdb->prepare("s.created >= %s", $filters['date_from']);
    }
    if (!empty($filters['date_to'])) {
        $stripe_where_clauses[] = $wpdb->prepare("s.created <= %s", $filters['date_to']);
    }
    if (!empty($filters['min_amount'])) {
        $stripe_where_clauses[] = $wpdb->prepare("s.amount >= %f", $filters['min_amount']);
    }
    if (!empty($filters['search'])) {
        $search = '%' . $wpdb->esc_like($filters['search']) . '%';
        $stripe_where_clauses[] = $wpdb->prepare(
            "(s.customer_email LIKE %s OR s.customer_name LIKE %s)",
            $search, $search
        );
    }

    $stripe_where_sql = implode(" AND ", $stripe_where_clauses);

    $query = "
        -- PART 1: Bank transactions (may have Stripe matches or not)
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

            -- Aggregated match information
            GROUP_CONCAT(DISTINCT m_stripe.stripe_transaction_id ORDER BY m_stripe.stripe_transaction_id) as matched_stripe_ids,
            GROUP_CONCAT(DISTINCT m_stripe.match_type ORDER BY m_stripe.stripe_transaction_id) as match_types,
            COUNT(DISTINCT m_stripe.stripe_transaction_id) as stripe_match_count,

            -- Use MAX to get one Stripe record (for basic info) - will get details later
            MAX(s.stripe_fee) as total_stripe_fee,
            MAX(s.customer_name) as customer_name,
            MAX(s.customer_email) as customer_email,
            MAX(s.amount) as stripe_amount,
            SUM(s.amount_refunded) as total_refunded,

            -- Gravity Forms member information
            MAX(gf_fname.meta_value) as gf_first_name,
            MAX(gf_lname.meta_value) as gf_last_name,
            MAX(gf_email.meta_value) as gf_email,
            MAX(gf_entry.id) as gf_entry_id,

            -- Member directory link
            MAX(mem.id) as member_id,
            MAX(mem.first_name) as member_first_name,
            MAX(mem.last_name) as member_last_name,

            -- Transaction Type Indicator
            CASE
                WHEN COUNT(DISTINCT m_stripe.stripe_transaction_id) > 0 THEN 'stripe_matched'
                WHEN b.credit > 0 THEN 'bank_deposit'
                WHEN b.debit > 0 THEN 'bank_expense'
                ELSE 'unknown'
            END as transaction_type,

            -- Source indicator
            'bank' as data_source

        FROM $bank_table b

        -- Left join to Stripe matches
        LEFT JOIN $matches_table m_stripe
            ON m_stripe.bank_transaction_id = b.id
            AND m_stripe.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
        LEFT JOIN $stripe_table s ON s.id = m_stripe.stripe_transaction_id

        -- Join to Gravity Forms data for member names
        LEFT JOIN $gf_table gf_txn ON s.stripe_charge_id = gf_txn.transaction_id
        LEFT JOIN swca_gf_entry gf_entry ON gf_txn.lead_id = gf_entry.id
        LEFT JOIN swca_gf_entry_meta gf_fname ON gf_entry.id = gf_fname.entry_id AND gf_fname.meta_key = '4.3'
        LEFT JOIN swca_gf_entry_meta gf_lname ON gf_entry.id = gf_lname.entry_id AND gf_lname.meta_key = '4.6'
        LEFT JOIN swca_gf_entry_meta gf_email ON gf_entry.id = gf_email.entry_id AND gf_email.meta_key = '6'

        -- Join to member directory via email (note: uses swca_members, not wp_swca_members)
        LEFT JOIN swca_members mem ON (
            LOWER(TRIM(mem.email_1)) = LOWER(TRIM(gf_email.meta_value))
            OR LOWER(TRIM(mem.email_1)) = LOWER(TRIM(s.customer_email))
        )

        WHERE $where_sql

        GROUP BY b.id

        UNION ALL

        -- PART 2: Unmatched Stripe transactions (awaiting bank deposit)
        SELECT
            -- Bank Transaction Data (NULL for unmatched)
            NULL as bank_id,
            s.created as transaction_date,
            CONCAT('⏳ Awaiting Bank Deposit - ', s.description) as bank_description,
            NULL as bank_notes,
            NULL as bank_category,
            NULL as bank_tags,
            NULL as check_number,
            NULL as recipient,
            0 as bank_debit,
            0 as bank_credit,
            'unmatched_stripe' as bank_status,
            NULL as bank_balance,

            -- Match information (single Stripe ID, unmatched)
            s.id as matched_stripe_ids,
            'unmatched' as match_types,
            1 as stripe_match_count,

            -- Stripe data
            s.stripe_fee as total_stripe_fee,
            s.customer_name,
            s.customer_email,
            s.amount as stripe_amount,
            s.amount_refunded as total_refunded,

            -- Gravity Forms member information
            gf_fname.meta_value as gf_first_name,
            gf_lname.meta_value as gf_last_name,
            gf_email.meta_value as gf_email,
            gf_entry.id as gf_entry_id,

            -- Member directory link
            mem.id as member_id,
            mem.first_name as member_first_name,
            mem.last_name as member_last_name,

            -- Transaction type
            'stripe_unmatched' as transaction_type,

            -- Source indicator
            'stripe' as data_source

        FROM $stripe_table s

        -- Left join to check if this Stripe transaction has a bank match
        LEFT JOIN $matches_table m_check
            ON m_check.stripe_transaction_id = s.id
            AND m_check.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')

        -- Join to Gravity Forms data for member names
        LEFT JOIN $gf_table gf_txn ON s.stripe_charge_id = gf_txn.transaction_id
        LEFT JOIN swca_gf_entry gf_entry ON gf_txn.lead_id = gf_entry.id
        LEFT JOIN swca_gf_entry_meta gf_fname ON gf_entry.id = gf_fname.entry_id AND gf_fname.meta_key = '4.3'
        LEFT JOIN swca_gf_entry_meta gf_lname ON gf_entry.id = gf_lname.entry_id AND gf_lname.meta_key = '4.6'
        LEFT JOIN swca_gf_entry_meta gf_email ON gf_entry.id = gf_email.entry_id AND gf_email.meta_key = '6'

        -- Join to member directory via email
        LEFT JOIN swca_members mem ON (
            LOWER(TRIM(mem.email_1)) = LOWER(TRIM(gf_email.meta_value))
            OR LOWER(TRIM(mem.email_1)) = LOWER(TRIM(s.customer_email))
        )

        -- ONLY show Stripe transactions that have NO bank match
        WHERE m_check.id IS NULL
        AND $stripe_where_sql

        -- Final ORDER BY for entire result set
        ORDER BY transaction_date DESC
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
                $wpdb->prefix . 'c3_bank_transactions',
                array($field => $value),
                array('id' => $bank_id),
                array('%s'),
                array('%d')
            );
            wp_send_json_success(array('message' => 'Updated successfully'));
        }
        wp_send_json_error(array('message' => 'Invalid field'));
    }

    $bank_statements_table = $wpdb->prefix . 'c3_bank_statements';

    // Get latest bank statement
    $latest_statement = $wpdb->get_row("
        SELECT * FROM {$bank_statements_table}
        ORDER BY statement_period_end DESC
        LIMIT 1
    ");

    // Get ALL bank statements for matching transaction dates
    $bank_statements = $wpdb->get_results("
        SELECT statement_period_end, ending_balance
        FROM {$bank_statements_table}
        ORDER BY statement_period_end ASC
    ");

    // Create lookup array of statement dates => balances
    $statement_balances = array();
    foreach ($bank_statements as $stmt) {
        $statement_balances[$stmt->statement_period_end] = $stmt->ending_balance;
    }

    // Get ledger data
    $transactions = five01c3po_get_transaction_ledger($filters);

    // Calculate totals
    $total_credits = 0;
    $total_debits = 0;
    $total_stripe_fees = 0;
    $stripe_matched_count = 0;
    $stripe_unmatched_count = 0;
    $cash_check_count = 0;

    foreach ($transactions as $txn) {
        $total_credits += floatval($txn->bank_credit);
        $total_debits += floatval($txn->bank_debit);
        $total_stripe_fees += floatval($txn->total_stripe_fee);

        // Categorize transaction type
        if ($txn->transaction_type == 'stripe_unmatched') {
            $stripe_unmatched_count++;
            // Add unmatched Stripe amount to credits for accurate totals
            $total_credits += floatval($txn->stripe_amount);
        } elseif ($txn->transaction_type == 'stripe_matched') {
            $stripe_matched_count++;
        } else {
            $cash_check_count++;
        }
    }

    $net_balance = $total_credits - $total_debits;

    // Calculate current estimated balance
    if ($latest_statement) {
        // Start with statement ending balance
        $current_balance = floatval($latest_statement->ending_balance);

        // Add all transactions since statement end date
        $bank_transactions_table = $wpdb->prefix . 'c3_bank_transactions';
        $transactions_since_statement = $wpdb->get_results($wpdb->prepare("
            SELECT SUM(credit) as total_credits, SUM(debit) as total_debits
            FROM {$bank_transactions_table}
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
                                FROM {$bank_transactions_table}
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
                <p><strong>⚠️ No Bank Statement Data</strong></p>
                <p>To enable balance tracking and reconciliation, you need to calculate running balances for your <?php echo count($transactions); ?> bank transactions.</p>
                <p>
                    <a href="admin.php?page=501c3PO-calculate-balances" class="button button-primary">
                        💰 Calculate Balances Now
                    </a>
                </p>
                <p class="description">This will populate the balance column and create monthly bank statement records automatically.</p>
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
                    <th>💳 Stripe (In Bank)</th>
                    <td><?php echo number_format($stripe_matched_count); ?> (<?php echo count($transactions) > 0 ? round(($stripe_matched_count / count($transactions)) * 100, 1) : 0; ?>%)</td>
                </tr>
                <tr>
                    <th>⏳ Stripe (Awaiting Deposit)</th>
                    <td style="color: #ff9800;"><strong><?php echo number_format($stripe_unmatched_count); ?></strong> (<?php echo count($transactions) > 0 ? round(($stripe_unmatched_count / count($transactions)) * 100, 1) : 0; ?>%)</td>
                </tr>
                <tr>
                    <th>💵 Cash/Check/Other</th>
                    <td><?php echo number_format($cash_check_count); ?> (<?php echo count($transactions) > 0 ? round(($cash_check_count / count($transactions)) * 100, 1) : 0; ?>%)</td>
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
                        $is_unmatched_stripe = ($txn->transaction_type == 'stripe_unmatched');

                        // For unmatched Stripe, amount comes from stripe_amount
                        $amount = $is_unmatched_stripe ? floatval($txn->stripe_amount) : ($is_credit ? $txn->bank_credit : $txn->bank_debit);

                        $has_stripe = intval($txn->stripe_match_count) > 0;
                        $is_refunded = floatval($txn->total_refunded) > 0;
                        $stripe_ids = $has_stripe ? explode(',', $txn->matched_stripe_ids) : array();

                        // Row styling based on transaction type
                        if ($is_unmatched_stripe) {
                            $row_style = 'background: #fff9e6; border-left: 4px solid #ff9800;';
                            $status_icon = '⏳';
                            $status_text = 'Awaiting Bank Deposit';
                        } elseif ($has_stripe && $is_refunded) {
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

                        // Build member name display with link
                        $display_name = '';
                        $member_link = '';

                        if ($has_stripe) {
                            // Priority 1: Gravity Forms name (most accurate)
                            if (!empty($txn->gf_first_name) && !empty($txn->gf_last_name)) {
                                $display_name = trim($txn->gf_first_name . ' ' . $txn->gf_last_name);
                            }
                            // Priority 2: Member directory name
                            elseif (!empty($txn->member_first_name) && !empty($txn->member_last_name)) {
                                $display_name = trim($txn->member_first_name . ' ' . $txn->member_last_name);
                            }
                            // Priority 3: Stripe customer name
                            elseif (!empty($txn->customer_name)) {
                                $display_name = $txn->customer_name;
                            }
                            // Priority 4: Email addresses
                            else {
                                $display_name = $txn->gf_email ?? $txn->customer_email ?? '';
                            }

                            // Create member directory link if we have a member_id
                            if (!empty($txn->member_id) && !empty($display_name)) {
                                // TODO: Update this URL to match your member directory page structure
                                $board_portal_slug = get_option('five01c3po_organization_settings')['board_portal_slug'] ?? 'board-portal';
                                $member_link = home_url("/{$board_portal_slug}/member/?id=" . $txn->member_id);
                            }
                        }
                        ?>
                        <tr style="<?php echo $row_style; ?>">
                            <td>
                                <strong><?php echo date('M j, Y', strtotime($txn->transaction_date)); ?></strong><br>
                                <small style="color: #666;"><?php echo date('l', strtotime($txn->transaction_date)); ?></small>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($is_unmatched_stripe || $is_credit): ?>
                                    <span style="color: <?php echo $is_unmatched_stripe ? '#ff9800' : '#28a745'; ?>; font-weight: bold;">CR</span>
                                <?php else: ?>
                                    <span style="color: #dc3545; font-weight: bold;">DR</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <strong style="color: <?php echo ($is_unmatched_stripe || $is_credit) ? '#28a745' : '#dc3545'; ?>;">
                                    <?php echo ($is_unmatched_stripe || $is_credit) ? '+' : '-'; ?>$<?php echo number_format($amount, 2); ?>
                                </strong>
                                <?php if ($has_stripe && $txn->total_stripe_fee > 0): ?>
                                    <br><small style="color: #999;">Fee: -$<?php echo number_format($txn->total_stripe_fee, 2); ?></small>
                                <?php endif; ?>
                                <?php if ($is_unmatched_stripe): ?>
                                    <br><small style="color: #ff9800;">⏳ Not in bank yet</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html(substr($txn->bank_description, 0, 40)); ?></strong>
                                <?php if ($txn->check_number): ?>
                                    <br><small style="color: #999;">Check #<?php echo esc_html($txn->check_number); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="editable-cell" data-bank-id="<?php echo $txn->bank_id; ?>" data-field="recipient">
                                <?php if ($has_stripe && !empty($display_name)): ?>
                                    <?php if (!empty($member_link)): ?>
                                        <a href="<?php echo esc_url($member_link); ?>"
                                           style="color: #0073aa; text-decoration: none; font-weight: 500;"
                                           title="View member profile">
                                            <?php echo esc_html($display_name); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #666; font-weight: 500;"><?php echo esc_html($display_name); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($txn->gf_email) || !empty($txn->customer_email)): ?>
                                        <br><small style="color: #999; font-size: 11px;">
                                            <?php echo esc_html($txn->gf_email ?? $txn->customer_email); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php elseif ($has_stripe): ?>
                                    <small style="color: #999;">No member data</small>
                                <?php else: ?>
                                    <span class="cell-display"><?php echo esc_html($txn->recipient ?: '(click to add)'); ?></span>
                                    <input type="text" class="cell-editor" style="display: none; width: 100%;" value="<?php echo esc_attr($txn->recipient); ?>">
                                <?php endif; ?>
                            </td>
                            <td <?php if (!$is_unmatched_stripe): ?>class="editable-cell" data-bank-id="<?php echo $txn->bank_id; ?>" data-field="notes"<?php endif; ?>>
                                <?php if ($is_unmatched_stripe): ?>
                                    <span style="color: #999;">—</span>
                                <?php else: ?>
                                    <span class="cell-display"><?php echo esc_html($txn->bank_notes ?: '(click to add)'); ?></span>
                                    <textarea class="cell-editor" style="display: none; width: 100%;" rows="2"><?php echo esc_textarea($txn->bank_notes); ?></textarea>
                                <?php endif; ?>
                            </td>
                            <td <?php if (!$is_unmatched_stripe): ?>class="editable-cell" data-bank-id="<?php echo $txn->bank_id; ?>" data-field="category"<?php endif; ?>>
                                <?php if ($is_unmatched_stripe): ?>
                                    <span style="color: #999;">—</span>
                                <?php else: ?>
                                    <span class="cell-display"><?php echo esc_html($txn->bank_category ?: '(click to add)'); ?></span>
                                    <input type="text" class="cell-editor" style="display: none; width: 100%;" value="<?php echo esc_attr($txn->bank_category); ?>">
                                <?php endif; ?>
                            </td>
                            <td <?php if (!$is_unmatched_stripe): ?>class="editable-cell" data-bank-id="<?php echo $txn->bank_id; ?>" data-field="tags"<?php endif; ?>>
                                <?php if ($is_unmatched_stripe): ?>
                                    <span style="color: #999;">—</span>
                                <?php else: ?>
                                    <span class="cell-display"><?php echo esc_html($txn->bank_tags ?: '(click to add)'); ?></span>
                                    <input type="text" class="cell-editor" style="display: none; width: 100%;" value="<?php echo esc_attr($txn->bank_tags); ?>">
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if (floatval($txn->bank_balance) != 0): ?>
                                    <strong style="font-size: 13px;">$<?php echo number_format($txn->bank_balance, 2); ?></strong>
                                    <?php
                                    // Check if this date matches a bank statement ending date
                                    if (isset($statement_balances[$txn->transaction_date])):
                                        $stmt_balance = $statement_balances[$txn->transaction_date];
                                        $balance_matches = abs($txn->bank_balance - $stmt_balance) < 0.01;
                                        ?>
                                        <br><span style="font-size: 10px; color: <?php echo $balance_matches ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                                            <?php if ($balance_matches): ?>
                                                ✓ Bank Statement
                                            <?php else: ?>
                                                ⚠️ Statement Mismatch
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;" title="<?php echo $status_text; ?>">
                                <span style="font-size: 20px;"><?php echo $status_icon; ?></span>
                            </td>
                        </tr>

                        <!-- Bank Statement Verification Row -->
                        <?php
                        // Check if this transaction date matches a bank statement ending date
                        if (isset($statement_balances[$txn->transaction_date]) && floatval($txn->bank_balance) != 0):
                            $stmt_balance = $statement_balances[$txn->transaction_date];
                            $balance_matches = abs($txn->bank_balance - $stmt_balance) < 0.01;
                            ?>
                            <tr style="background: <?php echo $balance_matches ? '#d4edda' : '#f8d7da'; ?>; border-top: none;">
                                <td colspan="10" style="padding: 10px 15px; font-size: 12px;">
                                    <?php if ($balance_matches): ?>
                                        <strong style="color: #155724;">✓ BANK STATEMENT VERIFIED:</strong>
                                        This balance of <strong>$<?php echo number_format($txn->bank_balance, 2); ?></strong>
                                        matches the official bank statement ending balance from
                                        <strong><?php echo date('F j, Y', strtotime($txn->transaction_date)); ?></strong>.
                                    <?php else: ?>
                                        <strong style="color: #721c24;">⚠️ BALANCE MISMATCH:</strong>
                                        Our calculated balance of <strong>$<?php echo number_format($txn->bank_balance, 2); ?></strong>
                                        does not match the bank statement ending balance of <strong>$<?php echo number_format($stmt_balance, 2); ?></strong>
                                        from <?php echo date('F j, Y', strtotime($txn->transaction_date)); ?>.
                                        Difference: <strong>$<?php echo number_format(abs($txn->bank_balance - $stmt_balance), 2); ?></strong>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <!-- Match Details Row -->
                        <?php if ($has_stripe): ?>
                            <tr style="background: #f8f9fa; border-top: none;">
                                <td colspan="10" style="padding: 8px 15px; font-size: 12px;">
                                    <strong>🔗 Complete Payout Breakdown:</strong>
                                    <?php
                                    global $wpdb;

                                    // NEW: Look up correct payout by matching bank date ±2 days AND exact amount
                                    // This ignores incorrect transaction matches and finds the RIGHT payout
                                    $date_start = date('Y-m-d', strtotime($txn->transaction_date . ' -2 days'));
                                    $date_end = date('Y-m-d', strtotime($txn->transaction_date . ' +2 days'));

                                    $balance_txns_table = $wpdb->prefix . 'c3_stripe_balance_transactions';

                                    $correct_payout = $wpdb->get_row($wpdb->prepare(
                                        "SELECT source_id as payout_id, available_on as payout_date
                                         FROM {$balance_txns_table}
                                         WHERE txn_type = 'payout'
                                         AND available_on BETWEEN %s AND %s
                                         AND ABS(ABS(amount) - %f) < 0.01
                                         ORDER BY ABS(ABS(amount) - %f) ASC
                                         LIMIT 1",
                                        $date_start, $date_end, $txn->bank_credit, $txn->bank_credit
                                    ));

                                    $payout_id = $correct_payout->payout_id ?? null;
                                    $payout_arrival_date = $correct_payout->payout_date ?? null;

                                    // Try to get balance transactions - either by payout_id or by available_on date
                                    if ($payout_id || $payout_arrival_date):
                                        echo '<div style="margin-top: 8px; font-family: monospace; font-size: 11px;">';

                                        // Get all balance transactions - try payout_id first, then fall back to available_on date
                                        if ($payout_id) {
                                            $balance_txns = $wpdb->get_results($wpdb->prepare(
                                                "SELECT * FROM {$balance_txns_table}
                                                 WHERE payout_id = %s OR (source_id = %s AND txn_type = 'payout')
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
                                                $payout_id, $payout_id
                                            ));
                                        } else {
                                            // Fall back to grouping by available_on date
                                            $balance_txns = $wpdb->get_results($wpdb->prepare(
                                                "SELECT * FROM {$balance_txns_table}
                                                 WHERE available_on = %s
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
                                                $payout_arrival_date
                                            ));
                                        }

                                            $running_total = 0;
                                            foreach ($balance_txns as $bal_txn):
                                                if ($bal_txn->txn_type == 'payout') continue; // Skip the payout itself

                                                $running_total += $bal_txn->net;
                                                $color = $bal_txn->net >= 0 ? '#28a745' : '#dc3545';
                                            ?>
                                                <div style="padding: 3px 0; border-bottom: 1px solid #e0e0e0;">
                                                    <?php if ($bal_txn->txn_type == 'payment' || $bal_txn->txn_type == 'charge'): ?>
                                                        💳 <strong>Charge:</strong>
                                                        $<?php echo number_format($bal_txn->amount, 2); ?>
                                                        (fee: $<?php echo number_format($bal_txn->fee, 2); ?>)
                                                        = <span style="color: <?php echo $color; ?>;">$<?php echo number_format($bal_txn->net, 2); ?></span>
                                                        <br>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo esc_html($bal_txn->description); ?>
                                                    <?php elseif ($bal_txn->txn_type == 'stripe_fee'): ?>
                                                        ⚠️ <strong>Stripe Fee:</strong>
                                                        <span style="color: <?php echo $color; ?>;">$<?php echo number_format($bal_txn->net, 2); ?></span>
                                                        - <?php echo esc_html($bal_txn->description); ?>
                                                    <?php else: ?>
                                                        📊 <strong><?php echo ucfirst(str_replace('_', ' ', $bal_txn->txn_type)); ?>:</strong>
                                                        <span style="color: <?php echo $color; ?>;">$<?php echo number_format($bal_txn->net, 2); ?></span>
                                                        <?php if ($bal_txn->description): ?>
                                                            - <?php echo esc_html($bal_txn->description); ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php
                                            endforeach;

                                            // Show total
                                            $total_color = $running_total >= 0 ? '#28a745' : '#dc3545';
                                            ?>
                                            <div style="padding: 5px 0; margin-top: 5px; border-top: 2px solid #333; font-weight: bold;">
                                                <strong>Total Payout:</strong>
                                                <span style="color: <?php echo $total_color; ?>;">$<?php echo number_format($running_total, 2); ?></span>
                                                <?php if (abs($running_total - $txn->bank_credit) > 0.01): ?>
                                                    <span style="color: #dc3545;"> ⚠️ ($<?php echo number_format(abs($running_total - $txn->bank_credit), 2); ?> difference from bank)</span>
                                                <?php else: ?>
                                                    <span style="color: #28a745;"> ✓ Matches bank deposit</span>
                                                <?php endif; ?>
                                            </div>
                                            </div>
                                        <?php
                                    else:
                                        // No payout_id, show basic match info
                                        foreach ($stripe_ids as $stripe_id):
                                            $stripe_details = $wpdb->get_row($wpdb->prepare(
                                                "SELECT stripe_charge_id, amount, stripe_fee, customer_name, customer_email
                                                 FROM swca_stripe_transactions WHERE id = %d",
                                                $stripe_id
                                            ));
                                            if ($stripe_details):
                                    ?>
                                        <span style="margin-left: 10px;">
                                            <a href="<?php echo admin_url('admin.php?page=501c3PO-view-stripe-transaction&id=' . $stripe_id); ?>"
                                               style="color: #0073aa; text-decoration: none;">
                                                💳 <?php echo esc_html($stripe_details->customer_name ?: $stripe_details->customer_email); ?>
                                                ($<?php echo number_format($stripe_details->amount, 2); ?>, fee: $<?php echo number_format($stripe_details->stripe_fee, 2); ?>)
                                            </a>
                                        </span>
                                    <?php
                                            endif;
                                        endforeach;
                                    endif;
                                    ?>
                                    <span style="margin-left: 15px;">
                                        <a href="<?php echo admin_url('admin.php?page=501c3PO-view-bank-transaction&id=' . $txn->bank_id); ?>"
                                           style="color: #0073aa; text-decoration: none;">
                                            🏦 View Bank Transaction
                                        </a>
                                    </span>
                                </td>
                            </tr>
                        <?php endif; ?>
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
                    <td style="padding: 5px;"><span style="font-size: 20px;">⏳</span> <strong>Awaiting Bank Deposit</strong></td>
                    <td>Stripe transaction not yet deposited in bank (payout pending or in transit)</td>
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
                <tr style="border-top: 1px solid #ddd;">
                    <td style="padding: 5px;"><strong style="color: #28a745;">✓ Bank Statement</strong></td>
                    <td>This balance has been verified against an official bank statement</td>
                </tr>
            </table>
            <p style="margin-top: 15px;"><strong>CR</strong> = Credit (money in) | <strong>DR</strong> = Debit (money out)</p>
            <p><strong>Balance Column:</strong> Shows running balance after each transaction. Green "✓ Bank Statement" indicates the balance matches your official bank statement for that date.</p>
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
            /* Hide WordPress admin elements */
            #wpadminbar, #adminmenuback, #adminmenuwrap,
            #wpfooter, .update-nag, .notice, .card {
                display: none !important;
            }

            /* Hide filters and buttons */
            .button, form, .description { display: none !important; }

            /* Full width for content */
            #wpcontent, #wpbody-content, .wrap {
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Clean page layout */
            body, html {
                background: white !important;
                color: black !important;
                font-size: 10pt !important;
            }

            /* Header styling */
            .wrap > h1 {
                page-break-after: avoid;
                font-size: 18pt !important;
                margin-bottom: 10pt !important;
            }

            /* Table formatting */
            table {
                page-break-inside: auto !important;
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 9pt !important;
            }

            tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }

            thead {
                display: table-header-group !important;
                font-weight: bold !important;
            }

            th, td {
                border: 1px solid #000 !important;
                padding: 4pt !important;
            }

            /* Remove hover effects */
            .editable-cell {
                cursor: default !important;
                background: transparent !important;
            }
            .editable-cell:hover {
                background: transparent !important;
            }

            /* Preserve row colors for context */
            tr[style*="background: #d4edda"] { background: #d4edda !important; } /* Stripe */
            tr[style*="background: #e7f3ff"] { background: #e7f3ff !important; } /* Cash */
            tr[style*="background: #ffe7e7"] { background: #ffe7e7 !important; } /* Expense */
            tr[style*="background: #fff3cd"] { background: #fff3cd !important; } /* Refunded */
            tr[style*="background: #f8f9fa"] { background: #f8f9fa !important; } /* Details */

            /* Legend styling */
            div[style*="border-left: 4px solid #007bff"] {
                page-break-before: always;
                border: 1px solid #000 !important;
                padding: 10pt !important;
            }

            /* Print timestamp */
            .wrap::before {
                content: "Printed: <?php echo date('F j, Y g:i A'); ?>";
                display: block;
                text-align: right;
                font-size: 8pt;
                color: #666;
                margin-bottom: 5pt;
            }
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

/**
 * Transaction Ledger Shortcode
 * For use in board portal and public pages
 */
add_shortcode('five01c3po_transaction_ledger', 'five01c3po_transaction_ledger_shortcode');

function five01c3po_transaction_ledger_shortcode($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'limit' => 0, // 0 = show all
        'date_from' => '',
        'date_to' => '',
    ), $atts);

    // Build filters from shortcode attributes
    $filters = array();
    if (!empty($atts['date_from'])) {
        $filters['date_from'] = $atts['date_from'];
    }
    if (!empty($atts['date_to'])) {
        $filters['date_to'] = $atts['date_to'];
    }

    // Also respect GET parameters if present
    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = sanitize_text_field($_GET['date_from']);
    }
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = sanitize_text_field($_GET['date_to']);
    }
    if (!empty($_GET['min_amount'])) {
        $filters['min_amount'] = floatval($_GET['min_amount']);
    }
    if (!empty($_GET['search'])) {
        $filters['search'] = sanitize_text_field($_GET['search']);
    }
    if (!empty($_GET['category'])) {
        $filters['category'] = sanitize_text_field($_GET['category']);
    }

    // Capture the output
    ob_start();

    // Call the main page function
    five01c3po_transaction_ledger_page();

    return ob_get_clean();
}
