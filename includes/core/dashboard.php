<?php
/**
 * Dashboard creation and management for 501c3PO
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Create dashboard pages on plugin activation
 */
function fiveohonec3po_create_dashboard_setup() {
    $org_settings = get_option('mm_organization_settings', array());
    $org_name = $org_settings['organization_name'] ?? 'Your Organization';
    
    // Check if dashboard page already exists
    $dashboard_page = get_page_by_path('dashboard');
    
    if (!$dashboard_page) {
        // Create the main dashboard page
        $dashboard_id = wp_insert_post(array(
            'post_title' => 'Dashboard',
            'post_name' => 'dashboard',
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
<p><a href="/dashboard">← Back to Dashboard</a> | <a href="/dashboard/historical-membership">Historical View</a> | <a href="/dashboard/member-directory">Member Directory</a></p>'),
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => $dashboard_id,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));
        
        $historical_membership_id = wp_insert_post(array(
            'post_title' => 'Historical Membership',
            'post_name' => 'historical-membership',
            'post_content' => '<h2>Historical Membership Overview</h2>
<p>View membership history across multiple years to see member retention and patterns.</p>

[member_historical_list]

<h3>Quick Links</h3>
<p><a href="/dashboard">← Back to Dashboard</a> | <a href="/dashboard/current-membership">Current Members</a> | <a href="/dashboard/member-directory">Member Directory</a></p>',
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
<p><a href="/dashboard">← Back to Dashboard</a> | <a href="/dashboard/current-membership">Current Members</a> | <a href="/dashboard/historical-membership">Historical View</a></p>', esc_html($org_name)),
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
<p><a href="/dashboard">← Back to Dashboard</a> | <a href="/dashboard/current-membership">Current Members</a> | <a href="/dashboard/fiscal-table">Fiscal Analysis</a></p>', esc_html($org_name)),
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => $dashboard_id,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));
        
        $fiscal_page_id = wp_insert_post(array(
            'post_title' => 'Fiscal Year Analysis',
            'post_name' => 'fiscal-table',
            'post_content' => sprintf('<h2>%s Fiscal Year Membership Analysis</h2>
[member_fiscal_table]

<h3>Quick Links</h3>
<p><a href="/dashboard">← Back to Dashboard</a> | <a href="/dashboard/stats">View Statistics</a> | <a href="/dashboard/current-membership">Current Members</a></p>', esc_html($org_name)),
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => $dashboard_id,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));
        
        // Only create feature pages if enabled
        $enabled_features = get_option('mm_enabled_features', array());
        
        if (!empty($enabled_features['email_management'])) {
            $email_dashboard_id = wp_insert_post(array(
                'post_title' => 'Email Management',
                'post_name' => 'email-dashboard',
                'post_content' => '<h2>📧 Email Management System</h2>
<p>Create, approve, and schedule bulk emails to members with role-based workflow.</p>

[email_dashboard]

<h3>Quick Links</h3>
<p><a href="/dashboard">← Back to Dashboard</a> | <a href="/dashboard/current-membership">Current Members</a> | <a href="/dashboard/member-directory">Member Directory</a></p>',
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
                'post_content' => '<h2>🎉 Event Management System</h2>
<p>Create events, manage RSVPs, and coordinate volunteer signups with SignUpGenius-style functionality.</p>

[event_dashboard]

<h3>Quick Links</h3>
<p><a href="/dashboard">← Back to Dashboard</a> | <a href="/dashboard/current-membership">Current Members</a> | <a href="/dashboard/email-dashboard">Email Management</a></p>',
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
<p><a href="/dashboard">← Back to Dashboard</a> | <a href="/dashboard/current-membership">Current Members</a> | <a href="/dashboard/event-dashboard">Event Management</a></p>', esc_html($org_name), esc_html($org_name)),
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
                'post_content' => '<h2>🏛️ Officer Management Tools</h2>
<p>Create agendas, manage meeting minutes, generate reports, and handle document uploads.</p>

[officer_tools_dashboard]

<h3>Quick Links</h3>
<p><a href="/dashboard">← Back to Dashboard</a> | <a href="/dashboard/settings">Settings</a> | <a href="/dashboard/event-dashboard">Event Management</a></p>',
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
}

/**
 * Hide dashboard pages from public menus
 */
function fiveohonec3po_hide_dashboard_from_menus($items, $menu, $args) {
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
function fiveohonec3po_hide_dashboard_from_page_lists($pages) {
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
add_filter('wp_get_nav_menu_items', 'fiveohonec3po_hide_dashboard_from_menus', 10, 3);
add_filter('get_pages', 'fiveohonec3po_hide_dashboard_from_page_lists');
add_action('wp', 'fiveohonec3po_dashboard_password_protection');