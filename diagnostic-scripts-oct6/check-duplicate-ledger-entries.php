<?php
/**
 * Check for duplicate entries in transaction ledger
 */

// Database credentials
$host = 'localhost';
$database = 'swca_swca2019';
$username = 'swca_swca2019';
$password = '5Corners!';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Checking for duplicate ledger entries...\n\n";

// Check for duplicate Stripe transactions
echo "1. Checking swca_stripe_transactions for duplicates:\n";
echo str_repeat("=", 100) . "\n";

$query = "
    SELECT stripe_charge_id, COUNT(*) as count
    FROM swca_stripe_transactions
    GROUP BY stripe_charge_id
    HAVING count > 1
";

$result = $mysqli->query($query);
if ($result->num_rows > 0) {
    echo "DUPLICATE STRIPE TRANSACTIONS FOUND:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - Charge ID: {$row['stripe_charge_id']} appears {$row['count']} times\n";
    }
} else {
    echo "✓ No duplicate Stripe transactions in swca_stripe_transactions table\n";
}

echo "\n";

// Check the specific transaction the user mentioned
echo "2. Checking Stripe #1 (the one user mentioned):\n";
echo str_repeat("=", 100) . "\n";

$query = "
    SELECT id, stripe_charge_id, customer_email, amount, stripe_fee, stripe_created
    FROM swca_stripe_transactions
    WHERE id = 1 OR stripe_charge_id LIKE 'ch_3S9fXWJHWUaRCmpE1%'
";

$result = $mysqli->query($query);
if ($result->num_rows > 0) {
    printf("%-5s %-30s %-30s %10s %10s %20s\n", "ID", "Charge ID", "Email", "Amount", "Fee", "Created");
    echo str_repeat("-", 100) . "\n";
    while ($row = $result->fetch_assoc()) {
        printf("%-5s %-30s %-30s %10.2f %10.2f %20s\n",
            $row['id'],
            $row['stripe_charge_id'],
            $row['customer_email'],
            $row['amount'],
            $row['stripe_fee'],
            $row['stripe_created']
        );
    }
}

echo "\n";

// Check for duplicate matches pointing to the same Stripe transaction
echo "3. Checking swca_transaction_matches for duplicate matches to Stripe #1:\n";
echo str_repeat("=", 100) . "\n";

$query = "
    SELECT
        m.id,
        m.stripe_transaction_id,
        m.bank_transaction_id,
        m.match_type,
        m.match_confidence
    FROM swca_transaction_matches m
    WHERE m.stripe_transaction_id = 1
";

$result = $mysqli->query($query);
if ($result->num_rows > 0) {
    echo "Matches found for Stripe #1:\n";
    printf("%-10s %-20s %-20s %-30s %-20s\n", "Match ID", "Stripe ID", "Bank ID", "Match Type", "Confidence");
    echo str_repeat("-", 100) . "\n";
    while ($row = $result->fetch_assoc()) {
        printf("%-10s %-20s %-20s %-30s %-20s\n",
            $row['id'],
            $row['stripe_transaction_id'],
            $row['bank_transaction_id'] ?: 'NULL',
            $row['match_type'],
            $row['match_confidence']
        );
    }

    if ($result->num_rows > 1) {
        echo "\n⚠ WARNING: Multiple matches found for the same Stripe transaction!\n";
        echo "   This could cause duplicate rows in the ledger.\n";
    }
} else {
    echo "No matches found for Stripe #1\n";
}

echo "\n";

// Run the actual ledger query to see what's being returned
echo "4. Running actual ledger query for Stripe #1:\n";
echo str_repeat("=", 100) . "\n";

$stripe_table = 'swca_stripe_transactions';
$gf_table = 'swca_gf_addon_payment_transaction';
$bank_table = 'wp_swca_bank_transactions';
$matches_table = 'swca_transaction_matches';

$query = "
    SELECT
        s.id as stripe_id,
        s.stripe_charge_id,
        s.customer_email,
        s.amount as stripe_amount,
        s.stripe_fee,
        s.amount_refunded,
        (s.amount - s.stripe_fee - s.amount_refunded) as net_amount,
        s.stripe_created,
        b.id as bank_id,
        b.description as bank_description,
        m_bank.id as match_id,
        m_bank.match_type
    FROM $stripe_table s
    LEFT JOIN $matches_table m_gf ON m_gf.stripe_transaction_id = s.id AND m_gf.match_type = 'gravity_stripe'
    LEFT JOIN $gf_table gf ON gf.id = m_gf.gravity_form_transaction_id
    LEFT JOIN $matches_table m_bank ON m_bank.stripe_transaction_id = s.id
        AND m_bank.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
    LEFT JOIN $bank_table b ON b.id = m_bank.bank_transaction_id
    WHERE s.id = 1
    ORDER BY s.stripe_created DESC
";

$result = $mysqli->query($query);
echo "Rows returned: " . $result->num_rows . "\n\n";

if ($result->num_rows > 0) {
    printf("%-10s %-30s %-15s %10s %10s %-10s %-30s\n",
        "Stripe ID", "Charge ID", "Email", "Amount", "Net", "Bank ID", "Match Type");
    echo str_repeat("-", 100) . "\n";
    while ($row = $result->fetch_assoc()) {
        printf("%-10s %-30s %-15s %10.2f %10.2f %-10s %-30s\n",
            $row['stripe_id'],
            substr($row['stripe_charge_id'], 0, 30),
            substr($row['customer_email'], 0, 15),
            $row['stripe_amount'],
            $row['net_amount'],
            $row['bank_id'] ?: 'NULL',
            $row['match_type'] ?: 'NULL'
        );
    }

    if ($result->num_rows > 1) {
        echo "\n❌ PROBLEM FOUND: Query returns " . $result->num_rows . " rows for a single Stripe transaction!\n";
        echo "   This causes duplicate entries in the ledger.\n";
        echo "   Likely cause: Multiple match records with different match_types for the same Stripe → Bank pair\n";
    }
}

$mysqli->close();

echo "\n";
echo str_repeat("=", 100) . "\n";
echo "DIAGNOSIS COMPLETE\n";
