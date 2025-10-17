#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== CHECKING STRIPE ID 2 (should match Bank #7) ===\n\n";

$result = $mysqli->query("
    SELECT *
    FROM swca_stripe_transactions
    WHERE id = 2
");

$row = $result->fetch_object();

echo "ID: " . $row->id . "\n";
echo "Stripe Created: " . $row->stripe_created . "\n";
echo "Amount: $" . number_format($row->amount, 2) . "\n";
echo "Stripe Fee: $" . number_format($row->stripe_fee, 2) . "\n";
echo "Amount Refunded: $" . number_format($row->amount_refunded, 2) . "\n";
echo "Net Amount: $" . number_format($row->net_amount, 2) . "\n";
echo "Payout Arrival Date: " . $row->payout_arrival_date . "\n";
echo "Status: " . $row->status . "\n\n";

echo "Expected Net: Amount - Fee - Refunded = $" . number_format($row->amount - $row->stripe_fee - $row->amount_refunded, 2) . "\n\n";

if ($row->net_amount <= 0) {
    echo "❌ PROBLEM: net_amount is $" . number_format($row->net_amount, 2) . " (not > 0)\n";
    echo "This is why it's filtered out by the WHERE net_amount > 0 clause!\n\n";

    echo "The transaction was refunded:\n";
    echo "  - Original charge: $" . number_format($row->amount, 2) . "\n";
    echo "  - Refunded: $" . number_format($row->amount_refunded, 2) . "\n";
    echo "  - Net revenue: $" . number_format($row->net_amount, 2) . "\n";
    echo "  - Lost to fees: $" . number_format($row->stripe_fee, 2) . "\n\n";

    echo "Bank statement shows deposit because Stripe initially paid out, then withdrew later.\n";
    echo "This is NOT a revenue transaction - it's a refund that cost fees.\n\n";
} else {
    echo "✓ net_amount is positive, should be matched\n";
}

// Check all 6 problem IDs
echo "\n=== CHECKING ALL 6 UNMATCHED DEPOSITS ===\n\n";

$ids = [2, 3, 7, 11, 22, 23];

foreach ($ids as $id) {
    $result = $mysqli->query("SELECT * FROM swca_stripe_transactions WHERE id = $id");
    if ($result && $row = $result->fetch_object()) {
        $is_refunded = ($row->amount_refunded > 0);
        $status = ($row->net_amount > 0) ? "✓ Matchable" : "❌ Refund (net ≤ 0)";

        echo sprintf("ID %d | Payout: %s | Amount: $%.2f | Net: $%.2f | %s\n",
            $id,
            $row->payout_arrival_date,
            floatval($row->amount),
            floatval($row->net_amount),
            $status
        );
    }
}

$mysqli->close();
echo "\n";
