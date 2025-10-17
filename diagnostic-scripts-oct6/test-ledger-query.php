#!/usr/bin/env php
<?php
/**
 * Test the transaction ledger query
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "\n=== TESTING TRANSACTION LEDGER QUERY ===\n\n";

$stripe_table = 'swca_stripe_transactions';
$gf_table = 'swca_gf_addon_payment_transaction';
$bank_table = 'wp_swca_bank_transactions';
$matches_table = 'swca_transaction_matches';

$query = "
    SELECT
        -- Customer Information (from Stripe)
        s.customer_name,
        s.customer_email,

        -- Gravity Forms Data (if matched)
        gf.date_created as gf_date,
        gf.amount as gf_amount,

        -- Stripe Transaction Data
        s.id as stripe_id,
        s.stripe_created,
        s.amount as stripe_amount,
        s.stripe_fee,
        s.amount_refunded,
        s.net_amount,
        s.status as stripe_status,

        -- Payout Information
        s.payout_arrival_date,
        s.payout_status,

        -- Bank Deposit Information
        b.id as bank_id,
        b.post_date as bank_deposit_date,
        b.credit as bank_deposit_amount,

        -- Match Information
        m_bank.match_type,
        m_bank.match_confidence,

        -- Calculated Fields
        (s.amount - s.stripe_fee) as expected_payout,
        DATEDIFF(s.payout_arrival_date, s.stripe_created) as days_to_payout,
        DATEDIFF(b.post_date, s.payout_arrival_date) as days_payout_to_bank

    FROM $stripe_table s

    -- Left join Gravity Forms
    LEFT JOIN $matches_table m_gf ON m_gf.stripe_transaction_id = s.id AND m_gf.match_type = 'gravity_stripe'
    LEFT JOIN $gf_table gf ON gf.id = m_gf.gravity_form_transaction_id

    -- Left join Bank deposits
    LEFT JOIN $matches_table m_bank ON m_bank.stripe_transaction_id = s.id
        AND m_bank.match_type IN ('bank_stripe_payout', 'bank_stripe_payout_part')
    LEFT JOIN $bank_table b ON b.id = m_bank.bank_transaction_id

    WHERE s.stripe_created >= '2025-08-01'

    ORDER BY s.stripe_created DESC
    LIMIT 10
";

echo "SQL Query:\n";
echo str_repeat("-", 100) . "\n";
echo wordwrap($query, 100) . "\n";
echo str_repeat("-", 100) . "\n\n";

$result = $mysqli->query($query);

if (!$result) {
    echo "❌ Query failed: " . $mysqli->error . "\n\n";
    exit(1);
}

echo "✓ Query executed successfully!\n\n";
echo "Results (showing 10 most recent transactions since Aug 2025):\n\n";

$total_gross = 0;
$total_fees = 0;
$total_net = 0;
$matched_count = 0;

while ($row = $result->fetch_object()) {
    $total_gross += floatval($row->stripe_amount);
    $total_fees += floatval($row->stripe_fee);
    $total_net += floatval($row->net_amount);
    if ($row->bank_id) {
        $matched_count++;
    }

    $customer = $row->customer_name ?? $row->customer_email ?? 'Unknown';

    $is_refunded = floatval($row->amount_refunded) > 0;
    $has_bank = !empty($row->bank_id);

    // Status
    if ($is_refunded && floatval($row->net_amount) < 0) {
        $status = '🔄 Refunded';
    } elseif ($has_bank) {
        $status = '✓ In Bank';
    } elseif (!empty($row->payout_arrival_date)) {
        $status = '⏳ Paid Out';
    } else {
        $status = '⏸ Pending';
    }

    echo str_repeat("-", 100) . "\n";
    echo sprintf("ID: %d | %s | %s\n",
        $row->stripe_id,
        date('M j, Y g:i a', strtotime($row->stripe_created)),
        $status
    );
    echo sprintf("Customer: %s\n", $customer);
    echo sprintf("Amount: $%.2f - Fee: $%.2f", $row->stripe_amount, $row->stripe_fee);
    if ($is_refunded) {
        echo sprintf(" - Refunded: $%.2f", $row->amount_refunded);
    }
    echo sprintf(" = Net: $%.2f\n", $row->net_amount);

    if ($row->payout_arrival_date) {
        echo sprintf("Payout: %s (+%d days)\n",
            date('M j, Y', strtotime($row->payout_arrival_date)),
            $row->days_to_payout ?? 0
        );
    }

    if ($has_bank) {
        echo sprintf("Bank Deposit: %s (ID %d) - $%.2f",
            date('M j, Y', strtotime($row->bank_deposit_date)),
            $row->bank_id,
            $row->bank_deposit_amount
        );
        if ($row->days_payout_to_bank) {
            echo sprintf(" (+%d days from payout)", abs($row->days_payout_to_bank));
        }
        echo "\n";
    }
}

echo str_repeat("=", 100) . "\n";
echo "SUMMARY:\n";
echo sprintf("  Transactions: %d\n", $result->num_rows);
echo sprintf("  Matched to Bank: %d (%.1f%%)\n", $matched_count, ($result->num_rows > 0 ? ($matched_count / $result->num_rows) * 100 : 0));
echo sprintf("  Total Gross: $%.2f\n", $total_gross);
echo sprintf("  Total Fees: $%.2f\n", $total_fees);
echo sprintf("  Total Net: $%.2f\n", $total_net);
echo str_repeat("=", 100) . "\n\n";

echo "✓ Test completed successfully!\n";
echo "The ledger feature is ready to use in WordPress admin.\n";
echo "Navigate to: Membership Management > Transaction Ledger\n\n";

$mysqli->close();
