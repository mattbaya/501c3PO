<?php
/**
 * Calculate Running Balances - Admin Interface
 * User-friendly interface to populate balance column and create monthly statements
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add menu item
 */
add_action('admin_menu', 'five01c3po_add_calculate_balances_menu', 26);

function five01c3po_add_calculate_balances_menu() {
    add_submenu_page(
        'membership-management',
        'Calculate Balances',
        '💰 Calculate Balances',
        'manage_options',
        '501c3PO-calculate-balances',
        'five01c3po_calculate_balances_page'
    );
}

/**
 * Calculate balances page
 */
function five01c3po_calculate_balances_page() {
    global $wpdb;

    // Handle form submission
    if (isset($_POST['calculate_balances']) && check_admin_referer('calculate_balances_action')) {
        $starting_balance = floatval($_POST['starting_balance']);
        $starting_date = sanitize_text_field($_POST['starting_date']);

        // Calculate running balances
        $running_balance = $starting_balance;
        $monthly_data = array();

        $transactions = $wpdb->get_results($wpdb->prepare("
            SELECT id, post_date, description, debit, credit
            FROM swca_c3_bank_transactions
            WHERE post_date >= %s
            ORDER BY post_date ASC, id ASC
        ", $starting_date));

        foreach ($transactions as $txn) {
            $running_balance += floatval($txn->credit);
            $running_balance -= floatval($txn->debit);

            // Update transaction balance
            $wpdb->update(
                'swca_c3_bank_transactions',
                array('balance' => $running_balance),
                array('id' => $txn->id),
                array('%f'),
                array('%d')
            );

            // Track monthly data
            $month_key = date('Y-m', strtotime($txn->post_date));
            if (!isset($monthly_data[$month_key])) {
                $monthly_data[$month_key] = array(
                    'start_date' => date('Y-m-01', strtotime($txn->post_date)),
                    'end_date' => date('Y-m-t', strtotime($txn->post_date)),
                    'starting_balance' => $running_balance - floatval($txn->credit) + floatval($txn->debit),
                    'credits' => 0,
                    'debits' => 0
                );
            }

            $monthly_data[$month_key]['credits'] += floatval($txn->credit);
            $monthly_data[$month_key]['debits'] += floatval($txn->debit);
            $monthly_data[$month_key]['ending_balance'] = $running_balance;
        }

        // Create monthly statement records
        foreach ($monthly_data as $month => $data) {
            $notes = sprintf(
                "Auto-generated from transactions for %s. Starting balance: $%s, Ending balance: $%s",
                date('F Y', strtotime($data['start_date'])),
                number_format($data['starting_balance'], 2),
                number_format($data['ending_balance'], 2)
            );

            $wpdb->query($wpdb->prepare("
                INSERT INTO swca_c3_bank_statements
                (statement_period_start, statement_period_end, starting_balance, ending_balance, total_credits, total_debits, notes)
                VALUES (%s, %s, %f, %f, %f, %f, %s)
                ON DUPLICATE KEY UPDATE
                starting_balance = VALUES(starting_balance),
                ending_balance = VALUES(ending_balance),
                total_credits = VALUES(total_credits),
                total_debits = VALUES(total_debits),
                notes = VALUES(notes)
            ",
                $data['start_date'],
                $data['end_date'],
                $data['starting_balance'],
                $data['ending_balance'],
                $data['credits'],
                $data['debits'],
                $notes
            ));
        }

        echo '<div class="notice notice-success"><p><strong>✅ Success!</strong> Calculated balances for ' .
             count($transactions) . ' transactions and created ' . count($monthly_data) . ' monthly statement records.</p></div>';
    }

    // Get current status
    $total_transactions = $wpdb->get_var("SELECT COUNT(*) FROM swca_c3_bank_transactions");
    $transactions_with_balance = $wpdb->get_var("SELECT COUNT(*) FROM swca_c3_bank_transactions WHERE balance != 0");
    $total_statements = $wpdb->get_var("SELECT COUNT(*) FROM swca_c3_bank_statements");

    $first_transaction = $wpdb->get_row("SELECT post_date, description FROM swca_c3_bank_transactions ORDER BY post_date ASC LIMIT 1");
    $last_transaction = $wpdb->get_row("SELECT post_date, description FROM swca_c3_bank_transactions ORDER BY post_date DESC LIMIT 1");

    ?>
    <div class="wrap">
        <h1>💰 Calculate Running Balances</h1>
        <p class="description">Populate the balance column for all transactions and create monthly bank statement records</p>

        <!-- Current Status -->
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>Current Status</h2>
            <table class="widefat">
                <tr>
                    <td><strong>Total Bank Transactions:</strong></td>
                    <td><?php echo $total_transactions; ?> transactions</td>
                </tr>
                <tr>
                    <td><strong>Transactions with Balance:</strong></td>
                    <td><?php echo $transactions_with_balance; ?> transactions
                        <?php if ($transactions_with_balance == 0): ?>
                            <span style="color: #dc3545;">⚠️ No balances calculated yet</span>
                        <?php else: ?>
                            <span style="color: #28a745;">✓ Balances populated</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Monthly Statements:</strong></td>
                    <td><?php echo $total_statements; ?> statements
                        <?php if ($total_statements == 0): ?>
                            <span style="color: #dc3545;">⚠️ No statements created</span>
                        <?php else: ?>
                            <span style="color: #28a745;">✓ Statements created</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Transaction Date Range:</strong></td>
                    <td><?php
                        if ($first_transaction) {
                            echo date('M j, Y', strtotime($first_transaction->post_date)) . ' to ' .
                                 date('M j, Y', strtotime($last_transaction->post_date));
                        }
                    ?></td>
                </tr>
            </table>
        </div>

        <?php if ($transactions_with_balance == 0): ?>
        <!-- Instructions -->
        <div class="notice notice-info" style="padding: 15px; margin: 20px 0;">
            <h3>📋 How This Works</h3>
            <ol>
                <li><strong>Find Your Starting Balance:</strong> Check your bank statement for the balance on the first transaction date</li>
                <li><strong>Enter Starting Balance:</strong> Enter the balance in the form below</li>
                <li><strong>Calculate:</strong> The system will:
                    <ul>
                        <li>Calculate running balance for each transaction (starting balance + credits - debits)</li>
                        <li>Update the balance column for all transactions</li>
                        <li>Create monthly statement records automatically</li>
                    </ul>
                </li>
            </ol>
            <p><strong>Example:</strong> If your first transaction is <?php echo date('M j, Y', strtotime($first_transaction->post_date)); ?>,
            look at your bank statement from that month and find the balance BEFORE that first transaction.</p>
        </div>

        <!-- Calculate Form -->
        <div class="card" style="max-width: 800px;">
            <h2>Calculate Running Balances</h2>
            <form method="post" action="">
                <?php wp_nonce_field('calculate_balances_action'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="starting_date">Starting Date</label></th>
                        <td>
                            <input type="date" id="starting_date" name="starting_date"
                                   value="<?php echo esc_attr($first_transaction->post_date); ?>"
                                   class="regular-text" required>
                            <p class="description">Calculate balances starting from this date (defaults to first transaction)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="starting_balance">Starting Balance</label></th>
                        <td>
                            $<input type="number" id="starting_balance" name="starting_balance"
                                   step="0.01" placeholder="0.00" class="regular-text" required>
                            <p class="description">The account balance on <?php echo date('M j, Y', strtotime($first_transaction->post_date)); ?>
                            (check your bank statement)</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="calculate_balances" class="button button-primary"
                           value="Calculate Running Balances">
                </p>
            </form>
        </div>
        <?php else: ?>
        <!-- Already Calculated -->
        <div class="notice notice-success" style="padding: 15px;">
            <p><strong>✅ Balances Already Calculated</strong></p>
            <p>Running balances have been calculated for <?php echo $transactions_with_balance; ?> transactions.
            To recalculate, clear the current balances first:</p>
            <pre style="background: #f5f5f5; padding: 10px; margin: 10px 0;">UPDATE swca_c3_bank_transactions SET balance = 0;
DELETE FROM swca_c3_bank_statements WHERE notes LIKE 'Auto-generated%';</pre>
            <p>Then refresh this page to enter a new starting balance.</p>
        </div>

        <!-- View Results -->
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>View Results</h2>
            <p>
                <a href="admin.php?page=501c3PO-transaction-ledger" class="button button-primary">
                    📒 View Transaction Ledger
                </a>
            </p>
            <p class="description">The Transaction Ledger now shows running balances and reconciliation info</p>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
