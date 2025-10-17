# Transaction Duplicate Audit Report
**Date:** October 16, 2025
**Status:** Issues Found - Action Required

---

## Executive Summary

✅ **Good News:**
- All source transaction tables are clean (no duplicates)
- Transaction ledger displays correctly (no duplicates shown to users)
- Data integrity is intact

⚠️ **Issues Found:**
1. **38 duplicate match records** in the matches table (from running GF matching 3x)
2. **Bank matching has never run** - 0% of Stripe transactions matched to bank deposits
3. **Import processes lack deduplication checks** (should prevent duplicates on re-import)

---

## Detailed Findings

### 1. Source Transaction Tables ✓

**Stripe Transactions (swca_c3_stripe_transactions):**
- Total records: 223
- Duplicate Stripe IDs: **0** ✓
- Status: **CLEAN**

**Gravity Forms Transactions (swca_gf_addon_payment_transaction):**
- Total payment records: 207
- Duplicate transaction IDs: **0** ✓
- Status: **CLEAN**

**Bank Transactions (wp_swca_bank_transactions):**
- Total deposits: 130
- Stripe deposits: 66 (have "STRIPE" in description)
- Duplicate transactions: **0** ✓
- Status: **CLEAN**

### 2. Transaction Matches Table ⚠️

**Current State:**
- Total match records: 243
- Matches by type:
  - `gravity_stripe`: 205 matches
  - `gf_stripe_txn_id`: 38 matches (OLD TYPE - these are duplicates)
  - **Bank matches: 0** ❌

**Duplicate Analysis:**
- 19 unique Stripe→GF matches exist **3 times each**
- Total duplicate records: **38 extra matches** (should only have 205 unique)
- Cause: Matching algorithm run multiple times without deduplication check

**Affected Match Records:**
```
Stripe #203 → GF #20: Match IDs 255, 234, 186 (3 duplicates)
Stripe #204 → GF #19: Match IDs 187, 254, 233 (3 duplicates)
... and 17 more (see full list in check-all-duplicates.php output)
```

### 3. Transaction Ledger Display ✓

**Status: Working Correctly**
- Uses `GROUP BY` to prevent duplicates from showing
- Users see each transaction only once
- Ledger query handles duplicate match records gracefully

### 4. Matching Statistics

**Gravity Forms → Stripe:**
- Total Stripe charges (net > 0): 215
- Matched to GF: 205 (95.3%)
- Unmatched: 10
- **Target: 100%** (need to investigate 10 unmatched)

**Bank → Stripe:**
- Total Stripe charges: 215
- Matched to Bank: **0 (0%)** ❌
- Unmatched: 215
- **Target: Near 100%** (some non-Stripe bank deposits expected)

**Why Bank Matching is 0%:**
The bank matching algorithm has NEVER been run successfully. The recent bug fix improved the algorithm logic, but it hasn't been executed yet.

---

## Root Causes

### Issue #1: Duplicate Match Records
**Cause:** Matching algorithm doesn't check for existing matches before inserting
- Algorithm was run multiple times (likely during testing/debugging)
- Each run created new match records for the same Stripe→GF pairs
- No `ON DUPLICATE KEY` or `WHERE NOT EXISTS` protection

**Impact:**
- Database bloat (243 records instead of 205)
- Potential confusion in reporting
- Wasted storage

**Solution:**
- Add duplicate prevention to matching algorithm
- Run cleanup script to remove 38 duplicate records

### Issue #2: Bank Matching Never Run
**Cause:** Algorithm bug was fixed but algorithm hasn't been executed since fix
- Fixed algorithm is in place (uses exact payout IDs now)
- User hasn't run it via web interface yet
- OR algorithm failed silently

**Impact:**
- 0% bank matching (should be ~90%+)
- Incomplete money trail
- Can't reconcile Stripe payouts to bank deposits

**Solution:**
- Run the fixed bank matching algorithm
- Verify it creates bank matches
- Target: 90-100% of Stripe deposits matched

### Issue #3: No Import Deduplication
**Cause:** Import processes don't prevent duplicates on re-import
- Stripe sync: No check if transaction already exists
- Bank CSV import: No check if transaction already imported
- GF import: No deduplication

**Impact:**
- Risk of duplicate data if imports are re-run
- Data integrity depends on manual care
- Accounting errors possible

**Solution:**
- Add `ON DUPLICATE KEY UPDATE` to all imports
- Or add `WHERE NOT EXISTS` checks
- Log skipped duplicates for user visibility

---

## Action Plan

### Step 1: Clean Up Duplicate Matches ⏳
**Script:** `cleanup-duplicate-matches.php`

```bash
php cleanup-duplicate-matches.php
```

This will:
- Identify all 19 duplicate match groups
- Keep the oldest match record for each
- Delete 38 duplicate records
- Reduce matches table from 243 → 205 records

**Expected Result:** Clean matches table with no duplicates

### Step 2: Run Bank Matching Algorithm ⏳
**URL:** https://southwilliamstown.org/wp-admin/admin.php?page=501c3PO-transaction-matching

1. Log into WordPress admin
2. Navigate to: 501c3PO → 🔗 Match Transactions
3. Click: **"Run Auto-Match Algorithm"**
4. Wait 30 seconds for completion

**Expected Result:**
- Bank matching: 0% → 90-100%
- ~60-66 bank deposits matched to Stripe payouts
- Some bank deposits intentionally unmatched (cash, checks, interest)

### Step 3: Verify 100% Matching ⏳
**Script:** `analyze-unmatched.php`

```bash
php analyze-unmatched.php
```

This will show:
- Final matching rates for GF and Bank
- List any unmatched transactions
- Explain why each is unmatched (missing data, timing, etc.)

**Target:**
- GF → Stripe: 100% (or document why not)
- Bank → Stripe: 90-100% of Stripe deposits
- All unmatched transactions explained

### Step 4: Add Deduplication to Imports ⏳
**Files to Update:**
- Stripe sync process
- Bank CSV import
- Matching algorithm

Add checks like:
```sql
INSERT INTO table ...
ON DUPLICATE KEY UPDATE ...
```

OR

```sql
INSERT INTO table ...
WHERE NOT EXISTS (
  SELECT 1 FROM table WHERE unique_id = ...
)
```

---

## Why Matching Isn't 100% (Yet)

### Gravity Forms (95.3%)
**10 unmatched Stripe transactions** could be:
- Charges not from Gravity Forms (admin-initiated, recurring, etc.)
- Timestamp mismatch (forms submitted outside 30-second window)
- Missing GF data (form abandoned, webhook failed)
- Test charges

**Action:** Review the 10 unmatched to categorize

### Bank (0%)
**All 215 Stripe transactions unmatched** because:
- Bank matching algorithm has never run successfully
- This is the #1 priority to fix

**Action:** Run the algorithm (Step 2 above)

---

## Accounting Standards Compliance

For proper accounting, we need:

✅ **Unique Transactions:** Each real-world transaction appears once
✅ **Complete Trail:** Customer → Stripe → Bank linkage
✅ **100% Coverage:** Every dollar accounted for (or documented why not)
✅ **Audit Trail:** Know when/why matches were created
✅ **Idempotent Imports:** Re-running imports doesn't create duplicates

**Current Status:**
- ✓ Unique transactions (source data clean)
- ✗ Complete trail (bank matching missing)
- ✗ 100% coverage (10 GF unmatched, 215 bank unmatched)
- ✓ Audit trail (match records have timestamps)
- ✗ Idempotent imports (no deduplication)

---

## Next Steps

1. **Immediate:** Clean up 38 duplicate match records
2. **Immediate:** Run bank matching algorithm
3. **Review:** Investigate 10 unmatched GF→Stripe transactions
4. **Enhancement:** Add deduplication to all import processes
5. **Documentation:** Document expected unmatched transactions (test charges, manual, etc.)

---

## Files Created

- `check-all-duplicates.php` - Comprehensive duplicate detection script
- `cleanup-duplicate-matches.php` - Remove duplicate match records
- `analyze-unmatched.php` - Explain why matching isn't 100%
- `DUPLICATE_AUDIT_REPORT.md` - This report

---

## Conclusion

**Data Integrity:** ✅ Source data is clean and accurate

**Matching Quality:** ⚠️ Needs work
- GF matching: 95.3% (good, but should be 100%)
- Bank matching: 0% (algorithm never run)

**Process Quality:** ⚠️ Needs improvement
- No deduplication on imports
- No duplicate prevention in matching algorithm

**Priority Actions:**
1. Run bank matching → expect 90%+ immediately
2. Investigate 10 unmatched GF transactions
3. Add deduplication to prevent future issues

**Estimated Time to 100%:** 30 minutes
- 5 min: Clean up duplicates
- 10 min: Run bank matching
- 15 min: Review and document unmatched transactions
