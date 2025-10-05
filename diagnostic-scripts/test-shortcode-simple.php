<?php
require_once('/home/swca/public_html/wp-load.php');

// Check if shortcode is registered
global $shortcode_tags;
$registered = isset($shortcode_tags['swca_transactions_dynamic']);

echo "Shortcode registered: " . ($registered ? "YES" : "NO") . "\n";

if ($registered) {
    echo "Testing execution...\n";
    $output = do_shortcode('[swca_transactions_dynamic]');

    if ($output === '[swca_transactions_dynamic]') {
        echo "ERROR: Shortcode not executed\n";
    } else {
        echo "SUCCESS: Shortcode executed\n";
        echo "Output length: " . strlen($output) . " bytes\n";

        // Check if we have table in output
        if (strpos($output, 'swca-transactions-table') !== false) {
            echo "✓ Table HTML found\n";
        }
        if (strpos($output, 'Total Transactions') !== false) {
            echo "✓ Summary cards found\n";
        }
    }
}
?>
