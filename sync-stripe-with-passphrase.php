<?php
/**
 * Sync Stripe using encrypted API key with passphrase
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');
global $wpdb;

// Get the passphrase from command line argument
$passphrase = 'POBox432';
$days_back = 30; // Get last 30 days

echo "=== STRIPE SYNC WITH PASSPHRASE ===\n\n";

// Get organization settings
$org_settings = get_option('five01c3po_organization_settings', array());
$encrypted_api_key = $org_settings['stripe_api_key_encrypted'] ?? '';
$passphrase_hash = $org_settings['stripe_passphrase_hash'] ?? '';

if (empty($encrypted_api_key) || empty($passphrase_hash)) {
    echo "ERROR: No encrypted Stripe API key found in settings.\n";
    exit(1);
}

// Verify passphrase
if (!password_verify($passphrase, $passphrase_hash)) {
    echo "ERROR: Incorrect passphrase.\n";
    exit(1);
}

echo "✓ Passphrase verified\n";

// Decrypt API key
function decrypt_stripe_key($encrypted_data, $passphrase) {
    $cipher = "AES-256-CBC";
    $decoded = base64_decode($encrypted_data);
    $parts = explode('::', $decoded, 2);

    if (count($parts) !== 2) {
        return false;
    }

    list($iv, $encrypted) = $parts;
    $decrypted = openssl_decrypt($encrypted, $cipher, $passphrase, 0, $iv);

    return $decrypted;
}

$api_key = decrypt_stripe_key($encrypted_api_key, $passphrase);

if ($api_key === false) {
    echo "ERROR: Failed to decrypt API key.\n";
    exit(1);
}

echo "✓ API key decrypted\n";
echo "Syncing last $days_back days of Stripe data...\n\n";

// Call the Stripe sync function
if (function_exists('five01c3po_sync_stripe_transactions')) {
    $results = five01c3po_sync_stripe_transactions($api_key, $days_back);

    echo "=== SYNC RESULTS ===\n";
    echo "Charges Downloaded: {$results['charges_count']}\n";
    echo "Refunds Downloaded: {$results['refunds_count']}\n";
    echo "Balance Transactions: " . ($results['balance_transactions_count'] ?? 0) . "\n";
    echo "New Transactions: {$results['new_transactions']}\n";
    echo "Updated Transactions: {$results['updated_transactions']}\n";
    echo "Duplicates Skipped: {$results['duplicate_transactions']}\n";
    echo "Payout IDs Captured: {$results['payout_ids_captured']} / {$results['charges_count']}\n";
    echo "Members Matched: {$results['members_matched']}\n";
    echo "Total Revenue: $" . number_format($results['total_revenue'], 2) . "\n";

    if (!empty($results['balance_txns_new'])) {
        echo "\nBalance Transactions:\n";
        echo "  New: {$results['balance_txns_new']}\n";
        echo "  Updated: {$results['balance_txns_updated']}\n";
        echo "  Debits: {$results['balance_txns_debits']}\n";
        echo "  Debit Total: $" . number_format($results['balance_txns_debit_total'], 2) . "\n";
    }

    if (!empty($results['payouts_processed'])) {
        echo "\nPayout Processing:\n";
        echo "  Payouts Processed: {$results['payouts_processed']}\n";
        echo "  Payout IDs Populated: {$results['payout_ids_populated']}\n";
    }

    echo "\n✅ Stripe sync complete!\n";
} else {
    echo "ERROR: Stripe sync function not found. Make sure the plugin is loaded.\n";
    exit(1);
}
