<?php
/**
 * Create stripe_balance_transactions table
 * Access via: yoursite.com/wp-content/plugins/501c3PO/create-balance-table-now.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

global $wpdb;

$table_name = $wpdb->prefix . 'stripe_balance_transactions';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    balance_txn_id varchar(255) NOT NULL,
    txn_type varchar(50) NOT NULL,
    source_id varchar(255),
    source_type varchar(50),
    amount decimal(10,2) NOT NULL,
    fee decimal(10,2) DEFAULT 0.00,
    net decimal(10,2) NOT NULL,
    currency varchar(10) DEFAULT 'usd',
    status varchar(50),
    description text,
    available_on date,
    created_at timestamp NOT NULL,
    payout_id varchar(255),
    synced_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_balance_txn (balance_txn_id),
    KEY idx_type (txn_type),
    KEY idx_source (source_id),
    KEY idx_payout (payout_id),
    KEY idx_available_on (available_on)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

echo "<h1>✓ Table Created!</h1>";

// Verify
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
if ($exists) {
    echo "<p style='color: green; font-weight: bold;'>Table '$table_name' exists!</p>";

    // Check if it has data
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "<p>Records in table: $count</p>";

    if ($count == 0) {
        echo "<p style='color: orange;'>Table is empty. Please run the Stripe sync again to populate it.</p>";
        echo "<p><a href='" . admin_url('admin.php?page=501c3PO-stripe-sync') . "'>Go to Stripe Sync →</a></p>";
    } else {
        echo "<p><a href='" . admin_url('admin.php?page=five01c3po-balance-analysis') . "'>View Balance Analysis →</a></p>";
    }
} else {
    echo "<p style='color: red;'>Error: Table was not created!</p>";
}
