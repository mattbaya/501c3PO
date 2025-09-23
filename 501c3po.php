<?php
/*
Plugin Name: 501c3PO
Plugin URI: https://github.com/mattbaya/501c3PO
Description: The nonprofit droid you're looking for - Complete management system for nonprofit organizations
Version: 2.0.0
Author: Your Organization
License: GPL v2 or later
Update URI: https://github.com/mattbaya/501c3PO
*/

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

// Enable automatic updates from GitHub
require_once plugin_dir_path(__FILE__) . 'includes/class-update-checker.php';
new FiveOhOnecThreePO_Update_Checker(__FILE__);

// Load WP-CLI commands if available
if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-cli-commands.php';
}

// Plugin activation hooks
register_activation_hook(__FILE__, 'fiveohonec3po_create_tables');
register_activation_hook(__FILE__, 'fiveohonec3po_create_dashboard_setup');
register_activation_hook(__FILE__, 'fiveohonec3po_create_custom_roles');
register_activation_hook(__FILE__, 'mm_initialize_feature_toggles');

// Initialize plugin
add_action('init', 'mm_init_shortcodes');

// Include core functionality
require_once plugin_dir_path(__FILE__) . 'includes/core/database.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/roles.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/dashboard.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/shortcodes.php';

// Include feature modules (if they exist)
$feature_files = array(
    'includes/features/email-management.php',
    'includes/features/event-management.php',
    'includes/features/financial-management.php',
    'includes/features/officer-tools.php',
    'includes/features/volunteer-management.php'
);

foreach ($feature_files as $file) {
    $file_path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    }
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