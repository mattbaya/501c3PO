# Transaction Matching Algorithm

## Overview

The 501c3PO plugin automatically matches transactions across three data sources:
1. **Stripe API Transactions** - Direct API synced charges
2. **Gravity Forms Payments** - Historical Stripe payments via forms
3. **Bank Transactions** - Imported bank CSV files

This document details the matching logic, confidence levels, and SQL queries used.

---

## Data Sources

### 1. Stripe API Transactions (`swca_stripe_transactions`)
- **Source**: Synced directly from Stripe API
- **Records**: 221 transactions (as of Oct 2025)
- **Key Fields**:
  - `stripe_charge_id` - Unique Stripe charge ID
  - `stripe_created` - Transaction timestamp from Stripe
  - `amount` - Gross amount charged
  - `net_amount` - Amount after refunds (0 if fully refunded)
  - `stripe_fee` - Stripe processing fee
  - `customer_email` - Customer email from Stripe

### 2. Gravity Forms Payments (`swca_gf_addon_payment_transaction`)
- **Source**: Historical Stripe payments processed via Gravity Forms
- **Records**: 204 payments, 5 refunds
- **Key Fields**:
  - `transaction_id` - Stripe charge ID from webhook
  - `date_created` - Timestamp when GF recorded the payment
  - `amount` - Payment amount
  - `transaction_type` - 'payment' or 'refund'

### 3. Bank Transactions (`wp_swca_bank_transactions`)
- **Source**: Imported CSV files from bank
- **Records**: 89 transactions (credits only)
- **Key Fields**:
  - `post_date` - Date transaction posted to bank
  - `credit` - Deposit amount
  - `description` - Bank transaction description
  - **Pattern**: `"ACH Credit TRANSFER STRIPE ID4270465600 ID: ST-XXXXXX"`

---

## Matching Patterns

### Pattern 1: Gravity Forms → Stripe (Exact Match)

**Confidence**: `auto_high` (100% match rate achieved)

**Logic**: Match by exact amount AND close timestamp

```sql
SELECT * FROM swca_stripe_transactions
WHERE amount = {gf_amount}
AND ABS(TIMESTAMPDIFF(SECOND, stripe_created, {gf_date_created})) <= 30
ORDER BY ABS(TIMESTAMPDIFF(SECOND, stripe_created, {gf_date_created})) ASC
LIMIT 1
```

**How It Works**:
1. Get all unmatched Gravity Forms payments
2. For each GF payment, search Stripe for:
   - Exact amount match (e.g., $36.35)
   - Within 30 seconds of GF timestamp
3. Pick closest match by time difference

**Observed Pattern**:
- ✅ GF timestamp is always 3-6 seconds AFTER Stripe
- ✅ This is because Gravity Forms records after receiving Stripe's webhook
- ✅ 100% match rate on test set (50/50 matched)

**Example**:
```
GF #209:  2025-09-21 05:10:28 | $5.00
Stripe #1: 2025-09-21 05:10:24 | $5.00
→ Match! (4 seconds apart)
```

**Why TIMESTAMPDIFF vs UNIX_TIMESTAMP**:
- ❌ Old code: `ABS(UNIX_TIMESTAMP(stripe_created) - UNIX_TIMESTAMP(gf_date)) <= 30`
- ✅ New code: `ABS(TIMESTAMPDIFF(SECOND, stripe_created, gf_date)) <= 30`
- **Reason**: UNIX_TIMESTAMP was failing to find matches (returned 0 results), TIMESTAMPDIFF works correctly

---

### Pattern 2: Bank → Stripe Payouts (Batch Match)

**Confidence**: `auto_high` or `auto_medium`

**Logic**: Bank deposits are BATCH PAYOUTS containing multiple Stripe charges

**Key Insight**:
- Bank deposits are NOT individual charges
- They are periodic payouts from Stripe containing sum of multiple charges
- Payout ID pattern in description: `ST-XXXXXX`

**Matching Strategy**:
1. Identify Stripe payout deposits by description containing "STRIPE"
2. For each bank deposit:
   - Find all Stripe charges between previous and current payout date
   - Sum the `net_amount` of all charges
   - Match if sum equals bank deposit (within $0.50)

**Example**:
```
Bank #24: 2025-08-13 | $127.37 | "ACH Credit TRANSFER STRIPE ID1800948598 ID: ST-U5U7S6R7V1Z1"

Stripe charges (Aug 8-11):
  #12: $5.44
  #13: $20.88
  #14: $0.51
  #15: $0.51
  #16: $103.29
  #17: $0.51
  #18: $1.33
  #19: $1.00
  #20: $1.33
  TOTAL: $134.80

Difference: $7.43 (too high, no match)
```

**Confidence Levels**:
- `auto_high`: Difference ≤ $0.05 AND date diff ≤ 3 days
- `auto_medium`: Difference ≤ $0.50 OR date diff 4-7 days

---

### Pattern 3: Refund Detection

**Logic**: Identify refunded Stripe transactions

```sql
SELECT id, stripe_charge_id, amount, net_amount
FROM swca_stripe_transactions
WHERE net_amount = 0 AND amount > 0
```

**How It Works**:
- Original charge: `amount = $51.80, net_amount = $51.80`
- After refund: `amount = $51.80, net_amount = $0.00` ← REFUNDED
- These should NOT match to bank deposits (money was returned)

**Match to Gravity Forms Refunds**:
Gravity Forms also has 5 refund records:
```
GF #208: refund | $51.80 | 2025-09-18
GF #207: refund | $51.80 | 2025-09-18
GF #206: refund | $103.29 | 2025-09-17
GF #205: refund | $36.35 | 2025-09-17
```

These can be matched to the corresponding Stripe charges with `net_amount = 0`.

---

## Match Confidence Levels

### `auto_high`
- Gravity Forms → Stripe: Amount match within 30 seconds
- Bank → Stripe: Net amount match within $0.05, date diff ≤ 3 days
- **Action**: Auto-create match, high confidence

### `auto_medium`
- Bank → Stripe: Net amount match within $0.50, date diff 4-7 days
- Bank → Stripe (combined): Multiple charges sum to bank deposit
- **Action**: Auto-create match, flag for review

### `manual`
- Treasurer manually links transactions
- **Action**: Accept as-is, trust human judgment

---

## Database Schema

### Transaction Matches Table (`swca_transaction_matches`)

```sql
CREATE TABLE swca_transaction_matches (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    stripe_transaction_id mediumint(9),
    gravity_form_transaction_id mediumint(9),
    bank_transaction_id mediumint(9),
    match_type varchar(50) NOT NULL,
    match_confidence varchar(20) NOT NULL,
    notes text,
    matched_by int,
    matched_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stripe (stripe_transaction_id),
    KEY idx_gravity (gravity_form_transaction_id),
    KEY idx_bank (bank_transaction_id),
    KEY idx_confidence (match_confidence)
)
```

**Match Types**:
- `gravity_stripe` - GF → Stripe direct match
- `bank_stripe_exact` - Bank → Single Stripe charge
- `bank_stripe_combined` - Bank → Multiple Stripe charges
- `bank_stripe_payout` - Bank → Stripe payout batch
- `manual` - Manually created by treasurer

---

## SQL Queries Used

### Find Unmatched GF Transactions
```sql
SELECT id, transaction_id, date_created, amount, lead_id
FROM swca_gf_addon_payment_transaction
WHERE transaction_type = 'payment'
AND id NOT IN (
    SELECT gravity_form_transaction_id
    FROM swca_transaction_matches
    WHERE gravity_form_transaction_id IS NOT NULL
)
ORDER BY date_created DESC
```

### Find Matching Stripe Transaction
```sql
SELECT * FROM swca_stripe_transactions
WHERE amount = %f
AND ABS(TIMESTAMPDIFF(SECOND, stripe_created, %s)) <= 30
AND id NOT IN (
    SELECT stripe_transaction_id
    FROM swca_transaction_matches
    WHERE stripe_transaction_id IS NOT NULL
)
ORDER BY ABS(TIMESTAMPDIFF(SECOND, stripe_created, %s)) ASC
LIMIT 1
```

### Find Refunded Stripe Transactions
```sql
SELECT id, stripe_charge_id, amount, net_amount, stripe_fee
FROM swca_stripe_transactions
WHERE net_amount = 0 AND amount > 0
```

### Find Stripe Payout Bank Deposits
```sql
SELECT id, post_date, description, credit
FROM wp_swca_bank_transactions
WHERE credit > 0
AND description LIKE '%STRIPE%'
ORDER BY post_date ASC
```

---

## Troubleshooting

### Issue: GF → Stripe Returns 0 Matches

**Cause**: Using `UNIX_TIMESTAMP()` in SQL query

**Fix**: Use `TIMESTAMPDIFF(SECOND, stripe_created, gf_date)` instead

**Why**: The UNIX_TIMESTAMP approach was failing to match even when dates were very close (3-6 seconds apart). TIMESTAMPDIFF correctly calculates the time difference and finds matches.

### Issue: Bank Transactions Show 0 Records

**Cause**: Table prefix mismatch

**Problem**: Code looks for `swca_swca_bank_transactions` but data is in `wp_swca_bank_transactions`

**Fix**: Hardcode table name as `wp_swca_bank_transactions` (not using dynamic prefix)

### Issue: Missing 'note' Column in GF Table

**Cause**: Query references non-existent column

**Columns That Exist**: id, lead_id, transaction_type, transaction_id, subscription_id, is_recurring, amount, date_created

**Columns That DON'T Exist**: note

**Fix**: Remove `note` from all GF queries

---

## Future Enhancements

### 1. Stripe Payout ID Matching
Extract payout IDs from bank descriptions (`ST-XXXXXX`) and potentially:
- Query Stripe API for payout details
- Match by payout ID instead of date range
- More accurate than summing charges

### 2. Member Association
- Link transactions to members by email
- Show transaction history on member profile
- Generate member-specific financial reports

### 3. Reconciliation Reports
- Monthly reconciliation: Stripe charges vs Bank deposits
- Identify missing deposits
- Flag discrepancies > $1.00

### 4. Automated Refund Matching
- Match GF refund records to Stripe `net_amount = 0` transactions
- Create three-way match: GF payment → Stripe charge → GF refund

---

## Testing & Validation

### Test Results (Oct 5, 2025)

**Gravity Forms → Stripe**:
- Test set: 50 recent GF payments
- Matches found: 50
- Match rate: **100%**
- Time differences: 3-6 seconds
- Confidence: auto_high

**Bank → Stripe**:
- Test set: 20 bank deposits
- Exact matches: 0 (expected - batched payouts)
- Combined matches: Needs further analysis
- Manual matching interface: Available

---

## References

- **Main Plugin File**: `501c3po.php`
- **Matching Feature**: `includes/features/transaction-matching.php`
- **Unified View**: `includes/features/unified-transactions.php`
- **Database Schema**: `includes/core/database.php`
- **Stripe Integration**: `includes/features/stripe-integration.php`

---

**Last Updated**: October 5, 2025
**Algorithm Version**: 1.0
**Match Rate**: 100% (GF → Stripe)
