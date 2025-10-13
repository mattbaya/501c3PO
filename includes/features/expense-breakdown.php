<?php
/**
 * Expense Category Breakdown
 * Pie chart showing where money goes by category
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Add menu item
add_action('admin_menu', 'expense_breakdown_menu', 27);
function expense_breakdown_menu() {
    add_submenu_page(
        'membership-management',
        'Expense Breakdown',
        '🥧 Expense Breakdown',
        'manage_options',
        '501c3PO-expense-breakdown',
        'expense_breakdown_page'
    );
}

function expense_breakdown_page() {
    global $wpdb;

    // Get date range filter
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

    // Build WHERE clause
    $where = "1=1";
    if (!empty($date_from)) {
        $where .= $wpdb->prepare(" AND post_date >= %s", $date_from);
    }
    if (!empty($date_to)) {
        $where .= $wpdb->prepare(" AND post_date <= %s", $date_to);
    }

    // Get expense data by category
    $category_data = $wpdb->get_results("
        SELECT
            COALESCE(NULLIF(category, ''), 'Uncategorized') as category,
            SUM(debit) as total_expenses,
            COUNT(*) as transaction_count
        FROM wp_swca_bank_transactions
        WHERE debit > 0 AND $where
        GROUP BY category
        ORDER BY total_expenses DESC
    ");

    // Get income data by category
    $income_category_data = $wpdb->get_results("
        SELECT
            COALESCE(NULLIF(category, ''), 'Uncategorized') as category,
            SUM(credit) as total_income,
            COUNT(*) as transaction_count
        FROM wp_swca_bank_transactions
        WHERE credit > 0 AND $where
        GROUP BY category
        ORDER BY total_income DESC
    ");

    // Calculate totals
    $total_expenses = array_sum(array_column($category_data, 'total_expenses'));
    $total_income = array_sum(array_column($income_category_data, 'total_income'));

    // Check if we have data
    $has_expenses = !empty($category_data) && $total_expenses > 0;
    $has_income = !empty($income_category_data) && $total_income > 0;

    // Get all transactions for detailed list
    $all_expenses = $wpdb->get_results("
        SELECT
            post_date,
            description,
            category,
            debit,
            notes
        FROM wp_swca_bank_transactions
        WHERE debit > 0 AND $where
        ORDER BY post_date DESC
        LIMIT 100
    ");

    ?>
    <div class="wrap">
        <h1>🥧 Expense Category Breakdown</h1>
        <p class="description">See where your money goes by category</p>

        <!-- Date Range Filter -->
        <div class="card" style="margin-bottom: 20px;">
            <h2>Filters</h2>
            <form method="get" action="">
                <input type="hidden" name="page" value="501c3PO-expense-breakdown">
                <table class="form-table">
                    <tr>
                        <th>Date Range</th>
                        <td>
                            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From">
                            to
                            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To">
                        </td>
                    </tr>
                </table>
                <?php submit_button('Apply Filters', 'secondary', 'submit', false); ?>
                <a href="?page=501c3PO-expense-breakdown" class="button">Clear Filters</a>
            </form>
        </div>

        <?php if (!$has_expenses && !$has_income): ?>
            <div class="notice notice-warning">
                <p><strong>No transaction data found.</strong></p>
                <p>Import bank statements and add categories to transactions to see breakdowns.</p>
                <p><a href="?page=501c3PO-transaction-ledger" class="button">Go to Transaction Ledger to Add Categories →</a></p>
            </div>
        <?php else: ?>

            <!-- Summary Stats -->
            <div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
                <h2>Summary</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <strong>Total Expenses:</strong><br>
                        <span style="color: #dc3545; font-size: 1.5em; font-weight: bold;">
                            $<?php echo number_format($total_expenses, 2); ?>
                        </span>
                    </div>
                    <div>
                        <strong>Total Income:</strong><br>
                        <span style="color: #28a745; font-size: 1.5em; font-weight: bold;">
                            $<?php echo number_format($total_income, 2); ?>
                        </span>
                    </div>
                    <div>
                        <strong>Net:</strong><br>
                        <span style="color: <?php echo ($total_income - $total_expenses) >= 0 ? '#28a745' : '#dc3545'; ?>; font-size: 1.5em; font-weight: bold;">
                            $<?php echo number_format($total_income - $total_expenses, 2); ?>
                        </span>
                    </div>
                    <div>
                        <strong>Expense Categories:</strong><br>
                        <span style="font-size: 1.5em; font-weight: bold;">
                            <?php echo count($category_data); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Charts Side by Side -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <?php if ($has_expenses): ?>
                <div style="background: white; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="text-align: center; margin-top: 0;">Expenses by Category</h3>
                    <canvas id="expensePieChart"></canvas>
                </div>
                <?php endif; ?>

                <?php if ($has_income): ?>
                <div style="background: white; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="text-align: center; margin-top: 0;">Income by Category</h3>
                    <canvas id="incomePieChart"></canvas>
                </div>
                <?php endif; ?>
            </div>

            <!-- Expense Category Table -->
            <?php if ($has_expenses): ?>
            <div class="card" style="margin-bottom: 20px;">
                <h2>Expense Categories</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Total Amount</th>
                            <th style="text-align: center;">Transactions</th>
                            <th style="text-align: right;">% of Total</th>
                            <th style="text-align: right;">Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_data as $row):
                            $percentage = ($row->total_expenses / $total_expenses) * 100;
                            $average = $row->transaction_count > 0 ? $row->total_expenses / $row->transaction_count : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($row->category); ?></strong></td>
                            <td style="text-align: right; color: #dc3545; font-weight: bold;">
                                $<?php echo number_format($row->total_expenses, 2); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php echo number_format($row->transaction_count); ?>
                            </td>
                            <td style="text-align: right;">
                                <?php echo number_format($percentage, 1); ?>%
                            </td>
                            <td style="text-align: right; color: #666;">
                                $<?php echo number_format($average, 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td>TOTAL</td>
                            <td style="text-align: right; color: #dc3545;">
                                $<?php echo number_format($total_expenses, 2); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php echo number_format(array_sum(array_column($category_data, 'transaction_count'))); ?>
                            </td>
                            <td style="text-align: right;">100%</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>

            <!-- Income Category Table -->
            <?php if ($has_income): ?>
            <div class="card" style="margin-bottom: 20px;">
                <h2>Income Categories</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Total Amount</th>
                            <th style="text-align: center;">Transactions</th>
                            <th style="text-align: right;">% of Total</th>
                            <th style="text-align: right;">Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($income_category_data as $row):
                            $percentage = ($row->total_income / $total_income) * 100;
                            $average = $row->transaction_count > 0 ? $row->total_income / $row->transaction_count : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($row->category); ?></strong></td>
                            <td style="text-align: right; color: #28a745; font-weight: bold;">
                                $<?php echo number_format($row->total_income, 2); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php echo number_format($row->transaction_count); ?>
                            </td>
                            <td style="text-align: right;">
                                <?php echo number_format($percentage, 1); ?>%
                            </td>
                            <td style="text-align: right; color: #666;">
                                $<?php echo number_format($average, 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td>TOTAL</td>
                            <td style="text-align: right; color: #28a745;">
                                $<?php echo number_format($total_income, 2); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php echo number_format(array_sum(array_column($income_category_data, 'transaction_count'))); ?>
                            </td>
                            <td style="text-align: right;">100%</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>

            <!-- Recent Expenses -->
            <div class="card">
                <h2>Recent Expenses (Last 100)</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th style="text-align: right;">Amount</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_expenses as $txn): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($txn->post_date)); ?></td>
                            <td><?php echo esc_html($txn->description); ?></td>
                            <td>
                                <strong><?php echo esc_html($txn->category ?: 'Uncategorized'); ?></strong>
                            </td>
                            <td style="text-align: right; color: #dc3545; font-weight: bold;">
                                $<?php echo number_format($txn->debit, 2); ?>
                            </td>
                            <td><?php echo esc_html($txn->notes); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top: 15px;">
                    <em>To categorize transactions, visit the <a href="?page=501c3PO-transaction-ledger">Transaction Ledger</a> and click on any Category field to edit.</em>
                </p>
            </div>

        <?php endif; ?>
    </div>

    <?php if ($has_expenses || $has_income): ?>
    <!-- Chart.js Script -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Color palette for pie charts
        const colors = [
            '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6610f2',
            '#fd7e14', '#20c997', '#e83e8c', '#6c757d', '#343a40', '#f8f9fa'
        ];

        <?php if ($has_expenses): ?>
        // Expense Pie Chart
        const expenseCtx = document.getElementById('expensePieChart');
        const expenseData = {
            labels: <?php echo json_encode(array_column($category_data, 'category')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($category_data, 'total_expenses')); ?>,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2
            }]
        };

        new Chart(expenseCtx, {
            type: 'doughnut',
            data: expenseData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': $' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($has_income): ?>
        // Income Pie Chart
        const incomeCtx = document.getElementById('incomePieChart');
        const incomeData = {
            labels: <?php echo json_encode(array_column($income_category_data, 'category')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($income_category_data, 'total_income')); ?>,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2
            }]
        };

        new Chart(incomeCtx, {
            type: 'doughnut',
            data: incomeData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': $' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
    <?php endif; ?>
    <?php
}
