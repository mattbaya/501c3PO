<?php
/**
 * Manual Stripe Sync Script
 * Syncs all Stripe data including balance transactions
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');

// Load Stripe integration functions
require_once('/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/stripe-integration.php');

echo "=== Manual Stripe Data Sync ===\n\n";

// Check for stored encrypted API key
$org_settings = get_option('five01c3po_organization_settings', array());
$encrypted_api_key = $org_settings['stripe_api_key_encrypted'] ?? '';
$passphrase_hash = $org_settings['stripe_passphrase_hash'] ?? '';
$has_encrypted_key = !empty($encrypted_api_key) && !empty($passphrase_hash);

$api_key = '';

if ($has_encrypted_key) {
    echo "✓ Found encrypted Stripe API key in settings\n";
    echo "Please enter the officer passphrase: ";

    // Read passphrase from stdin
    $passphrase = trim(fgets(STDIN));

    if (empty($passphrase)) {
        echo "Error: Passphrase required\n";
        exit(1);
    }

    // Verify passphrase
    if (!five01c3po_verify_officer_passphrase($passphrase, $passphrase_hash)) {
        echo "Error: Incorrect passphrase\n";
        exit(1);
    }

    // Decrypt API key
    $api_key = five01c3po_decrypt_stripe_key($encrypted_api_key, $passphrase);
    if ($api_key === false) {
        echo "Error: Failed to decrypt API key\n";
        exit(1);
    }

    echo "✓ API key decrypted successfully\n\n";

} else {
    echo "⚠ No encrypted API key found\n";
    echo "Please enter your Stripe Secret Key (sk_live_... or sk_test_...): ";

    // Read API key from stdin
    $api_key = trim(fgets(STDIN));

    if (empty($api_key)) {
        echo "Error: API key required\n";
        exit(1);
    }
}

// Validate API key format
if (!str_starts_with($api_key, 'sk_live_') && !str_starts_with($api_key, 'sk_test_')) {
    echo "Error: Invalid API key format (should start with sk_live_ or sk_test_)\n";
    exit(1);
}

$mode = str_starts_with($api_key, 'sk_live_') ? 'LIVE' : 'TEST';
echo "Using $mode mode API key\n\n";

// Ask for days to sync
echo "How many days back to sync? (default: 3650 = 10 years): ";
$days_input = trim(fgets(STDIN));
$days_back = !empty($days_input) ? intval($days_input) : 3650;

echo "\n=== Starting Sync ===\n";
echo "Syncing $days_back days of Stripe data...\n";
echo "This may take a few minutes for large datasets.\n\n";

// Perform the sync
$results = five01c3po_sync_stripe_transactions($api_key, $days_back);

// Display results
echo "\n=== Sync Complete! ===\n\n";
echo "📊 Summary:\n";
echo "  Charges Downloaded: " . ($results['charges_count'] ?? 0) . "\n";
echo "  Refunds Downloaded: " . ($results['refunds_count'] ?? 0) . "\n";
echo "  Balance Transactions: " . ($results['balance_transactions_count'] ?? 0) . "\n";

if (isset($results['balance_txns_debits']) && $results['balance_txns_debits'] > 0) {
    echo "  ⚠️  Debits/Fees Found: " . $results['balance_txns_debits'] . " transactions totaling -$" . number_format($results['balance_txns_debit_total'] ?? 0, 2) . "\n";
}

echo "\n💾 Database Operations:\n";
echo "  New Transactions: " . ($results['new_transactions'] ?? 0) . "\n";
echo "  Updated (Refunds): " . ($results['updated_transactions'] ?? 0) . "\n";
echo "  Duplicates Skipped: " . ($results['duplicate_transactions'] ?? 0) . "\n";
echo "  Payout IDs Captured: " . ($results['payout_ids_captured'] ?? 0) . " / " . ($results['charges_count'] ?? 0) . "\n";

echo "\n🎯 Matching:\n";
echo "  Members Matched: " . ($results['members_matched'] ?? 0) . "\n";
echo "  Total Revenue: $" . number_format($results['total_revenue'] ?? 0, 2) . "\n";

if (!empty($results['details'])) {
    echo "\n📝 Details:\n";
    echo $results['details'] . "\n";
}

echo "\n✅ Sync complete! You can now view all data in the Transaction Ledger.\n";
?>
