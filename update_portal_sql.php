#!/usr/bin/php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$page_id = 4579;

$new_title = 'Board Portal';
$new_content = '
<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <h1 style="text-align: center; margin-bottom: 40px;">📋 SWCA Board Portal</h1>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;">

        <!-- Financial Reports Card -->
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="color: #2271b1; margin-top: 0;">💰 Financial Reports</h2>
            <p>View and analyze all financial transactions including bank and Stripe data.</p>
            <a href="/all-transactions-basic/" class="button button-primary" style="display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">View Transactions (Basic)</a>
            <a href="/all-transactions-with-names/" class="button button-primary" style="display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">View Transactions (with Names)</a>
        </div>

        <!-- Membership Directory Card -->
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="color: #2271b1; margin-top: 0;">👥 Membership Directory</h2>
            <p>Access the complete membership directory with contact information and status.</p>
            <a href="/wp-admin/admin.php?page=swca-simple" class="button button-primary" style="display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">View Directory</a>
        </div>

        <!-- Data Management Card -->
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="color: #2271b1; margin-top: 0;">🔧 Data Management</h2>
            <p>Import bank statements, configure Stripe API, and manage transaction data.</p>
            <a href="/wp-admin/admin.php?page=swca-bank-import" class="button button-secondary" style="display: inline-block; padding: 10px 20px; background: #646970; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">Import Bank Data</a>
            <a href="/wp-admin/admin.php?page=swca-transaction-settings" class="button button-secondary" style="display: inline-block; padding: 10px 20px; background: #646970; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">Settings</a>
        </div>

    </div>

    <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin-top: 30px; border-radius: 4px;">
        <h3 style="margin-top: 0;">ℹ️ Getting Started</h3>
        <ul style="margin-bottom: 0;">
            <li>Use the <strong>Financial Reports</strong> to view all transactions from bank and Stripe</li>
            <li>The <strong>Membership Directory</strong> shows all current and historical members</li>
            <li>Use <strong>Data Management</strong> to import new bank statements or configure Stripe API access</li>
            <li>All data queries happen in real-time from the database and Stripe API</li>
        </ul>
    </div>

    <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
        <p style="margin: 0;"><strong>🔒 Password Protected:</strong> This page and all sub-pages are private and require login to access.</p>
    </div>
</div>
';

// Escape content for SQL
$new_content_escaped = $mysqli->real_escape_string($new_content);
$new_title_escaped = $mysqli->real_escape_string($new_title);

// Update the page
$sql = "UPDATE swca_posts SET post_title = '$new_title_escaped', post_content = '$new_content_escaped' WHERE ID = $page_id";

if ($mysqli->query($sql)) {
    echo "✓ Board Portal page updated successfully (ID: $page_id)\n";

    // Hide from menu
    $meta_sql = "INSERT INTO swca_postmeta (post_id, meta_key, meta_value) VALUES ($page_id, '_menu_item_visibility', 'hide') ON DUPLICATE KEY UPDATE meta_value = 'hide'";
    $mysqli->query($meta_sql);

    echo "✓ Title changed to: Board Portal\n";
    echo "✓ Content updated with links to tools\n";
    echo "✓ Hidden from public menus\n";
} else {
    echo "✗ Error: " . $mysqli->error . "\n";
}

$mysqli->close();
?>
