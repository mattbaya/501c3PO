<?php
/**
 * Investigate unmatched transactions in detail
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== UNMATCHED TRANSACTIONS INVESTIGATION ===\n\n";

// 1. Show unmatched Stripe -> GF transactions
echo "1. STRIPE TRANSACTIONS NOT MATCHED TO GRAVITY FORMS (18 expected):\n";
echo str_repeat("-", 80) . "\n";

$sql = "
    SELECT
        s.id,
        s.stripe_charge_id,
        s.amount,
        s.stripe_fee,
        s.net_amount,
        s.stripe_created,
        s.customer_email,
        s.customer_name,
        s.description,
        s.status,
        s.transaction_type
    FROM swca_c3_stripe_transactions s
    LEFT JOIN swca_c3_transaction_matches m ON s.id = m.stripe_transaction_id
    WHERE s.amount > 0
      AND m.gravity_form_transaction_id IS NULL
    ORDER BY s.stripe_created DESC
";

$result = $mysqli->query($sql);
if ($result) {
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $count++;
        echo "\n#{$count} - Stripe DB ID: {$row['id']}\n";
        echo "  Charge ID: {$row['stripe_charge_id']}\n";
        echo "  Type: {$row['transaction_type']}\n";
        echo "  Status: {$row['status']}\n";
        echo "  Amount: \${$row['amount']} (fee: \${$row['stripe_fee']}, net: \${$row['net_amount']})\n";
        echo "  Date: {$row['stripe_created']}\n";
        echo "  Customer: {$row['customer_name']} <{$row['customer_email']}>\n";
        echo "  Description: {$row['description']}\n";
    }
    echo "\n\nTotal unmatched to GF: $count\n";
} else {
    echo "ERROR: " . $mysqli->error . "\n";
}

// 2. Check if there are ANY GF transactions that might match these
echo "\n\n2. CHECKING FOR POTENTIAL GF MATCHES:\n";
echo str_repeat("-", 80) . "\n";

$sql = "
    SELECT
        COUNT(*) as unmatched_gf
    FROM swca_c3_gf_payment_transaction g
    LEFT JOIN swca_c3_transaction_matches m ON g.id = m.gravity_form_transaction_id
    WHERE m.id IS NULL
";
$result = $mysqli->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Unmatched Gravity Forms transactions: {$row['unmatched_gf']}\n\n";

    // Show some samples
    if ($row['unmatched_gf'] > 0) {
        echo "Sample unmatched GF transactions:\n";
        $sql = "
            SELECT
                g.id,
                g.lead_id,
                g.transaction_id,
                g.amount,
                g.date_created
            FROM swca_c3_gf_payment_transaction g
            LEFT JOIN swca_c3_transaction_matches m ON g.id = m.gravity_form_transaction_id
            WHERE m.id IS NULL
            ORDER BY g.date_created DESC
            LIMIT 10
        ";
        $result2 = $mysqli->query($sql);
        while ($row2 = $result2->fetch_assoc()) {
            echo "  GF ID {$row2['id']}: \${$row2['amount']} on {$row2['date_created']} (txn: {$row2['transaction_id']})\n";
        }
    }
}

// 3. Analyze payout grouping - how many transactions per payout?
echo "\n\n3. STRIPE PAYOUT GROUPING ANALYSIS:\n";
echo str_repeat("-", 80) . "\n";
echo "This shows how many Stripe transactions are grouped into each payout\n";
echo "(One payout = One bank deposit)\n\n";

$sql = "
    SELECT
        payout_id,
        payout_arrival_date,
        COUNT(*) as txn_count,
        SUM(amount) as gross_total,
        SUM(stripe_fee) as fee_total,
        SUM(net_amount) as net_total,
        COUNT(DISTINCT m.bank_transaction_id) as bank_matches
    FROM swca_c3_stripe_transactions s
    LEFT JOIN swca_c3_transaction_matches m ON s.id = m.stripe_transaction_id
    WHERE payout_id IS NOT NULL AND payout_id != ''
    GROUP BY payout_id, payout_arrival_date
    HAVING txn_count > 1
    ORDER BY payout_arrival_date DESC
    LIMIT 15
";

$result = $mysqli->query($sql);
if ($result) {
    echo sprintf("%-25s %-15s %8s %10s %8s %10s %6s\n",
        "Payout ID", "Arrival Date", "Txns", "Gross", "Fees", "Net", "Bank");
    echo str_repeat("-", 90) . "\n";

    while ($row = $result->fetch_assoc()) {
        $bank_status = $row['bank_matches'] > 0 ? "YES" : "NO";
        echo sprintf("%-25s %-15s %8d $%9.2f $%7.2f $%9.2f %6s\n",
            substr($row['payout_id'], 0, 24),
            $row['payout_arrival_date'],
            $row['txn_count'],
            $row['gross_total'],
            $row['fee_total'],
            $row['net_total'],
            $bank_status
        );
    }
}

// 4. Check individual transactions without payouts
echo "\n\n4. STRIPE TRANSACTIONS WITHOUT PAYOUT INFO:\n";
echo str_repeat("-", 80) . "\n";

$sql = "
    SELECT COUNT(*) as no_payout
    FROM swca_c3_stripe_transactions
    WHERE (payout_id IS NULL OR payout_id = '')
      AND amount > 0
";
$result = $mysqli->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Transactions without payout ID: {$row['no_payout']}\n";

    if ($row['no_payout'] > 0) {
        echo "\nThese transactions should be matched individually to bank deposits\n";
        echo "(or they haven't been paid out yet)\n";
    }
}

$mysqli->close();
echo "\n\n=== INVESTIGATION COMPLETE ===\n";
?>
