<?php
/**
 * Stripe Integration Feature
 * Sync Stripe transactions and match to members
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Stripe Integration menu
 */
add_action('admin_menu', 'mm_add_stripe_menu', 22);

function mm_add_stripe_menu() {
    add_submenu_page(
        'membership-management',
        'Stripe Sync',
        '💳 Stripe Sync',
        'manage_options',
        '501c3PO-stripe-sync',
        'mm_stripe_integration_page'
    );
}

/**
 * Stripe Integration page
 */
function mm_stripe_integration_page() {
    // Get stored API key from organization settings
    $org_settings = get_option('mm_organization_settings', array());
    $stored_api_key = $org_settings['stripe_api_key'] ?? '';
    $api_mode = $org_settings['stripe_api_mode'] ?? 'live';

    // Handle sync
    $sync_results = null;
    if (isset($_POST['sync_stripe_transactions'])) {
        check_admin_referer('mm_stripe_sync');

        // Use manual key if provided, otherwise use stored key
        $api_key = sanitize_text_field($_POST['stripe_api_key'] ?? '');
        if (empty($api_key)) {
            $api_key = $stored_api_key;
        }

        $days_back = intval($_POST['days_back'] ?? 30);

        if (empty($api_key)) {
            echo '<div class="notice notice-error"><p>Please enter a Stripe API key or configure one in Settings</p></div>';
        } else {
            $sync_results = mm_sync_stripe_transactions($api_key, $days_back);
        }
    }

    ?>
    <div class="wrap">
        <h1>💳 Stripe Integration</h1>

        <?php if ($sync_results): ?>
            <div class="notice notice-success">
                <h3>Sync Complete!</h3>
                <ul>
                    <li><strong>Charges Downloaded:</strong> <?php echo $sync_results['charges_count']; ?></li>
                    <li><strong>Refunds Downloaded:</strong> <?php echo $sync_results['refunds_count']; ?></li>
                    <li><strong>Members Matched:</strong> <?php echo $sync_results['members_matched']; ?></li>
                    <li><strong>Total Revenue:</strong> $<?php echo number_format($sync_results['total_revenue'], 2); ?></li>
                </ul>
                <?php if (!empty($sync_results['details'])): ?>
                    <details>
                        <summary>View Details</summary>
                        <pre><?php echo esc_html($sync_results['details']); ?></pre>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($stored_api_key)): ?>
            <div class="notice notice-info">
                <p><strong>✓ Stripe API Key Configured</strong> - Using saved key (<?php echo substr($stored_api_key, 0, 12); ?>...) in <?php echo strtoupper($api_mode); ?> mode.
                <a href="<?php echo admin_url('admin.php?page=501c3PO-settings'); ?>">Change in Settings →</a></p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p><strong>⚠ No API Key Configured</strong> - Please configure your Stripe API key in <a href="<?php echo admin_url('admin.php?page=501c3PO-settings'); ?>">Settings</a> or enter it manually below.</p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>🔄 Sync Stripe Transactions</h2>
            <p>Download recent transactions from Stripe and match them to member records.</p>

            <form method="post">
                <?php wp_nonce_field('mm_stripe_sync'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Stripe Secret Key</th>
                        <td>
                            <input type="password" name="stripe_api_key" class="regular-text"
                                   value="<?php echo !empty($stored_api_key) ? esc_attr($stored_api_key) : ''; ?>"
                                   placeholder="<?php echo $api_mode === 'live' ? 'sk_live_...' : 'sk_test_...'; ?>">
                            <p class="description">
                                <?php if (!empty($stored_api_key)): ?>
                                    Using saved key from settings. Leave blank to use saved key, or enter a different key to override.
                                <?php else: ?>
                                    Enter your Stripe Secret Key (starts with sk_live_ or sk_test_). <a href="<?php echo admin_url('admin.php?page=501c3PO-settings'); ?>">Save it in Settings →</a>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Days to Sync</th>
                        <td>
                            <input type="number" name="days_back" value="30" min="1" max="365">
                            <p class="description">How many days back should we sync? (default: 30)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Sync Transactions Now', 'primary', 'sync_stripe_transactions'); ?>
            </form>
        </div>

        <div class="card">
            <h2>📖 How It Works</h2>
            <ol>
                <li>Enter your Stripe Secret Key (found in your Stripe Dashboard)</li>
                <li>Choose how many days back to sync</li>
                <li>Click "Sync Transactions Now"</li>
                <li>The system will:
                    <ul style="margin-left: 20px;">
                        <li>Download all charges and refunds from Stripe</li>
                        <li>Match transactions to members by email address</li>
                        <li>Update member payment totals</li>
                        <li>Store transaction data for reporting</li>
                    </ul>
                </li>
            </ol>
        </div>
    </div>
    <?php
}

/**
 * Sync Stripe transactions
 */
function mm_sync_stripe_transactions($api_key, $days_back = 30) {
    global $wpdb;

    $results = array(
        'charges_count' => 0,
        'refunds_count' => 0,
        'members_matched' => 0,
        'total_revenue' => 0,
        'details' => ''
    );

    // Validate API key
    if (!str_starts_with($api_key, 'sk_live_') && !str_starts_with($api_key, 'sk_test_')) {
        $results['details'] = 'Invalid API key format';
        return $results;
    }

    $start_timestamp = time() - ($days_back * 24 * 60 * 60);

    // Download charges
    $charges_data = mm_stripe_api_call("charges?limit=100&created[gte]=$start_timestamp&expand[]=data.customer", $api_key);

    if (!$charges_data || !isset($charges_data['data'])) {
        $results['details'] = 'Failed to download charges from Stripe';
        return $results;
    }

    $results['charges_count'] = count($charges_data['data']);
    $details = "Stripe Sync Results:\n\n";

    // Download refunds
    $refunds_data = mm_stripe_api_call("refunds?limit=100&created[gte]=$start_timestamp", $api_key);
    $results['refunds_count'] = isset($refunds_data['data']) ? count($refunds_data['data']) : 0;

    // Build refunds lookup by charge ID
    $refunds_by_charge = array();
    if (isset($refunds_data['data'])) {
        foreach ($refunds_data['data'] as $refund) {
            $charge_id = $refund['charge'] ?? '';
            if (!isset($refunds_by_charge[$charge_id])) {
                $refunds_by_charge[$charge_id] = 0;
            }
            $refunds_by_charge[$charge_id] += $refund['amount'] / 100; // Convert cents to dollars
        }
    }

    // Process charges and match to members
    $member_table = $wpdb->prefix . 'swca_members';
    $matched_emails = array();

    foreach ($charges_data['data'] as $charge) {
        if ($charge['status'] !== 'succeeded') {
            continue;
        }

        $email = $charge['billing_details']['email'] ?? $charge['receipt_email'] ?? '';
        if (empty($email)) {
            continue;
        }

        $amount = $charge['amount'] / 100; // Convert cents to dollars
        $refund_amount = $refunds_by_charge[$charge['id']] ?? 0;
        $net_amount = $amount - $refund_amount;

        // Find member by email
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $member_table WHERE email_1 = %s OR email_2 = %s OR email_3 = %s OR email_4 = %s LIMIT 1",
            $email, $email, $email, $email
        ));

        if ($member) {
            $matched_emails[$email] = ($matched_emails[$email] ?? 0) + $net_amount;
            $results['total_revenue'] += $net_amount;

            $details .= sprintf(
                "%s - $%.2f (Refund: $%.2f) - %s %s\n",
                date('Y-m-d', $charge['created']),
                $amount,
                $refund_amount,
                $member->first_name,
                $member->last_name
            );
        }
    }

    $results['members_matched'] = count($matched_emails);
    $results['details'] = $details;

    return $results;
}

/**
 * Make Stripe API call
 */
function mm_stripe_api_call($endpoint, $api_key) {
    $url = "https://api.stripe.com/v1/" . $endpoint;

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ),
        'timeout' => 30,
        'sslverify' => true
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}
