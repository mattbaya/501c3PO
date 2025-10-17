<?php
/**
 * Year-over-Year Comparison
 * Compare income and expenses across multiple years
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Add menu item
add_action('admin_menu', 'year_over_year_comparison_menu', 26);
function year_over_year_comparison_menu() {
    add_submenu_page(
        'membership-management',
        'Year-over-Year Comparison',
        '📈 Year-over-Year',
        'manage_options',
        '501c3PO-year-over-year',
        'year_over_year_comparison_page'
    );
}

function year_over_year_comparison_page() {
    global $wpdb;

    // Get available years
    $years = $wpdb->get_col("
        SELECT DISTINCT YEAR(post_date) as year
        FROM swca_c3_bank_transactions
        ORDER BY year DESC
    ");

    if (empty($years)) {
        ?>
        <div class="wrap">
            <h1>📈 Year-over-Year Comparison</h1>
            <div class="notice notice-warning">
                <p><strong>No bank transaction data found.</strong></p>
                <p>Import bank statements to see year-over-year comparisons.</p>
            </div>
        </div>
        <?php
        return;
    }

    // Get data for each year, organized by month
    $years_data = [];
    $all_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    foreach ($years as $year) {
        $year_data = $wpdb->get_results($wpdb->prepare("
            SELECT
                MONTH(post_date) as month_num,
                DATE_FORMAT(post_date, '%%b') as month,
                SUM(COALESCE(credit, 0)) as income,
                SUM(COALESCE(debit, 0)) as expenses,
                COUNT(*) as transaction_count
            FROM swca_c3_bank_transactions
            WHERE YEAR(post_date) = %d
            GROUP BY MONTH(post_date)
            ORDER BY MONTH(post_date)
        ", $year));

        // Create array indexed by month for easier lookup
        $monthly = array_fill(1, 12, ['income' => 0, 'expenses' => 0, 'net' => 0]);
        foreach ($year_data as $row) {
            $monthly[$row->month_num] = [
                'income' => floatval($row->income),
                'expenses' => floatval($row->expenses),
                'net' => floatval($row->income) - floatval($row->expenses)
            ];
        }

        $years_data[$year] = $monthly;
    }

    // Calculate year totals
    $year_totals = [];
    foreach ($years as $year) {
        $totals = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(COALESCE(credit, 0)) as total_income,
                SUM(COALESCE(debit, 0)) as total_expenses,
                COUNT(*) as transaction_count
            FROM swca_c3_bank_transactions
            WHERE YEAR(post_date) = %d
        ", $year));

        $year_totals[$year] = [
            'income' => floatval($totals->total_income),
            'expenses' => floatval($totals->total_expenses),
            'net' => floatval($totals->total_income) - floatval($totals->total_expenses),
            'count' => intval($totals->transaction_count)
        ];
    }

    // Prepare chart data
    $income_by_year = [];
    $expenses_by_year = [];
    $net_by_year = [];

    foreach ($years as $year) {
        $income_by_year[$year] = array_column($years_data[$year], 'income');
        $expenses_by_year[$year] = array_column($years_data[$year], 'expenses');
        $net_by_year[$year] = array_column($years_data[$year], 'net');
    }

    ?>
    <div class="wrap">
        <h1>📈 Year-over-Year Comparison</h1>
        <p class="description">Compare income and expenses across multiple years</p>

        <!-- Year Totals Summary -->
        <div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
            <h2>Annual Totals</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th style="text-align: right;">Income</th>
                        <th style="text-align: right;">Expenses</th>
                        <th style="text-align: right;">Net</th>
                        <th style="text-align: center;">Transactions</th>
                        <th style="text-align: right;">Growth</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $prev_net = null;
                    foreach ($years as $year):
                        $data = $year_totals[$year];
                        $net_color = $data['net'] >= 0 ? '#28a745' : '#dc3545';

                        // Calculate growth percentage
                        $growth = null;
                        $growth_color = '#666';
                        if ($prev_net !== null && $prev_net != 0) {
                            $growth = (($data['net'] - $prev_net) / abs($prev_net)) * 100;
                            $growth_color = $growth >= 0 ? '#28a745' : '#dc3545';
                        }
                        $prev_net = $data['net'];
                    ?>
                    <tr>
                        <td><strong><?php echo $year; ?></strong></td>
                        <td style="text-align: right; color: #28a745;">$<?php echo number_format($data['income'], 2); ?></td>
                        <td style="text-align: right; color: #dc3545;">$<?php echo number_format($data['expenses'], 2); ?></td>
                        <td style="text-align: right; color: <?php echo $net_color; ?>; font-weight: bold;">
                            $<?php echo number_format($data['net'], 2); ?>
                        </td>
                        <td style="text-align: center;"><?php echo number_format($data['count']); ?></td>
                        <td style="text-align: right; color: <?php echo $growth_color; ?>;">
                            <?php if ($growth !== null): ?>
                                <?php echo $growth > 0 ? '▲' : '▼'; ?> <?php echo number_format(abs($growth), 1); ?>%
                            <?php else: ?>
                                <em style="color: #999;">—</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Chart Type Selector -->
        <div style="margin-bottom: 20px;">
            <label for="comparisonType" style="font-weight: bold; margin-right: 10px;">Show:</label>
            <select id="comparisonType" style="padding: 5px; font-size: 14px;">
                <option value="income">Income Comparison</option>
                <option value="expenses">Expenses Comparison</option>
                <option value="net">Net Comparison</option>
            </select>
        </div>

        <!-- Chart Canvas -->
        <div style="background: white; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <canvas id="yearOverYearChart" style="max-height: 400px;"></canvas>
        </div>

        <!-- Monthly Breakdown Tables -->
        <h2>Monthly Breakdown</h2>
        <?php foreach ($years as $year): ?>
            <div class="card" style="margin-bottom: 20px;">
                <h3><?php echo $year; ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th style="text-align: right;">Income</th>
                            <th style="text-align: right;">Expenses</th>
                            <th style="text-align: right;">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($m = 1; $m <= 12; $m++):
                            $data = $years_data[$year][$m];
                            $net_color = $data['net'] >= 0 ? '#28a745' : '#dc3545';
                            $has_data = $data['income'] > 0 || $data['expenses'] > 0;
                        ?>
                        <tr <?php echo !$has_data ? 'style="opacity: 0.4;"' : ''; ?>>
                            <td><?php echo $all_months[$m - 1]; ?></td>
                            <td style="text-align: right; color: #28a745;">
                                <?php echo $data['income'] > 0 ? '$' . number_format($data['income'], 2) : '—'; ?>
                            </td>
                            <td style="text-align: right; color: #dc3545;">
                                <?php echo $data['expenses'] > 0 ? '$' . number_format($data['expenses'], 2) : '—'; ?>
                            </td>
                            <td style="text-align: right; color: <?php echo $net_color; ?>; font-weight: bold;">
                                <?php echo $has_data ? '$' . number_format($data['net'], 2) : '—'; ?>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td>TOTAL</td>
                            <td style="text-align: right; color: #28a745;">
                                $<?php echo number_format($year_totals[$year]['income'], 2); ?>
                            </td>
                            <td style="text-align: right; color: #dc3545;">
                                $<?php echo number_format($year_totals[$year]['expenses'], 2); ?>
                            </td>
                            <td style="text-align: right; color: <?php echo $year_totals[$year]['net'] >= 0 ? '#28a745' : '#dc3545'; ?>;">
                                $<?php echo number_format($year_totals[$year]['net'], 2); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Chart.js Script -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const ctx = document.getElementById('yearOverYearChart');
        const months = <?php echo json_encode($all_months); ?>;

        // Define colors for each year
        const yearColors = [
            '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6610f2'
        ];

        // Prepare datasets
        const years = <?php echo json_encode($years); ?>;
        const incomeData = <?php echo json_encode($income_by_year); ?>;
        const expensesData = <?php echo json_encode($expenses_by_year); ?>;
        const netData = <?php echo json_encode($net_by_year); ?>;

        function createDatasets(dataType) {
            let sourceData;
            if (dataType === 'income') {
                sourceData = incomeData;
            } else if (dataType === 'expenses') {
                sourceData = expensesData;
            } else {
                sourceData = netData;
            }

            return years.map((year, index) => ({
                label: year.toString(),
                data: sourceData[year],
                backgroundColor: yearColors[index % yearColors.length] + '33',
                borderColor: yearColors[index % yearColors.length],
                borderWidth: 2
            }));
        }

        const config = {
            type: 'bar',
            data: {
                labels: months,
                datasets: createDatasets('income')
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Income Comparison',
                        font: { size: 18 }
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

        // Comparison type switcher
        document.getElementById('comparisonType').addEventListener('change', function(e) {
            const value = e.target.value;
            chart.data.datasets = createDatasets(value);

            // Update chart title
            let title = 'Monthly ';
            if (value === 'income') {
                title += 'Income Comparison';
            } else if (value === 'expenses') {
                title += 'Expenses Comparison';
            } else {
                title += 'Net Comparison';
            }
            chart.options.plugins.title.text = title;

            chart.update();
        });
    </script>
    <?php
}
