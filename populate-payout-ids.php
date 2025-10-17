<?php
/**
 * Populate payout_id in stripe_transactions from balance_transactions data
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  POPULATING PAYOUT IDs IN STRIPE TRANSACTIONS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Find all balance transactions with payout_id
$balance_txns = $mysqli->query("
    SELECT
        source_id as charge_id,
        payout_id,
        available_on as payout_arrival_date
    FROM swca_c3_stripe_balance_transactions
    WHERE txn_type = 'charge'
    AND payout_id IS NOT NULL
    AND source_id IS NOT NULL
");

$updated = 0;
$not_found = 0;

echo "Found " . $balance_txns->num_rows . " balance transactions with payout IDs\n\n";

while ($bal = $balance_txns->fetch_assoc()) {
    // Find the corresponding Stripe charge
    $charge_check = $mysqli->query("
        SELECT id, payout_id, stripe_charge_id
        FROM swca_c3_stripe_transactions
        WHERE stripe_charge_id = '{$bal['charge_id']}'
    ");

    if ($charge_check && $charge_check->num_rows > 0) {
        $charge = $charge_check->fetch_assoc();

        if ($charge['payout_id'] != $bal['payout_id']) {
            // Update the payout_id
            $mysqli->query("
                UPDATE swca_c3_stripe_transactions
                SET
                    payout_id = '{$bal['payout_id']}',
                    payout_arrival_date = '{$bal['payout_arrival_date']}'
                WHERE stripe_charge_id = '{$bal['charge_id']}'
            ");

            if ($mysqli->affected_rows > 0) {
                $updated++;
                if ($updated <= 10) {
                    echo sprintf("  ✓ Updated Stripe charge %s → Payout %s\n",
                        substr($bal['charge_id'], 0, 20),
                        substr($bal['payout_id'], 0, 20)
                    );
                }
            }
        }
    } else {
        $not_found++;
    }
}

if ($updated > 10) {
    echo "  ... (" . ($updated - 10) . " more updates)\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Charges updated with payout_id: $updated\n";
echo "Balance transactions not found in charges table: $not_found\n\n";

// Now check if Bank #7, #9, #13 can be matched
echo "Checking if Bank #7, #9, #13 can now be matched:\n\n";

$test_banks = [7, 9, 13];

foreach ($test_banks as $bank_id) {
    $bank = $mysqli->query("SELECT credit, post_date FROM wp_swca_bank_transactions WHERE id = $bank_id")->fetch_assoc();

    if (!$bank) {
        echo "Bank #$bank_id: NOT FOUND\n";
        continue;
    }

    // Search by amount and date range
    $date_range_start = date('Y-m-d', strtotime($bank['post_date'] . ' -7 days'));
    $date_range_end = date('Y-m-d', strtotime($bank['post_date'] . ' +2 days'));
    $bank_amount = floatval($bank['credit']);

    $charges = $mysqli->query("
        SELECT COUNT(*) as cnt, payout_id
        FROM swca_c3_stripe_transactions
        WHERE DATE(payout_arrival_date) BETWEEN '$date_range_start' AND '$date_range_end'
        AND payout_id IS NOT NULL
        GROUP BY payout_id
    ");

    echo sprintf("Bank #%d ($%.2f on %s): Found %d payouts in date range\n",
        $bank_id,
        $bank_amount,
        $bank['post_date'],
        $charges->num_rows
    );
}

$mysqli->close();
?>
