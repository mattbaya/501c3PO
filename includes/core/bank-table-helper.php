<?php
/**
 * Bank Table Helper
 * Finds the correct bank transactions table (handles double-prefix issue)
 */

if (!defined('ABSPATH')) exit;

/**
 * Get the correct bank transactions table name
 * Handles the wp_swca_ double prefix issue
 */
function five01c3po_get_bank_table() {
    global $wpdb;
    static $bank_table = null;

    if ($bank_table !== null) {
        return $bank_table;
    }

    // Check possible table names in order of preference
    $possible_tables = array(
        'wp_swca_bank_transactions',  // The actual table with data
        $wpdb->prefix . 'bank_transactions',  // Standard WordPress prefix
        'wp_bank_transactions'  // Fallback
    );

    foreach ($possible_tables as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            if ($count > 0) {
                $bank_table = $table;
                return $bank_table;
            }
        }
    }

    // If no table with data found, use the first one that exists
    foreach ($possible_tables as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($exists) {
            $bank_table = $table;
            return $bank_table;
        }
    }

    // Default fallback
    $bank_table = $wpdb->prefix . 'bank_transactions';
    return $bank_table;
}
