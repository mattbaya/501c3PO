<?php
/**
 * Bank Transaction Management Feature
 * Import and manage bank transactions
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Bank Transactions menu
 */
add_action('admin_menu', 'five01c3po_add_bank_transactions_menu', 21);

function five01c3po_add_bank_transactions_menu() {
    add_submenu_page(
        'membership-management',
        'Bank Transactions',
        '🏦 Bank Transactions',
        'manage_options',
        '501c3PO-bank-transactions',
        'five01c3po_bank_transactions_page'
    );

    add_submenu_page(
        'membership-management',
        'Financial Transactions',
        '💰 Transaction Viewer',
        'manage_options',
        '501c3PO-transaction-viewer',
        'five01c3po_transaction_viewer_page'
    );
}

/**
 * Bank Transactions page
 */
function five01c3po_bank_transactions_page() {
    global $wpdb;
    $bank_table = $wpdb->prefix . 'swca_bank_transactions';

    // Handle import
    if (isset($_POST['import_bank_csv']) && !empty($_FILES['bank_csv_file']['tmp_name'])) {
        check_admin_referer('five01c3po_import_bank');
        $result = five01c3po_import_bank_csv($_FILES['bank_csv_file']['tmp_name']);
        echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
    }

    // Handle clear
    if (isset($_POST['clear_bank_data'])) {
        check_admin_referer('five01c3po_clear_bank');
        $wpdb->query("TRUNCATE TABLE $bank_table");
        echo '<div class="notice notice-success"><p>Bank transaction data cleared successfully.</p></div>';
    }

    // Get current count and summary
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $bank_table");
    $total_credits = $wpdb->get_var("SELECT SUM(credit) FROM $bank_table");
    $total_debits = $wpdb->get_var("SELECT SUM(debit) FROM $bank_table");

    ?>
    <div class="wrap">
        <h1>🏦 Bank Transaction Management</h1>

        <div class="card">
            <h2>Current Status</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Transactions in database:</th>
                    <td><strong><?php echo esc_html(number_format($count)); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Total Credits:</th>
                    <td><strong>$<?php echo esc_html(number_format($total_credits ?? 0, 2)); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Total Debits:</th>
                    <td><strong>$<?php echo esc_html(number_format($total_debits ?? 0, 2)); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">Net:</th>
                    <td><strong>$<?php echo esc_html(number_format(($total_credits ?? 0) - ($total_debits ?? 0), 2)); ?></strong></td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>📥 Import Bank CSV</h2>
            <p>Upload a CSV file from your bank with the following columns:</p>
            <ul style="margin-left: 20px;">
                <li><strong>Account Number</strong> - Bank account number</li>
                <li><strong>Post Date</strong> - Transaction date (M/D/YYYY format)</li>
                <li><strong>Check</strong> - Check number (if applicable)</li>
                <li><strong>Description</strong> - Transaction description</li>
                <li><strong>Debit</strong> - Amount debited (money out)</li>
                <li><strong>Credit</strong> - Amount credited (money in)</li>
                <li><strong>Status</strong> - Transaction status</li>
                <li><strong>Balance</strong> - Account balance after transaction</li>
            </ul>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('five01c3po_import_bank'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Bank CSV File</th>
                        <td>
                            <input type="file" name="bank_csv_file" accept=".csv" required>
                            <p class="description">Select your bank transaction CSV file</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Import Bank CSV', 'primary', 'import_bank_csv'); ?>
            </form>
        </div>

        <div class="card">
            <h2>⚠️ Clear Data</h2>
            <p><strong>Warning:</strong> This will permanently delete all bank transactions from the database.</p>
            <form method="post" onsubmit="return confirm('Are you sure you want to delete ALL bank transaction data? This cannot be undone!');">
                <?php wp_nonce_field('five01c3po_clear_bank'); ?>
                <?php submit_button('Clear All Bank Data', 'delete', 'clear_bank_data'); ?>
            </form>
        </div>

        <div class="card">
            <h2>📊 Recent Transactions</h2>
            <?php five01c3po_display_recent_bank_transactions(); ?>
        </div>
    </div>
    <?php
}

/**
 * Import bank CSV function
 */
function five01c3po_import_bank_csv($file_path) {
    global $wpdb;
    $bank_table = $wpdb->prefix . 'swca_bank_transactions';

    if (!file_exists($file_path)) {
        return 'File not found';
    }

    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return 'Could not open file';
    }

    // Get headers
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return 'Could not read headers';
    }

    $imported = 0;
    $errors = 0;

    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < count($headers)) {
            $errors++;
            continue;
        }

        $row = array_combine($headers, $data);

        // Parse date - try multiple formats
        $date_str = $row['Post Date'] ?? '';
        $date = DateTime::createFromFormat('n/j/Y', $date_str);
        if (!$date) {
            $date = DateTime::createFromFormat('m/d/Y', $date_str);
        }
        if (!$date) {
            $date = DateTime::createFromFormat('Y-m-d', $date_str);
        }

        if (!$date) {
            $errors++;
            continue;
        }

        // Prepare data
        $insert_data = [
            'account_number' => $row['Account Number'] ?? '',
            'post_date' => $date->format('Y-m-d'),
            'check_number' => $row['Check'] ?? '',
            'description' => $row['Description'] ?? '',
            'debit' => !empty($row['Debit']) ? floatval($row['Debit']) : 0,
            'credit' => !empty($row['Credit']) ? floatval($row['Credit']) : 0,
            'status' => $row['Status'] ?? '',
            'balance' => !empty($row['Balance']) ? floatval($row['Balance']) : 0,
        ];

        // DUPLICATE PREVENTION: Check if this transaction already exists
        // Match on date + credit + debit (unique combination for a transaction)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $bank_table
             WHERE post_date = %s
             AND credit = %f
             AND debit = %f
             LIMIT 1",
            $insert_data['post_date'],
            $insert_data['credit'],
            $insert_data['debit']
        ));

        if ($existing) {
            // Skip duplicate - already imported
            continue;
        }

        $result = $wpdb->insert($bank_table, $insert_data);
        if ($result) {
            $imported++;
        } else {
            $errors++;
        }
    }

    fclose($handle);
    return "Import complete: $imported transactions imported, $errors errors";
}

/**
 * Display recent bank transactions
 */
function five01c3po_display_recent_bank_transactions() {
    global $wpdb;
    $bank_table = $wpdb->prefix . 'swca_bank_transactions';

    $transactions = $wpdb->get_results("SELECT * FROM $bank_table ORDER BY post_date DESC LIMIT 20");

    if (empty($transactions)) {
        echo '<p>No bank transactions found. Import a CSV file to get started.</p>';
        return;
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>Date</th><th>Description</th><th>Check #</th><th>Debit</th><th>Credit</th><th>Balance</th><th>Status</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($transactions as $txn) {
        echo '<tr>';
        echo '<td>' . esc_html(date('M d, Y', strtotime($txn->post_date))) . '</td>';
        echo '<td>' . esc_html($txn->description) . '</td>';
        echo '<td>' . esc_html($txn->check_number) . '</td>';
        echo '<td style="color: #d63638;">' . ($txn->debit > 0 ? '-$' . number_format($txn->debit, 2) : '') . '</td>';
        echo '<td style="color: #00a32a;">' . ($txn->credit > 0 ? '+$' . number_format($txn->credit, 2) : '') . '</td>';
        echo '<td>$' . number_format($txn->balance, 2) . '</td>';
        echo '<td>' . esc_html($txn->status) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p><em>Showing 20 most recent transactions</em></p>';
}

/**
 * Transaction Viewer page
 */
function five01c3po_transaction_viewer_page() {
    ?>
    <div class="wrap">
        <h1>💰 Financial Transaction Viewer</h1>

        <p class="description">View combined bank and Stripe transactions directly in WordPress.</p>

        <div class="card">
            <h2>📊 How to Display Transactions</h2>
            <p>Create a new page (or edit an existing one) and add this shortcode:</p>
            <div style="background: #f0f0f0; padding: 15px; border-radius: 4px; font-family: monospace; margin: 15px 0;">
                [five01c3po_bank_transactions]
            </div>
            <p>This will display all bank transactions in a searchable, sortable table.</p>

            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="button button-primary">
                    Create New Page with Shortcode
                </a>
            </p>
        </div>

        <div class="card">
            <h2>📝 Shortcode Options</h2>
            <p>The shortcode accepts optional parameters:</p>
            <ul style="margin-left: 20px;">
                <li><code>[five01c3po_bank_transactions limit="50"]</code> - Show only 50 most recent transactions</li>
                <li><code>[five01c3po_bank_transactions type="credit"]</code> - Show only credits (income)</li>
                <li><code>[five01c3po_bank_transactions type="debit"]</code> - Show only debits (expenses)</li>
            </ul>
        </div>

        <div class="card">
            <h2>🔍 Preview</h2>
            <?php
            // Show a preview of the shortcode output
            echo do_shortcode('[five01c3po_bank_transactions limit="10"]');
            ?>
        </div>
    </div>
    <?php
}

/**
 * Bank Transactions Shortcode
 */
add_shortcode('five01c3po_bank_transactions', 'five01c3po_bank_transactions_shortcode');

function five01c3po_bank_transactions_shortcode($atts) {
    global $wpdb;

    // Parse attributes
    $atts = shortcode_atts(array(
        'limit' => 100,
        'type' => '', // credit, debit, or empty for all
    ), $atts);

    // Use hardcoded table name due to prefix inconsistency
    // Bank tables use wp_ prefix, not swca_ prefix
    $bank_table = 'wp_swca_bank_transactions';

    // Build query
    $where = '';
    if ($atts['type'] === 'credit') {
        $where = 'WHERE credit > 0';
    } elseif ($atts['type'] === 'debit') {
        $where = 'WHERE debit > 0';
    }

    $limit = intval($atts['limit']);
    $transactions = $wpdb->get_results("
        SELECT * FROM $bank_table
        $where
        ORDER BY post_date DESC
        LIMIT $limit
    ");

    if (empty($transactions)) {
        return '<p>No bank transactions found. Import a CSV file to get started.</p>';
    }

    // Calculate totals
    $total_credits = array_sum(array_column($transactions, 'credit'));
    $total_debits = array_sum(array_column($transactions, 'debit'));

    // Build HTML output
    ob_start();
    ?>
    <div class="mm-transaction-viewer">
        <div class="transaction-summary" style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3 style="margin-top: 0;">Summary</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <strong>Total Transactions:</strong> <?php echo count($transactions); ?>
                </div>
                <div style="color: #00a32a;">
                    <strong>Total Credits:</strong> $<?php echo number_format($total_credits, 2); ?>
                </div>
                <div style="color: #d63638;">
                    <strong>Total Debits:</strong> $<?php echo number_format($total_debits, 2); ?>
                </div>
                <div>
                    <strong>Net:</strong> $<?php echo number_format($total_credits - $total_debits, 2); ?>
                </div>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Check #</th>
                    <th style="text-align: right;">Debit</th>
                    <th style="text-align: right;">Credit</th>
                    <th style="text-align: right;">Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $txn): ?>
                <tr>
                    <td><?php echo esc_html(date('M d, Y', strtotime($txn->post_date))); ?></td>
                    <td><?php echo esc_html($txn->description); ?></td>
                    <td><?php echo esc_html($txn->check_number); ?></td>
                    <td style="text-align: right; color: #d63638;">
                        <?php echo $txn->debit > 0 ? '-$' . number_format($txn->debit, 2) : ''; ?>
                    </td>
                    <td style="text-align: right; color: #00a32a;">
                        <?php echo $txn->credit > 0 ? '+$' . number_format($txn->credit, 2) : ''; ?>
                    </td>
                    <td style="text-align: right;">
                        $<?php echo number_format($txn->balance, 2); ?>
                    </td>
                    <td><?php echo esc_html($txn->status); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($transactions) >= $limit): ?>
        <p style="margin-top: 15px; font-style: italic; color: #666;">
            Showing <?php echo $limit; ?> most recent transactions.
        </p>
        <?php endif; ?>
    </div>

    <style>
        .mm-transaction-viewer table {
            border-collapse: collapse;
            width: 100%;
        }
        .mm-transaction-viewer th,
        .mm-transaction-viewer td {
            padding: 10px;
            text-align: left;
        }
        .mm-transaction-viewer tr:nth-child(even) {
            background-color: #f9f9f9;
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
