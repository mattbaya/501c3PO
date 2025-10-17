<?php
/**
 * Re-sync Stripe for July-August 2025
 * To fetch the 4 missing payout transactions
 */

// Days back from Oct 16, 2025 to July 1, 2025 = ~108 days
$days_back = 120; // Extra buffer to ensure we get everything

// Passphrase
$passphrase = 'POBox432';

// Database connection
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  RE-SYNCING STRIPE FOR JULY-AUGUST 2025\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Get organization settings (MUST use swca_options for multisite!)
$result = $mysqli->query("SELECT option_value FROM swca_options WHERE option_name = 'five01c3po_organization_settings'");

if (!$result || $result->num_rows == 0) {
    echo "ERROR: Could not find organization settings in swca_options table.\n";
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
echo "Syncing last $days_back days (July 1 - Oct 16, 2025)...\n\n";

// Calculate start timestamp
$start_timestamp = time() - ($days_back * 24 * 60 * 60);
echo "Start date: " . date('Y-m-d', $start_timestamp) . "\n\n";

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

// Download balance transactions (includes payout data)
echo "Downloading balance transactions...\n";
$all_balance_txns = array();
$has_more = true;
$starting_after = null;

while ($has_more) {
    $endpoint = "balance_transactions?limit=100&created[gte]=$start_timestamp&expand[]=data.source";
    if ($starting_after) {
        $endpoint .= "&starting_after=$starting_after";
    }

    $balance_data = call_stripe_api($endpoint, $api_key);

    if (!$balance_data || !isset($balance_data['data'])) {
        break;
    }

    $all_balance_txns = array_merge($all_balance_txns, $balance_data['data']);
    $has_more = $balance_data['has_more'] ?? false;

    if ($has_more && !empty($balance_data['data'])) {
        $starting_after = end($balance_data['data'])['id'];
    } else {
        $has_more = false;
    }

    echo "  Downloaded " . count($all_balance_txns) . " balance transactions...\r";
}

echo "\n✓ Downloaded " . count($all_balance_txns) . " balance transactions\n\n";

// Process and store balance transactions
$balance_table = 'swca_c3_stripe_balance_transactions';

$new_count = 0;
$updated_count = 0;
$duplicate_count = 0;
$payout_count = 0;

foreach ($all_balance_txns as $bal_txn) {
    $amount = $bal_txn['amount'] / 100;
    $fee = ($bal_txn['fee'] ?? 0) / 100;
    $net = $bal_txn['net'] / 100;

    // Extract payout ID
    $payout_id = null;
    if (isset($bal_txn['payout'])) {
        $payout_id = is_string($bal_txn['payout']) ? $bal_txn['payout'] : ($bal_txn['payout']['id'] ?? null);
    }

    // Extract source information
    $source_id = null;
    $source_type = null;
    if (isset($bal_txn['source'])) {
        if (is_string($bal_txn['source'])) {
            $source_id = $bal_txn['source'];
        } elseif (is_array($bal_txn['source'])) {
            $source_id = $bal_txn['source']['id'] ?? null;
            $source_type = $bal_txn['source']['object'] ?? null;
        }
    }

    // Check if this is a payout transaction
    if ($bal_txn['type'] === 'payout') {
        $payout_count++;
    }

    // Check if exists
    $stmt = $mysqli->prepare("SELECT id, payout_id FROM $balance_table WHERE balance_txn_id = ?");
    $stmt->bind_param('s', $bal_txn['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Update if payout_id changed
        if ($payout_id && $existing['payout_id'] != $payout_id) {
            $stmt = $mysqli->prepare("UPDATE $balance_table SET payout_id = ?, synced_at = NOW() WHERE balance_txn_id = ?");
            $stmt->bind_param('ss', $payout_id, $bal_txn['id']);
            $stmt->execute();
            $stmt->close();
            $updated_count++;
        } else {
            $duplicate_count++;
        }
    } else {
        // Insert new
        $stmt = $mysqli->prepare("
            INSERT INTO $balance_table
            (balance_txn_id, txn_type, source_id, source_type, amount, fee, net, currency, status, description, available_on, created_at, payout_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $currency = strtolower($bal_txn['currency'] ?? 'usd');
        $status = $bal_txn['status'] ?? '';
        $description = $bal_txn['description'] ?? '';
        $available_on = isset($bal_txn['available_on']) ? date('Y-m-d', $bal_txn['available_on']) : null;
        $created_at = date('Y-m-d H:i:s', $bal_txn['created']);

        $stmt->bind_param('ssssdddssssss',
            $bal_txn['id'],
            $bal_txn['type'],
            $source_id,
            $source_type,
            $amount,
            $fee,
            $net,
            $currency,
            $status,
            $description,
            $available_on,
            $created_at,
            $payout_id
        );

        $stmt->execute();
        $stmt->close();
        $new_count++;
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  SYNC RESULTS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Balance Transactions Downloaded: " . count($all_balance_txns) . "\n";
echo "Payout Transactions: $payout_count\n";
echo "New Records: $new_count\n";
echo "Updated Records: $updated_count\n";
echo "Duplicates Skipped: $duplicate_count\n\n";

// Check if the 4 missing payouts are now in the database
echo "Checking for the 4 missing payout transactions:\n\n";

$missing_payouts = [
    'txn_1S0BJFJHWUaRCmpE',
    'txn_1Rxe4AJHWUaRCmpE',
    'txn_1Rq2HkJHWUaRCmpE',
    'txn_1RpfZFJHWUaRCmpE'
];

$found_count = 0;
foreach ($missing_payouts as $txn_id) {
    $check = $mysqli->query("
        SELECT balance_txn_id, available_on, net
        FROM $balance_table
        WHERE balance_txn_id = '$txn_id'
    ");

    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        echo "  ✓ FOUND: {$txn_id} (${row['net']} on {$row['available_on']})\n";
        $found_count++;
    } else {
        echo "  ✗ MISSING: {$txn_id}\n";
    }
}

echo "\n";

if ($found_count == 4) {
    echo "🎉 SUCCESS! All 4 missing payout transactions are now in the database!\n";
    echo "Next step: Re-run matching algorithm to achieve 100% matching.\n";
} else {
    echo "⚠️  Only found $found_count of 4 missing payouts.\n";
    echo "May need to sync a longer date range or check Stripe Dashboard.\n";
}

$mysqli->close();
?>
