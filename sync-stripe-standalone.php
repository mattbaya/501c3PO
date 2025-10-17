<?php
/**
 * Standalone Stripe Sync (no WordPress required)
 * Uses passphrase to decrypt stored API key
 */

$passphrase = 'POBox432';
$days_back = 30;

// Database connection
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== STANDALONE STRIPE SYNC ===\n\n";

// Get organization settings from WordPress options
$result = $mysqli->query("SELECT option_value FROM wp_options WHERE option_name = 'five01c3po_organization_settings'");

if (!$result || $result->num_rows == 0) {
    echo "ERROR: Could not find organization settings.\n";
    echo "The Stripe API key must be configured via the WordPress admin interface first.\n";
    $mysqli->close();
    exit(1);
}

$row = $result->fetch_assoc();
$settings = unserialize($row['option_value']);

$encrypted_api_key = $settings['stripe_api_key_encrypted'] ?? '';
$passphrase_hash = $settings['stripe_passphrase_hash'] ?? '';

if (empty($encrypted_api_key) || empty($passphrase_hash)) {
    echo "ERROR: No encrypted Stripe API key found.\n";
    $mysqli->close();
    exit(1);
}

// Verify passphrase
if (!password_verify($passphrase, $passphrase_hash)) {
    echo "ERROR: Incorrect passphrase.\n";
    $mysqli->close();
    exit(1);
}

echo "✓ Passphrase verified\n";

// Decrypt API key
$cipher = "AES-256-CBC";
$decoded = base64_decode($encrypted_api_key);
$parts = explode('::', $decoded, 2);

if (count($parts) !== 2) {
    echo "ERROR: Invalid encrypted key format.\n";
    $mysqli->close();
    exit(1);
}

list($iv, $encrypted) = $parts;
$api_key = openssl_decrypt($encrypted, $cipher, $passphrase, 0, $iv);

if ($api_key === false) {
    echo "ERROR: Failed to decrypt API key.\n";
    $mysqli->close();
    exit(1);
}

echo "✓ API key decrypted successfully\n";
echo "✓ API key starts with: " . substr($api_key, 0, 10) . "...\n";
echo "Syncing last $days_back days from Stripe...\n\n";

// Calculate start timestamp
$start_timestamp = time() - ($days_back * 24 * 60 * 60);

// Function to call Stripe API
function call_stripe_api($endpoint, $api_key) {
    $url = "https://api.stripe.com/v1/" . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/x-www-form-urlencoded'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        return false;
    }

    return json_decode($response, true);
}

// Download charges
echo "Downloading charges...\n";
$all_charges = array();
$has_more = true;
$starting_after = null;

while ($has_more) {
    $endpoint = "charges?limit=100&created[gte]=$start_timestamp&expand[]=data.balance_transaction";
    if ($starting_after) {
        $endpoint .= "&starting_after=$starting_after";
    }

    $charges_data = call_stripe_api($endpoint, $api_key);

    if (!$charges_data || !isset($charges_data['data'])) {
        break;
    }

    $all_charges = array_merge($all_charges, $charges_data['data']);
    $has_more = $charges_data['has_more'] ?? false;

    if ($has_more && !empty($charges_data['data'])) {
        $starting_after = end($charges_data['data'])['id'];
    } else {
        $has_more = false;
    }

    echo "  Downloaded " . count($all_charges) . " charges...\r";
}

echo "\n✓ Downloaded " . count($all_charges) . " charges\n\n";

if (count($all_charges) == 0) {
    echo "No new charges found in the last $days_back days.\n";
    $mysqli->close();
    exit(0);
}

// Download refunds
echo "Downloading refunds...\n";
$all_refunds = array();
$has_more = true;
$starting_after = null;

while ($has_more) {
    $endpoint = "refunds?limit=100&created[gte]=$start_timestamp";
    if ($starting_after) {
        $endpoint .= "&starting_after=$starting_after";
    }

    $refunds_data = call_stripe_api($endpoint, $api_key);

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
}

echo "✓ Downloaded " . count($all_refunds) . " refunds\n\n";

// Build refunds lookup
$refunds_by_charge = array();
foreach ($all_refunds as $refund) {
    $charge_id = $refund['charge'] ?? '';
    if (!isset($refunds_by_charge[$charge_id])) {
        $refunds_by_charge[$charge_id] = 0;
    }
    $refunds_by_charge[$charge_id] += $refund['amount'] / 100;
}

// Process and store charges
echo "Processing charges...\n";
$new_count = 0;
$updated_count = 0;
$duplicate_count = 0;

foreach ($all_charges as $charge) {
    if ($charge['status'] !== 'succeeded') {
        continue;
    }

    $email = $charge['billing_details']['email'] ?? $charge['receipt_email'] ?? '';
    $amount = $charge['amount'] / 100;
    $refund_amount = $refunds_by_charge[$charge['id']] ?? 0;
    $net_amount = $amount - $refund_amount;

    // Get fee from balance transaction
    $stripe_fee = 0;
    $payout_arrival_date = null;

    if (isset($charge['balance_transaction']) && is_array($charge['balance_transaction'])) {
        $balance_txn = $charge['balance_transaction'];
        $stripe_fee = ($balance_txn['fee'] ?? 0) / 100;

        if (isset($balance_txn['available_on'])) {
            $payout_arrival_date = date('Y-m-d', $balance_txn['available_on']);
        }
    }

    // Check if exists
    $stmt = $mysqli->prepare("SELECT id, amount_refunded, payout_arrival_date FROM swca_stripe_transactions WHERE stripe_charge_id = ?");
    $stmt->bind_param('s', $charge['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Update if changed
        $needs_update = false;

        if ($existing['amount_refunded'] != $refund_amount) {
            $needs_update = true;
        }

        if ($payout_arrival_date && $existing['payout_arrival_date'] != $payout_arrival_date) {
            $needs_update = true;
        }

        if ($needs_update) {
            $stmt = $mysqli->prepare("UPDATE swca_stripe_transactions SET amount_refunded = ?, net_amount = ?, payout_arrival_date = ? WHERE stripe_charge_id = ?");
            $stmt->bind_param('ddss', $refund_amount, $net_amount, $payout_arrival_date, $charge['id']);
            $stmt->execute();
            $stmt->close();
            $updated_count++;
        } else {
            $duplicate_count++;
        }
    } else {
        // Insert new
        $stmt = $mysqli->prepare("
            INSERT INTO swca_stripe_transactions
            (stripe_charge_id, transaction_type, customer_email, amount, amount_refunded, net_amount, stripe_fee,
             currency, status, description, customer_name, payment_method, receipt_url, stripe_created, payout_arrival_date)
            VALUES (?, 'charge', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $currency = strtolower($charge['currency'] ?? 'usd');
        $status = $charge['status'];
        $description = $charge['description'] ?? '';
        $customer_name = $charge['billing_details']['name'] ?? '';
        $payment_method = $charge['payment_method_details']['type'] ?? '';
        $receipt_url = $charge['receipt_url'] ?? '';
        $stripe_created = date('Y-m-d H:i:s', $charge['created']);

        $stmt->bind_param('ssdddsssssss',
            $charge['id'], $email, $amount, $refund_amount, $net_amount, $stripe_fee,
            $currency, $status, $description, $customer_name, $payment_method, $receipt_url,
            $stripe_created, $payout_arrival_date
        );

        $stmt->execute();
        $stmt->close();
        $new_count++;
    }
}

echo "\n=== SYNC COMPLETE ===\n";
echo "Charges Downloaded: " . count($all_charges) . "\n";
echo "Refunds Downloaded: " . count($all_refunds) . "\n";
echo "New Transactions: $new_count\n";
echo "Updated Transactions: $updated_count\n";
echo "Duplicates Skipped: $duplicate_count\n";

$mysqli->close();
echo "\n✅ Stripe sync successful!\n";
