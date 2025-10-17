<?php
/**
 * Check net_amount calculation accuracy
 */

// Database credentials
$host = 'localhost';
$database = 'wordpress';
$username = 'wordpress';
$password = 'F1v3C0rn3rs';

// Connect
$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Checking net_amount accuracy in swca_stripe_transactions...\n\n";

$query = "
    SELECT
        id,
        stripe_charge_id,
        customer_email,
        amount,
        stripe_fee,
        amount_refunded,
        net_amount as stored_net,
        (amount - stripe_fee - amount_refunded) as calculated_net,
        ABS(net_amount - (amount - stripe_fee - amount_refunded)) as difference
    FROM swca_stripe_transactions
    WHERE ABS(net_amount - (amount - stripe_fee - amount_refunded)) > 0.01
    ORDER BY stripe_created DESC
    LIMIT 20
";

$result = $mysqli->query($query);

if ($result->num_rows > 0) {
    echo "ERRORS FOUND - net_amount does NOT match calculated value:\n";
    echo str_repeat("=", 120) . "\n";
    printf("%-10s %-30s %-30s %10s %10s %10s %12s %12s %10s\n",
        "ID", "Charge ID", "Email", "Amount", "Fee", "Refunded", "Stored Net", "Calc Net", "Diff");
    echo str_repeat("=", 120) . "\n";

    $error_count = 0;
    while ($row = $result->fetch_assoc()) {
        printf("%-10s %-30s %-30s %10.2f %10.2f %10.2f %12.2f %12.2f %10.2f\n",
            $row['id'],
            substr($row['stripe_charge_id'], 0, 30),
            substr($row['customer_email'], 0, 30),
            $row['amount'],
            $row['stripe_fee'],
            $row['amount_refunded'],
            $row['stored_net'],
            $row['calculated_net'],
            $row['difference']
        );
        $error_count++;
    }

    echo str_repeat("=", 120) . "\n";
    echo "Total errors found: $error_count\n\n";

    // Show total count
    $total_query = "SELECT COUNT(*) as total,
                    SUM(CASE WHEN ABS(net_amount - (amount - stripe_fee - amount_refunded)) > 0.01 THEN 1 ELSE 0 END) as errors
                    FROM swca_stripe_transactions";
    $total_result = $mysqli->query($total_query);
    $totals = $total_result->fetch_assoc();

    echo "Total transactions: {$totals['total']}\n";
    echo "Transactions with incorrect net_amount: {$totals['errors']}\n";
    echo "Accuracy rate: " . round(($totals['total'] - $totals['errors']) / $totals['total'] * 100, 2) . "%\n";
} else {
    echo "✓ All net_amount calculations are correct!\n";
}

$mysqli->close();
