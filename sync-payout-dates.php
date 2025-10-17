<?php
/**
 * Sync payout_arrival_date from balance_transactions to stripe_transactions
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  SYNCING PAYOUT DATES FROM BALANCE_TRANSACTIONS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// For each charge in balance_transactions, update the stripe_transactions payout_arrival_date
$updated = 0;

$balance_charges = $mysqli->query("
    SELECT
        source_id as charge_id,
        available_on,
        payout_id
    FROM swca_c3_stripe_balance_transactions
    WHERE txn_type = 'charge'
    AND source_id IS NOT NULL
    AND available_on IS NOT NULL
");

echo "Processing " . $balance_charges->num_rows . " charge records from balance_transactions...\n\n";

while ($bal = $balance_charges->fetch_assoc()) {
    // Update stripe_transactions with the payout date from balance_transactions
    $stmt = $mysqli->prepare("
        UPDATE swca_c3_stripe_transactions
        SET
            payout_arrival_date = ?,
            payout_id = ?
        WHERE stripe_charge_id = ?
        AND (payout_arrival_date IS NULL OR payout_arrival_date != ?)
    ");

    $stmt->bind_param('ssss',
        $bal['available_on'],
        $bal['payout_id'],
        $bal['charge_id'],
        $bal['available_on']
    );

    $stmt->execute();

    if ($mysqli->affected_rows > 0) {
        $updated++;
        if ($updated <= 20) {
            echo sprintf("  ✓ Updated %s → payout date %s\n",
                substr($bal['charge_id'], 0, 25),
                $bal['available_on']
            );
        }
    }

    $stmt->close();
}

if ($updated > 20) {
    echo "  ... (" . ($updated - 20) . " more updates)\n";
}

echo "\n";
echo "Updated $updated charge records with payout dates\n\n";

// Now check if Bank #7, #9, #13 have matching charges
echo "Checking Bank #7, #9, #13 for matches:\n\n";

$test_banks = [7 => '2025-09-18', 9 => '2025-09-12', 13 => '2025-09-02'];

foreach ($test_banks as $bank_id => $bank_date) {
    $charges = $mysqli->query("
        SELECT COUNT(*) as cnt, SUM(amount - stripe_fee - amount_refunded) as total_net
        FROM swca_c3_stripe_transactions
        WHERE DATE(payout_arrival_date) = '$bank_date'
        AND net_amount > 0
    ")->fetch_assoc();

    $bank_amount = $mysqli->query("SELECT credit FROM wp_swca_bank_transactions WHERE id = $bank_id")->fetch_assoc()['credit'];

    echo sprintf("Bank #%d ($%.2f on %s): Found %d charges, total net: $%.2f\n",
        $bank_id,
        $bank_amount,
        $bank_date,
        $charges['cnt'],
        $charges['total_net'] ?: 0
    );
}

$mysqli->close();
?>
