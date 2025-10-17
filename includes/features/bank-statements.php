<?php
/**
 * Bank Statement Management
 * Admin interface for importing and managing monthly bank statement balances
 */

// Add menu item
add_action('admin_menu', 'five01c3po_bank_statements_menu');

function five01c3po_bank_statements_menu() {
    add_submenu_page(
        '501c3PO',
        'Bank Statements',
        'Bank Statements',
        'manage_options',
        '501c3PO-bank-statements',
        'five01c3po_bank_statements_page'
    );
}

// Main page
function five01c3po_bank_statements_page() {
    global $wpdb;

    // Handle form submission
    if (isset($_POST['add_statement']) && check_admin_referer('add_bank_statement')) {
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $starting_balance = floatval($_POST['starting_balance']);
        $ending_balance = floatval($_POST['ending_balance']);
        $total_credits = floatval($_POST['total_credits']);
        $total_debits = floatval($_POST['total_debits']);
        $notes = sanitize_textarea_field($_POST['notes']);

        // Insert or update statement
        $wpdb->query($wpdb->prepare("
            INSERT INTO swca_c3_bank_statements
            (statement_period_start, statement_period_end, starting_balance, ending_balance,
             total_credits, total_debits, notes)
            VALUES (%s, %s, %f, %f, %f, %f, %s)
            ON DUPLICATE KEY UPDATE
            starting_balance = VALUES(starting_balance),
            ending_balance = VALUES(ending_balance),
            total_credits = VALUES(total_credits),
            total_debits = VALUES(total_debits),
            notes = VALUES(notes)
        ", $start_date, $end_date, $starting_balance, $ending_balance, $total_credits, $total_debits, $notes));

        echo '<div class="notice notice-success"><p>Bank statement saved successfully!</p></div>';
    }

    // Handle deletion
    if (isset($_POST['delete_statement']) && check_admin_referer('delete_bank_statement')) {
        $id = intval($_POST['statement_id']);
        $wpdb->delete('swca_c3_bank_statements', array('id' => $id));
        echo '<div class="notice notice-success"><p>Bank statement deleted.</p></div>';
    }

    // Get all statements
    $statements = $wpdb->get_results("
        SELECT * FROM swca_c3_bank_statements
        ORDER BY statement_period_start DESC
    ");

    ?>
    <div class="wrap">
        <h1>💳 Bank Statements</h1>
        <p>Manage monthly bank statement balances for reconciliation and balance tracking.</p>

        <!-- Add New Statement Form -->
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>Add Bank Statement</h2>
            <form method="post" action="">
                <?php wp_nonce_field('add_bank_statement'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="start_date">Statement Period Start</label></th>
                        <td><input type="date" name="start_date" id="start_date" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="end_date">Statement Period End</label></th>
                        <td><input type="date" name="end_date" id="end_date" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="starting_balance">Starting Balance</label></th>
                        <td>
                            <input type="number" name="starting_balance" id="starting_balance" step="0.01" required class="regular-text">
                            <p class="description">Beginning balance for this statement period</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ending_balance">Ending Balance</label></th>
                        <td>
                            <input type="number" name="ending_balance" id="ending_balance" step="0.01" required class="regular-text">
                            <p class="description">Ending balance from bank statement</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="total_credits">Total Credits</label></th>
                        <td>
                            <input type="number" name="total_credits" id="total_credits" step="0.01" required class="regular-text">
                            <p class="description">Total deposits/credits for period</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="total_debits">Total Debits</label></th>
                        <td>
                            <input type="number" name="total_debits" id="total_debits" step="0.01" required class="regular-text">
                            <p class="description">Total withdrawals/debits for period</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="notes">Notes</label></th>
                        <td>
                            <textarea name="notes" id="notes" rows="3" class="large-text"></textarea>
                            <p class="description">Optional notes about this statement</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="add_statement" class="button button-primary" value="Save Statement">
                </p>
            </form>
        </div>

        <!-- Existing Statements -->
        <h2>Existing Bank Statements</h2>

        <?php if (empty($statements)): ?>
            <div class="notice notice-info">
                <p>No bank statements have been added yet. Add your first statement above to begin tracking balances.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Starting Balance</th>
                        <th>Ending Balance</th>
                        <th>Net Change</th>
                        <th>Credits</th>
                        <th>Debits</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statements as $stmt): ?>
                        <?php
                        $net_change = $stmt->ending_balance - $stmt->starting_balance;
                        $change_class = $net_change >= 0 ? 'positive' : 'negative';
                        $change_symbol = $net_change >= 0 ? '+' : '';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo date('M j', strtotime($stmt->statement_period_start)); ?></strong> -
                                <strong><?php echo date('M j, Y', strtotime($stmt->statement_period_end)); ?></strong>
                            </td>
                            <td>$<?php echo number_format($stmt->starting_balance, 2); ?></td>
                            <td>$<?php echo number_format($stmt->ending_balance, 2); ?></td>
                            <td style="color: <?php echo $net_change >= 0 ? 'green' : 'red'; ?>">
                                <?php echo $change_symbol; ?>$<?php echo number_format(abs($net_change), 2); ?>
                            </td>
                            <td>$<?php echo number_format($stmt->total_credits, 2); ?></td>
                            <td>$<?php echo number_format($stmt->total_debits, 2); ?></td>
                            <td><?php echo esc_html($stmt->notes); ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('delete_bank_statement'); ?>
                                    <input type="hidden" name="statement_id" value="<?php echo $stmt->id; ?>">
                                    <button type="submit" name="delete_statement" class="button button-small"
                                            onclick="return confirm('Delete this statement?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="8">
                            <?php
                            $first = end($statements);
                            $last = reset($statements);
                            $total_change = $last->ending_balance - $first->starting_balance;
                            ?>
                            <strong>Total Change:</strong>
                            $<?php echo number_format($first->starting_balance, 2); ?> →
                            $<?php echo number_format($last->ending_balance, 2); ?>
                            (<span style="color: <?php echo $total_change >= 0 ? 'green' : 'red'; ?>">
                                <?php echo $total_change >= 0 ? '+' : ''; ?>$<?php echo number_format(abs($total_change), 2); ?>
                            </span>)
                        </th>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>

        <!-- Quick Import Helper -->
        <div class="card" style="margin-top: 30px;">
            <h3>💡 Quick Import Tip</h3>
            <p>To import multiple statements at once, you can use the form above repeatedly with data from your PDF bank statements. Each statement should include:</p>
            <ul>
                <li><strong>Period dates:</strong> First and last day of the statement month</li>
                <li><strong>Starting balance:</strong> Should match previous month's ending balance</li>
                <li><strong>Ending balance:</strong> From the bank statement</li>
                <li><strong>Credits/Debits:</strong> Total amounts from the statement summary</li>
            </ul>
        </div>
    </div>

    <style>
        .positive { color: green; }
        .negative { color: red; }
    </style>
    <?php
}
