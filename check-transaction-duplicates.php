<?php
/**
 * Check for duplicate Stripe transactions and matching status
 * Run: php check-transaction-duplicates.php
 */

// Database connection
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== STRIPE TRANSACTION DUPLICATE CHECK ===\n\n";

// First, let's see what columns exist
echo "1. Checking table structure...\n";
$result = $mysqli->query("DESCRIBE swca_c3_stripe_transactions");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo "Columns: " . implode(", ", $columns) . "\n\n";

// Check for duplicates by stripe_charge_id (the unique Stripe ID field)
echo "2. Checking for duplicate Stripe charge IDs...\n";
$result = $mysqli->query("
    SELECT
        stripe_charge_id,
        COUNT(*) as occurrences,
        GROUP_CONCAT(id) as duplicate_ids,
        GROUP_CONCAT(amount) as amounts,
        GROUP_CONCAT(stripe_created) as dates
    FROM swca_c3_stripe_transactions
    GROUP BY stripe_charge_id
    HAVING COUNT(*) > 1
    ORDER BY occurrences DESC
");

if ($result && $result->num_rows > 0) {
    echo "Found " . $result->num_rows . " duplicate Stripe charge IDs:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - Charge ID: {$row['stripe_charge_id']}\n";
        echo "    Occurrences: {$row['occurrences']}\n";
        echo "    Database IDs: {$row['duplicate_ids']}\n";
        echo "    Amounts: {$row['amounts']}\n";
        echo "    Dates: {$row['dates']}\n\n";
    }
} else {
    echo "✓ No duplicate Stripe charge IDs found\n\n";
}

// Check total counts
echo "3. Transaction counts:\n";
$result = $mysqli->query("SELECT COUNT(*) as total FROM swca_c3_stripe_transactions");
$row = $result->fetch_assoc();
echo "Total Stripe transactions: {$row['total']}\n";

$result = $mysqli->query("SELECT COUNT(*) as total FROM swca_c3_gf_payment_transaction");
$row = $result->fetch_assoc();
echo "Total Gravity Forms payments: {$row['total']}\n";

$result = $mysqli->query("SELECT COUNT(*) as total FROM swca_c3_bank_transactions");
$row = $result->fetch_assoc();
echo "Total Bank transactions: {$row['total']}\n\n";

// Check Stripe -> Gravity Forms matching
echo "4. Stripe -> Gravity Forms matching status:\n";
$result = $mysqli->query("
    SELECT
        COUNT(DISTINCT s.id) as total_stripe,
        COUNT(DISTINCT m.stripe_id) as matched_stripe,
        COUNT(DISTINCT s.id) - COUNT(DISTINCT m.stripe_id) as unmatched_stripe
    FROM swca_c3_stripe_transactions s
    LEFT JOIN swca_c3_transaction_matches m ON s.id = m.stripe_id AND m.gf_id IS NOT NULL
    WHERE s.amount > 0
");
$row = $result->fetch_assoc();
echo "Total Stripe income transactions: {$row['total_stripe']}\n";
echo "Matched to Gravity Forms: {$row['matched_stripe']}\n";
echo "Unmatched: {$row['unmatched_stripe']}\n\n";

// Check Stripe -> Bank matching via payouts
echo "5. Stripe -> Bank matching status:\n";
$result = $mysqli->query("
    SELECT
        COUNT(DISTINCT s.id) as total_stripe,
        COUNT(DISTINCT m.stripe_id) as matched_stripe,
        COUNT(DISTINCT s.id) - COUNT(DISTINCT m.stripe_id) as unmatched_stripe
    FROM swca_c3_stripe_transactions s
    LEFT JOIN swca_c3_transaction_matches m ON s.id = m.stripe_id AND m.bank_id IS NOT NULL
    WHERE s.amount > 0
");
$row = $result->fetch_assoc();
echo "Total Stripe income transactions: {$row['total_stripe']}\n";
echo "Matched to Bank: {$row['matched_stripe']}\n";
echo "Unmatched: {$row['unmatched_stripe']}\n\n";

// Show some unmatched Stripe transactions
echo "6. Sample unmatched Stripe transactions (first 10):\n";
$result = $mysqli->query("
    SELECT
        s.id,
        s.stripe_charge_id,
        s.amount,
        s.net_amount,
        s.stripe_fee,
        s.stripe_created,
        s.payout_id,
        s.payout_arrival_date,
        s.customer_email,
        s.description
    FROM swca_c3_stripe_transactions s
    LEFT JOIN swca_c3_transaction_matches m ON s.id = m.stripe_id
    WHERE s.amount > 0 AND m.id IS NULL
    ORDER BY s.stripe_created DESC
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "\n  Stripe ID #{$row['id']}: {$row['stripe_charge_id']}\n";
        echo "  Amount: \${$row['amount']} (net: \${$row['net_amount']}, fee: \${$row['stripe_fee']})\n";
        echo "  Date: {$row['stripe_created']}\n";
        echo "  Payout: {$row['payout_id']} (arrival: {$row['payout_arrival_date']})\n";
        echo "  Customer: {$row['customer_email']}\n";
        echo "  Description: {$row['description']}\n";
    }
} else {
    echo "✓ All Stripe transactions are matched!\n";
}

// Check payout grouping
echo "\n\n7. Payout grouping analysis:\n";
$result = $mysqli->query("
    SELECT
        payout_id,
        payout_arrival_date,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount,
        SUM(stripe_fee) as total_fees,
        SUM(net_amount) as net_total
    FROM swca_c3_stripe_transactions
    WHERE payout_id IS NOT NULL AND payout_id != ''
    GROUP BY payout_id, payout_arrival_date
    ORDER BY payout_arrival_date DESC
    LIMIT 10
");

echo "Recent payouts (showing how many transactions are grouped per payout):\n";
while ($row = $result->fetch_assoc()) {
    $matched = $mysqli->query("
        SELECT COUNT(*) as matched
        FROM swca_c3_transaction_matches m
        JOIN swca_c3_stripe_transactions s ON m.stripe_id = s.id
        WHERE s.payout_id = '{$row['payout_id']}'
    ")->fetch_assoc()['matched'];

    echo "\n  Payout: {$row['payout_id']}\n";
    echo "  Arrival Date: {$row['payout_arrival_date']}\n";
    echo "  Transactions in payout: {$row['transaction_count']}\n";
    echo "  Matched to bank: {$matched}\n";
    echo "  Gross: \${$row['total_amount']}, Fees: \${$row['total_fees']}, Net: \${$row['net_total']}\n";
}

$mysqli->close();
echo "\n\n=== ANALYSIS COMPLETE ===\n";
?>
