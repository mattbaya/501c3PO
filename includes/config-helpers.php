<?php
/**
 * Configuration Helper Functions
 * Provides dynamic organization configuration throughout the plugin
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Get organization configuration value
 *
 * @param string $key Configuration key
 * @param mixed $default Default value if not set
 * @return mixed Configuration value
 */
function npo_get_config($key, $default = '') {
    $config = get_option('npo_org_config', array());
    return isset($config[$key]) ? $config[$key] : $default;
}

/**
 * Set organization configuration value
 *
 * @param string $key Configuration key
 * @param mixed $value Configuration value
 * @return bool Success status
 */
function npo_set_config($key, $value) {
    $config = get_option('npo_org_config', array());
    $config[$key] = $value;
    return update_option('npo_org_config', $config);
}

/**
 * Get all organization configuration
 *
 * @return array All configuration values
 */
function npo_get_all_config() {
    return get_option('npo_org_config', npo_get_default_config());
}

/**
 * Get default configuration values
 *
 * @return array Default configuration
 */
function npo_get_default_config() {
    return array(
        'org_name' => 'Your Organization',
        'org_short_name' => 'YO',
        'org_prefix' => 'npo',
        'org_slug' => 'organization',
        'dashboard_password' => wp_generate_password(12, false),
        'is_configured' => false,
    );
}

/**
 * Check if plugin has been configured
 *
 * @return bool Configuration status
 */
function npo_is_configured() {
    return (bool) npo_get_config('is_configured', false);
}

/**
 * Get organization name
 *
 * @return string Organization name
 */
function npo_get_org_name() {
    return npo_get_config('org_name', 'Your Organization');
}

/**
 * Get organization short name (abbreviation)
 *
 * @return string Organization short name
 */
function npo_get_org_short_name() {
    return npo_get_config('org_short_name', 'YO');
}

/**
 * Get organization prefix (for database tables)
 *
 * @return string Organization prefix
 */
function npo_get_org_prefix() {
    return npo_get_config('org_prefix', 'npo');
}

/**
 * Get organization slug (for URLs)
 *
 * @return string Organization slug
 */
function npo_get_org_slug() {
    return npo_get_config('org_slug', 'organization');
}

/**
 * Get dashboard password
 *
 * @return string Dashboard password
 */
function npo_get_dashboard_password() {
    return npo_get_config('dashboard_password', '');
}

/**
 * Get table name with dynamic prefix
 *
 * @param string $table_name Base table name (without wp_ prefix)
 * @return string Full table name with prefix
 */
function npo_get_table_name($table_name) {
    global $wpdb;
    $org_prefix = npo_get_org_prefix();
    return $wpdb->prefix . $org_prefix . '_' . $table_name;
}

/**
 * Get role name with organization prefix
 *
 * @param string $role_suffix Role suffix (e.g., 'member', 'officer')
 * @return string Full role display name
 */
function npo_get_role_name($role_suffix) {
    $org_short_name = npo_get_org_short_name();
    $role_names = array(
        'member' => $org_short_name . ' Member',
        'officer' => $org_short_name . ' Officer',
        'treasurer' => $org_short_name . ' Treasurer',
        'committee_chair' => $org_short_name . ' Committee Chair',
    );

    return isset($role_names[$role_suffix]) ? $role_names[$role_suffix] : $org_short_name . ' ' . ucfirst($role_suffix);
}

/**
 * Get role slug with organization prefix
 *
 * @param string $role_suffix Role suffix (e.g., 'member', 'officer')
 * @return string Role slug for WordPress
 */
function npo_get_role_slug($role_suffix) {
    $org_prefix = npo_get_org_prefix();
    return $org_prefix . '_' . $role_suffix;
}

/**
 * Initialize default configuration on first activation
 */
function npo_init_config() {
    if (!get_option('npo_org_config')) {
        update_option('npo_org_config', npo_get_default_config());
    }
}

/**
 * Migrate from SWCA-specific configuration
 * Checks for existing SWCA data and prompts for migration
 *
 * @return bool Whether SWCA data was found
 */
function npo_check_swca_migration() {
    global $wpdb;

    // Check if SWCA tables exist
    $swca_table = $wpdb->prefix . 'swca_members';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$swca_table'") === $swca_table;

    if ($table_exists && !npo_is_configured()) {
        // Suggest SWCA as default configuration
        $config = array(
            'org_name' => 'South Williamstown Community Association',
            'org_short_name' => 'SWCA',
            'org_prefix' => 'swca',
            'org_slug' => 'swca',
            'dashboard_password' => 'F1v3C0rn3rs',
            'is_configured' => false,
            'needs_migration' => true,
        );
        update_option('npo_org_config', $config);
        return true;
    }

    return false;
}
