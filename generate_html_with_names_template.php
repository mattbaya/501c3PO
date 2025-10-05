<?php
// Generate HTML with names column
$bank_credits = array_sum(array_column($bank_trans, 'credit'));
$bank_debits = array_sum(array_column($bank_trans, 'debit'));
$stripe_gross = 0;
$stripe_fees = 0;
$stripe_refunded = 0;

foreach ($stripe_trans as $t) {
    if ($t['refunded'] == 0) {
        $stripe_gross += $t['amount'];
    }
    $stripe_fees += $t['fee'];
    $stripe_refunded += $t['refunded'];
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWCA Financial Transactions with Names - Jan-Sept 2025</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #fafafa;
            border-bottom: 1px solid #e0e0e0;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }
        .summary-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .summary-card.positive .value { color: #10b981; }
        .summary-card.negative .value { color: #ef4444; }
        .table-wrapper {
            overflow-x: auto;
            padding: 30px;
        }
        .controls {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .controls input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
            min-width: 250px;
        }
        .controls select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        thead {
            background: #f8f9fa;
            position: sticky;
            top: 0;
        }
        th {
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #dee2e6;
            cursor: pointer;
            user-select: none;
        }
        th:hover { background: #e9ecef; }
        th::after { content: ' ↕'; opacity: 0.3; }
        th.sort-asc::after { content: ' ↑'; opacity: 1; color: #667eea; }
        th.sort-desc::after { content: ' ↓'; opacity: 1; color: #667eea; }
        td {
            padding: 12px 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        tr:hover { background: #f8f9fa; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-bank { background: #dbeafe; color: #1e40af; }
        .badge-stripe { background: #e0e7ff; color: #5b21b6; }
        .amount-positive { color: #10b981; font-weight: 500; }
        .amount-negative { color: #ef4444; font-weight: 500; }
        .amount-neutral { color: #6b7280; }
        .name-column { font-weight: 500; color: #374151; }
        @media print {
            body { background: white; padding: 0; }
            .controls { display: none; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Financial Transactions with Names</h1>
            <p>January - September 2025 | Combined Bank & Stripe Records | Matched with Gravity Forms</p>
        </div>

        <div class="summary">
            <div class="summary-card">
                <h3>Total Transactions</h3>
                <div class="value"><?= count($all_trans) ?></div>
            </div>
            <div class="summary-card positive">
                <h3>Bank Credits</h3>
                <div class="value">$<?= number_format($bank_credits, 2) ?></div>
            </div>
            <div class="summary-card negative">
                <h3>Bank Debits</h3>
                <div class="value">$<?= number_format($bank_debits, 2) ?></div>
            </div>
            <div class="summary-card positive">
                <h3>Bank Net</h3>
                <div class="value">$<?= number_format($bank_credits - $bank_debits, 2) ?></div>
            </div>
            <div class="summary-card">
                <h3>Stripe Gross</h3>
                <div class="value">$<?= number_format($stripe_gross, 2) ?></div>
            </div>
            <div class="summary-card negative">
                <h3>Stripe Fees</h3>
                <div class="value">$<?= number_format($stripe_fees, 2) ?></div>
            </div>
        </div>

        <div class="table-wrapper">
            <div class="controls">
                <input type="text" id="searchInput" placeholder="🔍 Search transactions, names, emails...">
                <select id="sourceFilter">
                    <option value="">All Sources</option>
                    <option value="Bank">Bank Only</option>
                    <option value="Stripe">Stripe Only</option>
                </select>
                <select id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="Paid">Paid</option>
                    <option value="Posted">Posted</option>
                    <option value="Refunded">Refunded</option>
                </select>
            </div>

            <table id="transactionTable">
                <thead>
                    <tr>
                        <th data-sort="date">Date</th>
                        <th data-sort="source">Source</th>
                        <th data-sort="name">Name</th>
                        <th data-sort="description">Description</th>
                        <th data-sort="amount">Amount</th>
                        <th data-sort="fee">Fee</th>
                        <th data-sort="net_amount">Net Amount</th>
                        <th data-sort="status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_trans as $t): ?>
                    <tr>
                        <td><?= $t['date']->format('M j, Y') ?></td>
                        <td>
                            <span class="badge badge-<?= strtolower($t['source']) ?>">
                                <?= $t['source'] ?>
                            </span>
                        </td>
                        <td class="name-column"><?= htmlspecialchars($t['name']) ?></td>
                        <td><?= htmlspecialchars($t['description']) ?></td>
                        <td>
                            <?php if ($t['amount'] !== ''): ?>
                                <span class="<?= floatval($t['amount']) > 0 ? 'amount-positive' : 'amount-negative' ?>">
                                    $<?= number_format($t['amount'], 2) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $t['fee'] !== '' ? '$' . number_format($t['fee'], 2) : '' ?></td>
                        <td>
                            <strong>
                                <span class="<?= floatval($t['net_amount']) > 0 ? 'amount-positive' : (floatval($t['net_amount']) < 0 ? 'amount-negative' : 'amount-neutral') ?>">
                                    $<?= number_format($t['net_amount'], 2) ?>
                                </span>
                            </strong>
                        </td>
                        <td><?= $t['status'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let sortDirection = {};
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.addEventListener('click', function() {
                const column = this.dataset.sort;
                const tbody = document.querySelector('#transactionTable tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                sortDirection[column] = sortDirection[column] === 'asc' ? 'desc' : 'asc';

                document.querySelectorAll('th').forEach(h => h.className = '');
                this.className = 'sort-' + sortDirection[column];

                rows.sort((a, b) => {
                    let aVal = a.children[Array.from(th.parentElement.children).indexOf(th)].textContent;
                    let bVal = b.children[Array.from(th.parentElement.children).indexOf(th)].textContent;

                    if (column === 'amount' || column === 'fee' || column === 'net_amount') {
                        aVal = parseFloat(aVal.replace(/[$,]/g, '')) || 0;
                        bVal = parseFloat(bVal.replace(/[$,]/g, '')) || 0;
                    } else if (column === 'date') {
                        aVal = new Date(aVal);
                        bVal = new Date(bVal);
                    }

                    if (sortDirection[column] === 'asc') {
                        return aVal > bVal ? 1 : -1;
                    } else {
                        return aVal < bVal ? 1 : -1;
                    }
                });

                rows.forEach(row => tbody.appendChild(row));
            });
        });

        const searchInput = document.getElementById('searchInput');
        const sourceFilter = document.getElementById('sourceFilter');
        const statusFilter = document.getElementById('statusFilter');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const source = sourceFilter.value;
            const status = statusFilter.value;

            document.querySelectorAll('#transactionTable tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowSource = row.children[1].textContent.trim();
                const rowStatus = row.children[7].textContent.trim();

                const matchesSearch = text.includes(searchTerm);
                const matchesSource = !source || rowSource === source;
                const matchesStatus = !status || rowStatus === status;

                row.style.display = matchesSearch && matchesSource && matchesStatus ? '' : 'none';
            });
        }

        searchInput.addEventListener('keyup', filterTable);
        sourceFilter.addEventListener('change', filterTable);
        statusFilter.addEventListener('change', filterTable);
    </script>
</body>
</html>
<?php
$html = ob_get_clean();
file_put_contents($output_html, $html);
?>
