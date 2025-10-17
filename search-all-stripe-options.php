<?php
/**
 * Search ALL wp_options for anything related to stripe, api, key, passphrase
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== SEARCHING ALL WP_OPTIONS FOR STRIPE/API RELATED SETTINGS ===\n\n";

$search_terms = ['stripe', 'api', 'passphrase', 'organization', 'settings'];

foreach ($search_terms as $term) {
    echo "Searching for '$term'...\n";
    $result = $mysqli->query("SELECT option_name FROM wp_options WHERE option_name LIKE '%$term%' OR option_value LIKE '%$term%' LIMIT 20");

    if ($result && $result->num_rows > 0) {
        echo "  Found " . $result->num_rows . " matches:\n";
        while ($row = $result->fetch_assoc()) {
            echo "    - " . $row['option_name'] . "\n";
        }
    } else {
        echo "  No matches\n";
    }
    echo "\n";
}

// Also check the actual temp scripts to see what they're looking for
echo "\n=== CHECKING WHAT THE TEMP SCRIPTS USE ===\n\n";

$temp_files = [
    '/home/swca/public_html/wp-content/plugins/501c3PO/temp/sync_stripe.php',
    '/home/swca/public_html/wp-content/plugins/501c3PO/temp/run_sync.php'
];

foreach ($temp_files as $file) {
    if (file_exists($file)) {
        echo "File: " . basename($file) . "\n";
        $content = file_get_contents($file);

        // Extract the option name they're looking for
        if (preg_match('/get_option\([\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            echo "  Looking for option: " . $matches[1] . "\n";

            // Check if that option exists
            $option_name = $matches[1];
            $check = $mysqli->query("SELECT option_value FROM wp_options WHERE option_name = '$option_name'");

            if ($check && $check->num_rows > 0) {
                echo "  ✓ Option EXISTS in database\n";
                $row = $check->fetch_assoc();
                $value = unserialize($row['option_value']);
                if (is_array($value)) {
                    echo "  Keys in this option:\n";
                    foreach (array_keys($value) as $key) {
                        echo "    - $key\n";
                    }
                }
            } else {
                echo "  ⚠️  Option DOES NOT EXIST in database\n";
            }
        }
        echo "\n";
    }
}

$mysqli->close();
?>
