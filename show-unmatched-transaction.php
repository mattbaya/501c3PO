<?php
/**
 * Show details of the single unmatched Stripe transaction
 */

// Database credentials
$host = 'localhost';
$user = 'swca_swca2019';
$pass = '5Corners!';
$dbname = 'swca_swca2019';

// Connect to database
$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== UNMATCHED STRIPE TRANSACTION DETAILS ===\n\n";

// Get the unmatched transaction with all details
$result = $mysqli->query("
SELECT
    s.*,
    gf.transaction_id as gf_transaction_id,
    gf.lead_id as gf_lead_id
FROM swca_c3_stripe_transactions s
LEFT JOIN swca_c3_transaction_matches m ON m.stripe_transaction_id = s.id
LEFT JOIN swca_c3_gf_payment_transaction gf ON s.stripe_charge_id = gf.transaction_id
WHERE m.id IS NULL
LIMIT 1
");

if ($row = $result->fetch_assoc()) {
    echo "Transaction ID: {$row['id']}\n";
    echo "Stripe Charge ID: {$row['stripe_charge_id']}\n";
    echo "Amount: \$" . number_format($row['amount'], 2) . "\n";
    echo "Stripe Fee: \$" . number_format($row['stripe_fee'], 2) . "\n";
    echo "Net Amount: \$" . number_format($row['net_amount'], 2) . "\n";
    echo "Amount Refunded: \$" . number_format($row['amount_refunded'], 2) . "\n";
    echo "\nCustomer Name: {$row['customer_name']}\n";
    echo "Customer Email: {$row['customer_email']}\n";
    echo "\nDescription: {$row['description']}\n";
    echo "\nPayout ID: {$row['payout_id']}\n";
    echo "Payout Arrival Date: {$row['payout_arrival_date']}\n";

    // Show date fields
    echo "\n--- DATE INFORMATION ---\n";
    foreach ($row as $key => $value) {
        if (strpos($key, 'date') !== false || strpos($key, 'created') !== false || strpos($key, 'time') !== false) {
            echo "{$key}: {$value}\n";
        }
    }

    echo "\n--- GRAVITY FORMS MATCH ---\n";
    if ($row['gf_transaction_id']) {
        echo "Gravity Forms Transaction ID: {$row['gf_transaction_id']}\n";
        echo "Gravity Forms Lead ID: {$row['gf_lead_id']}\n";
    } else {
        echo "No Gravity Forms match found\n";
    }

    // Check if there are any bank deposits around this payout date
    echo "\n--- BANK DEPOSITS NEAR PAYOUT DATE ---\n";
    if ($row['payout_arrival_date']) {
        $date_start = date('Y-m-d', strtotime($row['payout_arrival_date'] . ' -3 days'));
        $date_end = date('Y-m-d', strtotime($row['payout_arrival_date'] . ' +3 days'));

        $bank_result = $mysqli->query("
        SELECT id, post_date, description, credit
        FROM swca_c3_bank_transactions
        WHERE post_date BETWEEN '{$date_start}' AND '{$date_end}'
        AND credit > 0
        AND description LIKE '%STRIPE%'
        ORDER BY post_date
        ");

        if ($bank_result->num_rows > 0) {
            echo "Found bank deposits within ±3 days of payout date:\n";
            while ($bank = $bank_result->fetch_assoc()) {
                echo "  Bank #{$bank['id']}: {$bank['post_date']} - \${$bank['credit']} - {$bank['description']}\n";
            }
        } else {
            echo "No Stripe bank deposits found within ±3 days of payout date ({$date_start} to {$date_end})\n";
        }
    } else {
        echo "No payout date available\n";
    }
} else {
    echo "No unmatched transactions found!\n";
}

$mysqli->close();
