<?php
/**
 * Transaction Matching Feature
 * Auto-match and manually review matches between Stripe, Gravity Forms, and Bank transactions
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Transaction Matching menu
 */
add_action('admin_menu', 'five01c3po_add_transaction_matching_menu', 24);

function five01c3po_add_transaction_matching_menu() {
    add_submenu_page(
        'membership-management',
        'Match Transactions',
        '🔗 Match Transactions',
        'manage_options',
        '501c3PO-transaction-matching',
        'five01c3po_transaction_matching_page'
    );
}

/**
 * Run auto-matching for all unmatched transactions
 */
function five01c3po_auto_match_transactions($dry_run = false) {
    global $wpdb;

    $results = array(
        'gravity_stripe_matches' => 0,
        'bank_stripe_matches_high' => 0,
        'bank_stripe_matches_medium' => 0,
        'bank_stripe_matches_low' => 0,
        'details' => array()
    );

    $matches_table = $wpdb->prefix . 'transaction_matches';
    $stripe_table = $wpdb->prefix . 'stripe_transactions';
    $bank_table = $wpdb->prefix . 'swca_bank_transactions';
    $gf_table = 'swca_gf_addon_payment_transaction';

    // 1. Match Gravity Forms → Stripe (by amount and timestamp)
    // Gravity Forms should have exact timestamps matching Stripe
    $gravity_txns = $wpdb->get_results("
        SELECT * FROM $gf_table
        WHERE transaction_type = 'payment'
        AND id NOT IN (SELECT gravity_form_transaction_id FROM $matches_table WHERE gravity_form_transaction_id IS NOT NULL)
        ORDER BY date_created DESC
    ");

    foreach ($gravity_txns as $gf_txn) {
        // Look for Stripe transaction with matching amount within 60 seconds
        $gf_amount = floatval($gf_txn->amount);
        $gf_timestamp = strtotime($gf_txn->date_created);

        // Search for Stripe charge within 60 seconds and matching amount
        $stripe_match = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $stripe_table
            WHERE amount = %f
            AND ABS(UNIX_TIMESTAMP(stripe_created) - %d) <= 60
            AND id NOT IN (SELECT stripe_transaction_id FROM $matches_table WHERE stripe_transaction_id IS NOT NULL)
            ORDER BY ABS(UNIX_TIMESTAMP(stripe_created) - %d) ASC
            LIMIT 1
        ", $gf_amount, $gf_timestamp, $gf_timestamp));

        if ($stripe_match) {
            $match_data = array(
                'stripe_transaction_id' => $stripe_match->id,
                'gravity_form_transaction_id' => $gf_txn->id,
                'match_type' => 'gravity_stripe',
                'match_confidence' => 'auto_high',
                'notes' => sprintf(
                    'Auto-matched: Amount $%.2f, Time diff: %d seconds',
                    $gf_amount,
                    abs(UNIX_TIMESTAMP($stripe_match->stripe_created) - $gf_timestamp)
                ),
                'matched_by' => get_current_user_id()
            );

            if (!$dry_run) {
                $wpdb->insert($matches_table, $match_data);
            }

            $results['gravity_stripe_matches']++;
            $results['details'][] = sprintf(
                "✓ Matched GF #%d → Stripe #%d ($%.2f on %s)",
                $gf_txn->id,
                $stripe_match->id,
                $gf_amount,
                date('Y-m-d', $gf_timestamp)
            );
        }
    }

    // 2. Match Bank → Stripe (harder - by net amount and date range)
    // Bank transactions can be delayed and combined
    $bank_txns = $wpdb->get_results("
        SELECT * FROM $bank_table
        WHERE credit > 0
        AND id NOT IN (SELECT bank_transaction_id FROM $matches_table WHERE bank_transaction_id IS NOT NULL)
        ORDER BY post_date DESC
    ");

    foreach ($bank_txns as $bank_txn) {
        $bank_amount = floatval($bank_txn->credit);
        $bank_date = strtotime($bank_txn->post_date);

        // Strategy 1: Look for exact net amount match within 7 days BEFORE bank date
        // (Stripe transactions happen first, bank deposit comes later)
        $stripe_match = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $stripe_table
            WHERE net_amount = %f
            AND stripe_created >= DATE_SUB(%s, INTERVAL 7 DAY)
            AND stripe_created <= %s
            AND id NOT IN (SELECT stripe_transaction_id FROM $matches_table WHERE stripe_transaction_id IS NOT NULL)
            ORDER BY ABS(DATEDIFF(stripe_created, %s)) ASC
            LIMIT 1
        ", $bank_amount, $bank_txn->post_date, $bank_txn->post_date, $bank_txn->post_date));

        if ($stripe_match) {
            $days_diff = abs((strtotime($stripe_match->stripe_created) - $bank_date) / 86400);
            $confidence = 'auto_high';

            if ($days_diff > 3) {
                $confidence = 'auto_medium';
            }

            $match_data = array(
                'stripe_transaction_id' => $stripe_match->id,
                'bank_transaction_id' => $bank_txn->id,
                'match_type' => 'bank_stripe_exact',
                'match_confidence' => $confidence,
                'notes' => sprintf(
                    'Auto-matched: Net amount $%.2f, %d days apart',
                    $bank_amount,
                    round($days_diff)
                ),
                'matched_by' => get_current_user_id()
            );

            if (!$dry_run) {
                $wpdb->insert($matches_table, $match_data);
            }

            if ($confidence === 'auto_high') {
                $results['bank_stripe_matches_high']++;
            } else {
                $results['bank_stripe_matches_medium']++;
            }

            $results['details'][] = sprintf(
                "✓ Matched Bank #%d → Stripe #%d ($%.2f, %d days apart) [%s confidence]",
                $bank_txn->id,
                $stripe_match->id,
                $bank_amount,
                round($days_diff),
                $confidence
            );
            continue;
        }

        // Strategy 2: Look for combined transactions (bank deposit = sum of multiple Stripe)
        // Find multiple Stripe transactions that sum to bank amount within date range
        $potential_matches = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $stripe_table
            WHERE stripe_created >= DATE_SUB(%s, INTERVAL 7 DAY)
            AND stripe_created <= %s
            AND id NOT IN (SELECT stripe_transaction_id FROM $matches_table WHERE stripe_transaction_id IS NOT NULL)
            AND net_amount <= %f
            ORDER BY stripe_created DESC
        ", $bank_txn->post_date, $bank_txn->post_date, $bank_amount));

        // Try to find combination that sums to bank amount (within $0.50)
        if (count($potential_matches) >= 2 && count($potential_matches) <= 10) {
            $combination = five01c3po_find_sum_combination($potential_matches, $bank_amount, 0.50);

            if ($combination && count($combination) >= 2) {
                $total = array_sum(array_column($combination, 'net_amount'));
                $days_diff = abs((strtotime($combination[0]->stripe_created) - $bank_date) / 86400);

                $match_data = array(
                    'bank_transaction_id' => $bank_txn->id,
                    'match_type' => 'bank_stripe_combined',
                    'match_confidence' => 'auto_medium',
                    'notes' => sprintf(
                        'Auto-matched: Combined %d Stripe transactions totaling $%.2f (Bank: $%.2f, diff: $%.2f)',
                        count($combination),
                        $total,
                        $bank_amount,
                        abs($total - $bank_amount)
                    ),
                    'matched_by' => get_current_user_id()
                );

                if (!$dry_run) {
                    // Insert main match record
                    $wpdb->insert($matches_table, $match_data);
                    $match_id = $wpdb->insert_id;

                    // Link each Stripe transaction to this match
                    foreach ($combination as $stripe_txn) {
                        $wpdb->insert($matches_table, array(
                            'stripe_transaction_id' => $stripe_txn->id,
                            'bank_transaction_id' => $bank_txn->id,
                            'match_type' => 'bank_stripe_combined_part',
                            'match_confidence' => 'auto_medium',
                            'notes' => "Part of combined match #$match_id",
                            'matched_by' => get_current_user_id()
                        ));
                    }
                }

                $results['bank_stripe_matches_medium']++;
                $results['details'][] = sprintf(
                    "✓ Matched Bank #%d → %d Stripe transactions ($%.2f combined)",
                    $bank_txn->id,
                    count($combination),
                    $total
                );
            }
        }
    }

    return $results;
}

/**
 * Find combination of transactions that sum to target amount (within tolerance)
 */
function five01c3po_find_sum_combination($transactions, $target, $tolerance = 0.50) {
    $n = count($transactions);

    // Try combinations of 2-5 transactions
    for ($size = 2; $size <= min(5, $n); $size++) {
        $combinations = five01c3po_get_combinations($transactions, $size);

        foreach ($combinations as $combo) {
            $sum = array_sum(array_column($combo, 'net_amount'));
            if (abs($sum - $target) <= $tolerance) {
                return $combo;
            }
        }
    }

    return null;
}

/**
 * Get all combinations of size k from array
 */
function five01c3po_get_combinations($array, $k) {
    $n = count($array);
    if ($k > $n || $k <= 0) return array();
    if ($k == 1) return array_map(function($item) { return array($item); }, $array);

    $result = array();

    // Simple recursive combination generator (limit to prevent timeout)
    $indices = array_keys($array);
    $combinations_count = 0;
    $max_combinations = 1000; // Safety limit

    // Generate combinations using bit mask for simplicity
    $total = pow(2, $n);
    for ($i = 0; $i < $total && $combinations_count < $max_combinations; $i++) {
        $combo = array();
        for ($j = 0; $j < $n; $j++) {
            if ($i & (1 << $j)) {
                $combo[] = $array[$j];
            }
        }
        if (count($combo) == $k) {
            $result[] = $combo;
            $combinations_count++;
        }
    }

    return $result;
}

/**
 * Transaction Matching admin page
 */
function five01c3po_transaction_matching_page() {
    global $wpdb;

    // Handle auto-match
    $match_results = null;
    if (isset($_POST['run_auto_match'])) {
        check_admin_referer('five01c3po_auto_match');
        $match_results = five01c3po_auto_match_transactions(false);
    }

    // Handle manual match
    if (isset($_POST['save_manual_match'])) {
        check_admin_referer('five01c3po_manual_match');

        $stripe_id = intval($_POST['stripe_id'] ?? 0);
        $gravity_id = intval($_POST['gravity_id'] ?? 0);
        $bank_id = intval($_POST['bank_id'] ?? 0);
        $notes = sanitize_textarea_field($_POST['match_notes'] ?? '');

        if ($stripe_id || $gravity_id || $bank_id) {
            $matches_table = $wpdb->prefix . 'transaction_matches';
            $wpdb->insert($matches_table, array(
                'stripe_transaction_id' => $stripe_id ?: null,
                'gravity_form_transaction_id' => $gravity_id ?: null,
                'bank_transaction_id' => $bank_id ?: null,
                'match_type' => 'manual',
                'match_confidence' => 'manual',
                'notes' => $notes,
                'matched_by' => get_current_user_id()
            ));

            echo '<div class="notice notice-success"><p>✓ Manual match saved</p></div>';
        }
    }

    // Get match statistics
    $matches_table = $wpdb->prefix . 'transaction_matches';
    $stripe_table = $wpdb->prefix . 'stripe_transactions';
    $bank_table = $wpdb->prefix . 'swca_bank_transactions';
    $gf_table = 'swca_gf_addon_payment_transaction';

    $total_stripe = $wpdb->get_var("SELECT COUNT(*) FROM $stripe_table");
    $total_bank = $wpdb->get_var("SELECT COUNT(*) FROM $bank_table WHERE credit > 0");
    $total_gf = $wpdb->get_var("SELECT COUNT(*) FROM $gf_table WHERE transaction_type = 'payment'");

    $matched_stripe = $wpdb->get_var("SELECT COUNT(DISTINCT stripe_transaction_id) FROM $matches_table WHERE stripe_transaction_id IS NOT NULL");
    $matched_bank = $wpdb->get_var("SELECT COUNT(DISTINCT bank_transaction_id) FROM $matches_table WHERE bank_transaction_id IS NOT NULL");
    $matched_gf = $wpdb->get_var("SELECT COUNT(DISTINCT gravity_form_transaction_id) FROM $matches_table WHERE gravity_form_transaction_id IS NOT NULL");

    $unmatched_stripe = $total_stripe - $matched_stripe;
    $unmatched_bank = $total_bank - $matched_bank;
    $unmatched_gf = $total_gf - $matched_gf;

    ?>
    <div class="wrap">
        <h1>🔗 Transaction Matching</h1>

        <?php if ($match_results): ?>
            <div class="notice notice-success">
                <h3>Auto-Matching Complete!</h3>
                <ul>
                    <li><strong>Gravity Forms → Stripe:</strong> <?php echo $match_results['gravity_stripe_matches']; ?> matches</li>
                    <li><strong>Bank → Stripe (High Confidence):</strong> <?php echo $match_results['bank_stripe_matches_high']; ?> matches</li>
                    <li><strong>Bank → Stripe (Medium Confidence):</strong> <?php echo $match_results['bank_stripe_matches_medium']; ?> matches</li>
                </ul>
                <?php if (!empty($match_results['details'])): ?>
                    <details>
                        <summary>View Details (<?php echo count($match_results['details']); ?> matches)</summary>
                        <pre style="max-height: 400px; overflow-y: auto;"><?php echo esc_html(implode("\n", $match_results['details'])); ?></pre>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>📊 Matching Statistics</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Total Transactions</th>
                        <th>Matched</th>
                        <th>Unmatched</th>
                        <th>Match Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>💳 Stripe API</strong></td>
                        <td><?php echo number_format($total_stripe); ?></td>
                        <td style="color: #00a32a;"><?php echo number_format($matched_stripe); ?></td>
                        <td style="color: #d63638;"><?php echo number_format($unmatched_stripe); ?></td>
                        <td><?php echo $total_stripe > 0 ? number_format(($matched_stripe / $total_stripe) * 100, 1) : 0; ?>%</td>
                    </tr>
                    <tr>
                        <td><strong>📝 Gravity Forms</strong></td>
                        <td><?php echo number_format($total_gf); ?></td>
                        <td style="color: #00a32a;"><?php echo number_format($matched_gf); ?></td>
                        <td style="color: #d63638;"><?php echo number_format($unmatched_gf); ?></td>
                        <td><?php echo $total_gf > 0 ? number_format(($matched_gf / $total_gf) * 100, 1) : 0; ?>%</td>
                    </tr>
                    <tr>
                        <td><strong>🏦 Bank Transactions</strong></td>
                        <td><?php echo number_format($total_bank); ?></td>
                        <td style="color: #00a32a;"><?php echo number_format($matched_bank); ?></td>
                        <td style="color: #d63638;"><?php echo number_format($unmatched_bank); ?></td>
                        <td><?php echo $total_bank > 0 ? number_format(($matched_bank / $total_bank) * 100, 1) : 0; ?>%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>🤖 Auto-Match Transactions</h2>
            <p>Run automated matching to link related transactions across systems:</p>
            <ul style="margin-left: 20px;">
                <li><strong>Gravity Forms → Stripe:</strong> Matches by exact amount and timestamp (within 60 seconds)</li>
                <li><strong>Bank → Stripe:</strong> Matches by net amount and date range (within 7 days)</li>
                <li><strong>Combined Deposits:</strong> Detects when multiple Stripe transactions are combined into one bank deposit</li>
            </ul>

            <form method="post">
                <?php wp_nonce_field('five01c3po_auto_match'); ?>
                <?php submit_button('Run Auto-Match', 'primary', 'run_auto_match'); ?>
            </form>

            <p style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">
                <strong>💡 Tip:</strong> Auto-matching is safe and can be run multiple times.
                It only creates matches for unmatched transactions and uses confidence scoring.
                Review the results and use manual matching below for any uncertain matches.
            </p>
        </div>

        <div class="card">
            <h2>👁️ Review Unmatched Transactions</h2>
            <p><a href="<?php echo admin_url('admin.php?page=501c3PO-transaction-review'); ?>" class="button button-secondary">
                Open Review Interface →
            </a></p>
        </div>
    </div>
    <?php
}

/**
 * Add Review Interface menu
 */
add_action('admin_menu', 'five01c3po_add_transaction_review_menu', 25);

function five01c3po_add_transaction_review_menu() {
    add_submenu_page(
        null, // Hidden from menu, accessed via link
        'Review Transactions',
        'Review Transactions',
        'manage_options',
        '501c3PO-transaction-review',
        'five01c3po_transaction_review_page'
    );
}

/**
 * Review interface page
 */
function five01c3po_transaction_review_page() {
    global $wpdb;

    $matches_table = $wpdb->prefix . 'transaction_matches';
    $stripe_table = $wpdb->prefix . 'stripe_transactions';
    $bank_table = $wpdb->prefix . 'swca_bank_transactions';
    $gf_table = 'swca_gf_addon_payment_transaction';

    // Get unmatched Stripe transactions
    $unmatched_stripe = $wpdb->get_results("
        SELECT * FROM $stripe_table
        WHERE id NOT IN (SELECT stripe_transaction_id FROM $matches_table WHERE stripe_transaction_id IS NOT NULL)
        ORDER BY stripe_created DESC
        LIMIT 50
    ");

    // Get unmatched Gravity Forms transactions
    $unmatched_gf = $wpdb->get_results("
        SELECT * FROM $gf_table
        WHERE transaction_type = 'payment'
        AND id NOT IN (SELECT gravity_form_transaction_id FROM $matches_table WHERE gravity_form_transaction_id IS NOT NULL)
        ORDER BY date_created DESC
        LIMIT 50
    ");

    // Get unmatched Bank transactions
    $unmatched_bank = $wpdb->get_results("
        SELECT * FROM $bank_table
        WHERE credit > 0
        AND id NOT IN (SELECT bank_transaction_id FROM $matches_table WHERE bank_transaction_id IS NOT NULL)
        ORDER BY post_date DESC
        LIMIT 50
    ");

    ?>
    <div class="wrap">
        <h1>👁️ Review Unmatched Transactions</h1>
        <p><a href="<?php echo admin_url('admin.php?page=501c3PO-transaction-matching'); ?>">&larr; Back to Matching</a></p>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px;">

            <!-- Unmatched Stripe -->
            <div class="card">
                <h3>💳 Unmatched Stripe (<?php echo count($unmatched_stripe); ?>)</h3>
                <div style="max-height: 600px; overflow-y: auto;">
                    <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unmatched_stripe as $txn): ?>
                            <tr>
                                <td><?php echo $txn->id; ?></td>
                                <td><?php echo date('m/d/y', strtotime($txn->stripe_created)); ?></td>
                                <td>$<?php echo number_format($txn->amount, 2); ?></td>
                                <td style="font-size: 10px;"><?php echo esc_html(substr($txn->customer_email, 0, 20)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Unmatched Gravity Forms -->
            <div class="card">
                <h3>📝 Unmatched Gravity Forms (<?php echo count($unmatched_gf); ?>)</h3>
                <div style="max-height: 600px; overflow-y: auto;">
                    <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unmatched_gf as $txn): ?>
                            <tr>
                                <td><?php echo $txn->id; ?></td>
                                <td><?php echo date('m/d/y', strtotime($txn->date_created)); ?></td>
                                <td>$<?php echo number_format($txn->amount, 2); ?></td>
                                <td style="font-size: 10px;"><?php echo esc_html(substr($txn->note, 0, 20)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Unmatched Bank -->
            <div class="card">
                <h3>🏦 Unmatched Bank (<?php echo count($unmatched_bank); ?>)</h3>
                <div style="max-height: 600px; overflow-y: auto;">
                    <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Credit</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unmatched_bank as $txn): ?>
                            <tr>
                                <td><?php echo $txn->id; ?></td>
                                <td><?php echo date('m/d/y', strtotime($txn->post_date)); ?></td>
                                <td>$<?php echo number_format($txn->credit, 2); ?></td>
                                <td style="font-size: 10px;"><?php echo esc_html(substr($txn->description, 0, 20)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>➕ Create Manual Match</h2>
            <form method="post" action="<?php echo admin_url('admin.php?page=501c3PO-transaction-matching'); ?>">
                <?php wp_nonce_field('five01c3po_manual_match'); ?>
                <table class="form-table">
                    <tr>
                        <th>Stripe Transaction ID</th>
                        <td><input type="number" name="stripe_id" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Gravity Form Transaction ID</th>
                        <td><input type="number" name="gravity_id" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Bank Transaction ID</th>
                        <td><input type="number" name="bank_id" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Notes</th>
                        <td><textarea name="match_notes" class="large-text" rows="3" placeholder="Why did you match these transactions?"></textarea></td>
                    </tr>
                </table>
                <?php submit_button('Save Manual Match', 'primary', 'save_manual_match'); ?>
            </form>
        </div>
    </div>

    <style>
        .card h3 {
            margin-top: 0;
            padding: 10px;
            background: #f9f9f9;
            border-bottom: 1px solid #ddd;
        }
    </style>
    <?php
}
