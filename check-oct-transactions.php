<?php
/**
 * Check if Oct 7-13 transactions were synced
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== CHECKING OCTOBER 2025 TRANSACTIONS ===\n\n";

// Check total Stripe transactions
$total = $mysqli->query("SELECT COUNT(*) as cnt FROM swca_stripe_transactions")->fetch_assoc();
echo "Total Stripe transactions: " . $total['cnt'] . "\n\n";

// Check October transactions
$oct = $mysqli->query("SELECT COUNT(*) as cnt, MIN(DATE(stripe_created)) as earliest, MAX(DATE(stripe_created)) as latest FROM swca_stripe_transactions WHERE DATE(stripe_created) >= '2025-10-01'")->fetch_assoc();
echo "October 2025 transactions: " . $oct['cnt'] . "\n";
echo "Date range: " . ($oct['earliest'] ?? 'none') . " to " . ($oct['latest'] ?? 'none') . "\n\n";

// Check last 7 days
$last7 = $mysqli->query("SELECT COUNT(*) as cnt, MIN(DATE(stripe_created)) as earliest, MAX(DATE(stripe_created)) as latest FROM swca_stripe_transactions WHERE DATE(stripe_created) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc();
echo "Last 7 days transactions: " . $last7['cnt'] . "\n";
echo "Date range: " . ($last7['earliest'] ?? 'none') . " to " . ($last7['latest'] ?? 'none') . "\n\n";

// Show recent transactions
echo "=== RECENT TRANSACTIONS ===\n";
$recent = $mysqli->query("SELECT stripe_charge_id, DATE(stripe_created) as date, amount, customer_name, customer_email FROM swca_stripe_transactions ORDER BY stripe_created DESC LIMIT 10");

while ($row = $recent->fetch_assoc()) {
    printf("%s | $%s | %s | %s\n",
        $row['date'],
        number_format($row['amount'], 2),
        substr($row['customer_name'], 0, 25),
        substr($row['customer_email'], 0, 30)
    );
}

$mysqli->close();
?>
