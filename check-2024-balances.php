<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "=== 2024 vs 2025 BALANCE STATUS ===\n\n";

// 2024 transactions
$result = $mysqli->query("SELECT COUNT(*) as total FROM wp_swca_bank_transactions WHERE post_date LIKE '2024%'");
$total_2024 = $result->fetch_assoc()['total'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions WHERE post_date LIKE '2024%' AND balance != 0");
$with_balance_2024 = $result->fetch_assoc()['count'];

echo "2024 Transactions:\n";
echo "  Total: $total_2024\n";
echo "  With balances: $with_balance_2024\n";
echo "  Without balances: " . ($total_2024 - $with_balance_2024) . "\n\n";

// 2025 transactions
$result = $mysqli->query("SELECT COUNT(*) as total FROM wp_swca_bank_transactions WHERE post_date LIKE '2025%'");
$total_2025 = $result->fetch_assoc()['total'];

$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions WHERE post_date LIKE '2025%' AND balance != 0");
$with_balance_2025 = $result->fetch_assoc()['count'];

echo "2025 Transactions:\n";
echo "  Total: $total_2025\n";
echo "  With balances: $with_balance_2025\n";
echo "  Without balances: " . ($total_2025 - $with_balance_2025) . "\n\n";

// Show some 2024 transactions WITH balances
$result = $mysqli->query("SELECT post_date, balance, description FROM wp_swca_bank_transactions WHERE post_date LIKE '2024%' AND balance != 0 ORDER BY post_date ASC LIMIT 5");
echo "First 5 of 2024 transactions WITH balances:\n";
while ($row = $result->fetch_assoc()) {
    echo "  {$row['post_date']}: \${$row['balance']} - {$row['description']}\n";
}

$mysqli->close();
?>
