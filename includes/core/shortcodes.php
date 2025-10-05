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
 * Helper: Get fiscal year configuration
 */
function mm_get_fiscal_year_config() {
    $config = get_option('mm_fiscal_year_config', array());

    // Return default if not set
    if (empty($config)) {
        return array(
            'type' => 'fiscal', // 'fiscal', 'calendar', or 'custom'
            'fiscal_start_month' => 7, // July
            'years' => array(
                array(
                    'label' => '2025-2026',
                    'start_date' => '2025-07-01',
                    'end_date' => '2026-12-31',
                    'column_name' => 'status_2025_2026'
                ),
                array(
                    'label' => '2024-2025',
                    'start_date' => '2024-07-01',
                    'end_date' => '2025-06-30',
                    'column_name' => 'status_2024_2025'
                ),
                array(
                    'label' => '2023-2024',
                    'start_date' => '2023-07-01',
                    'end_date' => '2024-06-30',
                    'column_name' => 'status_2023_2024'
                )
            )
        );
    }

    return $config;
}

/**
 * Helper: Get current fiscal year config based on today's date
 */
function mm_get_current_year_config() {
    $config = mm_get_fiscal_year_config();
    $today = date('Y-m-d');

    // Find the year that contains today's date
    foreach ($config['years'] as $year) {
        if ($today >= $year['start_date'] && $today <= $year['end_date']) {
            return $year;
        }
    }

    // If no match found, return the first year
    return $config['years'][0] ?? array(
        'label' => 'Current Year',
        'start_date' => date('Y') . '-01-01',
        'end_date' => date('Y') . '-12-31',
        'column_name' => 'status_2025_2026'
    );
}

/**
 * Helper: Get previous fiscal year config
 */
function mm_get_previous_year_config() {
    $config = mm_get_fiscal_year_config();
    $current = mm_get_current_year_config();

    // Find the index of current year
    $current_index = 0;
    foreach ($config['years'] as $index => $year) {
        if ($year['column_name'] === $current['column_name']) {
            $current_index = $index;
            break;
        }
    }

    // Return next year in array (previous chronologically)
    if (isset($config['years'][$current_index + 1])) {
        return $config['years'][$current_index + 1];
    }

    // Fallback
    return array(
        'label' => 'Previous Year',
        'start_date' => (date('Y') - 1) . '-01-01',
        'end_date' => (date('Y') - 1) . '-12-31',
        'column_name' => 'status_2024_2025'
    );
}

/**
 * Helper: Get year config by offset (0 = current, 1 = previous, 2 = 2 years ago, etc.)
 */
function mm_get_year_config_by_offset($offset = 0) {
    $config = mm_get_fiscal_year_config();
    $current = mm_get_current_year_config();

    // Find the index of current year
    $current_index = 0;
    foreach ($config['years'] as $index => $year) {
        if ($year['column_name'] === $current['column_name']) {
            $current_index = $index;
            break;
        }
    }

    // Return year at offset
    if (isset($config['years'][$current_index + $offset])) {
        return $config['years'][$current_index + $offset];
    }

    return null;
}

/**
 * Stats shortcode
 */
function fiveohonec3po_stats_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'swca_members';  // TODO: Make dynamic with org prefix

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return '<p>Member database not yet set up. Please contact an administrator.</p>';
    }

    $total_members = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $current_year_paid = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_2025_2026 = 'paid'");
    $current_year_unpaid = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status_2025_2026 = 'unpaid'");
    
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
    $table_name = $wpdb->prefix . 'swca_members';  // TODO: Make dynamic with org prefix
    
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
    $board_portal_slug = $org_settings['board_portal_slug'] ?? 'board-portal';

    ob_start();
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0;">
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>👥 Current Membership</h3>
            <p>View current year paid/unpaid members with color coding.</p>
            <a href="/<?php echo esc_attr($board_portal_slug); ?>/current-membership" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Current Members</a>
        </div>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>📚 Historical Membership</h3>
            <p>Browse membership history across multiple years.</p>
            <a href="/<?php echo esc_attr($board_portal_slug); ?>/historical-membership" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">History</a>
        </div>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>📞 Member Directory</h3>
            <p>Contact directory with addresses, emails, and phone numbers.</p>
            <a href="/<?php echo esc_attr($board_portal_slug); ?>/member-directory" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Directory</a>
        </div>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>📊 Membership Statistics</h3>
            <p>View current membership statistics.</p>
            <a href="/<?php echo esc_attr($board_portal_slug); ?>/stats" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">View Stats</a>
        </div>

        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>💰 Financial Reports</h3>
            <p>View financial reports, bank transactions, and fiscal year analysis.</p>
            <a href="/<?php echo esc_attr($board_portal_slug); ?>/financial" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Financial Reports</a>
        </div>

        <?php if (!empty($enabled_features['email_management'])): ?>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>📧 Email Management</h3>
            <p>Create, approve, and schedule bulk emails to members.</p>
            <a href="/<?php echo esc_attr($board_portal_slug); ?>/email-dashboard" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Manage Emails</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($enabled_features['event_management'])): ?>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>🎉 Event Management</h3>
            <p>Create events, manage RSVPs, and coordinate volunteer signups.</p>
            <a href="/<?php echo esc_attr($board_portal_slug); ?>/event-dashboard" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Manage Events</a>
        </div>
        <?php endif; ?>

        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>⚙️ Settings</h3>
            <p>Configure <?php echo esc_html($org_name); ?> settings and features.</p>
            <a href="/<?php echo esc_attr($board_portal_slug); ?>/settings" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Manage Settings</a>
        </div>

        <?php if (!empty($enabled_features['officer_tools'])): ?>
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
            <h3>🏛️ Officer Tools</h3>
            <p>Create agendas, meeting minutes, reports, and document management.</p>
            <a href="/<?php echo esc_attr($board_portal_slug); ?>/officer-tools" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">Officer Tools</a>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Current membership list shortcode
 */
function fiveohonec3po_current_list_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'swca_members';  // TODO: Make dynamic with org prefix

    // Get current year members (2025-2026 calendar year)
    $members = $wpdb->get_results("
        SELECT * FROM $table_name
        WHERE status_2025_2026 IN ('paid', 'unpaid')
        ORDER BY last_name, first_name
    ");

    if (empty($members)) {
        return '<p>No members found for the current year (2025-2026).</p>';
    }

    ob_start();
    ?>
    <table class="wp-list-table widefat fixed striped" style="width: 100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>City</th>
                <th>Status</th>
                <th>Renewal Date</th>
                <th>Type</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $member):
                $status = $member->status_2025_2026 ?? '';
                $bg_color = ($status === 'paid') ? '#d4f8ff' : '#fff3cd';

                // Format renewal date from membership_month (renewals from June 2025+ are for 2025-2026)
                $renewal_date = '';
                if (!empty($member->membership_month)) {
                    $month = intval($member->membership_month);
                    // If month is 1-6, assume 2026, if 7-12 assume 2025
                    $year = ($month >= 1 && $month <= 6) ? 2026 : 2025;
                    $renewal_date = date('M Y', mktime(0, 0, 0, $month, 1, $year));
                }
            ?>
            <tr style="background-color: <?php echo esc_attr($bg_color); ?>;">
                <td>
                    <strong><?php echo esc_html($member->first_name . ' ' . $member->last_name); ?></strong>
                    <?php if ($member->partner_first_name): ?>
                        <br><em><?php echo esc_html($member->partner_first_name . ' ' . $member->partner_last_name); ?></em>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html($member->email_1); ?></td>
                <td><?php echo esc_html($member->phone); ?></td>
                <td><?php echo esc_html($member->city); ?></td>
                <td><strong><?php echo esc_html(ucfirst($status)); ?></strong></td>
                <td><?php echo esc_html($renewal_date); ?></td>
                <td><?php echo esc_html($member->membership_type); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

/**
 * Historical membership list shortcode
 */
function fiveohonec3po_historical_list_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'swca_members';  // TODO: Make dynamic with org prefix

    // Get all members with historical data
    $members = $wpdb->get_results("
        SELECT * FROM $table_name
        ORDER BY last_name, first_name
    ");

    if (empty($members)) {
        return '<p>No members found.</p>';
    }

    ob_start();
    ?>
    <table class="wp-list-table widefat fixed striped" style="width: 100%; margin-top: 20px;">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>2025-2026</th>
                <th>2024-2025</th>
                <th>2023-2024</th>
                <th>Type</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $member): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($member->first_name . ' ' . $member->last_name); ?></strong>
                    <?php if ($member->partner_first_name): ?>
                        <br><em><?php echo esc_html($member->partner_first_name . ' ' . $member->partner_last_name); ?></em>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html($member->email_1); ?></td>
                <td><?php echo esc_html(ucfirst($member->status_2025_2026 ?? 'N/A')); ?></td>
                <td><?php echo esc_html(ucfirst($member->status_2024_2025 ?? 'N/A')); ?></td>
                <td><?php echo esc_html(ucfirst($member->status_2023_2024 ?? 'N/A')); ?></td>
                <td><?php echo esc_html($member->membership_type); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

/**
 * Fiscal table shortcode
 */
function fiveohonec3po_fiscal_table_shortcode() {
    global $wpdb;
    $members_table = $wpdb->prefix . 'swca_members';
    $bank_table = $wpdb->prefix . 'swca_bank_transactions'; // Using actual table name
    $financial_table = 'swca_swca_financial_transactions'; // Categorized transactions
    $gf_payments_table = 'swca_gf_addon_payment_transaction'; // Gravity Forms payments

    // Get fiscal year config
    $config = mm_get_fiscal_year_config();
    $current_year = mm_get_current_year_config();
    $previous_year = mm_get_previous_year_config();

    // Get member stats for current year
    $current_paid = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $members_table WHERE {$current_year['column_name']} = %s",
        'paid'
    ));
    $current_unpaid = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $members_table WHERE {$current_year['column_name']} = %s",
        'unpaid'
    ));

    // Get member stats for previous year
    $previous_paid = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $members_table WHERE {$previous_year['column_name']} = %s",
        'paid'
    ));

    // Get member revenue for current year
    $current_total_amount = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(total_amount) FROM $members_table WHERE {$current_year['column_name']} = %s",
        'paid'
    ));
    $current_membership_amount = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(membership_amount) FROM $members_table WHERE {$current_year['column_name']} = %s",
        'paid'
    ));
    $current_donation_amount = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(donation_amount) FROM $members_table WHERE {$current_year['column_name']} = %s",
        'paid'
    ));

    // Get bank transaction data for current year
    $bank_credits = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(credit) FROM $bank_table
         WHERE post_date >= %s AND post_date <= %s",
        $current_year['start_date'],
        $current_year['end_date']
    ));

    $bank_debits = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(debit) FROM $bank_table
         WHERE post_date >= %s AND post_date <= %s",
        $current_year['start_date'],
        $current_year['end_date']
    ));

    $bank_net = ($bank_credits ?? 0) - ($bank_debits ?? 0);

    // Get Stripe payment data from Gravity Forms for current year
    $stripe_revenue = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM $gf_payments_table
         WHERE transaction_type = 'payment'
         AND date_created >= %s AND date_created <= %s",
        $current_year['start_date'] . ' 00:00:00',
        $current_year['end_date'] . ' 23:59:59'
    ));

    $stripe_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $gf_payments_table
         WHERE transaction_type = 'payment'
         AND date_created >= %s AND date_created <= %s",
        $current_year['start_date'] . ' 00:00:00',
        $current_year['end_date'] . ' 23:59:59'
    ));

    ob_start();
    ?>
    <div style="margin: 20px 0;">
        <h3>Membership Overview</h3>
        <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th><?php echo esc_html($current_year['label']); ?></th>
                    <th><?php echo esc_html($previous_year['label']); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Paid Members</strong></td>
                    <td><?php echo number_format($current_paid); ?></td>
                    <td><?php echo number_format($previous_paid); ?></td>
                </tr>
                <tr>
                    <td><strong>Unpaid/Not Renewed</strong></td>
                    <td><?php echo number_format($current_unpaid); ?></td>
                    <td>-</td>
                </tr>
                <tr style="background: #f0f8ff;">
                    <td><strong>Membership Revenue</strong></td>
                    <td><strong>$<?php echo number_format($current_membership_amount ?? 0, 2); ?></strong></td>
                    <td>-</td>
                </tr>
                <tr>
                    <td><strong>Donations</strong></td>
                    <td>$<?php echo number_format($current_donation_amount ?? 0, 2); ?></td>
                    <td>-</td>
                </tr>
                <tr>
                    <td><strong>Total from Members</strong></td>
                    <td>$<?php echo number_format($current_total_amount ?? 0, 2); ?></td>
                    <td>-</td>
                </tr>
            </tbody>
        </table>

        <h3>Bank Account Activity <small style="color: #666;">(<?php echo esc_html($current_year['label']); ?>)</small></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Category</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr style="color: #00a32a;">
                    <td><strong>Total Credits (Income)</strong></td>
                    <td style="text-align: right;"><strong>+$<?php echo number_format($bank_credits ?? 0, 2); ?></strong></td>
                </tr>
                <tr style="color: #d63638;">
                    <td><strong>Total Debits (Expenses)</strong></td>
                    <td style="text-align: right;"><strong>-$<?php echo number_format($bank_debits ?? 0, 2); ?></strong></td>
                </tr>
                <tr style="background: #f0f8ff; font-size: 1.1em;">
                    <td><strong>Net Cash Flow</strong></td>
                    <td style="text-align: right;"><strong>$<?php echo number_format($bank_net, 2); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <?php if (!$bank_credits && !$bank_debits): ?>
            <div style="background: #fff3cd; padding: 15px; margin-top: 15px; border-radius: 4px; border-left: 4px solid #f0ad4e;">
                <p style="margin: 0;">
                    <strong>No bank transaction data found for this period.</strong><br>
                    <a href="/wp-admin/admin.php?page=501c3PO-bank-transactions">Import bank transactions →</a>
                </p>
            </div>
        <?php endif; ?>

        <h3 style="margin-top: 40px;">Stripe Payment Processing <small style="color: #666;">(<?php echo esc_html($current_year['label']); ?>)</small></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th style="text-align: right;">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Total Stripe Payments</strong></td>
                    <td style="text-align: right;"><?php echo number_format($stripe_count ?? 0); ?> transactions</td>
                </tr>
                <tr style="background: #f0f8ff;">
                    <td><strong>Total Revenue via Stripe</strong></td>
                    <td style="text-align: right;"><strong>$<?php echo number_format($stripe_revenue ?? 0, 2); ?></strong></td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size: 0.9em; color: #666; padding-top: 10px;">
                        Stripe data from Gravity Forms payment gateway
                        <?php if ($stripe_count > 0): ?>
                            | <a href="/wp-admin/admin.php?page=501c3PO-stripe-sync">Sync latest Stripe data →</a>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if (!$stripe_count): ?>
            <div style="background: #fff3cd; padding: 15px; margin-top: 15px; border-radius: 4px; border-left: 4px solid #f0ad4e;">
                <p style="margin: 0;">
                    <strong>No Stripe payment data found for this period.</strong><br>
                    <a href="/wp-admin/admin.php?page=501c3PO-stripe-sync">Sync Stripe transactions →</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function fiveohonec3po_email_dashboard_shortcode() {
    return '<p>Email management feature coming soon!</p>';
}

function fiveohonec3po_event_dashboard_shortcode() {
    return '<p>Event management feature coming soon!</p>';
}

function fiveohonec3po_settings_dashboard_shortcode() {
    // Check if user is logged in and has permission
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to access settings.</p>';
    }

    if (!current_user_can('manage_options')) {
        return '<p>You do not have permission to access settings.</p>';
    }

    $org_settings = get_option('mm_organization_settings', array());
    $enabled_features = get_option('mm_enabled_features', array());

    // Handle form submission
    $message = '';
    if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['settings_nonce'], 'save_board_portal_settings')) {
        // Update organization settings
        $new_org_settings = array(
            'organization_name' => sanitize_text_field($_POST['organization_name'] ?? 'Your Organization'),
            'dashboard_password' => sanitize_text_field($_POST['dashboard_password'] ?? ''),
            'fiscal_year_start' => intval($_POST['fiscal_year_start'] ?? 7),
            'currency' => sanitize_text_field($_POST['currency'] ?? 'USD'),
            'board_portal_slug' => sanitize_title($_POST['board_portal_slug'] ?? 'board-portal'),
            'stripe_api_key' => sanitize_text_field($_POST['stripe_api_key'] ?? ''),
            'stripe_api_mode' => sanitize_text_field($_POST['stripe_api_mode'] ?? 'live')
        );
        update_option('mm_organization_settings', $new_org_settings);

        // Update fiscal year configuration
        $fiscal_type = sanitize_text_field($_POST['fiscal_year_type'] ?? 'custom');
        $fiscal_config = array(
            'type' => $fiscal_type,
            'fiscal_start_month' => intval($_POST['fiscal_year_start'] ?? 7),
            'years' => array()
        );

        // Build years array from form inputs
        $year_count = intval($_POST['year_count'] ?? 0);
        for ($i = 0; $i < $year_count; $i++) {
            if (isset($_POST['year_' . $i . '_label']) && !empty($_POST['year_' . $i . '_label'])) {
                $fiscal_config['years'][] = array(
                    'label' => sanitize_text_field($_POST['year_' . $i . '_label']),
                    'start_date' => sanitize_text_field($_POST['year_' . $i . '_start']),
                    'end_date' => sanitize_text_field($_POST['year_' . $i . '_end']),
                    'column_name' => sanitize_text_field($_POST['year_' . $i . '_column'])
                );
            }
        }

        // If no years defined, use defaults
        if (empty($fiscal_config['years'])) {
            $fiscal_config['years'] = array(
                array(
                    'label' => '2025-2026',
                    'start_date' => '2025-07-01',
                    'end_date' => '2026-12-31',
                    'column_name' => 'status_2025_2026'
                ),
                array(
                    'label' => '2024-2025',
                    'start_date' => '2024-07-01',
                    'end_date' => '2025-06-30',
                    'column_name' => 'status_2024_2025'
                ),
                array(
                    'label' => '2023-2024',
                    'start_date' => '2023-07-01',
                    'end_date' => '2024-06-30',
                    'column_name' => 'status_2023_2024'
                )
            );
        }

        update_option('mm_fiscal_year_config', $fiscal_config);

        // Update enabled features
        $new_features = array();
        $all_features = array(
            'email_management', 'event_management', 'financial_management',
            'volunteer_signups', 'officer_tools', 'committee_management',
            'document_management', 'data_export_import', 'bank_transactions'
        );
        foreach ($all_features as $feature) {
            $new_features[$feature] = isset($_POST['feature_' . $feature]) ? true : false;
        }
        update_option('mm_enabled_features', $new_features);

        $message = '<div style="background: #d4f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">✅ Settings saved successfully!</div>';

        // Reload settings
        $org_settings = $new_org_settings;
        $enabled_features = $new_features;
    }

    // Get fiscal year config
    $fiscal_config = mm_get_fiscal_year_config();

    ob_start();
    ?>
    <div class="settings-dashboard" style="max-width: 900px;">
        <?php echo $message; ?>

        <form method="post" style="background: #f9f9f9; padding: 20px; border-radius: 8px;">
            <?php wp_nonce_field('save_board_portal_settings', 'settings_nonce'); ?>

            <h3>General Settings</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 15px; width: 40%; font-weight: bold;">Organization Name</td>
                    <td style="padding: 15px;">
                        <input type="text" name="organization_name"
                               value="<?php echo esc_attr($org_settings['organization_name'] ?? 'Your Organization'); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <p style="color: #666; font-size: 12px; margin: 5px 0 0;">Your organization's name (displayed throughout the site)</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 15px; font-weight: bold;">Board Portal Slug</td>
                    <td style="padding: 15px;">
                        <input type="text" name="board_portal_slug"
                               value="<?php echo esc_attr($org_settings['board_portal_slug'] ?? 'board-portal'); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <p style="color: #666; font-size: 12px; margin: 5px 0 0;">URL slug for board portal (e.g., "board-portal")</p>
                    </td>
                </tr>
            </table>

            <h3>Membership Settings</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <tr>
                    <td style="padding: 15px; width: 40%; font-weight: bold;">Dashboard Password</td>
                    <td style="padding: 15px;">
                        <input type="text" name="dashboard_password"
                               value="<?php echo esc_attr($org_settings['dashboard_password'] ?? ''); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <p style="color: #666; font-size: 12px; margin: 5px 0 0;">Password required to access member board portal</p>
                    </td>
                </tr>
            </table>

            <h3>Financial Settings</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 15px; width: 40%; font-weight: bold;">Currency</td>
                    <td style="padding: 15px;">
                        <select name="currency" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="USD" <?php selected($org_settings['currency'] ?? 'USD', 'USD'); ?>>USD ($)</option>
                            <option value="EUR" <?php selected($org_settings['currency'] ?? 'USD', 'EUR'); ?>>EUR (€)</option>
                            <option value="GBP" <?php selected($org_settings['currency'] ?? 'USD', 'GBP'); ?>>GBP (£)</option>
                            <option value="CAD" <?php selected($org_settings['currency'] ?? 'USD', 'CAD'); ?>>CAD ($)</option>
                        </select>
                        <p style="color: #666; font-size: 12px; margin: 5px 0 0;">Currency for financial displays</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 15px; font-weight: bold;">Fiscal Year Start Month</td>
                    <td style="padding: 15px;">
                        <select name="fiscal_year_start" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <?php
                            $months = array(
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                            );
                            $current_start = $org_settings['fiscal_year_start'] ?? 7;
                            foreach ($months as $num => $name) {
                                echo '<option value="' . $num . '"' . selected($current_start, $num, false) . '>' . $name . '</option>';
                            }
                            ?>
                        </select>
                        <p style="color: #666; font-size: 12px; margin: 5px 0 0;">Reference only - use Fiscal Year Configuration below for actual dates</p>
                    </td>
                </tr>
            </table>

            <h3>Stripe API Integration</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 15px; width: 40%; font-weight: bold;">API Mode</td>
                    <td style="padding: 15px;">
                        <select name="stripe_api_mode" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="live" <?php selected($org_settings['stripe_api_mode'] ?? 'live', 'live'); ?>>Live Mode</option>
                            <option value="test" <?php selected($org_settings['stripe_api_mode'] ?? 'live', 'test'); ?>>Test Mode</option>
                        </select>
                        <p style="color: #666; font-size: 12px; margin: 5px 0 0;">Select Live for production, Test for development</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 15px; font-weight: bold;">Stripe Secret Key</td>
                    <td style="padding: 15px;">
                        <input type="password" name="stripe_api_key"
                               value="<?php echo esc_attr($org_settings['stripe_api_key'] ?? ''); ?>"
                               placeholder="<?php echo ($org_settings['stripe_api_mode'] ?? 'live') === 'live' ? 'sk_live_...' : 'sk_test_...'; ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                        <p style="color: #666; font-size: 12px; margin: 5px 0 0;">
                            Your Stripe Secret Key (stored securely). Get it from your <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a>.
                            <?php if (!empty($org_settings['stripe_api_key'])): ?>
                                <br><span style="color: #00a32a;">✓ API key configured (<?php echo substr($org_settings['stripe_api_key'], 0, 12); ?>...)</span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3 style="margin-top: 30px;">📅 Fiscal Year Configuration</h3>
            <p style="color: #666;">Define your membership years with custom dates. Add historical years for data import.</p>

            <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px;">

                <div style="background: #f0f8ff; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #0073aa;">
                    <p style="margin: 0; font-size: 13px; color: #666;">
                        <strong>Current Year:</strong> <?php
                            $current = mm_get_current_year_config();
                            echo esc_html($current['label']);
                        ?> (<?php echo date('M j, Y', strtotime($current['start_date'])); ?> - <?php echo date('M j, Y', strtotime($current['end_date'])); ?>)
                    </p>
                </div>

                <h4>Membership Years</h4>
                <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Edit existing years or add new ones. The system automatically detects which year is current based on today's date.</p>

                <div id="years_container">
                    <?php
                    $years_to_show = !empty($fiscal_config['years']) ? $fiscal_config['years'] : array(
                        array('label' => '2025-2026', 'start_date' => '2025-07-01', 'end_date' => '2026-12-31', 'column_name' => 'status_2025_2026'),
                        array('label' => '2024-2025', 'start_date' => '2024-07-01', 'end_date' => '2025-06-30', 'column_name' => 'status_2024_2025'),
                        array('label' => '2023-2024', 'start_date' => '2023-07-01', 'end_date' => '2024-06-30', 'column_name' => 'status_2023_2024')
                    );

                    foreach ($years_to_show as $index => $year):
                        // Check if this is the current year
                        $is_current = false;
                        $today = date('Y-m-d');
                        if ($today >= $year['start_date'] && $today <= $year['end_date']) {
                            $is_current = true;
                        }
                    ?>
                        <div class="year-row" style="background: <?php echo $is_current ? '#d4f8ff' : '#f9f9f9'; ?>; padding: 15px; margin: 10px 0; border-radius: 4px; border: 1px solid #ddd; position: relative;">
                            <?php if ($is_current): ?>
                                <div style="position: absolute; top: 10px; right: 10px; background: #0073aa; color: white; padding: 3px 10px; border-radius: 3px; font-size: 11px; font-weight: bold;">CURRENT</div>
                            <?php endif; ?>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 2fr 40px; gap: 10px; align-items: end;">
                                <div>
                                    <label style="font-size: 12px; color: #666; font-weight: bold;">Year Label</label>
                                    <input type="text" name="year_<?php echo $index; ?>_label"
                                           value="<?php echo esc_attr($year['label']); ?>"
                                           placeholder="2025-2026"
                                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                                </div>
                                <div>
                                    <label style="font-size: 12px; color: #666; font-weight: bold;">Start Date</label>
                                    <input type="date" name="year_<?php echo $index; ?>_start"
                                           value="<?php echo esc_attr($year['start_date']); ?>"
                                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                                </div>
                                <div>
                                    <label style="font-size: 12px; color: #666; font-weight: bold;">End Date</label>
                                    <input type="date" name="year_<?php echo $index; ?>_end"
                                           value="<?php echo esc_attr($year['end_date']); ?>"
                                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                                </div>
                                <div>
                                    <label style="font-size: 12px; color: #666; font-weight: bold;">Database Column</label>
                                    <input type="text" name="year_<?php echo $index; ?>_column"
                                           value="<?php echo esc_attr($year['column_name']); ?>"
                                           placeholder="status_2025_2026"
                                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                                </div>
                                <div>
                                    <button type="button" onclick="removeYear(this)"
                                            style="background: #dc3232; color: white; border: none; padding: 8px 10px; border-radius: 3px; cursor: pointer; font-size: 18px;"
                                            title="Remove this year">×</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" onclick="addYear()"
                        style="background: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px; font-size: 14px;">
                    ➕ Add Another Year
                </button>

                <input type="hidden" name="year_count" id="year_count" value="<?php echo count($years_to_show); ?>">
                <input type="hidden" name="fiscal_year_type" value="custom">

                <div style="background: #fff3cd; padding: 15px; border-radius: 4px; margin-top: 20px; border-left: 4px solid #f0ad4e;">
                    <p style="margin: 0; font-size: 13px;">
                        <strong>Important:</strong> Database columns (like <code>status_2025_2026</code>) must exist in your <code>wp_swca_members</code> table.
                        The system uses these columns to store and retrieve membership status for each year.
                    </p>
                </div>
            </div>

            <script>
            let yearCounter = <?php echo count($years_to_show); ?>;

            function addYear() {
                const container = document.getElementById('years_container');
                const index = yearCounter++;

                const yearRow = document.createElement('div');
                yearRow.className = 'year-row';
                yearRow.style.cssText = 'background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 4px; border: 1px solid #ddd; position: relative;';

                yearRow.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 2fr 40px; gap: 10px; align-items: end;">
                        <div>
                            <label style="font-size: 12px; color: #666; font-weight: bold;">Year Label</label>
                            <input type="text" name="year_${index}_label"
                                   placeholder="2022-2023"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #666; font-weight: bold;">Start Date</label>
                            <input type="date" name="year_${index}_start"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #666; font-weight: bold;">End Date</label>
                            <input type="date" name="year_${index}_end"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #666; font-weight: bold;">Database Column</label>
                            <input type="text" name="year_${index}_column"
                                   placeholder="status_2022_2023"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                        </div>
                        <div>
                            <button type="button" onclick="removeYear(this)"
                                    style="background: #dc3232; color: white; border: none; padding: 8px 10px; border-radius: 3px; cursor: pointer; font-size: 18px;"
                                    title="Remove this year">×</button>
                        </div>
                    </div>
                `;

                container.appendChild(yearRow);
                document.getElementById('year_count').value = yearCounter;
            }

            function removeYear(button) {
                if (confirm('Are you sure you want to remove this year?')) {
                    button.closest('.year-row').remove();
                    // Recount remaining years
                    const rows = document.querySelectorAll('.year-row').length;
                    document.getElementById('year_count').value = rows;
                }
            }
            </script>

            <h3 style="margin-top: 30px;">Feature Toggles</h3>
            <p style="color: #666;">Enable or disable features for your organization</p>

            <table style="width: 100%; border-collapse: collapse;">
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

                foreach ($features as $key => $label) {
                    $checked = !empty($enabled_features[$key]) ? 'checked' : '';
                    echo '<tr style="border-bottom: 1px solid #ddd;">';
                    echo '<td style="padding: 15px; width: 40%; font-weight: bold;">' . esc_html($label) . '</td>';
                    echo '<td style="padding: 15px;">';
                    echo '<label>';
                    echo '<input type="checkbox" name="feature_' . $key . '" value="1" ' . $checked . ' style="margin-right: 8px;">';
                    echo 'Enable ' . esc_html($label);
                    echo '</label>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </table>

            <div style="margin-top: 30px;">
                <button type="submit" name="save_settings"
                        style="background: #0073aa; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;">
                    💾 Save Settings
                </button>
            </div>
        </form>

        <div style="background: #fff; padding: 20px; border-radius: 8px; margin-top: 30px; border: 1px solid #ddd;">
            <h3>Quick Actions</h3>
            <p>
                <a href="/wp-admin/admin.php?page=501c3PO-settings" style="color: #0073aa; text-decoration: none;">
                    ⚙️ Advanced Settings (WordPress Admin)
                </a>
            </p>
            <p>
                <a href="/wp-admin/admin.php?page=501c3PO-export-import" style="color: #0073aa; text-decoration: none;">
                    📦 Export & Import Data
                </a>
            </p>
            <p>
                <a href="/wp-admin/admin.php?page=501c3PO-bank-transactions" style="color: #0073aa; text-decoration: none;">
                    🏦 Manage Bank Transactions
                </a>
            </p>
            <p>
                <a href="/wp-admin/admin.php?page=501c3PO-stripe-sync" style="color: #0073aa; text-decoration: none;">
                    💳 Sync Stripe Transactions
                </a>
            </p>
        </div>
    </div>

    <style>
        .settings-dashboard input[type="text"],
        .settings-dashboard select {
            font-family: inherit;
        }
        .settings-dashboard button:hover {
            background: #005a87 !important;
        }
    </style>
    <?php
    return ob_get_clean();
}

function fiveohonec3po_officer_tools_dashboard_shortcode() {
    $org_settings = get_option('mm_organization_settings', array());
    $org_name = $org_settings['organization_name'] ?? 'Your Organization';
    $board_portal_slug = $org_settings['board_portal_slug'] ?? 'board-portal';

    ob_start();
    ?>
    <div class="officer-tools-dashboard" style="max-width: 1200px;">
        <div style="background: #f0f8ff; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #0073aa;">
            <h3 style="margin-top: 0;">🏛️ Officer Management Tools</h3>
            <p style="margin-bottom: 0;">Streamline board operations with agendas, minutes, reports, and document management.</p>
        </div>

        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #f0ad4e;">
            <h4 style="margin-top: 0;">🚧 Under Development</h4>
            <p style="margin-bottom: 0;">Officer tools features are currently being developed. The following capabilities are planned:</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <!-- Meeting Agendas -->
            <div style="background: #fff; border: 1px solid #ddd; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); opacity: 0.7;">
                <h3 style="margin-top: 0; color: #666;">📋 Meeting Agendas</h3>
                <p style="color: #666; margin-bottom: 20px;">Create and manage board meeting agendas</p>
                <ul style="list-style: disc; margin-left: 20px; color: #666; margin-bottom: 20px;">
                    <li>Create agenda templates</li>
                    <li>Add agenda items</li>
                    <li>Share with board members</li>
                </ul>
                <p style="margin-bottom: 0; color: #999; font-style: italic;">
                    Coming soon
                </p>
            </div>

            <!-- Meeting Minutes -->
            <div style="background: #fff; border: 1px solid #ddd; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); opacity: 0.7;">
                <h3 style="margin-top: 0; color: #666;">📝 Meeting Minutes</h3>
                <p style="color: #666; margin-bottom: 20px;">Record and archive meeting minutes</p>
                <ul style="list-style: disc; margin-left: 20px; color: #666; margin-bottom: 20px;">
                    <li>Create minutes from agendas</li>
                    <li>Approval workflow</li>
                    <li>Historical archive</li>
                </ul>
                <p style="margin-bottom: 0; color: #999; font-style: italic;">
                    Coming soon
                </p>
            </div>

            <!-- Reports -->
            <div style="background: #fff; border: 1px solid #ddd; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); opacity: 0.7;">
                <h3 style="margin-top: 0; color: #666;">📊 Board Reports</h3>
                <p style="color: #666; margin-bottom: 20px;">Generate reports for board meetings</p>
                <ul style="list-style: disc; margin-left: 20px; color: #666; margin-bottom: 20px;">
                    <li>Membership reports</li>
                    <li>Financial summaries</li>
                    <li>Committee updates</li>
                </ul>
                <p style="margin-bottom: 0; color: #999; font-style: italic;">
                    Coming soon
                </p>
            </div>

            <!-- Document Management -->
            <div style="background: #fff; border: 1px solid #ddd; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); opacity: 0.7;">
                <h3 style="margin-top: 0; color: #666;">📁 Document Library</h3>
                <p style="color: #666; margin-bottom: 20px;">Upload and organize board documents</p>
                <ul style="list-style: disc; margin-left: 20px; color: #666; margin-bottom: 20px;">
                    <li>Upload documents</li>
                    <li>Organize by category</li>
                    <li>Share with members</li>
                </ul>
                <p style="margin-bottom: 0; color: #999; font-style: italic;">
                    Coming soon
                </p>
            </div>
        </div>

        <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 4px solid #0073aa;">
            <h4 style="margin-top: 0;">💡 In the Meantime</h4>
            <p>You can use the existing features to manage your organization:</p>
            <ul style="list-style: disc; margin-left: 20px; margin-bottom: 0;">
                <li><a href="/<?php echo esc_attr($board_portal_slug); ?>/current-membership">Current Membership</a> - View and manage current members</li>
                <li><a href="/<?php echo esc_attr($board_portal_slug); ?>/financial">Financial Reports</a> - Access fiscal year analysis and bank transactions</li>
                <li><a href="/wp-admin/admin.php?page=501c3PO-export-import">Export & Import</a> - Manage member data (Admin only)</li>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}