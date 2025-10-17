<?php
/**
 * Check ALL options tables for Stripe settings
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== CHECKING ALL OPTIONS TABLES ===\n\n";

// Get all tables with 'options' in the name
$tables = [];
$result = $mysqli->query("SHOW TABLES LIKE '%options%'");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

echo "Found " . count($tables) . " options tables:\n";
foreach ($tables as $table) {
    echo "  - $table\n";
}
echo "\n";

// Check each table for 501c3PO settings
foreach ($tables as $table) {
    echo "Checking $table:\n";

    // Count total rows
    $count = $mysqli->query("SELECT COUNT(*) as total FROM $table");
    $count_row = $count->fetch_assoc();
    echo "  Total rows: " . $count_row['total'] . "\n";

    // Search for 501c3PO options
    $result = $mysqli->query("SELECT option_name FROM $table WHERE option_name LIKE '%501c3po%' OR option_name LIKE '%five01c3po%'");

    if ($result && $result->num_rows > 0) {
        echo "  ✓ FOUND 501c3PO options: " . $result->num_rows . "\n";
        while ($row = $result->fetch_assoc()) {
            echo "    - " . $row['option_name'] . "\n";
        }

        // Check specifically for organization settings
        $org_result = $mysqli->query("SELECT option_value FROM $table WHERE option_name = 'five01c3po_organization_settings'");
        if ($org_result && $org_result->num_rows > 0) {
            echo "\n  ✓✓✓ FOUND five01c3po_organization_settings!\n";
            $org_row = $org_result->fetch_assoc();
            $settings = unserialize($org_row['option_value']);

            if (is_array($settings)) {
                echo "  Settings keys:\n";
                foreach (array_keys($settings) as $key) {
                    echo "    - $key";
                    if ($key === 'stripe_api_key_encrypted' && !empty($settings[$key])) {
                        echo " ✓ HAS VALUE (length: " . strlen($settings[$key]) . " chars)";
                    }
                    if ($key === 'stripe_passphrase_hash' && !empty($settings[$key])) {
                        echo " ✓ HAS VALUE";
                    }
                    echo "\n";
                }
            }
        }
    } else {
        echo "  No 501c3PO options\n";
    }
    echo "\n";
}

$mysqli->close();
?>
