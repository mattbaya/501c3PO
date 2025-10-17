<?php
/**
 * Run matching algorithm standalone (no WordPress dependency)
 */

// Define WordPress constants to bypass security checks
define('ABSPATH', '/home/swca/public_html/');

// Mock WordPress functions
function add_action($hook, $function, $priority = 10) {
    // Do nothing - we're not running in WordPress context
}

function add_submenu_page() {
    // Do nothing
}

function get_current_user_id() {
    return 1; // Admin user
}

function wp_nonce_field() {
    // Do nothing
}

function check_admin_referer() {
    // Do nothing
}

function submit_button() {
    // Do nothing
}

function admin_url($path) {
    return "http://example.com/wp-admin/$path";
}

function sanitize_textarea_field($text) {
    return strip_tags($text);
}

// Setup database connection
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Mock WordPress $wpdb object
class FakeWpdb {
    public $prefix = 'swca_';
    private $mysqli;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    public function get_results($query) {
        $result = $this->mysqli->query($query);
        if (!$result) return [];
        $rows = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function get_row($query) {
        $result = $this->mysqli->query($query);
        if (!$result) return null;
        return $result->fetch_object();
    }
    
    public function get_var($query) {
        $result = $this->mysqli->query($query);
        if (!$result) return null;
        $row = $result->fetch_row();
        return $row ? $row[0] : null;
    }
    
    public function prepare($query, ...$args) {
        // Simple prepare implementation
        foreach ($args as $arg) {
            if (is_string($arg)) {
                $arg = $this->mysqli->real_escape_string($arg);
                $query = preg_replace('/%s/', "'$arg'", $query, 1);
            } elseif (is_float($arg)) {
                $query = preg_replace('/%f/', $arg, $query, 1);
            } elseif (is_int($arg)) {
                $query = preg_replace('/%d/', $arg, $query, 1);
            }
        }
        return $query;
    }
    
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $values = [];
        foreach ($data as $value) {
            if (is_null($value)) {
                $values[] = 'NULL';
            } elseif (is_string($value)) {
                $values[] = "'" . $this->mysqli->real_escape_string($value) . "'";
            } else {
                $values[] = $value;
            }
        }
        $values = implode(',', $values);
        $query = "INSERT INTO $table ($columns) VALUES ($values)";
        $result = $this->mysqli->query($query);
        if (!$result) {
            echo "❌ INSERT ERROR: " . $this->mysqli->error . "\n";
            echo "Query: $query\n";
        }
        return $result;
    }

    public function query($query) {
        $result = $this->mysqli->query($query);
        if (!$result && $this->mysqli->error) {
            echo "❌ QUERY ERROR: " . $this->mysqli->error . "\n";
        }
        return $result;
    }
}

$wpdb = new FakeWpdb($mysqli);

// Now include the matching function
require_once('/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/transaction-matching.php');

echo "═══════════════════════════════════════════════════════════════\n";
echo "  RUNNING TRANSACTION MATCHING ALGORITHM\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Run the matching algorithm
$results = five01c3po_auto_match_transactions(false);

echo "═══════════════════════════════════════════════════════════════\n";
echo "  RESULTS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "✅ Gravity Forms → Stripe: {$results['gravity_stripe_matches']} matches\n";
echo "✅ Bank → Stripe (High Confidence): {$results['bank_stripe_matches_high']} matches\n";
echo "⚠️  Bank → Stripe (Medium Confidence): {$results['bank_stripe_matches_medium']} matches\n\n";

if (!empty($results['debug'])) {
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  DEBUG INFORMATION (First 50 lines)\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    $debug_lines = array_slice($results['debug'], 0, 50);
    foreach ($debug_lines as $line) {
        echo $line . "\n";
    }
    if (count($results['debug']) > 50) {
        echo "\n... (" . (count($results['debug']) - 50) . " more debug lines)\n";
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  VERIFICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Check final match rates
$total_stripe = $mysqli->query("SELECT COUNT(*) as cnt FROM swca_c3_stripe_transactions")->fetch_assoc()['cnt'];
$matched_stripe_gf = $mysqli->query("SELECT COUNT(DISTINCT stripe_transaction_id) as cnt FROM swca_c3_transaction_matches WHERE gravity_form_transaction_id IS NOT NULL")->fetch_assoc()['cnt'];
$matched_stripe_bank = $mysqli->query("SELECT COUNT(DISTINCT stripe_transaction_id) as cnt FROM swca_c3_transaction_matches WHERE bank_transaction_id IS NOT NULL")->fetch_assoc()['cnt'];

$gf_rate = $total_stripe > 0 ? round(($matched_stripe_gf / $total_stripe) * 100, 1) : 0;
$bank_rate = $total_stripe > 0 ? round(($matched_stripe_bank / $total_stripe) * 100, 1) : 0;

echo "Final Matching Rates:\n";
echo "  • Stripe → GF: {$matched_stripe_gf} / {$total_stripe} ({$gf_rate}%)\n";
echo "  • Stripe → Bank: {$matched_stripe_bank} / {$total_stripe} ({$bank_rate}%)\n\n";

if ($bank_rate >= 90) {
    echo "🎉 SUCCESS! Bank matching is now {$bank_rate}%\n";
} elseif ($bank_rate >= 70) {
    echo "✅ GOOD! Bank matching improved to {$bank_rate}%\n";
} elseif ($bank_rate >= 50) {
    echo "📈 IMPROVED! Bank matching is now {$bank_rate}%\n";
} else {
    echo "⚠️  Bank matching at {$bank_rate}% - needs investigation\n";
}

$mysqli->close();
echo "\n";
?>
