<?php
/**
 * Check if old table has payout data that new table doesn't
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== OLD VS NEW TABLE COMPARISON ===\n\n";

// Check if old table exists
$old_table_exists = $mysqli->query("SHOW TABLES LIKE 'swca_stripe_transactions'")->num_rows > 0;
$new_table_exists = $mysqli->query("SHOW TABLES LIKE 'swca_c3_stripe_transactions'")->num_rows > 0;

echo "Table existence:\n";
echo "  swca_stripe_transactions (OLD): " . ($old_table_exists ? "EXISTS" : "DOES NOT EXIST") . "\n";
echo "  swca_c3_stripe_transactions (NEW): " . ($new_table_exists ? "EXISTS" : "DOES NOT EXIST") . "\n\n";

if ($old_table_exists) {
    echo "OLD TABLE (swca_stripe_transactions):\n";
    $result = $mysqli->query("SELECT COUNT(*) as total,
                              COUNT(DISTINCT payout_id) as unique_payouts,
                              SUM(CASE WHEN payout_id IS NOT NULL AND payout_id != '' THEN 1 ELSE 0 END) as has_payout
                              FROM swca_stripe_transactions");
    $row = $result->fetch_assoc();
    echo "  Total rows: {$row['total']}\n";
    echo "  With payout_id: {$row['has_payout']}\n";
    echo "  Unique payout IDs: {$row['unique_payouts']}\n\n";

    // Show sample with payout data
    $result = $mysqli->query("SELECT id, stripe_charge_id, amount, payout_id, payout_arrival_date
                              FROM swca_stripe_transactions
                              WHERE payout_id IS NOT NULL AND payout_id != ''
                              LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "  Sample transactions with payout data:\n";
        while ($row = $result->fetch_assoc()) {
            echo "    ID {$row['id']}: \${$row['amount']} → Payout {$row['payout_id']} (arrival: {$row['payout_arrival_date']})\n";
        }
    }
    echo "\n";
}

if ($new_table_exists) {
    echo "NEW TABLE (swca_c3_stripe_transactions):\n";
    $result = $mysqli->query("SELECT COUNT(*) as total,
                              COUNT(DISTINCT payout_id) as unique_payouts,
                              SUM(CASE WHEN payout_id IS NOT NULL AND payout_id != '' THEN 1 ELSE 0 END) as has_payout
                              FROM swca_c3_stripe_transactions");
    $row = $result->fetch_assoc();
    echo "  Total rows: {$row['total']}\n";
    echo "  With payout_id: {$row['has_payout']}\n";
    echo "  Unique payout IDs: {$row['unique_payouts']}\n\n";

    // Show sample
    $result = $mysqli->query("SELECT id, stripe_charge_id, amount, payout_id, payout_arrival_date
                              FROM swca_c3_stripe_transactions
                              WHERE payout_id IS NOT NULL AND payout_id != ''
                              LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "  Sample transactions with payout data:\n";
        while ($row = $result->fetch_assoc()) {
            echo "    ID {$row['id']}: \${$row['amount']} → Payout {$row['payout_id']} (arrival: {$row['payout_arrival_date']})\n";
        }
    } else {
        echo "  ❌ NO transactions have payout data!\n";
    }
}

$mysqli->close();
echo "\n=== ANALYSIS COMPLETE ===\n";
?>
