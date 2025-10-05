<?php
/**
 * Dashboard creation and management for 501c3PO
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Create dashboard pages on plugin activation
 */
function five01c3po_create_dashboard_setup() {
    $org_settings = get_option('five01c3po_organization_settings', array());
    $org_name = $org_settings['organization_name'] ?? 'Your Organization';
    $board_portal_slug = $org_settings['board_portal_slug'] ?? 'board-portal';

    // Check if dashboard page already exists (check both old 'dashboard' and new slug)
    $dashboard_page = get_page_by_path($board_portal_slug);
    if (!$dashboard_page) {
        $dashboard_page = get_page_by_path('dashboard'); // Check for old slug
    }

    // If page exists, update it with correct content and slug
    if ($dashboard_page) {
        // Update slug if needed
        if ($dashboard_page->post_name !== $board_portal_slug) {
            wp_update_post(array(
                'ID' => $dashboard_page->ID,
                'post_name' => $board_portal_slug,
                'post_title' => 'Board Portal'
            ));
        }

        // Update content if it has old links or isn't using the shortcode
        if (strpos($dashboard_page->post_content, 'all-transactions-basic') !== false ||
            strpos($dashboard_page->post_content, '[member_dashboard_grid]') === false) {
            wp_update_post(array(
                'ID' => $dashboard_page->ID,
                'post_content' => sprintf('<h2>%s Member Dashboard</h2>
<p>Welcome to the %s member dashboard. Use the links below to access different sections:</p>

[member_dashboard_grid]', esc_html($org_name), esc_html($org_name))
            ));
        }

        update_option('nonprofit_dashboard_page_id', $dashboard_page->ID);
        return; // Don't create new pages if dashboard already exists
    }

    // Create the main dashboard page (only if it doesn't exist)
    $dashboard_id = wp_insert_post(array(
        'post_title' => 'Board Portal',
        'post_name' => $board_portal_slug,
        'post_content' => sprintf('<h2>%s Member Dashboard</h2>
<p>Welcome to the %s member dashboard. Use the links below to access different sections:</p>

[member_dashboard_grid]', esc_html($org_name), esc_html($org_name)),
        'post_status' => 'publish',
        'post_type' => 'page',
        'menu_order' => 999,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ));

    // Create child pages
    $current_membership_id = wp_insert_post(array(
        'post_title' => 'Current Membership',
        'post_name' => 'current-membership',
        'post_content' => sprintf('<h2>Current Year Membership</h2>
<p>This view shows all members color-coded by their current year payment status:</p>
<ul>
<li><strong style="background: #d4f8ff; padding: 3px;">Light Blue Background</strong> = Paid Members</li>
<li><strong style="background: #fff3cd; padding: 3px;">Yellow Background</strong> = Unpaid/Not Renewed</li>
</ul>

[member_current_list]

<h3>Quick Links</h3>
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/historical-membership">Historical View</a> | <a href="/%s/member-directory">Member Directory</a></p>', $board_portal_slug, $board_portal_slug, $board_portal_slug),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $dashboard_id,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ));

    $historical_membership_id = wp_insert_post(array(
        'post_title' => 'Historical Membership',
        'post_name' => 'historical-membership',
        'post_content' => sprintf('<h2>Historical Membership Overview</h2>
<p>View membership history across multiple years to see member retention and patterns.</p>

[member_historical_list]

<h3>Quick Links</h3>
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/current-membership">Current Members</a> | <a href="/%s/member-directory">Member Directory</a></p>', $board_portal_slug, $board_portal_slug, $board_portal_slug),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $dashboard_id,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ));

    $directory_page_id = wp_insert_post(array(
        'post_title' => 'Member Directory',
        'post_name' => 'member-directory',
        'post_content' => sprintf('<h2>%s Member Contact Directory</h2>
<p>Complete member contact information including addresses, emails, and phone numbers.</p>

[member_directory]

<h3>Quick Links</h3>
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/current-membership">Current Members</a> | <a href="/%s/historical-membership">Historical View</a></p>', esc_html($org_name), $board_portal_slug, $board_portal_slug, $board_portal_slug),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $dashboard_id,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ));

    $stats_page_id = wp_insert_post(array(
        'post_title' => 'Membership Statistics',
        'post_name' => 'stats',
        'post_content' => sprintf('<h2>%s Membership Statistics</h2>
[member_stats]

<h3>Quick Links</h3>
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/current-membership">Current Members</a> | <a href="/%s/financial">Financial Reports</a></p>', esc_html($org_name), $board_portal_slug, $board_portal_slug, $board_portal_slug),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $dashboard_id,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ));

    // Create Financial Reports section
    $financial_landing_id = wp_insert_post(array(
        'post_title' => 'Financial Reports',
        'post_name' => 'financial',
        'post_content' => sprintf('<h2>💰 %s Financial Reports</h2>
<p>Access all financial reports, transaction history, and fiscal year analysis.</p>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0;">
    <div style="border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
        <h3>📊 Fiscal Year Analysis</h3>
        <p>View membership revenue and trends by fiscal year.</p>
        <p><a href="/%s/financial/fiscal-year-analysis" class="button button-primary">View Analysis →</a></p>
    </div>

    <div style="border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
        <h3>🏦 Bank Transactions</h3>
        <p>View all bank transaction history and account activity.</p>
        <p><a href="/%s/financial/bank-transactions" class="button button-primary">View Transactions →</a></p>
    </div>
</div>

<h3>Quick Links</h3>
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/stats">View Statistics</a> | <a href="/%s/current-membership">Current Members</a></p>', esc_html($org_name), $board_portal_slug, $board_portal_slug, $board_portal_slug, $board_portal_slug, $board_portal_slug),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $dashboard_id,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ));

    // Fiscal Year Analysis (child of Financial)
    $fiscal_page_id = wp_insert_post(array(
        'post_title' => 'Fiscal Year Analysis',
        'post_name' => 'fiscal-year-analysis',
        'post_content' => sprintf('<h2>%s Fiscal Year Membership Analysis</h2>
[member_fiscal_table]

<h3>Quick Links</h3>
<p><a href="/%s/financial">← Back to Financial Reports</a> | <a href="/%s">Dashboard</a> | <a href="/%s/stats">View Statistics</a></p>', esc_html($org_name), $board_portal_slug, $board_portal_slug, $board_portal_slug),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $financial_landing_id,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ));

    // Bank Transactions (child of Financial)
    $bank_transactions_page_id = wp_insert_post(array(
        'post_title' => 'Bank Transactions',
        'post_name' => 'bank-transactions',
        'post_content' => sprintf('<h2>🏦 %s Bank Transaction History</h2>
<p>Complete history of all bank transactions, deposits, and withdrawals.</p>

[five01c3po_bank_transactions limit="100"]

<h3>Quick Links</h3>
<p><a href="/%s/financial">← Back to Financial Reports</a> | <a href="/%s">Dashboard</a> | <a href="/%s/financial/fiscal-year-analysis">Fiscal Analysis</a></p>', esc_html($org_name), $board_portal_slug, $board_portal_slug, $board_portal_slug),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $financial_landing_id,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ));

    // Only create feature pages if enabled
    $enabled_features = get_option('five01c3po_enabled_features', array());

    if (!empty($enabled_features['email_management'])) {
        $email_dashboard_id = wp_insert_post(array(
            'post_title' => 'Email Management',
            'post_name' => 'email-dashboard',
            'post_content' => sprintf('<h2>📧 Email Management System</h2>
<p>Create, approve, and schedule bulk emails to members with role-based workflow.</p>

[email_dashboard]

<h3>Quick Links</h3>
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/current-membership">Current Members</a> | <a href="/%s/member-directory">Member Directory</a></p>', $board_portal_slug, $board_portal_slug, $board_portal_slug),
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => $dashboard_id,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));
    }

    if (!empty($enabled_features['event_management'])) {
        $event_dashboard_id = wp_insert_post(array(
            'post_title' => 'Event Management',
            'post_name' => 'event-dashboard',
            'post_content' => sprintf('<h2>🎉 Event Management System</h2>
<p>Create events, manage RSVPs, and coordinate volunteer signups with SignUpGenius-style functionality.</p>

[event_dashboard]

<h3>Quick Links</h3>
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/current-membership">Current Members</a> | <a href="/%s/email-dashboard">Email Management</a></p>', $board_portal_slug, $board_portal_slug, $board_portal_slug),
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => $dashboard_id,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));
    }

    $settings_page_id = wp_insert_post(array(
        'post_title' => 'Settings & API Keys',
        'post_name' => 'settings',
        'post_content' => sprintf('<h2>⚙️ %s Settings & Configuration</h2>
<p>Manage API keys, integrations, and system settings for %s membership management.</p>

[settings_dashboard]

<h3>Quick Links</h3>
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/current-membership">Current Members</a> | <a href="/%s/event-dashboard">Event Management</a></p>', esc_html($org_name), esc_html($org_name), $board_portal_slug, $board_portal_slug, $board_portal_slug),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $dashboard_id,
        'comment_status' => 'closed',
        'ping_status' => 'closed'
    ));

    if (!empty($enabled_features['officer_tools'])) {
        $officer_tools_id = wp_insert_post(array(
            'post_title' => 'Officer Tools',
            'post_name' => 'officer-tools',
            'post_content' => sprintf('<h2>🏛️ Officer Management Tools</h2>
<p>Create agendas, manage meeting minutes, generate reports, and handle document uploads.</p>

[officer_tools_dashboard]

<h3>Quick Links</h3>
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/settings">Settings</a> | <a href="/%s/event-dashboard">Event Management</a></p>', $board_portal_slug, $board_portal_slug, $board_portal_slug),
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => $dashboard_id,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));
    }

    // Store page IDs for future reference
    update_option('nonprofit_dashboard_page_id', $dashboard_id);
}

/**
 * Hide dashboard pages from public menus
 */
function five01c3po_hide_dashboard_from_menus($items, $menu, $args) {
    if (is_admin()) {
        return $items;
    }

    $dashboard_page_id = get_option('nonprofit_dashboard_page_id');
    if (!$dashboard_page_id) {
        return $items;
    }

    foreach ($items as $key => $item) {
        if ($item->object_id == $dashboard_page_id ||
            wp_get_post_parent_id($item->object_id) == $dashboard_page_id) {
            unset($items[$key]);
        }
    }

    return $items;
}

/**
 * Hide dashboard pages from page lists
 */
function five01c3po_hide_dashboard_from_page_lists($pages) {
    if (is_admin()) {
        return $pages;
    }

    $dashboard_page_id = get_option('nonprofit_dashboard_page_id');
    if (!$dashboard_page_id) {
        return $pages;
    }

    foreach ($pages as $key => $page) {
        if ($page->ID == $dashboard_page_id ||
            wp_get_post_parent_id($page->ID) == $dashboard_page_id) {
            unset($pages[$key]);
        }
    }

    return $pages;
}

// Hook functions
add_filter('wp_get_nav_menu_items', 'five01c3po_hide_dashboard_from_menus', 10, 3);
add_filter('get_pages', 'five01c3po_hide_dashboard_from_page_lists');
add_action('wp', 'five01c3po_dashboard_password_protection');
