<?php
/**
 * Stripe Integration Feature
 * Sync Stripe transactions and match to members
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Encrypt Stripe API key with officer passphrase
 */
function five01c3po_encrypt_stripe_key($api_key, $passphrase) {
    $cipher = "AES-256-CBC";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);

    $encrypted = openssl_encrypt($api_key, $cipher, $passphrase, 0, $iv);

    // Return base64 encoded IV + encrypted data
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Decrypt Stripe API key with officer passphrase
 */
function five01c3po_decrypt_stripe_key($encrypted_data, $passphrase) {
    $cipher = "AES-256-CBC";

    $decoded = base64_decode($encrypted_data);
    $parts = explode('::', $decoded, 2);

    if (count($parts) !== 2) {
        return false;
    }

    list($iv, $encrypted) = $parts;

    $decrypted = openssl_decrypt($encrypted, $cipher, $passphrase, 0, $iv);

    return $decrypted;
}

/**
 * Verify officer passphrase
 */
function five01c3po_verify_officer_passphrase($passphrase, $stored_hash) {
    return password_verify($passphrase, $stored_hash);
}

/**
 * Add Stripe Integration menu
 */
add_action('admin_menu', 'five01c3po_add_stripe_menu', 22);

function five01c3po_add_stripe_menu() {
    add_submenu_page(
        'membership-management',
        'Stripe Sync',
        '💳 Stripe Sync',
        'manage_options',
        '501c3PO-stripe-sync',
        'five01c3po_stripe_integration_page'
    );
}

/**
 * Stripe Integration page
 */
function five01c3po_stripe_integration_page() {
    // Get stored API key from organization settings
    $org_settings = get_option('five01c3po_organization_settings', array());
    $encrypted_api_key = $org_settings['stripe_api_key_encrypted'] ?? '';
    $passphrase_hash = $org_settings['stripe_passphrase_hash'] ?? '';
    $api_mode = $org_settings['stripe_api_mode'] ?? 'live';
    $has_encrypted_key = !empty($encrypted_api_key) && !empty($passphrase_hash);

    // Handle sync
    $sync_results = null;
    $error_message = '';

    if (isset($_POST['sync_stripe_transactions'])) {
        check_admin_referer('five01c3po_stripe_sync');

        $manual_api_key = sanitize_text_field($_POST['stripe_api_key'] ?? '');
        $officer_passphrase = $_POST['officer_passphrase'] ?? '';
        $save_key = isset($_POST['save_api_key']);
        $new_passphrase = $_POST['new_passphrase'] ?? '';
        $days_back = intval($_POST['days_back'] ?? 30);

        $api_key = '';

        // Determine which API key to use
        if (!empty($manual_api_key)) {
            // Manual key provided
            $api_key = $manual_api_key;

            // Save if requested
            if ($save_key) {
                if (empty($new_passphrase)) {
                    $error_message = 'Please provide a passphrase to encrypt the API key';
                } else {
                    // Encrypt and save the API key
                    $org_settings['stripe_api_key_encrypted'] = five01c3po_encrypt_stripe_key($api_key, $new_passphrase);
                    $org_settings['stripe_passphrase_hash'] = password_hash($new_passphrase, PASSWORD_DEFAULT);
                    update_option('five01c3po_organization_settings', $org_settings);
                    echo '<div class="notice notice-success"><p>✓ Stripe API key encrypted and saved to settings</p></div>';
                    $has_encrypted_key = true;
                }
            }
        } elseif ($has_encrypted_key) {
            // Use stored encrypted key
            if (empty($officer_passphrase)) {
                $error_message = 'Please enter the officer passphrase to decrypt the API key';
            } elseif (!five01c3po_verify_officer_passphrase($officer_passphrase, $passphrase_hash)) {
                $error_message = 'Incorrect officer passphrase';
            } else {
                // Decrypt the stored API key
                $api_key = five01c3po_decrypt_stripe_key($encrypted_api_key, $officer_passphrase);
                if ($api_key === false) {
                    $error_message = 'Failed to decrypt API key';
                }
            }
        } else {
            $error_message = 'No API key available. Please enter one manually.';
        }

        // Perform sync if we have a valid API key
        if (!empty($api_key) && empty($error_message)) {
            $sync_results = five01c3po_sync_stripe_transactions($api_key, $days_back);
        } elseif (!empty($error_message)) {
            echo '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
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
                    <li><strong>New Transactions Stored:</strong> <?php echo $sync_results['new_transactions']; ?></li>
                    <li><strong>Updated (Refunds Changed):</strong> <?php echo $sync_results['updated_transactions']; ?></li>
                    <li><strong>Duplicates Skipped:</strong> <?php echo $sync_results['duplicate_transactions']; ?></li>
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

        <?php if ($has_encrypted_key): ?>
            <div class="notice notice-info">
                <p><strong>🔒 Stripe API Key Configured (Encrypted)</strong> - API key is stored encrypted in <?php echo strtoupper($api_mode); ?> mode.
                <br>Enter the officer passphrase below to use it for syncing.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p><strong>⚠ No API Key Configured</strong> - Please enter your Stripe API key manually below and set an officer passphrase to encrypt it.</p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>🔄 Sync Stripe Transactions</h2>
            <p>Download recent transactions from Stripe and match them to member records.</p>

            <form method="post">
                <?php wp_nonce_field('five01c3po_stripe_sync'); ?>
                <table class="form-table">
                    <?php if ($has_encrypted_key): ?>
                    <tr>
                        <th scope="row">Officer Passphrase <span style="color: red;">*</span></th>
                        <td>
                            <input type="password" name="officer_passphrase" class="regular-text" required
                                   placeholder="Enter passphrase to decrypt API key">
                            <p class="description">Enter the passphrase set by the Treasurer or authorized officer to decrypt the Stripe API key.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th scope="row">Stripe Secret Key <span style="color: red;">*</span></th>
                        <td>
                            <input type="password" name="stripe_api_key" class="regular-text" required
                                   placeholder="<?php echo $api_mode === 'live' ? 'sk_live_...' : 'sk_test_...'; ?>">
                            <p class="description">Enter your Stripe Secret Key (starts with sk_live_ or sk_test_).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label>
                                <input type="checkbox" name="save_api_key" value="1" checked>
                                Save API Key (Encrypted)
                            </label>
                        </th>
                        <td>
                            <input type="password" name="new_passphrase" class="regular-text" id="new_passphrase"
                                   placeholder="Create officer passphrase">
                            <p class="description">
                                <strong>Recommended:</strong> Set a passphrase to encrypt the API key for secure storage.
                                <br>This passphrase will be required for future Stripe syncs.
                                <br><strong>Share this passphrase only with the Treasurer, President, or Secretary.</strong>
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row">Days to Sync</th>
                        <td>
                            <input type="number" name="days_back" value="30" min="1" max="3650" style="width: 100px;">
                            <p class="description">
                                How many days back should we sync?
                                <br><strong>Recommendations:</strong>
                                <br>• 30 days - Recent transactions only
                                <br>• 365 days - Last year
                                <br>• <strong>3650 days (10 years)</strong> - Complete historical data (recommended for first sync)
                                <br><em>Note: Pagination handles any amount of data automatically. Duplicates are detected and skipped.</em>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Sync Transactions Now', 'primary', 'sync_stripe_transactions'); ?>
            </form>
        </div>

        <div class="card">
            <h2>📖 How It Works</h2>
            <ol>
                <li><strong>First Time Setup:</strong>
                    <ul style="margin-left: 20px;">
                        <li>Enter your Stripe Secret Key (found in your Stripe Dashboard)</li>
                        <li>Create an officer passphrase (known only to Treasurer, President, Secretary)</li>
                        <li>The API key will be encrypted with AES-256 encryption and stored securely</li>
                        <li>The passphrase is hashed and cannot be recovered if lost</li>
                    </ul>
                </li>
                <li><strong>Future Syncs:</strong>
                    <ul style="margin-left: 20px;">
                        <li>Enter the officer passphrase to decrypt the API key</li>
                        <li>The key is decrypted in memory only and never stored in plaintext</li>
                    </ul>
                </li>
                <li><strong>Sync Process:</strong>
                    <ul style="margin-left: 20px;">
                        <li>Choose how many days back to sync</li>
                        <li>Downloads all charges and refunds from Stripe</li>
                        <li>Matches transactions to members by email address</li>
                        <li>Updates member payment totals</li>
                        <li>Stores transaction data for reporting</li>
                    </ul>
                </li>
            </ol>
            <p style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">
                <strong>🔐 Security Note:</strong> The Stripe API key is encrypted using AES-256 encryption.
                Only authorized officers with the passphrase can decrypt and use it. The passphrase is never stored
                in plaintext - only a secure hash is kept to verify it.
            </p>
        </div>
    </div>
    <?php
}

/**
 * Sync Stripe transactions
 */
function five01c3po_sync_stripe_transactions($api_key, $days_back = 30) {
    global $wpdb;

    $results = array(
        'charges_count' => 0,
        'refunds_count' => 0,
        'members_matched' => 0,
        'total_revenue' => 0,
        'new_transactions' => 0,
        'updated_transactions' => 0,
        'duplicate_transactions' => 0,
        'details' => ''
    );

    // Validate API key
    if (!str_starts_with($api_key, 'sk_live_') && !str_starts_with($api_key, 'sk_test_')) {
        $results['details'] = 'Invalid API key format';
        return $results;
    }

    $start_timestamp = time() - ($days_back * 24 * 60 * 60);

    // Download ALL charges with pagination
    $all_charges = array();
    $has_more = true;
    $starting_after = null;

    while ($has_more) {
        $endpoint = "charges?limit=100&created[gte]=$start_timestamp&expand[]=data.customer&expand[]=data.balance_transaction&expand[]=data.balance_transaction.payout";
        if ($starting_after) {
            $endpoint .= "&starting_after=$starting_after";
        }

        $charges_data = five01c3po_stripe_api_call($endpoint, $api_key);

        if (!$charges_data || !isset($charges_data['data'])) {
            if (empty($all_charges)) {
                $results['details'] = 'Failed to download charges from Stripe';
                return $results;
            }
            break;
        }

        $all_charges = array_merge($all_charges, $charges_data['data']);
        $has_more = $charges_data['has_more'] ?? false;

        if ($has_more && !empty($charges_data['data'])) {
            $starting_after = end($charges_data['data'])['id'];
        } else {
            $has_more = false;
        }

        // Safety limit: stop after 10,000 charges
        if (count($all_charges) >= 10000) {
            break;
        }
    }

    $results['charges_count'] = count($all_charges);
    $details = "Stripe Sync Results:\n\n";

    // Download ALL refunds with pagination
    $all_refunds = array();
    $has_more = true;
    $starting_after = null;

    while ($has_more) {
        $endpoint = "refunds?limit=100&created[gte]=$start_timestamp";
        if ($starting_after) {
            $endpoint .= "&starting_after=$starting_after";
        }

        $refunds_data = five01c3po_stripe_api_call($endpoint, $api_key);

        if (!$refunds_data || !isset($refunds_data['data'])) {
            break;
        }

        $all_refunds = array_merge($all_refunds, $refunds_data['data']);
        $has_more = $refunds_data['has_more'] ?? false;

        if ($has_more && !empty($refunds_data['data'])) {
            $starting_after = end($refunds_data['data'])['id'];
        } else {
            $has_more = false;
        }

        // Safety limit
        if (count($all_refunds) >= 10000) {
            break;
        }
    }

    $results['refunds_count'] = count($all_refunds);

    // Build refunds lookup by charge ID
    $refunds_by_charge = array();
    foreach ($all_refunds as $refund) {
        $charge_id = $refund['charge'] ?? '';
        if (!isset($refunds_by_charge[$charge_id])) {
            $refunds_by_charge[$charge_id] = 0;
        }
        $refunds_by_charge[$charge_id] += $refund['amount'] / 100; // Convert cents to dollars
    }

    // Process charges and match to members
    $member_table = $wpdb->prefix . 'swca_members';
    $stripe_table = $wpdb->prefix . 'stripe_transactions';
    $matched_emails = array();

    foreach ($all_charges as $charge) {
        if ($charge['status'] !== 'succeeded') {
            continue;
        }

        $email = $charge['billing_details']['email'] ?? $charge['receipt_email'] ?? '';
        $amount = $charge['amount'] / 100; // Convert cents to dollars
        $refund_amount = $refunds_by_charge[$charge['id']] ?? 0;
        $net_amount = $amount - $refund_amount;

        // Get Stripe fee and payout data from balance transaction if available
        $stripe_fee = 0;
        $payout_id = null;
        $payout_date = null;
        $payout_arrival_date = null;
        $payout_status = null;
        $balance_txn_id = null;

        if (isset($charge['balance_transaction']) && is_array($charge['balance_transaction'])) {
            $balance_txn = $charge['balance_transaction'];
            $stripe_fee = ($balance_txn['fee'] ?? 0) / 100;
            $balance_txn_id = $balance_txn['id'] ?? null;

            // Get payout information
            if (isset($balance_txn['payout'])) {
                if (is_string($balance_txn['payout'])) {
                    // Payout ID only - need to fetch full payout details
                    $payout_id = $balance_txn['payout'];
                } elseif (is_array($balance_txn['payout'])) {
                    // Payout object expanded
                    $payout_id = $balance_txn['payout']['id'] ?? null;
                    $payout_status = $balance_txn['payout']['status'] ?? null;

                    if (isset($balance_txn['payout']['created'])) {
                        $payout_date = date('Y-m-d', $balance_txn['payout']['created']);
                    }
                    if (isset($balance_txn['payout']['arrival_date'])) {
                        $payout_arrival_date = date('Y-m-d', $balance_txn['payout']['arrival_date']);
                    }
                }
            }
        }

        // Find member by email
        $member_id = null;
        if (!empty($email)) {
            $member = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $member_table WHERE email_1 = %s OR email_2 = %s OR email_3 = %s OR email_4 = %s LIMIT 1",
                $email, $email, $email, $email
            ));

            if ($member) {
                $member_id = $member->id;
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

        // Prepare transaction data
        $transaction_data = array(
            'stripe_charge_id' => $charge['id'],
            'transaction_type' => 'charge',
            'member_id' => $member_id,
            'customer_email' => $email,
            'amount' => $amount,
            'amount_refunded' => $refund_amount,
            'net_amount' => $net_amount,
            'stripe_fee' => $stripe_fee,
            'currency' => strtolower($charge['currency'] ?? 'usd'),
            'status' => $charge['status'],
            'description' => $charge['description'] ?? '',
            'customer_name' => $charge['billing_details']['name'] ?? '',
            'payment_method' => $charge['payment_method_details']['type'] ?? '',
            'receipt_url' => $charge['receipt_url'] ?? '',
            'stripe_created' => date('Y-m-d H:i:s', $charge['created']),
            'payout_id' => $payout_id,
            'payout_date' => $payout_date,
            'payout_arrival_date' => $payout_arrival_date,
            'payout_status' => $payout_status,
            'balance_transaction_id' => $balance_txn_id
        );

        // Check if transaction already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $stripe_table WHERE stripe_charge_id = %s",
            $charge['id']
        ));

        if ($existing) {
            // Update if refund amount or payout data changed
            $needs_update = false;

            if ($existing->amount_refunded != $refund_amount) {
                $needs_update = true;
            }

            // Check if payout data is new/different
            if ($payout_id && $existing->payout_id != $payout_id) {
                $needs_update = true;
            }

            if ($needs_update) {
                $wpdb->update(
                    $stripe_table,
                    array(
                        'amount_refunded' => $refund_amount,
                        'net_amount' => $net_amount,
                        'payout_id' => $payout_id,
                        'payout_date' => $payout_date,
                        'payout_arrival_date' => $payout_arrival_date,
                        'payout_status' => $payout_status,
                        'balance_transaction_id' => $balance_txn_id,
                        'synced_at' => current_time('mysql')
                    ),
                    array('stripe_charge_id' => $charge['id'])
                );
                $results['updated_transactions']++;
            } else {
                $results['duplicate_transactions']++;
            }
        } else {
            // Insert new transaction
            $wpdb->insert($stripe_table, $transaction_data);
            $results['new_transactions']++;
        }
    }

    $results['members_matched'] = count($matched_emails);
    $results['details'] = $details;

    return $results;
}

/**
 * Make Stripe API call
 */
function five01c3po_stripe_api_call($endpoint, $api_key) {
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
