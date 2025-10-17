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
        'details' => array(),
        'debug' => array()
    );

    $matches_table = $wpdb->prefix . 'c3_transaction_matches';
    $stripe_table = $wpdb->prefix . 'c3_stripe_transactions';
    $bank_table = 'wp_swca_bank_transactions'; // Using actual table with data
    $gf_table = 'swca_gf_addon_payment_transaction';

    // Debug: Check table counts
    $stripe_count = $wpdb->get_var("SELECT COUNT(*) FROM $stripe_table");
    $gf_count = $wpdb->get_var("SELECT COUNT(*) FROM $gf_table WHERE transaction_type = 'payment'");
    $bank_count = $wpdb->get_var("SELECT COUNT(*) FROM $bank_table");
    $bank_credit_count = $wpdb->get_var("SELECT COUNT(*) FROM $bank_table WHERE credit > 0");

    $results['debug'][] = "Stripe transactions: $stripe_count";
    $results['debug'][] = "Gravity Forms payments: $gf_count";
    $results['debug'][] = "Bank transactions total: $bank_count";
    $results['debug'][] = "Bank transactions with credit > 0: $bank_credit_count";

    // 1. Match Gravity Forms → Stripe (by amount and timestamp)
    // Gravity Forms should have exact timestamps matching Stripe
    $gravity_txns = $wpdb->get_results("
        SELECT * FROM $gf_table
        WHERE transaction_type = 'payment'
        AND id NOT IN (SELECT gravity_form_transaction_id FROM $matches_table WHERE gravity_form_transaction_id IS NOT NULL)
        ORDER BY date_created DESC
    ");

    $results['debug'][] = "Unmatched Gravity Forms transactions: " . count($gravity_txns);

    // Sample first few GF and Stripe for debugging
    if (count($gravity_txns) > 0) {
        $sample_gf = array_slice($gravity_txns, 0, 3);
        foreach ($sample_gf as $idx => $gf) {
            $results['debug'][] = sprintf("Sample GF #%d: ID=%d, Amount=$%.2f, Date=%s",
                $idx + 1, $gf->id, floatval($gf->amount), $gf->date_created);
        }
    }

    $sample_stripe = $wpdb->get_results("SELECT * FROM $stripe_table ORDER BY stripe_created DESC LIMIT 3");
    foreach ($sample_stripe as $idx => $st) {
        $results['debug'][] = sprintf("Sample Stripe #%d: ID=%d, Amount=$%.2f, Date=%s",
            $idx + 1, $st->id, floatval($st->amount), $st->stripe_created);
    }

    foreach ($gravity_txns as $gf_txn) {
        // Look for Stripe transaction with matching amount within 30 seconds
        $gf_amount = floatval($gf_txn->amount);
        $gf_datetime = $gf_txn->date_created;

        // Search for Stripe charge within 30 seconds and matching amount
        // Fixed: Use TIMESTAMPDIFF instead of UNIX_TIMESTAMP for better accuracy
        $stripe_match = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $stripe_table
            WHERE amount = %f
            AND ABS(TIMESTAMPDIFF(SECOND, stripe_created, %s)) <= 30
            AND id NOT IN (SELECT stripe_transaction_id FROM $matches_table WHERE stripe_transaction_id IS NOT NULL)
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, stripe_created, %s)) ASC
            LIMIT 1
        ", $gf_amount, $gf_datetime, $gf_datetime));

        if ($stripe_match) {
            $time_diff = abs(strtotime($stripe_match->stripe_created) - strtotime($gf_datetime));

            // DUPLICATE PREVENTION: Check if this exact match already exists
            if (!$dry_run) {
                $existing_match = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $matches_table
                     WHERE stripe_transaction_id = %d
                     AND gravity_form_transaction_id = %d
                     AND match_type = 'gravity_stripe'",
                    $stripe_match->id,
                    $gf_txn->id
                ));

                if ($existing_match) {
                    $results['debug'][] = sprintf(
                        "⏭ Skipped duplicate: GF #%d → Stripe #%d (Match #%d already exists)",
                        $gf_txn->id,
                        $stripe_match->id,
                        $existing_match
                    );
                    continue; // Skip this match, it already exists
                }
            }

            $match_data = array(
                'stripe_transaction_id' => $stripe_match->id,
                'gravity_form_transaction_id' => $gf_txn->id,
                'match_type' => 'gravity_stripe',
                'match_confidence' => 'auto_high',
                'notes' => sprintf(
                    'Auto-matched: Amount $%.2f, Time diff: %d seconds',
                    $gf_amount,
                    $time_diff
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
                substr($gf_datetime, 0, 10)
            );
        }
    }

    // 2. Match Bank → Stripe Payouts (using payout_arrival_date)
    // Group Stripe transactions by payout date and match to bank deposits
    // ONLY match deposits with "STRIPE" in description (filter out cash deposits, checks, etc.)
    $bank_txns = $wpdb->get_results("
        SELECT * FROM $bank_table
        WHERE credit > 0
        AND description LIKE '%STRIPE%'
        AND id NOT IN (SELECT bank_transaction_id FROM $matches_table WHERE bank_transaction_id IS NOT NULL)
        ORDER BY post_date DESC
    ");

    $results['debug'][] = "Unmatched bank transactions: " . count($bank_txns);

    // Debug: Show sample bank dates
    if (count($bank_txns) > 0) {
        $sample_bank = array_slice($bank_txns, 0, 3);
        foreach ($sample_bank as $idx => $b) {
            $results['debug'][] = sprintf("Sample Bank #%d: ID=%d, Amount=$%.2f, Date=%s, Desc=%s",
                $idx + 1, $b->id, floatval($b->credit), $b->post_date, substr($b->description, 0, 30));
        }
    }

    // Debug: Check payout data
    $payout_count = $wpdb->get_var("SELECT COUNT(*) FROM $stripe_table WHERE payout_arrival_date IS NOT NULL");
    $payout_id_count = $wpdb->get_var("SELECT COUNT(*) FROM $stripe_table WHERE payout_id IS NOT NULL");
    $results['debug'][] = "Stripe transactions with payout dates: $payout_count";
    $results['debug'][] = "Stripe transactions with payout IDs: $payout_id_count";

    if ($payout_id_count == 0 && $payout_count > 0) {
        $results['debug'][] = "⚠️ WARNING: Payout IDs are missing! Using fallback date-based matching.";
    }

    if ($payout_count > 0) {
        $sample_payouts = $wpdb->get_results("
            SELECT id, stripe_created, amount, payout_arrival_date
            FROM $stripe_table
            WHERE payout_arrival_date IS NOT NULL
            ORDER BY payout_arrival_date DESC
            LIMIT 3
        ");
        foreach ($sample_payouts as $idx => $p) {
            $results['debug'][] = sprintf("Sample Payout #%d: ID=%d, Amount=$%.2f, Charge Date=%s, Payout Date=%s",
                $idx + 1, $p->id, floatval($p->amount), substr($p->stripe_created, 0, 10), $p->payout_arrival_date);
        }
    } else {
        $results['debug'][] = "⚠️ WARNING: No Stripe transactions have payout_arrival_date! Payout-based matching cannot work.";
        $results['debug'][] = "Run Stripe sync again to populate payout dates.";
    }

    $balance_table = $wpdb->prefix . 'c3_stripe_balance_transactions';
    $balance_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$balance_table'") === $balance_table;

    $results['debug'][] = "\n=== STARTING PAYOUT-BASED BANK MATCHING ===";
    if ($balance_table_exists) {
        $results['debug'][] = "✓ Using EXACT payout amounts from Stripe balance transactions";
    } else {
        $results['debug'][] = "⚠️ Falling back to charge date grouping (balance transactions table not found)";
    }
    $results['debug'][] = "About to process " . count($bank_txns) . " unmatched bank transactions...";

    $bank_processed = 0;
    foreach ($bank_txns as $bank_txn) {
        $bank_amount = floatval($bank_txn->credit);
        $bank_date = $bank_txn->post_date;
        $bank_processed++;

        // PAYOUT-BASED MATCHING
        // Match bank deposits to actual Stripe payout transactions
        $payout_groups = array();

        if ($balance_table_exists) {
            // Match to actual payout transactions from balance_transactions
            $payout_groups = $wpdb->get_results($wpdb->prepare("
                SELECT
                    balance_txn_id as payout_id,
                    available_on as payout_arrival_date,
                    ABS(net) as payout_net_total,
                    description,
                    balance_txn_id
                FROM $balance_table
                WHERE txn_type = 'payout'
                AND DATE(available_on) BETWEEN DATE_SUB(%s, INTERVAL 7 DAY) AND DATE_ADD(%s, INTERVAL 2 DAY)
                AND ABS(net) > 0
                ORDER BY ABS(DATEDIFF(DATE(available_on), %s)) ASC,
                         ABS(ABS(net) - %f) ASC
            ", $bank_date, $bank_date, $bank_date, $bank_amount));
        }

        // Fallback: Group charges by payout date if no balance transactions table
        if (empty($payout_groups)) {
            $payout_groups = $wpdb->get_results($wpdb->prepare("
                SELECT
                    COALESCE(payout_id, CONCAT('date_', DATE(payout_arrival_date))) as payout_id,
                    DATE(payout_arrival_date) as payout_arrival_date,
                    COUNT(*) as txn_count,
                    SUM(net_amount) as payout_net_total,
                    SUM(stripe_fee) as payout_fees_total,
                    GROUP_CONCAT(id) as stripe_ids
                FROM $stripe_table
                WHERE DATE(payout_arrival_date) BETWEEN DATE_SUB(%s, INTERVAL 7 DAY) AND DATE_ADD(%s, INTERVAL 2 DAY)
                AND net_amount > 0
                AND id NOT IN (
                    SELECT stripe_transaction_id
                    FROM $matches_table
                    WHERE stripe_transaction_id IS NOT NULL
                    AND bank_transaction_id = %d
                )
                GROUP BY COALESCE(payout_id, DATE(payout_arrival_date))
                HAVING payout_net_total > 0
                ORDER BY ABS(DATEDIFF(DATE(payout_arrival_date), %s)) ASC
            ", $bank_date, $bank_date, $bank_txn->id, $bank_date));
        }

        // Debug first 3 bank transactions in detail
        if ($bank_processed <= 3) {
            $results['debug'][] = sprintf("\n--- Analyzing Bank #%d: $%.2f on %s ---", $bank_txn->id, $bank_amount, $bank_date);

            // Calculate date range
            $range_start = date('Y-m-d', strtotime($bank_date . ' -7 days'));
            $range_end = date('Y-m-d', strtotime($bank_date . ' +2 days'));

            // Check ALL Stripe transactions in date range (IGNORING match status for debug)
            // First, simple direct query without subquery
            $all_in_range = $wpdb->get_results($wpdb->prepare("
                SELECT
                    id,
                    stripe_created,
                    amount,
                    net_amount,
                    stripe_fee,
                    payout_arrival_date,
                    DATE(payout_arrival_date) as payout_date_only
                FROM $stripe_table
                WHERE DATE(payout_arrival_date) >= %s
                AND DATE(payout_arrival_date) <= %s
                AND net_amount > 0
                ORDER BY payout_arrival_date, id
            ", $range_start, $range_end));

            $results['debug'][] = sprintf("SQL: payout_arrival_date >= '%s' AND <= '%s'", $range_start, $range_end);

            $results['debug'][] = sprintf("Found %d Stripe charges in date range (%s to %s)",
                count($all_in_range),
                date('Y-m-d', strtotime($bank_date . ' -7 days')),
                date('Y-m-d', strtotime($bank_date . ' +2 days'))
            );

            if (count($all_in_range) > 0) {
                foreach ($all_in_range as $s) {
                    $results['debug'][] = sprintf(
                        "  Stripe #%d: Gross=$%.2f, Net=$%.2f, Fee=$%.2f, Payout=%s",
                        $s->id,
                        floatval($s->amount),
                        floatval($s->net_amount),
                        floatval($s->stripe_fee),
                        $s->payout_arrival_date
                    );
                }
            } else {
                // If no results, check if ANY transactions have payout dates
                $total_with_payouts = $wpdb->get_var("SELECT COUNT(*) FROM $stripe_table WHERE payout_arrival_date IS NOT NULL");
                $results['debug'][] = "  ⚠️ No charges found. Total Stripe txns with payout dates: $total_with_payouts";

                // DIAGNOSTIC: Check exact value of Stripe #2 and #3's payout dates AND net_amount
                $diagnostic = $wpdb->get_results("SELECT id, amount, amount_refunded, stripe_fee, net_amount, payout_arrival_date FROM $stripe_table WHERE id IN (2,3) ORDER BY id");
                foreach ($diagnostic as $d) {
                    $results['debug'][] = sprintf("  DIAGNOSTIC Stripe #%d: Gross=$%.2f, Refunded=$%.2f, Fee=$%.2f, Net=$%.2f, Payout=%s",
                        $d->id,
                        floatval($d->amount),
                        floatval($d->amount_refunded),
                        floatval($d->stripe_fee),
                        floatval($d->net_amount),
                        $d->payout_arrival_date
                    );
                }

                // Check if they're in the exclusion list
                $excluded_check = $wpdb->get_results($wpdb->prepare("
                    SELECT stripe_transaction_id, bank_transaction_id
                    FROM $matches_table
                    WHERE stripe_transaction_id IN (2,3)
                    AND bank_transaction_id IS NOT NULL
                    AND bank_transaction_id != %d
                ", $bank_txn->id));
                if (count($excluded_check) > 0) {
                    foreach ($excluded_check as $ex) {
                        $results['debug'][] = sprintf("  Stripe #%d is excluded (matched to Bank #%d)",
                            $ex->stripe_transaction_id, $ex->bank_transaction_id);
                    }
                }

                // Check if charges exist in range but are already matched
                $already_matched = $wpdb->get_results($wpdb->prepare("
                    SELECT
                        s.id,
                        s.amount,
                        s.net_amount,
                        s.payout_arrival_date as payout_date_raw,
                        DATE(s.payout_arrival_date) as payout_date,
                        GROUP_CONCAT(m.bank_transaction_id) as matched_banks
                    FROM $stripe_table s
                    LEFT JOIN $matches_table m ON s.id = m.stripe_transaction_id AND m.bank_transaction_id IS NOT NULL
                    WHERE s.payout_arrival_date >= %s
                    AND s.payout_arrival_date <= %s
                    AND s.net_amount > 0
                    GROUP BY s.id
                ", $range_start . ' 00:00:00', $range_end . ' 23:59:59'));

                if (count($already_matched) > 0) {
                    $results['debug'][] = "  Found " . count($already_matched) . " charges in range BUT:";
                    foreach ($already_matched as $am) {
                        $status = $am->matched_banks ? "matched to Bank #" . $am->matched_banks : "NOT matched";
                        $results['debug'][] = sprintf("    Stripe #%d: Net=$%.2f, Payout=%s - %s",
                            $am->id, floatval($am->net_amount), $am->payout_date, $status);
                    }
                }
            }

            $results['debug'][] = sprintf("Found %d payout groups (%s)",
                count($payout_groups),
                $using_payout_txns ? 'using actual payout transactions' : 'using charge date grouping'
            );

            foreach ($payout_groups as $pg) {
                $payout_total = floatval($pg->payout_net_total);
                $amount_diff = abs($payout_total - $bank_amount);
                $days_diff = abs((strtotime($pg->payout_arrival_date) - strtotime($bank_date)) / 86400);
                $tolerance = $using_payout_txns ? 0.50 : max(10.00, $bank_amount * 0.05);

                if ($using_payout_txns) {
                    $results['debug'][] = sprintf(
                        "  Payout %s (%s): Exact payout = $%.2f | Diff: $%.2f | Days: %.1f | Tolerance: $%.2f | %s",
                        substr($pg->balance_txn_id, 0, 20),
                        $pg->payout_arrival_date,
                        $payout_total,
                        $amount_diff,
                        $days_diff,
                        $tolerance,
                        ($amount_diff <= $tolerance) ? '✓ EXACT Match!' : '✗ Too large'
                    );
                } else {
                    $results['debug'][] = sprintf(
                        "  Payout %s (%s): %d charges = $%.2f | Diff: $%.2f | Days: %.1f | Tolerance: $%.2f | %s",
                        $pg->payout_id,
                        $pg->payout_arrival_date,
                        $pg->txn_count ?? 0,
                        $payout_total,
                        $amount_diff,
                        $days_diff,
                        $tolerance,
                        ($amount_diff <= $tolerance) ? '✓ Match!' : '✗ Too large'
                    );
                }
            }
        }

        $best_match = null;
        $best_diff = PHP_FLOAT_MAX;
        $using_payout_txns = $balance_table_exists && !empty($payout_groups) && isset($payout_groups[0]->balance_txn_id);

        // Find best matching payout group
        foreach ($payout_groups as $payout_group) {
            $payout_total = floatval($payout_group->payout_net_total);
            $amount_diff = abs($payout_total - $bank_amount);

            // Tight tolerance for actual payout transactions, looser for charge grouping
            $tolerance = $using_payout_txns ? 0.50 : max(10.00, $bank_amount * 0.05);

            // Consider match if within tolerance
            if ($amount_diff <= $tolerance && $amount_diff < $best_diff) {
                $best_match = $payout_group;
                $best_diff = $amount_diff;
            }
        }

        if ($best_match) {
            $days_diff = abs((strtotime($best_match->payout_arrival_date) - strtotime($bank_date)));
            $days_diff_rounded = round($days_diff / 86400);

            // Determine confidence
            $confidence = 'auto_high';
            if ($best_diff > 0.10 || $days_diff_rounded > 1) {
                $confidence = 'auto_medium';
            }

            // Get Stripe charge IDs for this payout
            $stripe_ids = array();
            $txn_count = 0;

            if ($using_payout_txns) {
                // Look up charges by actual payout ID (not just date!)
                // First, get the actual payout_id from balance transactions
                $payout_lookup = $wpdb->get_row($wpdb->prepare(
                    "SELECT source_id as actual_payout_id
                     FROM $balance_table
                     WHERE balance_txn_id = %s
                     AND txn_type = 'payout'
                     LIMIT 1",
                    $best_match->balance_txn_id
                ));

                if ($payout_lookup && $payout_lookup->actual_payout_id) {
                    // Match by exact payout ID
                    $charges = $wpdb->get_results($wpdb->prepare(
                        "SELECT id FROM $stripe_table
                         WHERE payout_id = %s
                         AND net_amount > 0
                         ORDER BY id",
                        $payout_lookup->actual_payout_id
                    ));
                } else {
                    // Fallback: use payout arrival date (less accurate)
                    $payout_date = $best_match->payout_arrival_date;
                    $charges = $wpdb->get_results($wpdb->prepare(
                        "SELECT id FROM $stripe_table
                         WHERE DATE(payout_arrival_date) = %s
                         AND net_amount > 0
                         ORDER BY id",
                        $payout_date
                    ));
                }
                $stripe_ids = array_map(function($c) { return $c->id; }, $charges);
                $txn_count = count($stripe_ids);
            } else {
                // Using charge grouping fallback
                $stripe_ids = explode(',', $best_match->stripe_ids);
                $txn_count = intval($best_match->txn_count);
            }

            // Create match for each Stripe transaction in the payout
            if (!$dry_run && !empty($stripe_ids)) {
                foreach ($stripe_ids as $idx => $stripe_id) {
                    $match_type = ($idx === 0) ? 'bank_stripe_payout' : 'bank_stripe_payout_part';

                    // DUPLICATE PREVENTION: Check if this exact match already exists
                    $existing_match = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $matches_table
                         WHERE stripe_transaction_id = %d
                         AND bank_transaction_id = %d
                         AND match_type = %s",
                        intval($stripe_id),
                        $bank_txn->id,
                        $match_type
                    ));

                    if ($existing_match) {
                        $results['debug'][] = sprintf(
                            "⏭ Skipped duplicate: Stripe #%d → Bank #%d (Match #%d already exists)",
                            $stripe_id,
                            $bank_txn->id,
                            $existing_match
                        );
                        continue; // Skip this match, it already exists
                    }

                    $match_data = array(
                        'stripe_transaction_id' => intval($stripe_id),
                        'bank_transaction_id' => $bank_txn->id,
                        'match_type' => $match_type,
                        'match_confidence' => $confidence,
                        'notes' => sprintf(
                            'Payout %s: %d charges = $%.2f → Bank $%.2f on %s (diff: $%.2f, %d days)',
                            $best_match->payout_id,
                            $txn_count,
                            floatval($best_match->payout_net_total),
                            $bank_amount,
                            $bank_date,
                            $best_diff,
                            $days_diff_rounded
                        ),
                        'matched_by' => get_current_user_id()
                    );
                    $wpdb->insert($matches_table, $match_data);
                }
            }

            if ($confidence === 'auto_high') {
                $results['bank_stripe_matches_high']++;
            } else {
                $results['bank_stripe_matches_medium']++;
            }

            $results['details'][] = sprintf(
                "✓ Payout %s: Bank #%d ($%.2f on %s) → %d Stripe charges ($%.2f) [%s confidence]",
                $best_match->payout_id,
                $bank_txn->id,
                $bank_amount,
                $bank_date,
                $txn_count,
                floatval($best_match->payout_net_total),
                $confidence
            );
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

    // Smart match removed - payout-based matching is the recommended approach

    // Handle manual match
    if (isset($_POST['save_manual_match'])) {
        check_admin_referer('five01c3po_manual_match');

        $stripe_id = intval($_POST['stripe_id'] ?? 0);
        $gravity_id = intval($_POST['gravity_id'] ?? 0);
        $bank_id = intval($_POST['bank_id'] ?? 0);
        $notes = sanitize_textarea_field($_POST['match_notes'] ?? '');

        if ($stripe_id || $gravity_id || $bank_id) {
            $matches_table = $wpdb->prefix . 'c3_transaction_matches';
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
    $matches_table = $wpdb->prefix . 'c3_transaction_matches';
    $stripe_table = $wpdb->prefix . 'c3_stripe_transactions';
    $bank_table = 'wp_swca_bank_transactions'; // Using actual table with data
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

        <?php if ($total_stripe == 0): ?>
            <div class="notice notice-warning">
                <h3>⚠️ No Stripe Data Found</h3>
                <p>The Stripe transactions table is empty. You need to sync Stripe data before matching can work.</p>
                <p><strong>Next Steps:</strong></p>
                <ol>
                    <li>Go to <a href="<?php echo admin_url('admin.php?page=501c3PO-stripe-sync'); ?>"><strong>💳 Stripe Sync</strong></a></li>
                    <li>Enter your officer passphrase or Stripe API key</li>
                    <li>Set <strong>Days to Sync: 3650</strong> (for complete historical data)</li>
                    <li>Click "Sync Transactions Now"</li>
                    <li>Return here and run "Auto-Match"</li>
                </ol>
            </div>
        <?php endif; ?>

        <?php if ($match_results): ?>
            <div class="notice notice-success">
                <h3>Auto-Matching Complete!</h3>
                <ul>
                    <li><strong>Gravity Forms → Stripe:</strong> <?php echo $match_results['gravity_stripe_matches']; ?> matches</li>
                    <li><strong>Bank → Stripe (High Confidence):</strong> <?php echo $match_results['bank_stripe_matches_high']; ?> matches</li>
                    <li><strong>Bank → Stripe (Medium Confidence):</strong> <?php echo $match_results['bank_stripe_matches_medium']; ?> matches</li>
                </ul>
                <?php if (!empty($match_results['debug'])): ?>
                    <details open>
                        <summary><strong>🐛 Debug Information</strong></summary>
                        <pre style="max-height: 300px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-left: 4px solid #2271b1;"><?php echo esc_html(implode("\n", $match_results['debug'])); ?></pre>
                    </details>
                <?php endif; ?>
                <?php if (!empty($match_results['details'])): ?>
                    <details>
                        <summary>View Match Details (<?php echo count($match_results['details']); ?> matches)</summary>
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

            <div style="border: 2px solid #2271b1; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="margin-top: 0;">How Matching Works</h3>
                <ul style="margin-left: 20px; font-size: 14px; line-height: 1.6;">
                    <li><strong>Gravity Forms → Stripe:</strong> Matches by exact amount + timestamp (within 30 seconds)</li>
                    <li><strong>Bank → Stripe Payouts:</strong> Uses <u>exact payout amounts</u> from Stripe API balance transactions</li>
                    <li><strong>Payout Identification:</strong> Matches each Stripe payout transaction to bank deposits by payout ID</li>
                    <li><strong>Amount Tolerance:</strong> $0.50 (for minor rounding or bank wire fees)</li>
                    <li><strong>Date Window:</strong> -7 days before / +2 days after bank deposit date</li>
                    <li><strong>Bank Filter:</strong> Only matches deposits with "STRIPE" in description (ignores cash/check deposits)</li>
                </ul>
                <form method="post" style="margin-top: 15px;">
                    <?php wp_nonce_field('five01c3po_auto_match'); ?>
                    <?php submit_button('Run Auto-Match Algorithm', 'primary large', 'run_auto_match', false); ?>
                </form>
            </div>

            <p style="background: #e7f5ff; padding: 15px; border-left: 4px solid #2271b1;">
                <strong>✓ Safe to run multiple times:</strong> The algorithm only creates matches for unmatched transactions and prevents duplicates.
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
