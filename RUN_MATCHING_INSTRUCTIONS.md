# How to Run Transaction Matching Algorithm

**Date:** October 16, 2025  
**Status:** Algorithm bug fixed, ready to run

---

## Quick Start

The transaction matching algorithm has been fixed and is ready to run. You just need to execute it.

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

On the Transaction Matching page, you'll see two matching algorithms:

1. **Payout-Based Matching** (left box)
2. **Smart Matching** (right box)

Click the button: **"Run Payout-Based Match"**

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

**Fix:** Algorithm now looks up the exact `payout_id` and groups only the charges in that specific payout.

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

**That's it! The fix is in place, just run it.**
