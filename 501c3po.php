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
register_activation_hook(__FILE__, 'five01c3po_create_tables');
register_activation_hook(__FILE__, 'five01c3po_create_dashboard_setup');
register_activation_hook(__FILE__, 'five01c3po_create_custom_roles');
register_activation_hook(__FILE__, 'five01c3po_initialize_feature_toggles');

// Initialize plugin
add_action('init', 'five01c3po_init_shortcodes');

// Include core functionality
require_once plugin_dir_path(__FILE__) . 'includes/core/database.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/roles.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/dashboard.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/migrate-add-payout-columns.php';

// Include feature modules (if they exist)
$feature_files = array(
    'includes/features/email-management.php',
    'includes/features/event-management.php',
    'includes/features/financial-management.php',
    'includes/features/officer-tools.php',
    'includes/features/volunteer-management.php',
    'includes/features/data-export-import.php',
    'includes/features/bank-transactions.php',
    'includes/features/stripe-integration.php',
    'includes/features/unified-transactions.php',
    'includes/features/transaction-matching.php',
    'includes/features/grouped-transactions.php',
    'includes/features/transaction-ledger.php',
    'includes/features/calculate-balances.php',
    'includes/features/bank-statements.php',
    'includes/features/view-stripe-transaction.php',
    'includes/features/view-bank-transaction.php'
);

foreach ($feature_files as $file) {
    $file_path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}


// Initialize feature toggles
function five01c3po_initialize_feature_toggles() {
    $default_features = array(
        'email_management' => true,
        'event_management' => true,
        'financial_management' => true,
        'volunteer_signups' => true,
        'officer_tools' => true,
        'committee_management' => true,
        'document_management' => true,
        'data_export_import' => true,
        'bank_transactions' => true
    );
    
    if (!get_option('five01c3po_enabled_features')) {
        update_option('five01c3po_enabled_features', $default_features);
    }
    
    // Initialize organization settings
    if (!get_option('five01c3po_organization_settings')) {
        update_option('five01c3po_organization_settings', array(
            'organization_name' => 'Your Organization',
            'dashboard_password' => wp_generate_password(12, false),
            'fiscal_year_start' => 7, // July
            'currency' => 'USD',
            'board_portal_slug' => 'board-portal'
        ));
    } else {
        // Add board_portal_slug to existing settings if not present
        $org_settings = get_option('five01c3po_organization_settings');
        if (!isset($org_settings['board_portal_slug'])) {
            $org_settings['board_portal_slug'] = 'board-portal';
            update_option('five01c3po_organization_settings', $org_settings);
        }
    }
}

// Admin menu
add_action('admin_menu', 'five01c3po_admin_menu');

function five01c3po_admin_menu() {
    // Get organization name from settings, default to 501c3PO
    $org_settings = get_option('five01c3po_organization_settings', array());
    $menu_label = !empty($org_settings['organization_name']) && $org_settings['organization_name'] !== 'Your Organization'
        ? $org_settings['organization_name']
        : '501c3PO';

    add_menu_page(
        '501c3PO Management',
        $menu_label,
        'manage_options',
        'membership-management',
        'five01c3po_admin_dashboard',
        'dashicons-groups',
        30
    );
    
    add_submenu_page(
        'membership-management',
        'Settings',
        'Settings',
        'manage_options',
        '501c3PO-settings',
        'five01c3po_settings_page'
    );

    // Add Board Portal link (external)
    add_submenu_page(
        'membership-management',
        'Board Portal',
        '🔐 Board Portal',
        'read',  // All logged-in users can see the link
        'board-portal-redirect',
        'five01c3po_board_portal_redirect'
    );

    // Export & Import and Bank Transactions menus are added by their respective feature modules
}

// Board Portal redirect function
function five01c3po_board_portal_redirect() {
    $org_settings = get_option('five01c3po_organization_settings', array());
    $board_portal_slug = $org_settings['board_portal_slug'] ?? 'board-portal';
    wp_redirect(home_url('/' . $board_portal_slug));
    exit;
}

// Create missing financial pages if they don't exist
add_action('admin_init', 'five01c3po_create_missing_financial_pages');

function five01c3po_create_missing_financial_pages() {
    // Only run once
    if (get_option('five01c3po_financial_pages_created')) {
        return;
    }

    $org_settings = get_option('five01c3po_organization_settings', array());
    $org_name = $org_settings['organization_name'] ?? 'Your Organization';
    $board_portal_slug = $org_settings['board_portal_slug'] ?? 'board-portal';

    $board_portal = get_page_by_path($board_portal_slug);
    if (!$board_portal) {
        return;
    }

    // Check if financial page exists
    $financial_page = get_page_by_path($board_portal_slug . '/financial');

    if (!$financial_page) {
        // Create the financial landing page
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
<p><a href="/%s">← Back to Dashboard</a> | <a href="/%s/stats">View Statistics</a> | <a href="/%s/current-membership">Current Members</a></p>',
                esc_html($org_name), $board_portal_slug, $board_portal_slug, $board_portal_slug, $board_portal_slug, $board_portal_slug),
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => $board_portal->ID,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));

        if ($financial_landing_id) {
            // Create Fiscal Year Analysis child page
            $fiscal_page = get_page_by_path($board_portal_slug . '/financial/fiscal-year-analysis');
            if (!$fiscal_page) {
                wp_insert_post(array(
                    'post_title' => 'Fiscal Year Analysis',
                    'post_name' => 'fiscal-year-analysis',
                    'post_content' => sprintf('<h2>%s Fiscal Year Membership Analysis</h2>
[member_fiscal_table]

<h3>Quick Links</h3>
<p><a href="/%s/financial">← Back to Financial Reports</a> | <a href="/%s">Dashboard</a> | <a href="/%s/stats">View Statistics</a></p>',
                        esc_html($org_name), $board_portal_slug, $board_portal_slug, $board_portal_slug),
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_parent' => $financial_landing_id,
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ));
            }

            // Create Bank Transactions child page
            $bank_page = get_page_by_path($board_portal_slug . '/financial/bank-transactions');
            if (!$bank_page) {
                wp_insert_post(array(
                    'post_title' => 'Bank Transactions',
                    'post_name' => 'bank-transactions',
                    'post_content' => sprintf('<h2>🏦 %s Bank Transaction History</h2>
<p>Complete history of all bank transactions, deposits, and withdrawals.</p>

[five01c3po_bank_transactions limit="100"]

<h3>Quick Links</h3>
<p><a href="/%s/financial">← Back to Financial Reports</a> | <a href="/%s">Dashboard</a> | <a href="/%s/financial/fiscal-year-analysis">Fiscal Analysis</a></p>',
                        esc_html($org_name), $board_portal_slug, $board_portal_slug, $board_portal_slug),
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_parent' => $financial_landing_id,
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ));
            }
        }

        flush_rewrite_rules();
    }

    update_option('five01c3po_financial_pages_created', true);
}

// Make board portal pages full width (remove sidebar)
add_filter('body_class', 'five01c3po_add_board_portal_body_class');
add_action('wp_enqueue_scripts', 'five01c3po_enqueue_board_portal_styles');

function five01c3po_add_board_portal_body_class($classes) {
    $org_settings = get_option('five01c3po_organization_settings', array());
    $board_portal_slug = $org_settings['board_portal_slug'] ?? 'board-portal';

    // Check if we're on a board portal page (main page or any child page)
    global $post;
    if ($post) {
        // Check if current page slug matches board portal slug
        if ($post->post_name === $board_portal_slug) {
            $classes[] = 'board-portal-full-width';
        }

        // Check if this is a child of the board portal page
        $board_portal = get_page_by_path($board_portal_slug);
        if ($board_portal && ($post->post_parent === $board_portal->ID || wp_get_post_parent_id($post->ID) === $board_portal->ID)) {
            $classes[] = 'board-portal-full-width';
        }

        // Also check for grandchildren (like financial/fiscal-year-analysis)
        if ($post->post_parent) {
            $parent = get_post($post->post_parent);
            if ($parent && $board_portal && $parent->post_parent === $board_portal->ID) {
                $classes[] = 'board-portal-full-width';
            }
        }
    }

    return $classes;
}

function five01c3po_enqueue_board_portal_styles() {
    // Add inline CSS for full-width board portal pages
    $custom_css = "
        /* Make board portal pages full width - remove sidebar */
        .board-portal-full-width #left-area {
            width: 100% !important;
            float: none !important;
            padding-right: 0 !important;
        }

        .board-portal-full-width #sidebar {
            display: none !important;
        }

        .board-portal-full-width #main-content .container {
            max-width: 100% !important;
        }

        /* Divi theme specific overrides */
        .board-portal-full-width.et_pb_pagebuilder_layout #left-area,
        .board-portal-full-width.et_right_sidebar #left-area {
            width: 100% !important;
        }

        .board-portal-full-width.et_right_sidebar #sidebar,
        .board-portal-full-width.et_left_sidebar #sidebar {
            display: none !important;
        }

        /* Additional spacing for better readability */
        .board-portal-full-width #main-content {
            max-width: 1200px;
            margin: 0 auto;
        }
    ";

    wp_add_inline_style('wp-block-library', $custom_css);
}

// Settings page
function five01c3po_settings_page() {
    // Handle manual fix triggers
    ?>
    <div class="wrap">
        <h1>Membership Management Settings</h1>

        <form method="post" action="options.php">
            <?php settings_fields('five01c3po_settings_group'); ?>

            <h2>General Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Organization Name</th>
                    <td>
                        <input type="text" name="five01c3po_organization_settings[organization_name]"
                               value="<?php echo esc_attr(get_option('five01c3po_organization_settings')['organization_name'] ?? 'Your Organization'); ?>"
                               class="regular-text" />
                        <p class="description">Your organization's name (displayed throughout the site)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Board Portal URL Slug</th>
                    <td>
                        <input type="text" name="five01c3po_organization_settings[board_portal_slug]"
                               value="<?php echo esc_attr(get_option('five01c3po_organization_settings')['board_portal_slug'] ?? 'board-portal'); ?>"
                               class="regular-text" />
                        <p class="description">URL slug for the member board portal (e.g., "board-portal" becomes yoursite.com/board-portal)</p>
                    </td>
                </tr>
            </table>

            <h2>Membership Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Dashboard Password</th>
                    <td>
                        <input type="text" name="five01c3po_organization_settings[dashboard_password]"
                               value="<?php echo esc_attr(get_option('five01c3po_organization_settings')['dashboard_password'] ?? ''); ?>"
                               class="regular-text" />
                        <p class="description">Password required to access the member board portal</p>
                    </td>
                </tr>
            </table>

            <h2>Financial Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Currency</th>
                    <td>
                        <select name="five01c3po_organization_settings[currency]" class="regular-text">
                            <?php
                            $current_currency = get_option('five01c3po_organization_settings')['currency'] ?? 'USD';
                            ?>
                            <option value="USD" <?php selected($current_currency, 'USD'); ?>>USD ($)</option>
                            <option value="EUR" <?php selected($current_currency, 'EUR'); ?>>EUR (€)</option>
                            <option value="GBP" <?php selected($current_currency, 'GBP'); ?>>GBP (£)</option>
                            <option value="CAD" <?php selected($current_currency, 'CAD'); ?>>CAD ($)</option>
                        </select>
                        <p class="description">Currency for financial displays</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe API Mode</th>
                    <td>
                        <?php $current_mode = get_option('five01c3po_organization_settings')['stripe_api_mode'] ?? 'live'; ?>
                        <select name="five01c3po_organization_settings[stripe_api_mode]" class="regular-text">
                            <option value="live" <?php selected($current_mode, 'live'); ?>>Live Mode</option>
                            <option value="test" <?php selected($current_mode, 'test'); ?>>Test Mode</option>
                        </select>
                        <p class="description">Select Live for production, Test for development</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe API Key Status</th>
                    <td>
                        <?php
                        $org_settings = get_option('five01c3po_organization_settings', array());
                        $has_encrypted_key = !empty($org_settings['stripe_api_key_encrypted']) && !empty($org_settings['stripe_passphrase_hash']);
                        ?>
                        <?php if ($has_encrypted_key): ?>
                            <p style="color: #00a32a; margin: 0;">
                                <strong>✓ API Key Configured (Encrypted)</strong>
                            </p>
                            <p class="description">
                                API key is stored with AES-256 encryption. Officer passphrase required to use.
                                <br><a href="<?php echo admin_url('admin.php?page=501c3PO-stripe-sync'); ?>">Manage API Key →</a>
                            </p>
                        <?php else: ?>
                            <p style="color: #d63638; margin: 0;">
                                <strong>⚠ No API Key Configured</strong>
                            </p>
                            <p class="description">
                                Set up your Stripe API key with encrypted storage.
                                <br><a href="<?php echo admin_url('admin.php?page=501c3PO-stripe-sync'); ?>" class="button button-primary" style="margin-top: 10px;">Set Up Stripe API Key →</a>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Fiscal Year Configuration</th>
                    <td>
                        <p class="description">
                            <a href="<?php echo home_url('/' . (get_option('five01c3po_organization_settings')['board_portal_slug'] ?? 'board-portal') . '/settings'); ?>" class="button button-secondary">
                                Configure Fiscal Years →
                            </a>
                            <br>Define membership years, dates, and historical periods in the board portal settings.
                        </p>
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
                    'document_management' => 'Document Management',
                    'data_export_import' => 'Data Export/Import',
                    'bank_transactions' => 'Bank Transactions'
                );
                
                $enabled_features = get_option('five01c3po_enabled_features', array());
                
                foreach ($features as $key => $label) {
                    ?>
                    <tr>
                        <th scope="row"><?php echo $label; ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="five01c3po_enabled_features[<?php echo $key; ?>]" 
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
add_action('admin_init', 'five01c3po_register_settings');

function five01c3po_register_settings() {
    register_setting('five01c3po_settings_group', 'five01c3po_organization_settings');
    register_setting('five01c3po_settings_group', 'five01c3po_enabled_features');
}


// Basic admin dashboard
function five01c3po_admin_dashboard() {
    ?>
    <div class="wrap">
        <h1>Membership Management Dashboard</h1>
        <p>Welcome to the Membership Management System. Use the menu on the left to access different features.</p>
        
        <div class="dashboard-widgets">
            <?php do_action('five01c3po_admin_dashboard_widgets'); ?>
        </div>
    </div>
    <?php
}