<?php
// Test dynamic shortcode
require_once('/home/swca/public_html/wp-load.php');

echo "Testing swca_transactions_dynamic shortcode...\n\n";

// Check if Stripe API key is set
$stripe_key = get_option('swca_stripe_secret_key', '');
echo "Stripe API key: " . ($stripe_key ? "Set (***" . substr($stripe_key, -4) . ")" : "NOT SET") . "\n";

// Check bank transactions count
global $wpdb;
$bank_count = $wpdb->get_var("SELECT COUNT(*) FROM wp_swca_bank_transactions");
echo "Bank transactions in database: $bank_count\n";

// Check Gravity Forms entries count
$gf_count = $wpdb->get_var("SELECT COUNT(*) FROM wp_gf_entry WHERE form_id IN (2, 5, 7, 8) AND status = 'active'");
echo "Gravity Forms entries: $gf_count\n\n";

// Execute shortcode
echo "Executing shortcode...\n";
$output = do_shortcode('[swca_transactions_dynamic start_date="2025-01-01" end_date="2025-09-30"]');

// Check for errors
if (strpos($output, 'error') !== false || empty($output)) {
    echo "ERROR: Shortcode failed or returned empty output\n";
    echo substr($output, 0, 500) . "...\n";
} else {
    echo "SUCCESS: Shortcode executed successfully\n";
    echo "Output length: " . strlen($output) . " bytes\n";
    echo "Output preview (first 500 chars):\n";
    echo substr($output, 0, 500) . "...\n";
}
?>
