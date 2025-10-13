<?php
/**
 * Income & Expense Graph
 * Visual representation of bank statement data over time
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Add menu item
add_action('admin_menu', 'income_expense_graph_menu', 25);
function income_expense_graph_menu() {
    add_submenu_page(
        'membership-management',
        'Income & Expense Graph',
        '📊 Income & Expense Graph',
        'manage_options',
        '501c3PO-income-expense-graph',
        'income_expense_graph_page'
    );
}

function income_expense_graph_page() {
    global $wpdb;

    // Get monthly summary data
    $monthly_data = $wpdb->get_results("
        SELECT
            DATE_FORMAT(post_date, '%Y-%m') as month,
            SUM(COALESCE(credit, 0)) as total_income,
            SUM(COALESCE(debit, 0)) as total_expenses,
            COUNT(*) as transaction_count
        FROM wp_swca_bank_transactions
        GROUP BY DATE_FORMAT(post_date, '%Y-%m')
        ORDER BY month
    ");

    // Get overall stats
    $stats = $wpdb->get_row("
        SELECT
            MIN(post_date) as earliest_date,
            MAX(post_date) as latest_date,
            COUNT(*) as total_transactions,
            SUM(COALESCE(credit, 0)) as total_income,
            SUM(COALESCE(debit, 0)) as total_expenses
        FROM wp_swca_bank_transactions
    ");

    // Prepare data for Chart.js
    $months = [];
    $income = [];
    $expenses = [];
    $net = [];

    foreach ($monthly_data as $row) {
        $months[] = $row->month;
        $income[] = floatval($row->total_income);
        $expenses[] = floatval($row->total_expenses);
        $net[] = floatval($row->total_income) - floatval($row->total_expenses);
    }

    ?>
    <div class="wrap">
        <h1>📊 Income & Expense Graph</h1>

        <?php if (empty($monthly_data)): ?>
            <div class="notice notice-warning">
                <p><strong>No bank transaction data found.</strong></p>
                <p>Import bank statements via <a href="?page=501c3po-bank-transactions">Bank Transactions</a> to see your income and expense graph.</p>
            </div>
        <?php else: ?>

            <!-- Summary Statistics -->
            <div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
                <h2>Summary Statistics</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px;">
                    <div>
                        <strong>Date Range:</strong><br>
                        <?php echo date('M j, Y', strtotime($stats->earliest_date)); ?> -
                        <?php echo date('M j, Y', strtotime($stats->latest_date)); ?>
                    </div>
                    <div>
                        <strong>Total Transactions:</strong><br>
                        <?php echo number_format($stats->total_transactions); ?>
                    </div>
                    <div>
                        <strong>Total Income:</strong><br>
                        <span style="color: #28a745; font-size: 1.2em;">$<?php echo number_format($stats->total_income, 2); ?></span>
                    </div>
                    <div>
                        <strong>Total Expenses:</strong><br>
                        <span style="color: #dc3545; font-size: 1.2em;">$<?php echo number_format($stats->total_expenses, 2); ?></span>
                    </div>
                    <div>
                        <strong>Net:</strong><br>
                        <?php
                        $net_amount = $stats->total_income - $stats->total_expenses;
                        $color = $net_amount >= 0 ? '#28a745' : '#dc3545';
                        ?>
                        <span style="color: <?php echo $color; ?>; font-size: 1.2em; font-weight: bold;">
                            $<?php echo number_format($net_amount, 2); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Chart Controls -->
            <div style="margin-bottom: 20px;">
                <label for="chartType" style="font-weight: bold; margin-right: 10px;">Chart Type:</label>
                <select id="chartType" style="padding: 5px; font-size: 14px;">
                    <option value="line">Line Chart</option>
                    <option value="bar">Bar Chart</option>
                </select>

                <label for="dataView" style="font-weight: bold; margin-left: 30px; margin-right: 10px;">Show:</label>
                <select id="dataView" style="padding: 5px; font-size: 14px;">
                    <option value="all">Income & Expenses</option>
                    <option value="net">Net Only</option>
                    <option value="income">Income Only</option>
                    <option value="expenses">Expenses Only</option>
                </select>
            </div>

            <!-- Chart Canvas -->
            <div style="background: white; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <canvas id="incomeExpenseChart" style="max-height: 400px;"></canvas>
            </div>

            <!-- Monthly Data Table -->
            <div style="margin-top: 30px;">
                <h2>Monthly Breakdown</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th style="text-align: right;">Income</th>
                            <th style="text-align: right;">Expenses</th>
                            <th style="text-align: right;">Net</th>
                            <th style="text-align: center;">Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_data as $row):
                            $month_net = $row->total_income - $row->total_expenses;
                            $net_color = $month_net >= 0 ? '#28a745' : '#dc3545';
                        ?>
                        <tr>
                            <td><strong><?php echo date('F Y', strtotime($row->month . '-01')); ?></strong></td>
                            <td style="text-align: right; color: #28a745;">$<?php echo number_format($row->total_income, 2); ?></td>
                            <td style="text-align: right; color: #dc3545;">$<?php echo number_format($row->total_expenses, 2); ?></td>
                            <td style="text-align: right; color: <?php echo $net_color; ?>; font-weight: bold;">
                                $<?php echo number_format($month_net, 2); ?>
                            </td>
                            <td style="text-align: center;"><?php echo number_format($row->transaction_count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td>TOTAL</td>
                            <td style="text-align: right; color: #28a745;">$<?php echo number_format($stats->total_income, 2); ?></td>
                            <td style="text-align: right; color: #dc3545;">$<?php echo number_format($stats->total_expenses, 2); ?></td>
                            <td style="text-align: right; color: <?php echo $color; ?>;">
                                $<?php echo number_format($net_amount, 2); ?>
                            </td>
                            <td style="text-align: center;"><?php echo number_format($stats->total_transactions); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Chart.js Script -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
                const ctx = document.getElementById('incomeExpenseChart');

                const chartData = {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [
                        {
                            label: 'Income',
                            data: <?php echo json_encode($income); ?>,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            borderWidth: 2,
                            tension: 0.4
                        },
                        {
                            label: 'Expenses',
                            data: <?php echo json_encode($expenses); ?>,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 2,
                            tension: 0.4
                        },
                        {
                            label: 'Net',
                            data: <?php echo json_encode($net); ?>,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            borderWidth: 3,
                            borderDash: [5, 5],
                            tension: 0.4
                        }
                    ]
                };

                const config = {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Monthly Income & Expenses',
                                font: {
                                    size: 18
                                }
                            },
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += '$' + context.parsed.y.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                                    }
                                }
                            }
                        }
                    }
                };

                const chart = new Chart(ctx, config);

                // Chart type switcher
                document.getElementById('chartType').addEventListener('change', function(e) {
                    chart.config.type = e.target.value;
                    chart.update();
                });

                // Data view switcher
                document.getElementById('dataView').addEventListener('change', function(e) {
                    const value = e.target.value;
                    chart.data.datasets.forEach((dataset, index) => {
                        if (value === 'all') {
                            dataset.hidden = false;
                        } else if (value === 'net') {
                            dataset.hidden = index !== 2; // Show only Net
                        } else if (value === 'income') {
                            dataset.hidden = index !== 0; // Show only Income
                        } else if (value === 'expenses') {
                            dataset.hidden = index !== 1; // Show only Expenses
                        }
                    });
                    chart.update();
                });
            </script>

        <?php endif; ?>
    </div>
    <?php
}
