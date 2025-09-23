<?php
/*
Plugin Name: 501c3PO
Description: The nonprofit droid you're looking for - Complete management system for nonprofit organizations
Version: 2.0.0
Author: Your Organization
License: GPL v2 or later
*/

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

// Plugin activation hooks
register_activation_hook(__FILE__, 'mm_create_dashboard_setup');
register_activation_hook(__FILE__, 'mm_create_custom_roles');
register_activation_hook(__FILE__, 'mm_create_email_tables');
register_activation_hook(__FILE__, 'mm_create_event_tables');
register_activation_hook(__FILE__, 'mm_create_officer_tools_tables');
register_activation_hook(__FILE__, 'mm_initialize_feature_toggles');
register_activation_hook(__FILE__, 'mm_create_member_tables');

// Initialize plugin
add_action('init', 'mm_init_shortcodes');
add_action('init', 'mm_dashboard_auth_check');
add_action('wp', 'mm_dashboard_password_protection');

// Include modular features
require_once plugin_dir_path(__FILE__) . 'includes/core/database.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/roles.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/dashboard.php';
require_once plugin_dir_path(__FILE__) . 'includes/features/email-management.php';
require_once plugin_dir_path(__FILE__) . 'includes/features/event-management.php';
require_once plugin_dir_path(__FILE__) . 'includes/features/financial-management.php';
require_once plugin_dir_path(__FILE__) . 'includes/features/officer-tools.php';
require_once plugin_dir_path(__FILE__) . 'includes/features/volunteer-management.php';

// Create member tables
function mm_create_member_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $table_name = $wpdb->prefix . 'members';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        partner_first_name varchar(100),
        partner_last_name varchar(100),
        family_members text,
        email_1 varchar(100),
        email_2 varchar(100),
        email_3 varchar(100),
        email_4 varchar(100),
        phone varchar(20),
        alternate_phone varchar(20),
        address varchar(255),
        city varchar(100),
        state varchar(50),
        zip_code varchar(10),
        alternate_address varchar(255),
        membership_type varchar(50),
        status_current_year varchar(10),
        status_previous_year varchar(10),
        membership_amount decimal(10,2),
        donation_amount decimal(10,2),
        total_amount decimal(10,2),
        payment_type varchar(50),
        business_affiliation varchar(255),
        on_email_list tinyint(1) DEFAULT 1,
        notes text,
        membership_month int,
        membership_month_previous int,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_last_name (last_name),
        KEY idx_email (email_1),
        KEY idx_status (status_current_year)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Initialize feature toggles
function mm_initialize_feature_toggles() {
    $default_features = array(
        'email_management' => true,
        'event_management' => true,
        'financial_management' => true,
        'volunteer_signups' => true,
        'officer_tools' => true,
        'committee_management' => true,
        'document_management' => true
    );
    
    if (!get_option('mm_enabled_features')) {
        update_option('mm_enabled_features', $default_features);
    }
    
    // Initialize organization settings
    if (!get_option('mm_organization_settings')) {
        update_option('mm_organization_settings', array(
            'organization_name' => 'Your Organization',
            'dashboard_password' => wp_generate_password(12, false),
            'fiscal_year_start' => 7, // July
            'currency' => 'USD'
        ));
    }
}

// Admin menu
add_action('admin_menu', 'mm_admin_menu');

function mm_admin_menu() {
    add_menu_page(
        'Membership Management',
        'Membership',
        'manage_options',
        'membership-management',
        'mm_admin_dashboard',
        'dashicons-groups',
        30
    );
    
    add_submenu_page(
        'membership-management',
        'Settings',
        'Settings',
        'manage_options',
        'mm-settings',
        'mm_settings_page'
    );
    
    add_submenu_page(
        'membership-management',
        'Export & Import',
        'Export & Import',
        'manage_options',
        'mm-export-import',
        'mm_export_import_page'
    );
}

// Settings page
function mm_settings_page() {
    ?>
    <div class="wrap">
        <h1>Membership Management Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('mm_settings_group'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Organization Name</th>
                    <td>
                        <input type="text" name="mm_organization_settings[organization_name]" 
                               value="<?php echo esc_attr(get_option('mm_organization_settings')['organization_name'] ?? 'Your Organization'); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Dashboard Password</th>
                    <td>
                        <input type="text" name="mm_organization_settings[dashboard_password]" 
                               value="<?php echo esc_attr(get_option('mm_organization_settings')['dashboard_password'] ?? ''); ?>" 
                               class="regular-text" />
                        <p class="description">Password required to access the member dashboard</p>
                    </td>
                </tr>
            </table>
            
            <h2>Feature Toggles</h2>
            <table class="form-table">
                <?php
                $features = array(
                    'email_management' => 'Email Management',
                    'event_management' => 'Event Management',
                    'financial_management' => 'Financial Management',
                    'volunteer_signups' => 'Volunteer Signups',
                    'officer_tools' => 'Officer Tools',
                    'committee_management' => 'Committee Management',
                    'document_management' => 'Document Management'
                );
                
                $enabled_features = get_option('mm_enabled_features', array());
                
                foreach ($features as $key => $label) {
                    ?>
                    <tr>
                        <th scope="row"><?php echo $label; ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="mm_enabled_features[<?php echo $key; ?>]" 
                                       value="1" <?php checked(isset($enabled_features[$key]) && $enabled_features[$key]); ?> />
                                Enable <?php echo $label; ?>
                            </label>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'mm_register_settings');

function mm_register_settings() {
    register_setting('mm_settings_group', 'mm_organization_settings');
    register_setting('mm_settings_group', 'mm_enabled_features');
}

// Create custom roles
function mm_create_custom_roles() {
    // Member role
    add_role('member', 'Member', array(
        'read' => true,
        'view_dashboard' => true,
        'view_member_directory' => true
    ));
    
    // Officer role
    add_role('officer', 'Officer', array(
        'read' => true,
        'view_dashboard' => true,
        'view_member_directory' => true,
        'manage_members' => true,
        'manage_events' => true,
        'approve_emails' => true,
        'create_agendas' => true,
        'create_minutes' => true
    ));
    
    // Treasurer role
    add_role('treasurer', 'Treasurer', array(
        'read' => true,
        'view_dashboard' => true,
        'view_member_directory' => true,
        'manage_members' => true,
        'manage_finances' => true,
        'view_financial_reports' => true,
        'manage_api_keys' => true
    ));
    
    // Committee Chair role
    add_role('committee_chair', 'Committee Chair', array(
        'read' => true,
        'view_dashboard' => true,
        'view_member_directory' => true,
        'manage_committee' => true,
        'create_committee_reports' => true
    ));
}

// Initialize shortcodes
function mm_init_shortcodes() {
    add_shortcode('member_stats', 'mm_stats_shortcode');
    add_shortcode('member_directory', 'mm_directory_shortcode');
    add_shortcode('member_dashboard', 'mm_dashboard_shortcode');
}

// Stats shortcode
function mm_stats_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'members';
    
    $total_members = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $current_year_paid = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_current_year = 'paid'");
    
    $org_settings = get_option('mm_organization_settings', array());
    $org_name = $org_settings['organization_name'] ?? 'Organization';
    
    ob_start();
    ?>
    <div class="membership-stats">
        <h3><?php echo esc_html($org_name); ?> Membership Statistics</h3>
        <ul>
            <li>Total Members: <?php echo $total_members; ?></li>
            <li>Current Year Paid Members: <?php echo $current_year_paid; ?></li>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}

// Basic admin dashboard
function mm_admin_dashboard() {
    ?>
    <div class="wrap">
        <h1>Membership Management Dashboard</h1>
        <p>Welcome to the Membership Management System. Use the menu on the left to access different features.</p>
        
        <div class="dashboard-widgets">
            <?php do_action('mm_admin_dashboard_widgets'); ?>
        </div>
    </div>
    <?php
}