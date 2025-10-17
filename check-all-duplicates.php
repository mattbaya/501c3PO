<?php
/**
 * Comprehensive Duplicate Transaction Detection
 * Checks all transaction tables for duplicates
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=====================================\n";
echo "COMPREHENSIVE DUPLICATE DETECTION\n";
echo "=====================================\n\n";

// 1. CHECK STRIPE TRANSACTIONS FOR DUPLICATES
echo "1. STRIPE TRANSACTIONS (swca_c3_stripe_transactions)\n";
echo "-----------------------------------------------------\n";

// Check for duplicate Stripe IDs
$result = $mysqli->query("
    SELECT stripe_id, COUNT(*) as count, GROUP_CONCAT(id) as db_ids
    FROM swca_c3_stripe_transactions
    GROUP BY stripe_id
    HAVING count > 1
");

$stripe_duplicates = 0;
if ($result && $result->num_rows > 0) {
    echo "⚠️ DUPLICATE STRIPE IDs FOUND:\n";
    while ($row = $result->fetch_assoc()) {
        $stripe_duplicates++;
        echo sprintf("  Stripe ID %s appears %d times (DB IDs: %s)\n",
            $row['stripe_id'], $row['count'], $row['db_ids']);
    }
} else {
    echo "✓ No duplicate Stripe IDs found\n";
}

// Check for duplicate amount+date combinations (potential duplicates)
$result = $mysqli->query("
    SELECT amount, stripe_created, COUNT(*) as count, GROUP_CONCAT(id) as db_ids, GROUP_CONCAT(stripe_id) as stripe_ids
    FROM swca_c3_stripe_transactions
    GROUP BY amount, stripe_created
    HAVING count > 1
");

if ($result && $result->num_rows > 0) {
    echo "\n⚠️ DUPLICATE AMOUNT+DATE COMBINATIONS:\n";
    while ($row = $result->fetch_assoc()) {
        echo sprintf("  $%.2f on %s appears %d times (DB IDs: %s, Stripe IDs: %s)\n",
            $row['amount'], $row['stripe_created'], $row['count'], $row['db_ids'], $row['stripe_ids']);
    }
}

echo "\nTotal Stripe records: " . $mysqli->query("SELECT COUNT(*) FROM swca_c3_stripe_transactions")->fetch_row()[0] . "\n";

// 2. CHECK GRAVITY FORMS TRANSACTIONS FOR DUPLICATES
echo "\n2. GRAVITY FORMS TRANSACTIONS (swca_gf_addon_payment_transaction)\n";
echo "-----------------------------------------------------\n";

// Check for duplicate transaction_ids
$result = $mysqli->query("
    SELECT transaction_id, COUNT(*) as count, GROUP_CONCAT(id) as db_ids
    FROM swca_gf_addon_payment_transaction
    WHERE transaction_type = 'payment'
    GROUP BY transaction_id
    HAVING count > 1
");

$gf_duplicates = 0;
if ($result && $result->num_rows > 0) {
    echo "⚠️ DUPLICATE GRAVITY FORMS TRANSACTION IDs FOUND:\n";
    while ($row = $result->fetch_assoc()) {
        $gf_duplicates++;
        echo sprintf("  GF Transaction ID %s appears %d times (DB IDs: %s)\n",
            $row['transaction_id'], $row['count'], $row['db_ids']);
    }
} else {
    echo "✓ No duplicate Gravity Forms transaction IDs found\n";
}

// Check for duplicate amount+date combinations
$result = $mysqli->query("
    SELECT amount, date_created, COUNT(*) as count, GROUP_CONCAT(id) as db_ids
    FROM swca_gf_addon_payment_transaction
    WHERE transaction_type = 'payment'
    GROUP BY amount, date_created
    HAVING count > 1
");

if ($result && $result->num_rows > 0) {
    echo "\n⚠️ DUPLICATE AMOUNT+DATE COMBINATIONS:\n";
    while ($row = $result->fetch_assoc()) {
        echo sprintf("  $%.2f on %s appears %d times (DB IDs: %s)\n",
            $row['amount'], $row['date_created'], $row['count'], $row['db_ids']);
    }
}

echo "\nTotal GF payment records: " . $mysqli->query("SELECT COUNT(*) FROM swca_gf_addon_payment_transaction WHERE transaction_type = 'payment'")->fetch_row()[0] . "\n";

// 3. CHECK BANK TRANSACTIONS FOR DUPLICATES
echo "\n3. BANK TRANSACTIONS (wp_swca_bank_transactions)\n";
echo "-----------------------------------------------------\n";

// Check for duplicate post_date+amount combinations
$result = $mysqli->query("
    SELECT post_date, credit, description, COUNT(*) as count, GROUP_CONCAT(id) as db_ids
    FROM wp_swca_bank_transactions
    WHERE credit > 0
    GROUP BY post_date, credit, description
    HAVING count > 1
");

$bank_duplicates = 0;
if ($result && $result->num_rows > 0) {
    echo "⚠️ DUPLICATE BANK TRANSACTIONS FOUND:\n";
    while ($row = $result->fetch_assoc()) {
        $bank_duplicates++;
        echo sprintf("  $%.2f on %s (%s) appears %d times (DB IDs: %s)\n",
            $row['credit'], $row['post_date'], substr($row['description'], 0, 40), $row['count'], $row['db_ids']);
    }
} else {
    echo "✓ No duplicate bank transactions found\n";
}

echo "\nTotal bank deposits: " . $mysqli->query("SELECT COUNT(*) FROM wp_swca_bank_transactions WHERE credit > 0")->fetch_row()[0] . "\n";

// 4. CHECK TRANSACTION MATCHES FOR DUPLICATES
echo "\n4. TRANSACTION MATCHES (swca_c3_transaction_matches)\n";
echo "-----------------------------------------------------\n";

// Check for duplicate match records (same stripe→gf→bank combination)
$result = $mysqli->query("
    SELECT
        stripe_transaction_id,
        gravity_form_transaction_id,
        bank_transaction_id,
        COUNT(*) as count,
        GROUP_CONCAT(id) as match_ids,
        GROUP_CONCAT(match_type) as match_types
    FROM swca_c3_transaction_matches
    WHERE stripe_transaction_id IS NOT NULL
       OR gravity_form_transaction_id IS NOT NULL
       OR bank_transaction_id IS NOT NULL
    GROUP BY stripe_transaction_id, gravity_form_transaction_id, bank_transaction_id
    HAVING count > 1
");

$match_duplicates = 0;
if ($result && $result->num_rows > 0) {
    echo "⚠️ DUPLICATE MATCH RECORDS FOUND:\n";
    while ($row = $result->fetch_assoc()) {
        $match_duplicates++;
        echo sprintf("  Stripe #%s / GF #%s / Bank #%s: %d duplicate matches (Match IDs: %s, Types: %s)\n",
            $row['stripe_transaction_id'] ?: 'NULL',
            $row['gravity_form_transaction_id'] ?: 'NULL',
            $row['bank_transaction_id'] ?: 'NULL',
            $row['count'],
            $row['match_ids'],
            $row['match_types']);
    }
} else {
    echo "✓ No duplicate match records found\n";
}

// Check for Stripe transactions matched multiple times to different records
$result = $mysqli->query("
    SELECT
        stripe_transaction_id,
        COUNT(*) as match_count,
        GROUP_CONCAT(CONCAT('Match#', id, '→Bank#', COALESCE(bank_transaction_id, 'NULL'))) as matches
    FROM swca_c3_transaction_matches
    WHERE stripe_transaction_id IS NOT NULL
    GROUP BY stripe_transaction_id
    HAVING match_count > 1
");

if ($result && $result->num_rows > 0) {
    echo "\n⚠️ STRIPE TRANSACTIONS WITH MULTIPLE MATCHES:\n";
    while ($row = $result->fetch_assoc()) {
        echo sprintf("  Stripe #%s has %d matches: %s\n",
            $row['stripe_transaction_id'], $row['match_count'], $row['matches']);
    }
}

echo "\nTotal match records: " . $mysqli->query("SELECT COUNT(*) FROM swca_c3_transaction_matches")->fetch_row()[0] . "\n";

// 5. CHECK LEDGER QUERY FOR DUPLICATES
echo "\n5. TRANSACTION LEDGER OUTPUT (as displayed to user)\n";
echo "-----------------------------------------------------\n";

// Simplified version of the ledger query to check for duplicates
$result = $mysqli->query("
    SELECT
        s.id as stripe_id,
        s.stripe_id as stripe_external_id,
        s.amount,
        s.stripe_created,
        COUNT(*) as appears_in_ledger
    FROM swca_c3_stripe_transactions s
    LEFT JOIN swca_c3_transaction_matches m_gf ON s.id = m_gf.stripe_transaction_id
    LEFT JOIN swca_gf_addon_payment_transaction gf ON m_gf.gravity_form_transaction_id = gf.id
    LEFT JOIN (
        SELECT
            m.stripe_transaction_id,
            MIN(b.id) as bank_id
        FROM swca_c3_transaction_matches m
        LEFT JOIN wp_swca_bank_transactions b ON m.bank_transaction_id = b.id
        WHERE m.bank_transaction_id IS NOT NULL
        GROUP BY m.stripe_transaction_id
    ) bank_matches ON s.id = bank_matches.stripe_transaction_id
    GROUP BY s.id
    HAVING appears_in_ledger > 1
    ORDER BY appears_in_ledger DESC
");

$ledger_duplicates = 0;
if ($result && $result->num_rows > 0) {
    echo "⚠️ TRANSACTIONS APPEARING MULTIPLE TIMES IN LEDGER:\n";
    while ($row = $result->fetch_assoc()) {
        $ledger_duplicates++;
        echo sprintf("  Stripe #%d (%s) - $%.2f on %s appears %d times\n",
            $row['stripe_id'], $row['stripe_external_id'], $row['amount'],
            substr($row['stripe_created'], 0, 10), $row['appears_in_ledger']);
    }
} else {
    echo "✓ No transactions appear multiple times in ledger\n";
}

// SUMMARY
echo "\n=====================================\n";
echo "SUMMARY\n";
echo "=====================================\n";
echo sprintf("Stripe duplicate Stripe IDs: %d\n", $stripe_duplicates);
echo sprintf("Gravity Forms duplicate transaction IDs: %d\n", $gf_duplicates);
echo sprintf("Bank duplicate transactions: %d\n", $bank_duplicates);
echo sprintf("Duplicate match records: %d\n", $match_duplicates);
echo sprintf("Transactions appearing multiple times in ledger: %d\n", $ledger_duplicates);

$total_issues = $stripe_duplicates + $gf_duplicates + $bank_duplicates + $match_duplicates + $ledger_duplicates;

if ($total_issues > 0) {
    echo "\n⚠️ TOTAL ISSUES FOUND: $total_issues\n";
    echo "Action required: Review duplicates and run cleanup script\n";
} else {
    echo "\n✓ NO DUPLICATES FOUND - Data integrity verified!\n";
}

$mysqli->close();
