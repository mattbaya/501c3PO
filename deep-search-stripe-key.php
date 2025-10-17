<?php
/**
 * Deep search for Stripe API key in database
 * Check all possible locations and table prefixes
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== DEEP SEARCH FOR STRIPE API KEY ===\n\n";

// 1. Check all possible option names
echo "1. Checking all possible option names:\n";
$option_patterns = [
    'five01c3po_organization_settings',
    '501c3po_organization_settings',
    'swca_organization_settings',
    'stripe_settings',
    'stripe_api_key',
    'organization_settings'
];

foreach ($option_patterns as $pattern) {
    $result = $mysqli->query("SELECT option_name, option_value FROM wp_options WHERE option_name LIKE '%$pattern%'");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "  ✓ FOUND: " . $row['option_name'] . "\n";
            $value = @unserialize($row['option_value']);
            if (is_array($value)) {
                echo "    Keys: " . implode(', ', array_keys($value)) . "\n";
                if (isset($value['stripe_api_key_encrypted'])) {
                    echo "    ✓✓✓ HAS ENCRYPTED STRIPE KEY!\n";
                    echo "    Encrypted key length: " . strlen($value['stripe_api_key_encrypted']) . " chars\n";
                    echo "    Has passphrase hash: " . (isset($value['stripe_passphrase_hash']) ? 'YES' : 'NO') . "\n";
                }
            }
        }
    }
}

// 2. Search for any serialized data containing 'stripe_api_key_encrypted'
echo "\n2. Searching for serialized data with 'stripe_api_key_encrypted':\n";
$result = $mysqli->query("SELECT option_name FROM wp_options WHERE option_value LIKE '%stripe_api_key_encrypted%'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  ✓ FOUND in: " . $row['option_name'] . "\n";
    }
} else {
    echo "  No matches found\n";
}

// 3. Check if WordPress is even using wp_options (could be different prefix)
echo "\n3. Checking table structure:\n";
$result = $mysqli->query("SHOW TABLES LIKE '%options%'");
if ($result) {
    echo "  Tables with 'options' in name:\n";
    while ($row = $result->fetch_array()) {
        echo "    - " . $row[0] . "\n";
    }
}

// 4. Count total options in wp_options
$count = $mysqli->query("SELECT COUNT(*) as total FROM wp_options");
if ($count) {
    $row = $count->fetch_assoc();
    echo "  Total rows in wp_options: " . $row['total'] . "\n";
}

// 5. Check if there are ANY 501c3PO-related options
echo "\n4. Checking for ANY 501c3PO options:\n";
$result = $mysqli->query("SELECT option_name FROM wp_options WHERE option_name LIKE '%501%' OR option_name LIKE '%five%' OR option_name LIKE '%c3po%'");
if ($result && $result->num_rows > 0) {
    echo "  Found " . $result->num_rows . " options:\n";
    while ($row = $result->fetch_assoc()) {
        echo "    - " . $row['option_name'] . "\n";
    }
} else {
    echo "  ⚠️  NO 501c3PO options found at all\n";
}

// 6. Check WordPress active plugins to see if 501c3PO is even activated
echo "\n5. Checking if 501c3PO plugin is activated:\n";
$result = $mysqli->query("SELECT option_value FROM wp_options WHERE option_name = 'active_plugins'");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $plugins = @unserialize($row['option_value']);
    if (is_array($plugins)) {
        echo "  Active plugins:\n";
        foreach ($plugins as $plugin) {
            echo "    - $plugin\n";
            if (strpos($plugin, '501') !== false || strpos($plugin, 'c3po') !== false) {
                echo "      ✓ THIS IS THE 501c3PO PLUGIN!\n";
            }
        }
    }
}

$mysqli->close();
?>
