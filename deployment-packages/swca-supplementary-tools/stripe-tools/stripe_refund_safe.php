<?php
/**
 * Safe Stripe refund processor - allows manual key input for security
 */

// Set WordPress environment
$wp_path = '/var/www/html';
require_once($wp_path . '/wp-config.php');
require_once($wp_path . '/wp-load.php');

echo "=== STRIPE REFUND PROCESSOR ===\n\n";

// For security, ask for API key manually
echo "This script will download recent Stripe transactions and update member totals.\n";
echo "Please provide your Stripe Secret Key (it will not be stored):\n";
echo "Key should start with 'sk_live_' for live mode or 'sk_test_' for test mode.\n\n";

// Read API key from environment variable if set
$api_key = getenv('STRIPE_SECRET_KEY');
if (empty($api_key)) {
    echo "Enter Stripe Secret Key: ";
    $api_key = trim(fgets(STDIN));
}

if (empty($api_key) || (!str_starts_with($api_key, 'sk_live_') && !str_starts_with($api_key, 'sk_test_'))) {
    echo "❌ Invalid or empty Stripe API key. Must start with 'sk_live_' or 'sk_test_'\n";
    exit(1);
}

$is_live = str_starts_with($api_key, 'sk_live_');
echo "\n✅ Using " . ($is_live ? "LIVE" : "TEST") . " Stripe API\n";
echo "✅ API Key: " . substr($api_key, 0, 12) . "...\n\n";

// Function to make Stripe API calls
function stripe_api_call($endpoint, $api_key) {
    $url = "https://api.stripe.com/v1/" . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer " . $api_key,
        "Content-Type: application/x-www-form-urlencoded"
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if (!empty($curl_error)) {
        echo "❌ CURL error: $curl_error\n";
        return false;
    }
    
    if ($http_code !== 200) {
        echo "❌ Stripe API error (HTTP $http_code): $response\n";
        return false;
    }
    
    return json_decode($response, true);
}

// Test API connection first
echo "🔍 Testing Stripe API connection...\n";
$test_call = stripe_api_call("charges?limit=1", $api_key);
if (!$test_call) {
    echo "❌ Failed to connect to Stripe API\n";
    exit(1);
}
echo "✅ Stripe API connection successful\n\n";

// Get date range for transactions
$days_back = 7; // Default to last 7 days
echo "How many days back should we check for transactions? (default: 7): ";
$input = trim(fgets(STDIN));
if (!empty($input) && is_numeric($input)) {
    $days_back = intval($input);
}

$start_date = time() - ($days_back * 24 * 60 * 60);
echo "📅 Checking transactions from " . date('Y-m-d', $start_date) . " to " . date('Y-m-d') . "\n\n";

// Download recent charges
echo "📥 Downloading charges...\n";
$charges_endpoint = "charges?limit=100&created[gte]=$start_date&expand[]=data.customer";
$charges_data = stripe_api_call($charges_endpoint, $api_key);

if (!$charges_data) {
    exit(1);
}

echo "✅ Found " . count($charges_data['data']) . " charges\n";

// Download recent refunds
echo "📥 Downloading refunds...\n";
$refunds_endpoint = "refunds?limit=100&created[gte]=$start_date";
$refunds_data = stripe_api_call($refunds_endpoint, $api_key);

if (!$refunds_data) {
    exit(1);
}

echo "✅ Found " . count($refunds_data['data']) . " refunds\n\n";

// Process the data
global $wpdb;

$member_adjustments = array();
$charge_lookup = array();

// First pass: collect all charges
echo "=== ANALYZING CHARGES ===\n";
foreach ($charges_data['data'] as $charge) {
    $charge_id = $charge['id'];
    $amount = $charge['amount'] / 100; // Convert from cents
    $customer_email = $charge['billing_details']['email'] ?? '';
    $customer_name = $charge['billing_details']['name'] ?? '';
    $description = $charge['description'] ?? '';
    $created = date('Y-m-d H:i:s', $charge['created']);
    $status = $charge['status'];
    
    // Store charge info for refund lookup
    $charge_lookup[$charge_id] = array(
        'email' => $customer_email,
        'name' => $customer_name,
        'amount' => $amount,
        'description' => $description
    );
    
    if (!empty($customer_email) && $status === 'succeeded') {
        if (!isset($member_adjustments[$customer_email])) {
            $member_adjustments[$customer_email] = array(
                'name' => $customer_name,
                'charges' => 0,
                'refunds' => 0,
                'net_amount' => 0,
                'transactions' => array()
            );
        }
        $member_adjustments[$customer_email]['charges'] += $amount;
        $member_adjustments[$customer_email]['net_amount'] += $amount;
        $member_adjustments[$customer_email]['transactions'][] = array(
            'type' => 'charge',
            'id' => $charge_id,
            'amount' => $amount,
            'date' => $created,
            'description' => $description
        );
    }
    
    echo "Charge: $charge_id | \$$amount | $customer_email | $status | $description\n";
}

echo "\n=== ANALYZING REFUNDS ===\n";
foreach ($refunds_data['data'] as $refund) {
    $refund_id = $refund['id'];
    $amount = $refund['amount'] / 100; // Convert from cents
    $charge_id = $refund['charge'];
    $created = date('Y-m-d H:i:s', $refund['created']);
    $status = $refund['status'];
    $reason = $refund['reason'] ?? 'requested_by_customer';
    
    echo "Refund: $refund_id | \$$amount | Charge: $charge_id | $status | Reason: $reason\n";
    
    // Look up original charge info
    if (isset($charge_lookup[$charge_id])) {
        $charge_info = $charge_lookup[$charge_id];
        $customer_email = $charge_info['email'];
        $customer_name = $charge_info['name'];
        
        if (!empty($customer_email) && $status === 'succeeded') {
            if (!isset($member_adjustments[$customer_email])) {
                $member_adjustments[$customer_email] = array(
                    'name' => $customer_name,
                    'charges' => 0,
                    'refunds' => 0,
                    'net_amount' => 0,
                    'transactions' => array()
                );
            }
            $member_adjustments[$customer_email]['refunds'] += $amount;
            $member_adjustments[$customer_email]['net_amount'] -= $amount;
            $member_adjustments[$customer_email]['transactions'][] = array(
                'type' => 'refund',
                'id' => $refund_id,
                'amount' => -$amount,
                'date' => $created,
                'reason' => $reason,
                'original_charge' => $charge_id
            );
            
            echo "  → Refunded to: $customer_email ($customer_name)\n";
        }
    } else {
        echo "  ⚠️  Could not find original charge $charge_id for refund\n";
    }
}

echo "\n=== MEMBER IMPACT ANALYSIS ===\n";
$members_with_refunds = 0;
$total_refund_amount = 0;

foreach ($member_adjustments as $email => $adjustment) {
    if ($adjustment['refunds'] > 0) {
        $members_with_refunds++;
        $total_refund_amount += $adjustment['refunds'];
        
        echo "📧 Email: $email\n";
        echo "   Name: {$adjustment['name']}\n";
        echo "   Charges: \${$adjustment['charges']}\n";
        echo "   Refunds: \${$adjustment['refunds']}\n";
        echo "   Net Adjustment: \${$adjustment['net_amount']}\n";
        echo "   Transactions:\n";
        foreach ($adjustment['transactions'] as $txn) {
            echo "     - {$txn['type']}: \${$txn['amount']} on {$txn['date']}\n";
        }
        echo "\n";
    }
}

echo "Summary: $members_with_refunds members received refunds totaling \$$total_refund_amount\n\n";

if ($members_with_refunds == 0) {
    echo "✅ No refunds found in the specified date range. No database updates needed.\n";
    exit(0);
}

// Ask for confirmation before updating database
echo "=== DATABASE UPDATE CONFIRMATION ===\n";
echo "This will update member total amounts in the database.\n";
echo "Members affected: $members_with_refunds\n";
echo "Total refunds to apply: \$$total_refund_amount\n\n";
echo "Proceed with database updates? (y/N): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) !== 'y' && strtolower($confirm) !== 'yes') {
    echo "❌ Database update cancelled by user.\n";
    exit(0);
}

echo "\n=== UPDATING DATABASE ===\n";
$updated_members = 0;
$update_errors = 0;

foreach ($member_adjustments as $email => $adjustment) {
    if (abs($adjustment['net_amount']) < 0.01) {
        continue; // Skip if net amount is essentially zero
    }
    
    // Find member by email (check all email fields)
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM wp_swca_members WHERE email_1 = %s OR email_2 = %s OR email_3 = %s OR email_4 = %s",
        $email, $email, $email, $email
    ));
    
    if ($member) {
        $current_total = floatval($member->total_amount);
        $new_total = max(0, $current_total + $adjustment['net_amount']); // Don't allow negative totals
        
        $result = $wpdb->update(
            'wp_swca_members',
            array('total_amount' => $new_total),
            array('id' => $member->id)
        );
        
        if ($result !== false) {
            $updated_members++;
            echo "✅ Updated: {$member->first_name} {$member->last_name} ($email)\n";
            echo "   \${$current_total} → \${$new_total} (adjustment: \${$adjustment['net_amount']})\n";
        } else {
            $update_errors++;
            echo "❌ Error updating: {$member->first_name} {$member->last_name} ($email)\n";
        }
    } else {
        echo "⚠️  Member not found for email: $email ({$adjustment['name']})\n";
        echo "   Would adjust by: \${$adjustment['net_amount']}\n";
    }
}

echo "\n=== FINAL SUMMARY ===\n";
echo "✅ Members successfully updated: $updated_members\n";
echo "❌ Update errors: $update_errors\n";
echo "💰 Total refunds processed: \$$total_refund_amount\n";

// Show updated database statistics
$stats = $wpdb->get_row("
    SELECT 
        COUNT(*) as total_members,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_donation
    FROM wp_swca_members
    WHERE total_amount > 0
");

echo "\n📊 Updated Database Statistics:\n";
echo "Total Members: {$stats->total_members}\n";
echo "Total Revenue: \$" . number_format($stats->total_revenue, 2) . "\n";
echo "Average Donation: \$" . number_format($stats->avg_donation, 2) . "\n";

echo "\n🎉 Refund processing complete!\n";
?>