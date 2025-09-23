<?php
/**
 * Shortcode implementations for 501c3PO
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Initialize all shortcodes
 */
function mm_init_shortcodes() {
    add_shortcode('member_stats', 'fiveohonec3po_stats_shortcode');
    add_shortcode('member_directory', 'fiveohonec3po_directory_shortcode');
    add_shortcode('member_dashboard_grid', 'fiveohonec3po_dashboard_grid_shortcode');
    add_shortcode('member_current_list', 'fiveohonec3po_current_list_shortcode');
    add_shortcode('member_historical_list', 'fiveohonec3po_historical_list_shortcode');
    add_shortcode('member_fiscal_table', 'fiveohonec3po_fiscal_table_shortcode');
    add_shortcode('email_dashboard', 'fiveohonec3po_email_dashboard_shortcode');
    add_shortcode('event_dashboard', 'fiveohonec3po_event_dashboard_shortcode');
    add_shortcode('settings_dashboard', 'fiveohonec3po_settings_dashboard_shortcode');
    add_shortcode('officer_tools_dashboard', 'fiveohonec3po_officer_tools_dashboard_shortcode');
}

/**
 * Stats shortcode
 */
function fiveohonec3po_stats_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'members';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return '<p>Member database not yet set up. Please contact an administrator.</p>';
    }
    
    $total_members = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $current_year_paid = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_current_year = 'paid'");
    $current_year_unpaid = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_current_year = 'unpaid'");
    
    $org_settings = get_option('mm_organization_settings', array());
    $org_name = $org_settings['organization_name'] ?? 'Organization';
    
    ob_start();
    ?>
    <div class="membership-stats" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h3>📊 <?php echo esc_html($org_name); ?> Membership Statistics</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo $total_members; ?></div>
                <div>Total Members</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #46b450;"><?php echo $current_year_paid; ?></div>
                <div>Current Year Paid</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #dc3232;"><?php echo $current_year_unpaid; ?></div>
                <div>Current Year Unpaid</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Member directory shortcode
 */
function fiveohonec3po_directory_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'members';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return '<p>Member database not yet set up. Please contact an administrator.</p>';
    }
    
    $members = $wpdb->get_results("SELECT * FROM $table_name ORDER BY last_name, first_name");
    
    if (empty($members)) {
        return '<p>No members found. Contact an administrator to import member data.</p>';
    }
    
    ob_start();
    ?>
    <div class="member-directory">
        <style>
            .member-card {
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 15px;
                margin: 10px 0;
                background: white;
            }
            .member-name {
                font-weight: bold;
                font-size: 16px;
                margin-bottom: 5px;
            }
            .member-details {
                color: #666;
                line-height: 1.4;
            }
        </style>
        
        <?php foreach ($members as $member): ?>
            <div class="member-card">
                <div class="member-name">
                    <?php echo esc_html($member->first_name . ' ' . $member->last_name); ?>
                    <?php if ($member->partner_first_name): ?>
                        & <?php echo esc_html($member->partner_first_name . ' ' . $member->partner_last_name); ?>
                    <?php endif; ?>
                </div>
                <div class="member-details">
                    <?php if ($member->email_1): ?>
                        📧 <a href="mailto:<?php echo esc_attr($member->email_1); ?>"><?php echo esc_html($member->email_1); ?></a><br>
                    <?php endif; ?>
                    <?php if ($member->phone): ?>
                        📞 <?php echo esc_html($member->phone); ?><br>
                    <?php endif; ?>
                    <?php if ($member->address): ?>
                        🏠 <?php echo esc_html($member->address . ', ' . $member->city . ', ' . $member->state . ' ' . $member->zip_code); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Dashboard grid shortcode
 */
function fiveohonec3po_dashboard_grid_shortcode() {
    $enabled_features = get_option('mm_enabled_features', array());
    $org_settings = get_option('mm_organization_settings', array());
    $org_name = $org_settings['organization_name'] ?? 'Your Organization';
    
    ob_start();
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0;">
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>💰 Current Membership</h3>
            <p>View current year paid/unpaid members with color coding.</p>
            <a href="/dashboard/current-membership" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Current Members</a>
        </div>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>📚 Historical Membership</h3>
            <p>Browse membership history across multiple years.</p>
            <a href="/dashboard/historical-membership" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">History</a>
        </div>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>📞 Member Directory</h3>
            <p>Contact directory with addresses, emails, and phone numbers.</p>
            <a href="/dashboard/member-directory" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Directory</a>
        </div>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>📊 Membership Statistics</h3>
            <p>View current membership statistics.</p>
            <a href="/dashboard/stats" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">View Stats</a>
        </div>
        
        <?php if (!empty($enabled_features['email_management'])): ?>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>📧 Email Management</h3>
            <p>Create, approve, and schedule bulk emails to members.</p>
            <a href="/dashboard/email-dashboard" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Manage Emails</a>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($enabled_features['event_management'])): ?>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>🎉 Event Management</h3>
            <p>Create events, manage RSVPs, and coordinate volunteer signups.</p>
            <a href="/dashboard/event-dashboard" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Manage Events</a>
        </div>
        <?php endif; ?>
        
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>⚙️ Settings</h3>
            <p>Configure <?php echo esc_html($org_name); ?> settings and features.</p>
            <a href="/dashboard/settings" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Manage Settings</a>
        </div>
        
        <?php if (!empty($enabled_features['officer_tools'])): ?>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>🏛️ Officer Tools</h3>
            <p>Create agendas, meeting minutes, reports, and document management.</p>
            <a href="/dashboard/officer-tools" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Officer Tools</a>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Placeholder shortcodes for features not yet implemented
 */
function fiveohonec3po_current_list_shortcode() {
    return '<p>Current membership listing feature coming soon!</p>';
}

function fiveohonec3po_historical_list_shortcode() {
    return '<p>Historical membership listing feature coming soon!</p>';
}

function fiveohonec3po_fiscal_table_shortcode() {
    return '<p>Fiscal year analysis feature coming soon!</p>';
}

function fiveohonec3po_email_dashboard_shortcode() {
    return '<p>Email management feature coming soon!</p>';
}

function fiveohonec3po_event_dashboard_shortcode() {
    return '<p>Event management feature coming soon!</p>';
}

function fiveohonec3po_settings_dashboard_shortcode() {
    return '<p>Settings dashboard feature coming soon!</p>';
}

function fiveohonec3po_officer_tools_dashboard_shortcode() {
    return '<p>Officer tools feature coming soon!</p>';
}