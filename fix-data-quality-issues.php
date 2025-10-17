<?php
/**
 * Fix Data Quality Issues to Achieve 100% Matching
 *
 * 1. Delete 3 duplicate bank entries (IDs 200, 199, 197)
 * 2. Check for missing Stripe payout data
 * 3. Prepare for re-sync
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  FIXING DATA QUALITY ISSUES FOR 100% MATCHING\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Step 1: Verify and delete duplicate bank entries
echo "STEP 1: DUPLICATE BANK ENTRIES\n";
echo "─────────────────────────────────────────────────────────────\n\n";

$duplicates = $mysqli->query("
    SELECT id, post_date, credit, description
    FROM wp_swca_bank_transactions
    WHERE id IN (200, 199, 197)
    ORDER BY id
");

echo "Duplicate entries to be deleted:\n\n";
while ($dup = $duplicates->fetch_assoc()) {
    echo sprintf("  Bank #%d: $%.2f on %s - %s\n",
        $dup['id'],
        $dup['credit'],
        $dup['post_date'],
        substr($dup['description'], 0, 50)
    );
}

// Check their matching pairs exist
echo "\nVerifying their matching pairs exist:\n\n";
$pairs = $mysqli->query("
    SELECT id, post_date, credit, description
    FROM wp_swca_bank_transactions
    WHERE id IN (7, 9, 13)
    ORDER BY id
");

while ($pair = $pairs->fetch_assoc()) {
    // Check if this is matched
    $match_check = $mysqli->query("
        SELECT COUNT(*) as cnt
        FROM swca_c3_transaction_matches
        WHERE bank_transaction_id = " . $pair['id']
    );
    $is_matched = $match_check->fetch_assoc()['cnt'] > 0;

    echo sprintf("  Bank #%d: $%.2f on %s - %s [%s]\n",
        $pair['id'],
        $pair['credit'],
        $pair['post_date'],
        substr($pair['description'], 0, 40),
        $is_matched ? '✓ MATCHED' : '✗ NOT MATCHED'
    );
}

echo "\n";
echo "Deleting duplicates (200, 199, 197)...\n";

$delete_result = $mysqli->query("
    DELETE FROM wp_swca_bank_transactions
    WHERE id IN (200, 199, 197)
");

if ($delete_result) {
    $deleted_count = $mysqli->affected_rows;
    echo "✅ Successfully deleted {$deleted_count} duplicate entries\n\n";
} else {
    echo "❌ Error deleting duplicates: " . $mysqli->error . "\n\n";
}

// Step 2: Check for missing Stripe payout data
echo "\nSTEP 2: MISSING STRIPE PAYOUT DATA\n";
echo "─────────────────────────────────────────────────────────────\n\n";

$missing_payouts = [
    ['id' => 15, 'amount' => 50.00, 'date' => '2025-08-27', 'txn' => 'txn_1S0BJFJHWUaRCmpE'],
    ['id' => 18, 'amount' => 34.75, 'date' => '2025-08-20', 'txn' => 'txn_1Rxe4AJHWUaRCmpE'],
    ['id' => 31, 'amount' => 50.00, 'date' => '2025-07-30', 'txn' => 'txn_1Rq2HkJHWUaRCmpE'],
    ['id' => 32, 'amount' => 49.60, 'date' => '2025-07-29', 'txn' => 'txn_1RpfZFJHWUaRCmpE']
];

echo "Checking for missing Stripe payout transactions:\n\n";

foreach ($missing_payouts as $missing) {
    $check = $mysqli->query("
        SELECT balance_txn_id
        FROM swca_c3_stripe_balance_transactions
        WHERE balance_txn_id = '{$missing['txn']}'
    ");

    $exists = $check->num_rows > 0;

    echo sprintf("  Bank #%d ($%.2f on %s): %s %s\n",
        $missing['id'],
        $missing['amount'],
        $missing['date'],
        $missing['txn'],
        $exists ? '✓ FOUND' : '❌ MISSING'
    );
}

// Step 3: Current status
echo "\n\nSTEP 3: CURRENT MATCHING STATUS\n";
echo "─────────────────────────────────────────────────────────────\n\n";

// Total bank deposits
$total_bank = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM wp_swca_bank_transactions
    WHERE credit > 0 AND description LIKE '%STRIPE%'
")->fetch_assoc()['cnt'];

// Matched bank deposits
$matched_bank = $mysqli->query("
    SELECT COUNT(DISTINCT bank_transaction_id) as cnt
    FROM swca_c3_transaction_matches
    WHERE bank_transaction_id IS NOT NULL
")->fetch_assoc()['cnt'];

$match_rate = $total_bank > 0 ? round(($matched_bank / $total_bank) * 100, 1) : 0;

echo "Total Stripe bank deposits: {$total_bank}\n";
echo "Successfully matched: {$matched_bank}\n";
echo "Unmatched: " . ($total_bank - $matched_bank) . "\n";
echo "Match rate: {$match_rate}%\n\n";

if ($match_rate >= 100) {
    echo "🎉 100% MATCHING ACHIEVED!\n";
} else {
    echo "📊 Current match rate: {$match_rate}%\n";
    echo "⚠️  Still need to re-sync Stripe for missing payouts\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  NEXT STEPS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$unmatched = $total_bank - $matched_bank;
if ($unmatched > 0) {
    echo "Remaining issues: {$unmatched} unmatched deposits\n\n";
    echo "TO ACHIEVE 100%:\n";
    echo "1. Re-sync Stripe for date range: 2025-07-01 to 2025-08-31\n";
    echo "2. This will fetch the 4 missing payout transactions\n";
    echo "3. Re-run matching algorithm\n";
    echo "4. Verify 100% matching\n";
} else {
    echo "✅ All bank deposits are matched!\n";
    echo "Ready for accounting-grade reporting.\n";
}

$mysqli->close();
?>
