<?php
/**
 * Automated Stripe Sync with Provided Passphrase
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');

// Load Stripe integration functions
require_once('/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/stripe-integration.php');

echo "=== Automated Stripe Data Sync ===\n\n";

// Get stored encrypted API key
$org_settings = get_option('five01c3po_organization_settings', array());
$encrypted_api_key = $org_settings['stripe_api_key_encrypted'] ?? '';
$passphrase_hash = $org_settings['stripe_passphrase_hash'] ?? '';

if (empty($encrypted_api_key) || empty($passphrase_hash)) {
    echo "Error: No encrypted API key found\n";
    exit(1);
}

// Prompt for passphrase
echo "Enter officer passphrase: ";
$passphrase = trim(fgets(STDIN));

if (empty($passphrase)) {
    echo "Error: Passphrase required\n";
    exit(1);
}

echo "Verifying passphrase...\n";

// Verify passphrase
if (!five01c3po_verify_officer_passphrase($passphrase, $passphrase_hash)) {
    echo "Error: Incorrect passphrase\n";
    exit(1);
}

echo "✓ Passphrase verified\n";

// Decrypt API key
echo "Decrypting API key...\n";
$api_key = five01c3po_decrypt_stripe_key($encrypted_api_key, $passphrase);

if ($api_key === false) {
    echo "Error: Failed to decrypt API key\n";
    exit(1);
}

echo "✓ API key decrypted successfully\n";

$mode = str_starts_with($api_key, 'sk_live_') ? 'LIVE' : 'TEST';
echo "✓ Using $mode mode\n\n";

// Sync 10 years of data
$days_back = 3650;

echo "=== Starting Full Stripe Sync ===\n";
echo "Syncing $days_back days (10 years) of data...\n";
echo "This will download:\n";
echo "  - All charges and payments\n";
echo "  - All refunds\n";
echo "  - All balance transactions (fees, adjustments, disputes)\n";
echo "  - Complete payout history\n\n";
echo "Please wait, this may take several minutes...\n\n";

// Perform the sync
$start_time = time();
$results = five01c3po_sync_stripe_transactions($api_key, $days_back);
$elapsed = time() - $start_time;

// Display results
echo "\n" . str_repeat("=", 60) . "\n";
echo "=== Sync Complete! ===\n";
echo str_repeat("=", 60) . "\n\n";

echo "⏱️  Time Elapsed: {$elapsed} seconds\n\n";

echo "📊 Downloaded from Stripe:\n";
echo "  Charges: " . number_format($results['charges_count'] ?? 0) . "\n";
echo "  Refunds: " . number_format($results['refunds_count'] ?? 0) . "\n";
echo "  Balance Transactions: " . number_format($results['balance_transactions_count'] ?? 0) . "\n";

if (isset($results['balance_txns_debits']) && $results['balance_txns_debits'] > 0) {
    echo "\n⚠️  Fees & Debits Found:\n";
    echo "  Count: " . $results['balance_txns_debits'] . " transactions\n";
    echo "  Total Amount: -$" . number_format($results['balance_txns_debit_total'] ?? 0, 2) . "\n";
}

echo "\n💾 Database Operations:\n";
echo "  New Transactions Stored: " . number_format($results['new_transactions'] ?? 0) . "\n";
echo "  Updated (Refunds Changed): " . number_format($results['updated_transactions'] ?? 0) . "\n";
echo "  Duplicates Skipped: " . number_format($results['duplicate_transactions'] ?? 0) . "\n";
echo "  Payout IDs Captured: " . ($results['payout_ids_captured'] ?? 0) . " / " . ($results['charges_count'] ?? 0) . "\n";

echo "\n🎯 Member Matching:\n";
echo "  Members Matched: " . number_format($results['members_matched'] ?? 0) . "\n";
echo "  Total Revenue: $" . number_format($results['total_revenue'] ?? 0, 2) . "\n";

if (!empty($results['details'])) {
    echo "\n📝 Additional Details:\n";
    echo str_repeat("-", 60) . "\n";
    echo $results['details'] . "\n";
    echo str_repeat("-", 60) . "\n";
}

echo "\n✅ Sync complete!\n\n";
echo "You can now:\n";
echo "  1. View the Transaction Ledger: https://southwilliamstown.org/wp-admin/admin.php?page=501c3PO-transaction-ledger\n";
echo "  2. Check the July 1st payout to see all fees\n";
echo "  3. Review balance transactions for the complete breakdown\n";
?>
