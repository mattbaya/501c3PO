<?php
/**
 * User roles and capabilities for 501c3PO
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Create custom roles for the organization
 */
function five01c3po_create_custom_roles() {
    // Remove existing roles if they exist (for clean reinstall)
    remove_role('nonprofit_member');
    remove_role('nonprofit_officer');
    remove_role('nonprofit_treasurer');
    remove_role('nonprofit_committee_chair');
    
    // Member role - Basic access
    add_role('nonprofit_member', 'Member', array(
        'read' => true,
        'view_dashboard' => true,
        'view_member_directory' => true,
        'update_own_profile' => true,
        'rsvp_events' => true,
        'volunteer_signup' => true
    ));
    
    // Officer role - Management capabilities
    add_role('nonprofit_officer', 'Officer', array(
        'read' => true,
        'view_dashboard' => true,
        'view_member_directory' => true,
        'update_own_profile' => true,
        'rsvp_events' => true,
        'volunteer_signup' => true,
        'manage_members' => true,
        'manage_events' => true,
        'create_emails' => true,
        'approve_emails' => true,
        'create_agendas' => true,
        'create_minutes' => true,
        'approve_minutes' => true,
        'view_reports' => true
    ));
    
    // Treasurer role - Financial access
    add_role('nonprofit_treasurer', 'Treasurer', array(
        'read' => true,
        'view_dashboard' => true,
        'view_member_directory' => true,
        'update_own_profile' => true,
        'rsvp_events' => true,
        'volunteer_signup' => true,
        'manage_members' => true,
        'manage_finances' => true,
        'view_financial_reports' => true,
        'manage_api_keys' => true,
        'export_data' => true,
        'import_data' => true,
        'process_refunds' => true
    ));
    
    // Committee Chair role
    add_role('nonprofit_committee_chair', 'Committee Chair', array(
        'read' => true,
        'view_dashboard' => true,
        'view_member_directory' => true,
        'update_own_profile' => true,
        'rsvp_events' => true,
        'volunteer_signup' => true,
        'manage_own_committee' => true,
        'create_committee_reports' => true,
        'manage_committee_members' => true,
        'create_committee_agendas' => true,
        'create_committee_minutes' => true
    ));
    
    // Grant administrator all custom capabilities
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $all_caps = array(
            'view_dashboard',
            'view_member_directory',
            'update_own_profile',
            'rsvp_events',
            'volunteer_signup',
            'manage_members',
            'manage_events',
            'create_emails',
            'approve_emails',
            'create_agendas',
            'create_minutes',
            'approve_minutes',
            'view_reports',
            'manage_finances',
            'view_financial_reports',
            'manage_api_keys',
            'export_data',
            'import_data',
            'process_refunds',
            'manage_own_committee',
            'create_committee_reports',
            'manage_committee_members',
            'create_committee_agendas',
            'create_committee_minutes'
        );
        
        foreach ($all_caps as $cap) {
            $admin_role->add_cap($cap);
        }
    }
}

/**
 * Check if user has permission to view dashboard
 */
function five01c3po_can_view_dashboard() {
    if (is_user_logged_in()) {
        return current_user_can('view_dashboard');
    }
    
    // Check if password cookie is set
    $org_settings = get_option('five01c3po_organization_settings', array());
    $dashboard_password = $org_settings['dashboard_password'] ?? '';
    
    if (empty($dashboard_password)) {
        return true; // No password set, allow access
    }
    
    $cookie_name = 'nonprofit_dashboard_' . COOKIEHASH;
    return isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] === md5($dashboard_password);
}

/**
 * Handle dashboard password protection
 */
function five01c3po_dashboard_password_protection() {
    if (!is_page()) {
        return;
    }
    
    $current_page = get_post();
    if (!$current_page) {
        return;
    }
    
    // Check if this is a dashboard page
    $dashboard_pages = array('dashboard', 'current-membership', 'historical-membership', 
                           'member-directory', 'stats', 'fiscal-table', 'email-dashboard',
                           'event-dashboard', 'settings', 'officer-tools');
    
    $is_dashboard_page = false;
    foreach ($dashboard_pages as $slug) {
        if ($current_page->post_name === $slug || 
            (strpos($current_page->post_name, $slug) === 0) ||
            (get_page_by_path('dashboard/' . $slug))) {
            $is_dashboard_page = true;
            break;
        }
    }
    
    if (!$is_dashboard_page) {
        return;
    }
    
    // Check if user has access
    if (five01c3po_can_view_dashboard()) {
        return;
    }
    
    // Handle password submission
    if (isset($_POST['dashboard_password'])) {
        $org_settings = get_option('five01c3po_organization_settings', array());
        $correct_password = $org_settings['dashboard_password'] ?? '';
        
        if ($_POST['dashboard_password'] === $correct_password) {
            $cookie_name = 'nonprofit_dashboard_' . COOKIEHASH;
            setcookie($cookie_name, md5($correct_password), time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
            wp_redirect(remove_query_arg('incorrect_password'));
            exit;
        } else {
            wp_redirect(add_query_arg('incorrect_password', '1'));
            exit;
        }
    }
    
    // Show password form
    five01c3po_show_password_form();
    exit;
}

/**
 * Display password protection form
 */
function five01c3po_show_password_form() {
    $org_settings = get_option('five01c3po_organization_settings', array());
    $org_name = $org_settings['organization_name'] ?? '501c3PO';
    
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($org_name); ?> Dashboard - Password Required</title>
        <?php wp_head(); ?>
        <style>
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                background: #f0f0f0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .password-form-container {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 400px;
                width: 90%;
                text-align: center;
            }
            .password-form-container h2 {
                margin-bottom: 10px;
                color: #333;
            }
            .password-form-container p {
                color: #666;
                margin-bottom: 30px;
            }
            .password-form-container input[type="password"] {
                width: 100%;
                padding: 12px;
                font-size: 16px;
                border: 2px solid #ddd;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .password-form-container input[type="submit"] {
                background: #0073aa;
                color: white;
                padding: 12px 30px;
                font-size: 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: background 0.3s;
            }
            .password-form-container input[type="submit"]:hover {
                background: #005a87;
            }
            .error-message {
                color: #d63638;
                margin-bottom: 20px;
            }
            .droid-icon {
                font-size: 48px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="password-form-container">
            <div class="droid-icon">🤖</div>
            <h2><?php echo esc_html($org_name); ?> Dashboard</h2>
            <p>This area is password protected</p>
            
            <?php if (isset($_GET['incorrect_password'])): ?>
                <p class="error-message">Incorrect password. Please try again.</p>
            <?php endif; ?>
            
            <form method="post">
                <input type="password" name="dashboard_password" placeholder="Enter password" autofocus required>
                <input type="submit" value="Access Dashboard">
            </form>
        </div>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
}