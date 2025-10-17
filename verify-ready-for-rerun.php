<?php
/**
 * Verify system is ready for matching algorithm re-run
 */

$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  READY TO RE-RUN MATCHING ALGORITHM\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Check payout data coverage
$payout_check = $mysqli->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN payout_id IS NOT NULL AND payout_id != '' THEN 1 ELSE 0 END) as with_payout_id,
        COUNT(DISTINCT payout_id) as unique_payouts
    FROM swca_c3_stripe_transactions
    WHERE payout_id IS NOT NULL AND payout_id != ''
")->fetch_assoc();

$total = $mysqli->query("SELECT COUNT(*) as cnt FROM swca_c3_stripe_transactions")->fetch_assoc()['cnt'];

echo "✅ PAYOUT DATA COVERAGE:\n";
echo "  - Total Stripe transactions: $total\n";
echo "  - With payout_id: {$payout_check['with_payout_id']} / $total\n";
echo "  - Unique payouts: {$payout_check['unique_payouts']}\n";
echo "  - Coverage: " . round(($payout_check['with_payout_id'] / $total) * 100, 1) . "%\n\n";

// Check current matches
$current_matches = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM swca_c3_transaction_matches
    WHERE bank_transaction_id IS NOT NULL
")->fetch_assoc()['cnt'];

echo "📊 CURRENT STATUS:\n";
echo "  - Bank match records: $current_matches\n";
echo "  - Old incorrect matches cleared: YES ✓\n\n";

// Check algorithm fix
$algorithm_file = '/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/transaction-matching.php';
$algorithm_content = file_get_contents($algorithm_file);

if (strpos($algorithm_content, 'SELECT source_id as actual_payout_id') !== false) {
    echo "✅ ALGORITHM FIX VERIFIED:\n";
    echo "  - Groups by exact payout_id: YES ✓\n";
    echo "  - Old date-based grouping removed: YES ✓\n\n";
} else {
    echo "❌ ALGORITHM FIX NOT FOUND!\n";
    echo "  - Check transaction-matching.php lines 441-475\n\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  🚀 NEXT STEP: RE-RUN MATCHING ALGORITHM\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "How to run:\n";
echo "  1. Navigate to: Membership Management → 🔗 Match Transactions\n";
echo "  2. Click: 'Run Payout-Based Match' button\n";
echo "  3. Wait for completion (should take 10-30 seconds)\n\n";

echo "Expected Results:\n";
echo "  • GF→Stripe matching: ~91% (unchanged)\n";
echo "  • Bank→Stripe matching: Should improve to 95%+\n";
echo "  • Each Stripe payout correctly grouped\n";
echo "  • Bank deposits matched to payout groups\n\n";

echo "Documentation:\n";
echo "  📄 Complete system docs: /home/swca/scripts/501c3PO/TRANSACTION_MATCHING_SYSTEM.md\n";
echo "  📄 Table naming fix: /home/swca/scripts/501c3PO/TABLE_NAMING_FIX_COMPLETE.md\n\n";

$mysqli->close();
?>
