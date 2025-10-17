<?php
/**
 * Fix Bank Transactions Page Shortcode
 * Changes [mm_bank_transactions] to [five01c3po_bank_transactions]
 */

// Override wp_die for JSON warning
if (!function_exists('wp_die')) {
    function wp_die($message, $title = '', $args = array()) {
        if (is_string($message) && strpos($message, 'json') !== false && strpos($message, 'extension') !== false) {
            return;
        }
        die($message);
    }
}

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');

echo "=== FIXING BANK TRANSACTIONS PAGE SHORTCODE ===\n\n";

// Find the page
$page = get_page_by_path('board-portal/financial/bank-transactions');

if (!$page) {
    echo "ERROR: Could not find bank transactions page\n";
    exit(1);
}

echo "Found page: {$page->post_title} (ID: {$page->ID})\n\n";

// Show current content
echo "CURRENT CONTENT:\n";
echo str_repeat('-', 60) . "\n";
echo $page->post_content . "\n";
echo str_repeat('-', 60) . "\n\n";

// Replace the shortcode
$new_content = str_replace('[mm_bank_transactions', '[five01c3po_bank_transactions', $page->post_content);

if ($new_content === $page->post_content) {
    echo "No changes needed - shortcode already correct\n";
    exit(0);
}

// Update the page
$result = wp_update_post(array(
    'ID' => $page->ID,
    'post_content' => $new_content
));

if (is_wp_error($result)) {
    echo "ERROR: " . $result->get_error_message() . "\n";
    exit(1);
}

echo "✅ Page updated successfully!\n\n";

echo "NEW CONTENT:\n";
echo str_repeat('-', 60) . "\n";
echo $new_content . "\n";
echo str_repeat('-', 60) . "\n\n";

echo "✅ The bank transactions page should now display correctly!\n";
echo "Visit: https://southwilliamstown.org/board-portal/financial/bank-transactions/\n";
