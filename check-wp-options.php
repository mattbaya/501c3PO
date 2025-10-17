<?php
/**
 * Check wp_options for 501c3PO settings
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== CHECKING WP_OPTIONS FOR 501c3PO SETTINGS ===\n\n";

// Check for any 501c3po or five01c3po options
$result = $mysqli->query("SELECT option_name FROM wp_options WHERE option_name LIKE '%501c3po%' OR option_name LIKE '%five01c3po%'");

if ($result && $result->num_rows > 0) {
    echo "Found " . $result->num_rows . " 501c3PO option(s):\n\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['option_name'] . "\n";
    }
    echo "\n";

    // Now check specifically for organization settings
    $result2 = $mysqli->query("SELECT option_value FROM wp_options WHERE option_name = 'five01c3po_organization_settings'");

    if ($result2 && $result2->num_rows > 0) {
        $row = $result2->fetch_assoc();
        $settings = unserialize($row['option_value']);

        echo "=== ORGANIZATION SETTINGS ===\n\n";

        if (isset($settings['stripe_api_key_encrypted']) && !empty($settings['stripe_api_key_encrypted'])) {
            echo "✓ Stripe API key IS CONFIGURED (encrypted)\n";
            echo "  - Encrypted key length: " . strlen($settings['stripe_api_key_encrypted']) . " characters\n";
            echo "  - API Mode: " . ($settings['stripe_api_mode'] ?? 'unknown') . "\n";
            echo "  - Has passphrase hash: " . (isset($settings['stripe_passphrase_hash']) ? 'YES' : 'NO') . "\n";

            if (isset($settings['stripe_passphrase_hash'])) {
                echo "  - Passphrase hash: " . substr($settings['stripe_passphrase_hash'], 0, 20) . "...\n";
            }
        } else {
            echo "⚠️  No Stripe API key configured\n";
        }

        echo "\nAll settings keys:\n";
        foreach (array_keys($settings) as $key) {
            echo "  - $key\n";
        }
    }
} else {
    echo "⚠️  No 501c3PO options found in wp_options table\n";
    echo "\nThis could mean:\n";
    echo "1. The plugin hasn't been activated yet\n";
    echo "2. The settings haven't been saved yet\n";
    echo "3. Wrong table prefix (checking 'wp_options')\n";
}

$mysqli->close();
?>
