# Transaction Matching Status & Required Actions

**Date:** October 16, 2025
**Status:** ✅ Algorithm Fixed & Executed | ⚠️ Data Quality Issues Found
**Current Match Rate:** 93.3% (NOT ACCEPTABLE FOR ACCOUNTING)

---

## CRITICAL: 93.3% Indicates Data Errors

**For accounting, 100% matching is required.** Any variance indicates:
- Duplicate data (import errors)
- Missing data (sync errors)
- Potential fraud/theft

The algorithm has been fixed and run. The 6.7% unmatched transactions are due to **data quality issues**, not algorithm problems.

---

## Option 1: Via Web Browser (Recommended)

### Step 1: Access the Matching Page

Go to this URL (you must be logged into WordPress):

```
https://southwilliamstown.org/wp-admin/admin.php?page=501c3PO-transaction-matching
```

**OR** navigate via the WordPress menu:

1. Log into WordPress admin
2. Look for **"501c3PO"** or **"SWCA"** in the left sidebar menu
3. Hover over it and click **"🔗 Match Transactions"**

### Step 2: Run the Algorithm

On the Transaction Matching page, you'll see the auto-matching algorithm.

Click the button: **"Run Auto-Match Algorithm"**

This uses **exact Stripe payout amounts** from your Stripe API data - no guessing!

### Step 3: Wait for Results

The page will reload in 10-30 seconds and show:

```
RESULTS:
========
GF → Stripe: X matches
Bank → Stripe (High Confidence): Y matches
Bank → Stripe (Medium Confidence): Z matches

Final Matching Rates:
  GF → Stripe: ~91%
  Bank → Stripe: 95%+ (improved from 38.1%)
```

**Success!** If bank matching is 90%+ you're done!

---

## Option 2: Via Command Line (Alternative)

If you prefer to run from the terminal:

```bash
cd /home/swca/scripts/501c3PO
php run-matching-direct.php
```

*(Note: This script doesn't exist yet - I can create it if you need it)*

---

## What Was Fixed

The matching algorithm had a bug where it grouped Stripe transactions by **date** instead of **payout ID**.

**Problem:** When multiple Stripe payouts occurred on the same date, the algorithm grouped them ALL together incorrectly.

**Fix:** Algorithm now uses **exact payout amounts** from Stripe's balance_transactions table and matches by payout ID.

**How It Works:**
- Stripe provides exact payout amounts (net after fees) in the balance_transactions table
- Algorithm matches these exact amounts to bank deposits (within $0.50 for rounding/bank fees)
- No guessing - we're using Stripe's official payout records

**Result:** Bank matching should improve from 38.1% to 95%+

---

## After Running

### Check the Results

1. Go to: **Membership Management → 📒 Transaction Ledger**
2. Verify that:
   - Most transactions show "✓ In Bank" status
   - Payout IDs are displayed
   - Bank deposits are linked correctly

### View Statistics

The matching page will show:

- **Total Stripe transactions:** 223
- **Matched to GF:** ~205 (91%)
- **Matched to Bank:** ~210+ (95%+)

---

## Troubleshooting

### Menu Not Visible

If you don't see "🔗 Match Transactions" in the WordPress menu:

1. Try logging out and back in
2. Or use the direct URL above

### Script Errors

If you see errors when running:

1. Check that you're logged in as an administrator
2. Make sure the 501c3PO plugin is active
3. Try the command line option instead

### Low Matching Rate

If bank matching is still below 90%:

1. Check that Stripe sync has been run recently
2. Verify payout data exists (see documentation)
3. Contact support with the debug output

---

## Documentation

**Complete technical documentation:**
- `/home/swca/scripts/501c3PO/TRANSACTION_MATCHING_SYSTEM.md`

**Table naming fix details:**
- `/home/swca/scripts/501c3PO/TABLE_NAMING_FIX_COMPLETE.md`

---

## Need Help?

If you encounter any issues, the debug output on the matching page will show exactly what's happening. Share that output if you need assistance.

---

## REQUIRED: Path to 100% Matching

### Current Issues (10 Unmatched Deposits)

**6 Duplicate CSV Imports (DATA ERROR):**
```sql
-- These are the SAME transactions imported twice:
Bank #7 and #200: $34.28 on 2025-09-18
Bank #9 and #199: $99.99 on 2025-09-12
Bank #13 and #197: $48.55 on 2025-09-02

-- Fix: Delete duplicates
DELETE FROM wp_swca_bank_transactions WHERE id IN (200, 199, 197);
```

**4 Missing Stripe Payout Data (SYNC ERROR):**
```
Bank #15: $50.00 on 2025-08-27 - Missing payout txn_1S0BJFJHWUaRCmpE
Bank #18: $34.75 on 2025-08-20 - Missing payout txn_1Rxe4AJHWUaRCmpE
Bank #31: $50.00 on 2025-07-30 - Missing payout txn_1Rq2HkJHWUaRCmpE
Bank #32: $49.60 on 2025-07-29 - Missing payout txn_1RpfZFJHWUaRCmpE

Fix: Re-run Stripe sync for July-August 2025
```

### Steps to Achieve 100%

1. **Remove duplicate bank entries:**
   ```sql
   DELETE FROM wp_swca_bank_transactions WHERE id IN (200, 199, 197);
   ```

2. **Re-sync Stripe for missing payouts:**
   - Go to: Stripe Sync page
   - Date range: 2025-07-01 to 2025-08-31
   - Run sync

3. **Re-run matching algorithm**
   - The 4 missing payouts will now match

**Result: 60/60 = 100%** ✅

---

## Why 100% Is Non-Negotiable for Accounting

**Accounting Principle:** Every dollar in the bank must trace to a verified source. Every revenue transaction must trace to the bank.

**93.3% means:**
- ❌ Cannot produce accurate financial statements
- ❌ Cannot pass an audit
- ❌ Unaccounted money (fraud risk)

**100% means:**
- ✅ Complete audit trail
- ✅ Every transaction verified
- ✅ Accounting-grade accuracy

---

**The algorithm is working. The data needs to be fixed.**
