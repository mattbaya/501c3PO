<?php
/**
 * Simple matching check
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== SIMPLE MATCH CHECK ===\n\n";

// Test 1: Basic counts
echo "1. Basic counts:\n";
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM swca_c3_stripe_transactions");
if ($result) {
    $row = $result->fetch_assoc();
    echo "  Stripe transactions: {$row['cnt']}\n";
} else {
    echo "  ERROR: " . $mysqli->error . "\n";
}

$result = $mysqli->query("SELECT COUNT(*) as cnt FROM swca_c3_gf_payment_transaction");
if ($result) {
    $row = $result->fetch_assoc();
    echo "  GF payments: {$row['cnt']}\n";
} else {
    echo "  ERROR: " . $mysqli->error . "\n";
}

$result = $mysqli->query("SELECT COUNT(*) as cnt FROM swca_c3_transaction_matches");
if ($result) {
    $row = $result->fetch_assoc();
    echo "  Match records: {$row['cnt']}\n\n";
} else {
    echo "  ERROR: " . $mysqli->error . "\n";
}

// Test 2: Check match table structure
echo "2. Match table structure:\n";
$result = $mysqli->query("DESCRIBE swca_c3_transaction_matches");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "  ERROR: " . $mysqli->error . "\n";
}
echo "\n";

// Test 3: Simple join test
echo "3. Testing Stripe -> Match join:\n";
$sql = "
    SELECT COUNT(*) as cnt
    FROM swca_c3_stripe_transactions s
    LEFT JOIN swca_c3_transaction_matches m ON s.id = m.stripe_transaction_id
";
$result = $mysqli->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "  Join result: {$row['cnt']} rows\n";
} else {
    echo "  ERROR: " . $mysqli->error . "\n";
}

// Test 4: Count matches
echo "\n4. Counting matches:\n";
$sql = "
    SELECT
        COUNT(DISTINCT s.id) as total_stripe,
        COUNT(DISTINCT CASE WHEN m.gravity_form_transaction_id IS NOT NULL THEN s.id END) as has_gf_match,
        COUNT(DISTINCT CASE WHEN m.bank_transaction_id IS NOT NULL THEN s.id END) as has_bank_match
    FROM swca_c3_stripe_transactions s
    LEFT JOIN swca_c3_transaction_matches m ON s.id = m.stripe_transaction_id
    WHERE s.amount > 0
";
$result = $mysqli->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "  Total Stripe (amount > 0): {$row['total_stripe']}\n";
    echo "  With GF match: {$row['has_gf_match']}\n";
    echo "  With Bank match: {$row['has_bank_match']}\n";
    echo "  Unmatched to GF: " . ($row['total_stripe'] - $row['has_gf_match']) . "\n";
    echo "  Unmatched to Bank: " . ($row['total_stripe'] - $row['has_bank_match']) . "\n";
} else {
    echo "  ERROR: " . $mysqli->error . "\n";
}

$mysqli->close();
echo "\n=== DONE ===\n";
?>
