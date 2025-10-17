<?php
/**
 * Test passphrase against encrypted key in swca_options
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== TESTING PASSPHRASE ===\n\n";

// Get organization settings from CORRECT table
$result = $mysqli->query("SELECT option_value FROM swca_options WHERE option_name = 'five01c3po_organization_settings'");

if (!$result || $result->num_rows == 0) {
    die("ERROR: Could not find organization settings\n");
}

$row = $result->fetch_assoc();
$settings = unserialize($row['option_value']);

$encrypted_api_key = $settings['stripe_api_key_encrypted'] ?? '';
$passphrase_hash = $settings['stripe_passphrase_hash'] ?? '';

if (empty($encrypted_api_key) || empty($passphrase_hash)) {
    die("ERROR: No encrypted key or passphrase hash found\n");
}

echo "✓ Found encrypted API key (length: " . strlen($encrypted_api_key) . " chars)\n";
echo "✓ Found passphrase hash\n\n";

// Test the passphrase
$passphrase = 'POBox432';
echo "Testing passphrase: $passphrase\n";

if (password_verify($passphrase, $passphrase_hash)) {
    echo "✓✓✓ PASSPHRASE IS CORRECT!\n\n";

    // Try to decrypt the API key
    echo "Attempting to decrypt API key...\n";

    $cipher = "AES-256-CBC";
    $decoded = base64_decode($encrypted_api_key);
    $parts = explode('::', $decoded, 2);

    if (count($parts) !== 2) {
        die("ERROR: Invalid encrypted key format\n");
    }

    list($iv, $encrypted) = $parts;
    $api_key = openssl_decrypt($encrypted, $cipher, $passphrase, 0, $iv);

    if ($api_key === false) {
        echo "✗ FAILED to decrypt API key\n";
        echo "OpenSSL error: " . openssl_error_string() . "\n";
    } else {
        echo "✓✓✓ SUCCESSFULLY DECRYPTED API KEY!\n";
        echo "API key starts with: " . substr($api_key, 0, 10) . "...\n";
        $mode = (strpos($api_key, 'sk_live_') === 0) ? 'LIVE' : 'TEST';
        echo "Mode: $mode\n";
    }
} else {
    echo "✗ PASSPHRASE IS INCORRECT\n";
    echo "\nThe passphrase 'POBox432' does not match the stored hash.\n";
    echo "You may have used a different passphrase when encrypting the key.\n";
}

$mysqli->close();
?>
