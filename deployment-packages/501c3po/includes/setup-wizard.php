<?php
/**
 * Setup Wizard for First-Time Configuration
 * Guides users through initial organization setup
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add setup wizard menu to WordPress admin
 */
function npo_add_setup_wizard_menu() {
    if (!npo_is_configured()) {
        add_menu_page(
            'Setup Organization',
            'Setup NPO Plugin',
            'manage_options',
            'npo-setup-wizard',
            'npo_render_setup_wizard',
            'dashicons-admin-generic',
            3
        );
    }
}
add_action('admin_menu', 'npo_add_setup_wizard_menu');

/**
 * Show admin notice if plugin is not configured
 */
function npo_show_setup_notice() {
    if (!npo_is_configured() && current_user_can('manage_options')) {
        $setup_url = admin_url('admin.php?page=npo-setup-wizard');
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Non-Profit Organization Plugin:</strong> Please complete the <a href="' . esc_url($setup_url) . '">initial setup</a> to configure your organization settings.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'npo_show_setup_notice');

/**
 * Render the setup wizard page
 */
function npo_render_setup_wizard() {
    // Handle form submission
    if (isset($_POST['npo_setup_submit']) && check_admin_referer('npo_setup_wizard')) {
        npo_process_setup_wizard();
    }

    $config = npo_get_all_config();
    $needs_migration = isset($config['needs_migration']) && $config['needs_migration'];

    ?>
    <div class="wrap npo-setup-wizard">
        <h1>🏛️ Non-Profit Organization Plugin Setup</h1>

        <?php if ($needs_migration): ?>
            <div class="notice notice-info">
                <p><strong>SWCA Data Detected:</strong> We found existing SWCA membership data. The form below is pre-filled with SWCA settings. You can keep these or customize them for your organization.</p>
            </div>
        <?php endif; ?>

        <div class="npo-setup-container">
            <p class="description">Welcome! Let's configure this plugin for your organization. These settings will customize all aspects of the plugin including database tables, user roles, and display text.</p>

            <form method="post" action="" class="npo-setup-form">
                <?php wp_nonce_field('npo_setup_wizard'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="org_name">Organization Full Name</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="org_name"
                                   name="org_name"
                                   value="<?php echo esc_attr($config['org_name']); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description">Full name of your organization (e.g., "South Williamstown Community Association")</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="org_short_name">Organization Abbreviation</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="org_short_name"
                                   name="org_short_name"
                                   value="<?php echo esc_attr($config['org_short_name']); ?>"
                                   class="regular-text"
                                   required
                                   pattern="[A-Z]{2,10}"
                                   title="2-10 uppercase letters">
                            <p class="description">Abbreviation/acronym (e.g., "SWCA"). Used in role names and display text. 2-10 uppercase letters.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="org_prefix">Database Table Prefix</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="org_prefix"
                                   name="org_prefix"
                                   value="<?php echo esc_attr($config['org_prefix']); ?>"
                                   class="regular-text"
                                   required
                                   pattern="[a-z0-9_]{2,20}"
                                   title="2-20 lowercase letters, numbers, and underscores"
                                   <?php echo $needs_migration ? 'readonly' : ''; ?>>
                            <p class="description">
                                Database table prefix (e.g., "swca", "npo"). Lowercase letters, numbers, and underscores only.
                                <?php if ($needs_migration): ?>
                                    <strong>Note:</strong> This is locked to "swca" because existing data was detected.
                                <?php else: ?>
                                    Tables will be named: <code>wp_<em>prefix</em>_members</code>, <code>wp_<em>prefix</em>_events</code>, etc.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="org_slug">Organization Slug</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="org_slug"
                                   name="org_slug"
                                   value="<?php echo esc_attr($config['org_slug']); ?>"
                                   class="regular-text"
                                   required
                                   pattern="[a-z0-9-]{2,50}"
                                   title="2-50 lowercase letters, numbers, and hyphens">
                            <p class="description">URL-friendly slug (e.g., "swca", "my-org"). Used in URLs and file names. Lowercase letters, numbers, and hyphens only.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="dashboard_password">Dashboard Password</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="dashboard_password"
                                   name="dashboard_password"
                                   value="<?php echo esc_attr($config['dashboard_password']); ?>"
                                   class="regular-text"
                                   required
                                   minlength="8">
                            <p class="description">Password to access the member dashboard. Minimum 8 characters. You can change this later in settings.</p>
                        </td>
                    </tr>
                </table>

                <div class="npo-setup-actions">
                    <input type="submit"
                           name="npo_setup_submit"
                           class="button button-primary button-hero"
                           value="Complete Setup & Configure Plugin">

                    <?php if (!$needs_migration): ?>
                        <p class="description" style="margin-top: 15px;">
                            <strong>Note:</strong> After completing setup, the plugin will create database tables and configure WordPress roles based on your settings.
                        </p>
                    <?php else: ?>
                        <p class="description" style="margin-top: 15px;">
                            <strong>Note:</strong> Your existing SWCA data will be preserved. This setup will configure the plugin to use your existing database tables.
                        </p>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <style>
        .npo-setup-wizard {
            max-width: 900px;
        }
        .npo-setup-container {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 30px;
            margin-top: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .npo-setup-form .form-table th {
            padding-left: 0;
        }
        .npo-setup-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .npo-setup-wizard h1 {
            margin-bottom: 10px;
        }
    </style>
    <?php
}

/**
 * Process setup wizard form submission
 */
function npo_process_setup_wizard() {
    // Sanitize and validate input
    $org_name = sanitize_text_field($_POST['org_name']);
    $org_short_name = strtoupper(sanitize_text_field($_POST['org_short_name']));
    $org_prefix = sanitize_key($_POST['org_prefix']);
    $org_slug = sanitize_title($_POST['org_slug']);
    $dashboard_password = $_POST['dashboard_password']; // Don't sanitize password

    // Validate
    if (empty($org_name) || empty($org_short_name) || empty($org_prefix) || empty($org_slug) || empty($dashboard_password)) {
        add_settings_error('npo_setup', 'missing_fields', 'All fields are required.', 'error');
        return;
    }

    if (!preg_match('/^[A-Z]{2,10}$/', $org_short_name)) {
        add_settings_error('npo_setup', 'invalid_short_name', 'Organization abbreviation must be 2-10 uppercase letters.', 'error');
        return;
    }

    if (!preg_match('/^[a-z0-9_]{2,20}$/', $org_prefix)) {
        add_settings_error('npo_setup', 'invalid_prefix', 'Database prefix must be 2-20 lowercase letters, numbers, and underscores.', 'error');
        return;
    }

    if (strlen($dashboard_password) < 8) {
        add_settings_error('npo_setup', 'weak_password', 'Dashboard password must be at least 8 characters.', 'error');
        return;
    }

    // Save configuration
    $config = array(
        'org_name' => $org_name,
        'org_short_name' => $org_short_name,
        'org_prefix' => $org_prefix,
        'org_slug' => $org_slug,
        'dashboard_password' => $dashboard_password,
        'is_configured' => true,
        'configured_at' => current_time('mysql'),
    );

    update_option('npo_org_config', $config);

    // Create/update database tables
    npo_create_all_tables();

    // Create/update WordPress roles
    npo_create_custom_roles();

    // Redirect to success page
    wp_redirect(admin_url('admin.php?page=npo-setup-complete'));
    exit;
}

/**
 * Add setup complete page
 */
function npo_add_setup_complete_menu() {
    if (isset($_GET['page']) && $_GET['page'] === 'npo-setup-complete') {
        add_submenu_page(
            null, // Hidden from menu
            'Setup Complete',
            'Setup Complete',
            'manage_options',
            'npo-setup-complete',
            'npo_render_setup_complete'
        );
    }
}
add_action('admin_menu', 'npo_add_setup_complete_menu');

/**
 * Render setup complete page
 */
function npo_render_setup_complete() {
    $org_name = npo_get_org_name();
    $dashboard_url = home_url('/dashboard');
    ?>
    <div class="wrap npo-setup-complete">
        <h1>✅ Setup Complete!</h1>

        <div class="notice notice-success" style="padding: 20px; margin-top: 20px;">
            <h2>Your <?php echo esc_html($org_name); ?> plugin is now configured!</h2>
            <p>Database tables have been created and WordPress roles have been configured.</p>
        </div>

        <div class="npo-next-steps" style="background: #fff; border: 1px solid #ccd0d4; padding: 30px; margin-top: 20px;">
            <h2>Next Steps:</h2>
            <ol style="font-size: 16px; line-height: 2;">
                <li><strong>Import Members:</strong> Go to <a href="<?php echo admin_url('admin.php?page=npo-import'); ?>">CRM → Data Import Tools</a> to import your member data</li>
                <li><strong>Configure Features:</strong> Visit <a href="<?php echo esc_url($dashboard_url . '/settings'); ?>">Dashboard → Settings</a> to enable/disable optional features</li>
                <li><strong>Set Up API Keys:</strong> If you're using Stripe, Google Calendar, or Gmail integration, add your API keys in Settings</li>
                <li><strong>Access Dashboard:</strong> Visit <a href="<?php echo esc_url($dashboard_url); ?>">your member dashboard</a> (password: <code><?php echo esc_html(npo_get_dashboard_password()); ?></code>)</li>
            </ol>

            <p style="margin-top: 30px;">
                <a href="<?php echo admin_url(); ?>" class="button button-primary button-hero">Go to WordPress Dashboard</a>
                <a href="<?php echo esc_url($dashboard_url); ?>" class="button button-secondary button-hero" style="margin-left: 10px;">Visit Member Dashboard</a>
            </p>
        </div>
    </div>
    <?php
}
