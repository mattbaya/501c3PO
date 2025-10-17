<?php
/**
 * Check if Stripe API key is configured
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

// Check WordPress options table for 501c3PO settings
$result = $mysqli->query("SELECT option_value FROM wp_options WHERE option_name = 'five01c3po_organization_settings'");

if ($result && $row = $result->fetch_assoc()) {
    $settings = unserialize($row['option_value']);

    echo "=== STRIPE CONFIGURATION STATUS ===\n\n";

    if (isset($settings['stripe_api_key_encrypted']) && !empty($settings['stripe_api_key_encrypted'])) {
        echo "✓ Stripe API key is configured (encrypted)\n";
        echo "  Mode: " . ($settings['stripe_api_mode'] ?? 'unknown') . "\n";
        echo "  Passphrase required: " . (isset($settings['stripe_passphrase_hash']) ? 'YES' : 'NO') . "\n\n";
        echo "To sync Stripe, you need to:\n";
        echo "1. Visit: https://southwilliamstown.org/wp-admin/admin.php?page=501c3PO-stripe-sync\n";
        echo "2. Enter the officer passphrase\n";
        echo "3. Click 'Sync Transactions Now'\n";
    } else {
        echo "⚠️  No Stripe API key configured\n\n";
        echo "To sync Stripe, you need to:\n";
        echo "1. Visit: https://southwilliamstown.org/wp-admin/admin.php?page=501c3PO-stripe-sync\n";
        echo "2. Enter your Stripe Secret Key\n";
        echo "3. Optionally save it with an officer passphrase\n";
        echo "4. Click 'Sync Transactions Now'\n";
    }
} else {
    echo "⚠️  Could not find 501c3PO settings\n";
}

$mysqli->close();
?>
