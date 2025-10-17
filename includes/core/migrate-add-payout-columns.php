<?php
/**
 * Migration: Add payout columns to existing stripe_transactions table
 * Run this once to add payout tracking to existing installations
 */

if (!defined('ABSPATH')) exit;

function five01c3po_migrate_add_payout_columns() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'c3_stripe_transactions';

    // Check if columns already exist
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $column_names = array_map(function($col) { return $col->Field; }, $columns);

    $columns_to_add = array();

    if (!in_array('payout_id', $column_names)) {
        $columns_to_add[] = "ADD COLUMN payout_id varchar(255) AFTER stripe_created";
    }

    if (!in_array('payout_date', $column_names)) {
        $columns_to_add[] = "ADD COLUMN payout_date date AFTER payout_id";
    }

    if (!in_array('payout_arrival_date', $column_names)) {
        $columns_to_add[] = "ADD COLUMN payout_arrival_date date AFTER payout_date";
    }

    if (!in_array('payout_status', $column_names)) {
        $columns_to_add[] = "ADD COLUMN payout_status varchar(50) AFTER payout_arrival_date";
    }

    if (!in_array('balance_transaction_id', $column_names)) {
        $columns_to_add[] = "ADD COLUMN balance_transaction_id varchar(255) AFTER payout_status";
    }

    if (count($columns_to_add) > 0) {
        $sql = "ALTER TABLE $table_name " . implode(', ', $columns_to_add);
        $wpdb->query($sql);

        // Add indexes
        $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_payout (payout_id)");
        $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_payout_date (payout_date)");

        return array(
            'success' => true,
            'message' => 'Added ' . count($columns_to_add) . ' payout columns to stripe_transactions table',
            'columns_added' => count($columns_to_add)
        );
    } else {
        return array(
            'success' => true,
            'message' => 'Payout columns already exist',
            'columns_added' => 0
        );
    }
}

// Add admin page to run migration
add_action('admin_menu', function() {
    add_submenu_page(
        'five01c3po-settings',
        'Database Migration',
        'Database Migration',
        'manage_options',
        'five01c3po-migrate',
        'five01c3po_migrate_page'
    );
}, 99);

function five01c3po_migrate_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    ?>
    <div class="wrap">
        <h1>Database Migration</h1>

        <?php
        if (isset($_POST['run_migration']) && check_admin_referer('five01c3po_migrate')) {
            $result = five01c3po_migrate_add_payout_columns();

            if ($result['success']) {
                echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }
        ?>

        <div class="card">
            <h2>Add Payout Tracking Columns</h2>
            <p>This migration adds payout tracking columns to the Stripe transactions table:</p>
            <ul>
                <li><code>payout_id</code> - Stripe payout batch ID</li>
                <li><code>payout_date</code> - Date Stripe created the payout</li>
                <li><code>payout_arrival_date</code> - Expected bank arrival date (24-96 hours after charge)</li>
                <li><code>payout_status</code> - Status of payout (paid, in_transit, etc.)</li>
                <li><code>balance_transaction_id</code> - Stripe balance transaction ID</li>
            </ul>

            <p><strong>This migration is safe to run multiple times.</strong> It will only add columns that don't already exist.</p>

            <form method="post">
                <?php wp_nonce_field('five01c3po_migrate'); ?>
                <button type="submit" name="run_migration" class="button button-primary">
                    Run Migration
                </button>
            </form>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>After Migration</h2>
            <p>After running this migration, you should:</p>
            <ol>
                <li>Go to <strong>Membership → Stripe Integration</strong></li>
                <li>Click <strong>"Sync Historical Transactions"</strong></li>
                <li>This will re-sync all Stripe data and populate the new payout columns</li>
            </ol>
        </div>
    </div>
    <?php
}
