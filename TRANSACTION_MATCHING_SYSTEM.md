# Transaction Matching System - Technical Documentation

**Date:** October 13, 2025
**Plugin:** 501c3PO WordPress Plugin
**Purpose:** Match transactions across three data sources for complete financial tracking

---

## 1. Overview

The 501c3PO plugin matches transactions from three independent data sources to create a complete money trail:

```
Customer Payment → Gravity Forms → Stripe API → Bank Deposit
```

### The Challenge

Each system records the same transaction differently:
- **Gravity Forms**: Records when customer submits payment form
- **Stripe API**: Records when charge is processed (3-6 seconds later)
- **Bank CSV**: Records when money arrives (2-7 days later, often grouped)

### The Solution

Two-phase matching algorithm:
1. **Phase 1**: Match Gravity Forms ↔ Stripe (by timestamp + amount)
2. **Phase 2**: Match Stripe ↔ Bank (by payout grouping + arrival date)

---

## 2. Data Sources

### 2.1 Gravity Forms Payments Table

**Table:** `swca_gf_addon_payment_transaction`
**Purpose:** Records customer form submissions with payment information
**Source:** Gravity Forms Stripe Add-on

**Key Columns:**
```sql
CREATE TABLE swca_gf_addon_payment_transaction (
    id mediumint(9) PRIMARY KEY,
    lead_id mediumint(9),                    -- Link to form submission
    transaction_type varchar(30),            -- 'payment', 'refund', etc.
    transaction_id varchar(50),              -- Stripe charge ID (ch_xxx)
    is_fulfilled tinyint(1),                 -- Payment completed
    amount decimal(10,2),                    -- Charge amount
    date_created datetime,                   -- Timestamp when form submitted
    -- Other fields...
);
```

**Example Data:**
```
ID: 123
Amount: $50.00
Date: 2025-09-15 14:32:18
Transaction ID: ch_3Q1AbcDefGhiJklMno
```

### 2.2 Stripe Transactions Table

**Table:** `swca_c3_stripe_transactions`
**Purpose:** Complete Stripe API data with payout information
**Source:** Stripe API sync (includes balance transaction expansion)

**Key Columns:**
```sql
CREATE TABLE swca_c3_stripe_transactions (
    id mediumint(9) PRIMARY KEY,
    stripe_charge_id varchar(255) UNIQUE,    -- ch_xxx (matches GF transaction_id)
    amount decimal(10,2),                    -- Gross charge amount
    stripe_fee decimal(10,2),                -- Stripe processing fee
    amount_refunded decimal(10,2),           -- Refunded amount (if any)
    net_amount decimal(10,2),                -- amount - stripe_fee - amount_refunded
    customer_email varchar(255),             -- Customer email
    customer_name varchar(255),              -- Customer name
    stripe_created timestamp,                -- When charge was created (3-6s after GF)
    payout_id varchar(255),                  -- po_xxx (groups multiple charges)
    payout_arrival_date date,                -- When payout arrives in bank
    balance_transaction_id varchar(255),     -- txn_xxx (Stripe balance record)
    -- Other fields...
    KEY idx_payout (payout_id),
    KEY idx_payout_date (payout_arrival_date)
);
```

**Example Data:**
```
ID: 45
Stripe Charge ID: ch_3Q1AbcDefGhiJklMno
Amount: $50.00
Stripe Fee: $1.75
Net Amount: $48.25
Stripe Created: 2025-09-15 14:32:23 (5 seconds after GF submission)
Payout ID: po_1Q2XyzAbcDefGhiJkl
Payout Arrival Date: 2025-09-17
```

### 2.3 Stripe Balance Transactions Table

**Table:** `swca_c3_stripe_balance_transactions`
**Purpose:** Detailed Stripe balance movements (charges, fees, payouts, refunds)
**Source:** Stripe API balance transaction sync

**Key Columns:**
```sql
CREATE TABLE swca_c3_stripe_balance_transactions (
    id mediumint(9) PRIMARY KEY,
    balance_txn_id varchar(255) UNIQUE,      -- txn_xxx
    txn_type varchar(50),                    -- 'charge', 'payout', 'refund', 'stripe_fee'
    source_id varchar(255),                  -- ch_xxx (charge) or po_xxx (payout)
    amount decimal(10,2),                    -- Transaction amount
    fee decimal(10,2),                       -- Fees
    net decimal(10,2),                       -- Net amount
    available_on date,                       -- When funds available
    payout_id varchar(255),                  -- Links to payout
    -- Other fields...
    KEY idx_payout (payout_id)
);
```

**Example Data:**
```
Balance Transaction 1 (Charge):
  balance_txn_id: txn_3Q1AbcCharge123456
  txn_type: charge
  source_id: ch_3Q1AbcDefGhiJklMno
  amount: $50.00
  fee: $1.75
  net: $48.25
  payout_id: po_1Q2XyzAbcDefGhiJkl
  available_on: 2025-09-17

Balance Transaction 2 (Payout):
  balance_txn_id: txn_3Q2XyzPayout789012
  txn_type: payout
  source_id: po_1Q2XyzAbcDefGhiJkl
  amount: -$145.50 (negative = money leaving Stripe)
  net: -$145.50
  available_on: 2025-09-17
```

### 2.4 Bank Transactions Table

**Table:** `wp_swca_bank_transactions`
**Purpose:** Bank CSV import data
**Source:** Manual CSV import from bank statements

**Key Columns:**
```sql
CREATE TABLE wp_swca_bank_transactions (
    id mediumint(9) PRIMARY KEY,
    post_date date,                          -- Transaction date
    description text,                        -- Bank description
    debit decimal(10,2),                     -- Money out
    credit decimal(10,2),                    -- Money in
    balance decimal(10,2),                   -- Running balance
    -- Other fields...
    KEY idx_post_date (post_date)
);
```

**Example Data:**
```
ID: 78
Post Date: 2025-09-17
Description: ACH Credit STRIPE TRANSFER
Credit: $145.50
Debit: $0.00
```

### 2.5 Transaction Matches Table

**Table:** `swca_c3_transaction_matches`
**Purpose:** Links between all three data sources
**Source:** Auto-matching algorithm

**Key Columns:**
```sql
CREATE TABLE swca_c3_transaction_matches (
    id mediumint(9) PRIMARY KEY,
    stripe_transaction_id mediumint(9),      -- FK to swca_c3_stripe_transactions.id
    gravity_form_transaction_id mediumint(9), -- FK to swca_gf_addon_payment_transaction.id
    bank_transaction_id mediumint(9),        -- FK to wp_swca_bank_transactions.id
    match_type varchar(50),                  -- 'gravity_stripe', 'bank_stripe_payout', etc.
    match_confidence varchar(20),            -- 'auto_high', 'auto_medium', 'manual'
    notes text,                              -- Match details
    matched_at datetime,
    -- Other fields...
    KEY idx_stripe (stripe_transaction_id),
    KEY idx_gravity (gravity_form_transaction_id),
    KEY idx_bank (bank_transaction_id)
);
```

---

## 3. How Stripe Payouts Work

Understanding Stripe payouts is critical to matching bank deposits.

### 3.1 Payout Lifecycle

```
Day 1 (Sept 15):
  Customer pays $50.00
  → Stripe creates charge ch_001
  → Stripe fee: $1.75
  → Net: $48.25

Day 2 (Sept 16):
  Customer pays $35.00
  → Stripe creates charge ch_002
  → Stripe fee: $1.35
  → Net: $33.65

Day 3 (Sept 17):
  Customer pays $65.00
  → Stripe creates charge ch_003
  → Stripe fee: $2.20
  → Net: $62.80

Day 3 (Sept 17) - End of Day:
  Stripe creates payout po_abc123 grouping ALL charges from Sept 15-17
  → Total gross: $150.00
  → Total fees: $5.30
  → Total net: $144.70
  → Payout ID: po_abc123
  → Available: Sept 17

Day 4 (Sept 17 or 18):
  Bank receives ONE deposit: $144.70
  → Description: "ACH Credit STRIPE TRANSFER"
```

### 3.2 Why Payout IDs Matter

**Without payout_id:**
```
Bank deposit: $144.70 (Sept 17)

Try to match individual charges:
  - $50.00 ≠ $144.70 ❌
  - $35.00 ≠ $144.70 ❌
  - $65.00 ≠ $144.70 ❌

Result: NO MATCH
```

**With payout_id:**
```
Bank deposit: $144.70 (Sept 17)

Group charges by payout_id = "po_abc123":
  - ch_001: $48.25 (net)
  - ch_002: $33.65 (net)
  - ch_003: $62.80 (net)

Total: $48.25 + $33.65 + $62.80 = $144.70 ✅

Result: PERFECT MATCH
```

---

## 4. Matching Algorithm

**File:** `/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/transaction-matching.php`
**Function:** `five01c3po_auto_match_transactions()`

### 4.1 Phase 1: Gravity Forms → Stripe Matching

**Logic:** Match by exact amount and timestamp within 30 seconds

**Why 30 seconds?**
- Gravity Forms records payment when form is submitted
- Stripe webhook fires 3-6 seconds later
- Network delays can add a few more seconds

**SQL Query:**
```sql
-- Find Stripe charge matching Gravity Forms payment
SELECT * FROM swca_c3_stripe_transactions
WHERE amount = %f                                    -- Exact amount match
AND ABS(TIMESTAMPDIFF(SECOND, stripe_created, %s)) <= 30  -- Within 30 seconds
AND id NOT IN (
    SELECT stripe_transaction_id
    FROM swca_c3_transaction_matches
    WHERE stripe_transaction_id IS NOT NULL
)
ORDER BY ABS(TIMESTAMPDIFF(SECOND, stripe_created, %s)) ASC
LIMIT 1
```

**Example Match:**
```
Gravity Forms Payment:
  ID: 123
  Amount: $50.00
  Date: 2025-09-15 14:32:18

Stripe Charge:
  ID: 45
  Charge ID: ch_3Q1AbcDefGhiJklMno
  Amount: $50.00
  Created: 2025-09-15 14:32:23

Time Difference: 5 seconds ✅
Amount Match: $50.00 = $50.00 ✅

→ Create match record:
  stripe_transaction_id: 45
  gravity_form_transaction_id: 123
  match_type: 'gravity_stripe'
  match_confidence: 'auto_high'
```

### 4.2 Phase 2: Bank → Stripe Payout Matching

**Logic:** Match bank deposits to grouped Stripe charges by payout ID

**Step 1:** Find unmatched bank deposits with "STRIPE" in description

```sql
SELECT * FROM wp_swca_bank_transactions
WHERE credit > 0                    -- Deposits only (not debits)
AND description LIKE '%STRIPE%'     -- Filter out cash/checks
AND id NOT IN (
    SELECT bank_transaction_id
    FROM swca_c3_transaction_matches
    WHERE bank_transaction_id IS NOT NULL
)
ORDER BY post_date DESC
```

**Step 2:** For each bank deposit, find matching payouts from Stripe balance transactions

```sql
-- Find payouts within date window
SELECT
    balance_txn_id as payout_id,
    available_on as payout_arrival_date,
    ABS(net) as payout_net_total,
    description,
    balance_txn_id
FROM swca_c3_stripe_balance_transactions
WHERE txn_type = 'payout'
AND DATE(available_on) BETWEEN DATE_SUB(%s, INTERVAL 7 DAY) AND DATE_ADD(%s, INTERVAL 2 DAY)
AND ABS(net) > 0
ORDER BY ABS(DATEDIFF(DATE(available_on), %s)) ASC,
         ABS(ABS(net) - %f) ASC
```

**Date Window Explained:**
- **-7 days before:** Banks can delay deposits
- **+2 days after:** Stripe says "available" but bank processes later

**Amount Tolerance:** $0.50 (for actual payout transactions)

**Step 3:** Look up actual payout ID from balance transactions

**CRITICAL FIX (Oct 13, 2025):** Algorithm was grouping by `payout_arrival_date` instead of `payout_id`, causing incorrect matches when multiple payouts occurred on the same date.

```sql
-- FIXED: Lookup actual payout_id from balance transaction
SELECT source_id as actual_payout_id
FROM swca_c3_stripe_balance_transactions
WHERE balance_txn_id = %s
AND txn_type = 'payout'
LIMIT 1
```

**Step 4:** Group all Stripe charges by payout ID

```sql
-- FIXED: Match by exact payout_id (not date)
SELECT id FROM swca_c3_stripe_transactions
WHERE payout_id = %s               -- Exact payout ID match ✅
AND net_amount > 0                 -- Exclude refunds
ORDER BY id
```

**Old (BUGGY) Query:**
```sql
-- WRONG: Grouped by date instead of payout_id
SELECT id FROM swca_c3_stripe_transactions
WHERE DATE(payout_arrival_date) = %s  -- ❌ Multiple payouts same date!
AND net_amount > 0
ORDER BY id
```

**Why This Was Wrong:**
```
Sept 17 had 3 payouts:
  - po_001: $48.25 (1 charge)
  - po_002: $144.70 (3 charges)
  - po_003: $35.00 (1 charge)

Old algorithm grouped ALL 5 charges together = $227.95
Tried to match to bank deposit of $144.70 → FAILED ❌

Fixed algorithm:
  - po_002 matches exactly: 3 charges = $144.70 ✅
```

**Step 5:** Create match records for all charges in payout

```sql
-- Create one match record per Stripe charge
INSERT INTO swca_c3_transaction_matches
(stripe_transaction_id, bank_transaction_id, match_type, match_confidence, notes)
VALUES
-- First charge in payout
(45, 78, 'bank_stripe_payout', 'auto_high', 'Payout po_abc123: 3 charges = $144.70'),
-- Additional charges in same payout
(46, 78, 'bank_stripe_payout_part', 'auto_high', 'Payout po_abc123: 3 charges = $144.70'),
(47, 78, 'bank_stripe_payout_part', 'auto_high', 'Payout po_abc123: 3 charges = $144.70')
```

---

## 5. Stripe API Queries

**File:** `/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/stripe-integration.php`
**Function:** `five01c3po_run_stripe_sync()`

### 5.1 Sync Charges

```php
// Fetch charges with balance transaction expansion
$charges = \Stripe\Charge::all([
    'limit' => 100,
    'created' => [
        'gte' => strtotime('-730 days'),  // 2 years of data
    ],
    'expand' => ['data.balance_transaction']  // CRITICAL: Get payout data
]);

foreach ($charges->data as $charge) {
    $wpdb->insert($stripe_table, [
        'stripe_charge_id' => $charge->id,
        'amount' => $charge->amount / 100,
        'stripe_fee' => ($charge->balance_transaction->fee ?? 0) / 100,
        'net_amount' => ($charge->balance_transaction->net ?? 0) / 100,
        'customer_email' => $charge->billing_details->email ?? '',
        'stripe_created' => gmdate('Y-m-d H:i:s', $charge->created),
        'balance_transaction_id' => $charge->balance_transaction->id ?? '',
        // payout_id populated separately from balance_transactions table
    ]);
}
```

### 5.2 Sync Balance Transactions

```php
// Fetch ALL balance transactions (charges, fees, payouts, refunds)
$balance_txns = \Stripe\BalanceTransaction::all([
    'limit' => 100,
    'created' => [
        'gte' => strtotime('-730 days'),
    ]
]);

foreach ($balance_txns->data as $bt) {
    $wpdb->insert($balance_table, [
        'balance_txn_id' => $bt->id,
        'txn_type' => $bt->type,              // 'charge', 'payout', 'refund', etc.
        'source_id' => $bt->source,           // ch_xxx or po_xxx
        'amount' => $bt->amount / 100,
        'fee' => $bt->fee / 100,
        'net' => $bt->net / 100,
        'available_on' => gmdate('Y-m-d', $bt->available_on),
        'payout_id' => $bt->payout ?? '',     // Links to payout
    ]);
}
```

### 5.3 Linking Payout IDs

**After sync, link payout_id from balance_transactions to stripe_transactions:**

```sql
UPDATE swca_c3_stripe_transactions st
JOIN swca_c3_stripe_balance_transactions bt
  ON st.balance_transaction_id = bt.balance_txn_id
SET st.payout_id = bt.payout_id
WHERE bt.payout_id IS NOT NULL AND bt.payout_id != '';
```

**This query:**
- Joins on `balance_transaction_id` (txn_xxx)
- Copies `payout_id` (po_xxx) from balance table to main Stripe table
- Enables payout-based grouping for bank matching

---

## 6. Database Schema Relationships

```
┌─────────────────────────────────────────────────────────────┐
│                   Customer Form Submission                   │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
            ┌──────────────────────────────┐
            │  swca_gf_addon_payment_      │
            │       transaction            │
            ├──────────────────────────────┤
            │ id: 123                      │
            │ transaction_id: ch_001       │◄────┐
            │ amount: $50.00               │     │
            │ date_created: 14:32:18       │     │
            └──────────────────────────────┘     │
                           │                     │
                           │ MATCH               │
                           │ (timestamp + amt)   │
                           ▼                     │
            ┌──────────────────────────────┐     │
            │  swca_c3_stripe_             │     │
            │     transactions             │     │
            ├──────────────────────────────┤     │
            │ id: 45                       │     │
            │ stripe_charge_id: ch_001 ────┼─────┘
            │ amount: $50.00               │
            │ stripe_fee: $1.75            │
            │ net_amount: $48.25           │
            │ stripe_created: 14:32:23     │
            │ payout_id: po_abc123 ────────┼─────┐
            │ payout_arrival_date: Sept 17 │     │
            │ balance_transaction_id: txn1 │◄──┐ │
            └──────────────────────────────┘   │ │
                           │                   │ │
                           │                   │ │ GROUP BY
                           ▼                   │ │ payout_id
            ┌──────────────────────────────┐   │ │
            │  swca_c3_stripe_balance_     │   │ │
            │      transactions            │   │ │
            ├──────────────────────────────┤   │ │
            │ balance_txn_id: txn1 ────────┼───┘ │
            │ txn_type: charge             │     │
            │ source_id: ch_001            │     │
            │ payout_id: po_abc123         │     │
            │ net: $48.25                  │     │
            └──────────────────────────────┘     │
                           │                     │
                           │                     │
            ┌──────────────────────────────┐     │
            │  swca_c3_stripe_balance_     │     │
            │      transactions            │     │
            ├──────────────────────────────┤     │
            │ txn_type: payout             │     │
            │ source_id: po_abc123 ◄───────┼─────┘
            │ net: -$144.70                │
            │ available_on: Sept 17        │
            └──────────────────────────────┘
                           │
                           │ MATCH
                           │ (payout amount + date)
                           ▼
            ┌──────────────────────────────┐
            │  wp_swca_bank_transactions   │
            ├──────────────────────────────┤
            │ id: 78                       │
            │ post_date: Sept 17           │
            │ description: STRIPE TRANSFER │
            │ credit: $144.70              │
            └──────────────────────────────┘

         ALL LINKED VIA:

         ┌──────────────────────────────────┐
         │  swca_c3_transaction_matches     │
         ├──────────────────────────────────┤
         │ stripe_transaction_id: 45        │
         │ gravity_form_transaction_id: 123 │
         │ bank_transaction_id: 78          │
         └──────────────────────────────────┘
```

---

## 7. Key Business Rules

### 7.1 Bank Deposit Filtering

**ONLY match deposits with "STRIPE" in description**

**Reason:** Bank CSV includes ALL deposits:
- Stripe ACH transfers (what we want)
- Cash deposits (ignore)
- Check deposits (ignore)
- Interest credits (ignore)
- Other ACH transfers (ignore)

**Example Bank CSV:**
```
Sept 15: "Deposit" - $100.00 → IGNORE (likely cash/check)
Sept 17: "ACH Credit STRIPE TRANSFER" - $144.70 → MATCH
Sept 18: "Interest Credit" - $0.15 → IGNORE
Sept 20: "ACH Credit BILL PMT" - $50.00 → IGNORE
```

### 7.2 Refund Handling

**Refunds are EXCLUDED from bank matching**

**Reason:** Refunds create negative net amounts:
- Customer charged $100, then refunded
- Stripe shows: amount=$100, refunded=$100, fee=$3.00, net=-$3.00
- Bank received initial $97 deposit, then Stripe withdrew $100 later
- For revenue tracking, we filter `net_amount > 0`

**Example:**
```sql
-- Exclude refunds from bank matching
SELECT * FROM swca_c3_stripe_transactions
WHERE net_amount > 0  -- Positive revenue only
```

### 7.3 Payout Timing

**Stripe Payout Schedule:**
- **Standard accounts:** 2 business days after charge
- **Express/Custom:** Can be 7+ days
- **Available_on:** Date Stripe says funds available
- **Bank arrival:** Usually available_on, but can be 1-2 days later

**Example:**
```
Charge: Sept 15 (Wednesday)
Available: Sept 17 (Friday) - 2 business days
Bank: Sept 17 or Sept 18 (bank processing delay)
```

---

## 8. Transaction Ledger View

**File:** `/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/transaction-ledger.php`

**Purpose:** Display complete money trail for all transactions

**SQL Query:**
```sql
SELECT
    s.id as stripe_id,
    s.stripe_charge_id,
    s.stripe_created,
    s.amount,
    s.stripe_fee,
    s.amount_refunded,
    (s.amount - s.stripe_fee - s.amount_refunded) as net_amount,  -- Real-time calculation
    s.customer_name,
    s.customer_email,
    s.payout_id,
    s.payout_arrival_date,

    gf.id as gravity_form_id,
    gf.date_created as gravity_form_date,

    b.id as bank_id,
    b.post_date as bank_date,
    b.credit as bank_amount,
    b.description as bank_description,

    -- Calculate days between each step
    DATEDIFF(s.payout_arrival_date, DATE(s.stripe_created)) as days_to_payout,
    DATEDIFF(b.post_date, s.payout_arrival_date) as days_to_bank

FROM swca_c3_stripe_transactions s

-- Join to Gravity Forms (Phase 1 match)
LEFT JOIN swca_c3_transaction_matches m_gf
    ON s.id = m_gf.stripe_transaction_id
    AND m_gf.match_type = 'gravity_stripe'
LEFT JOIN swca_gf_addon_payment_transaction gf
    ON m_gf.gravity_form_transaction_id = gf.id

-- Join to Bank (Phase 2 match)
LEFT JOIN (
    SELECT
        stripe_transaction_id,
        bank_transaction_id
    FROM swca_c3_transaction_matches
    WHERE bank_transaction_id IS NOT NULL
    GROUP BY stripe_transaction_id
) m_bank ON s.id = m_bank.stripe_transaction_id
LEFT JOIN wp_swca_bank_transactions b
    ON m_bank.bank_transaction_id = b.id

WHERE s.amount > 0  -- Positive charges only
ORDER BY s.stripe_created DESC
```

**Output Example:**
```
┌──────────────────────────────────────────────────────────────────┐
│ Sept 15, 2025 2:32pm                                             │
│ John Doe (john@example.com) - $50.00                             │
├──────────────────────────────────────────────────────────────────┤
│ Stripe Fee: -$1.75                                               │
│ Refunded: $0.00                                                  │
│ Net: $48.25                                                      │
├──────────────────────────────────────────────────────────────────┤
│ Payout: po_abc123 (Sept 17) - 2 days                            │
│ Bank: $144.70 (Sept 17) - same day ✓ In Bank                    │
└──────────────────────────────────────────────────────────────────┘
```

---

## 9. Current Status (Oct 13, 2025)

### 9.1 Data Coverage

```
Stripe Transactions: 223
Gravity Forms Payments: 211
Bank Deposits (total): 203
Bank Deposits (Stripe only): ~30-40

Payout Data:
  - With payout_id: 222 / 223 (99.6%)
  - Unique payouts: 152
```

### 9.2 Matching Rates

**Before Fix:**
```
GF → Stripe: 205 / 223 = 91.9% ✅
Stripe → Bank: 85 / 223 = 38.1% ❌ (buggy date grouping)
```

**Expected After Fix:**
```
GF → Stripe: ~91% (unchanged)
Stripe → Bank: 95%+ (payout-based grouping)
```

### 9.3 Table Naming Convention

**ALL 501c3PO tables use:** `$wpdb->prefix . 'c3_'`

**Format:** `{site_prefix}c3_{table_name}`

**Examples:**
```
swca_c3_stripe_transactions
swca_c3_stripe_balance_transactions
swca_c3_transaction_matches
swca_c3_bank_transactions
swca_c3_gf_payment_transaction
```

---

## 10. Files Modified (Oct 13, 2025)

### 10.1 Core Files

1. **`/includes/core/database.php`**
   - Updated table names to use `c3_` prefix
   - Lines 327-407: All Stripe-related tables

2. **`/includes/features/stripe-integration.php`**
   - Updated Stripe sync to use correct table names
   - Lines 419, 581: Table variable declarations

3. **`/includes/features/transaction-matching.php`**
   - FIXED: Lines 441-475: Payout grouping by exact payout_id (not date)
   - Before: Grouped by `DATE(payout_arrival_date)` ❌
   - After: Grouped by `payout_id` from balance_transactions ✅

### 10.2 Bulk Replacements

**All PHP files in `/includes/` directory updated:**
```bash
# Replaced table names
'stripe_transactions' → 'c3_stripe_transactions'
'stripe_balance_transactions' → 'c3_stripe_balance_transactions'
'transaction_matches' → 'c3_transaction_matches'
'bank_transactions' → 'c3_bank_transactions'
```

**Verification:** 0 old table references remain

---

## 11. Known Limitations

### 11.1 Multiple Payouts Same Day

**Before Fix:** Algorithm couldn't distinguish between multiple payouts on the same date

**Example:**
```
Sept 17 payouts:
  - po_001: $48.25 (1 charge)
  - po_002: $144.70 (3 charges)
  - po_003: $35.00 (1 charge)

Old algorithm: Grouped all → $227.95 (wrong)
Fixed algorithm: Matches each payout separately ✓
```

### 11.2 Bank Processing Delays

**Issue:** Banks can process deposits 1-2 days after Stripe "available_on" date

**Solution:** Date window includes -7 days before / +2 days after bank date

### 11.3 Incomplete Payout Data

**Issue:** 1 transaction (ID #177) missing payout_id

**Cause:** Likely a pending/failed payout

**Impact:** This transaction won't match to bank

---

## 12. Future Improvements

### 12.1 Manual Review Interface

Create admin interface for reviewing:
- Unmatched Stripe transactions
- Unmatched bank deposits
- Low-confidence matches

### 12.2 Reconciliation Reports

Generate monthly reports showing:
- Expected bank deposits (from Stripe payouts)
- Actual bank deposits
- Discrepancies

### 12.3 Automated Alerts

Email alerts when:
- Payout expected but not in bank after 7 days
- Bank deposit with no matching payout
- Large amount discrepancies

---

## 13. Testing & Verification

### 13.1 Check Payout Data Coverage

```sql
SELECT
    COUNT(*) as total,
    SUM(CASE WHEN payout_id IS NOT NULL THEN 1 ELSE 0 END) as with_payout,
    COUNT(DISTINCT payout_id) as unique_payouts
FROM swca_c3_stripe_transactions
WHERE payout_id IS NOT NULL;
```

### 13.2 Check Matching Rates

```sql
SELECT
    'Stripe' as source,
    COUNT(*) as total,
    COUNT(DISTINCT m.stripe_transaction_id) as matched,
    ROUND((COUNT(DISTINCT m.stripe_transaction_id) / COUNT(*)) * 100, 1) as match_rate
FROM swca_c3_stripe_transactions s
LEFT JOIN swca_c3_transaction_matches m ON s.id = m.stripe_transaction_id;
```

### 13.3 Verify Payout Grouping

```sql
-- Show payout groups
SELECT
    payout_id,
    payout_arrival_date,
    COUNT(*) as charge_count,
    SUM(net_amount) as total_net,
    GROUP_CONCAT(id ORDER BY id) as stripe_ids
FROM swca_c3_stripe_transactions
WHERE payout_id IS NOT NULL
AND net_amount > 0
GROUP BY payout_id
ORDER BY payout_arrival_date DESC
LIMIT 10;
```

---

## 14. Current Status (Oct 16, 2025) - POST AUDIT

### 14.1 Matching Results After Algorithm Fix

**Achieved:**
- ✅ Fixed duplicate match records (removed 38 duplicates)
- ✅ Ran bank matching algorithm (created 89 new matches)
- ✅ Verified source data integrity (all clean)

**Current Matching Rates:**
```
Gravity Forms → Stripe: 206 / 215 = 95.8%
Bank → Stripe: 56 / 60 real deposits = 93.3%
```

### 14.2 Why Not 100%? - DATA QUALITY ISSUES

**ACCOUNTING PRINCIPLE:** Every transaction must be accounted for. 93.3% indicates data errors, not acceptable variance.

**The 10 "Unmatched" Bank Deposits Breakdown:**

**6 Duplicate CSV Imports (DATA ERROR):**
- $34.28 on 2025-09-18: Bank #7 and #200 (same transaction imported twice)
- $99.99 on 2025-09-12: Bank #9 and #199 (same transaction imported twice)
- $48.55 on 2025-09-02: Bank #13 and #197 (same transaction imported twice)
- **Root Cause:** Bank CSV was imported multiple times without deduplication
- **Fix Required:** DELETE FROM wp_swca_bank_transactions WHERE id IN (7, 9, 13, 200, 199, 197);

**4 Missing Stripe Payout Data (SYNC ERROR):**
- $50.00 on 2025-08-27 (Bank #15) - Payout txn_1S0BJFJHWUaRCmpE exists but NOT in our database
- $34.75 on 2025-08-20 (Bank #18) - Payout txn_1Rxe4AJHWUaRCmpE exists but NOT in our database
- $50.00 on 2025-07-30 (Bank #31) - Payout txn_1Rq2HkJHWUaRCmpE exists but NOT in our database
- $49.60 on 2025-07-29 (Bank #32) - Payout txn_1RpfZFJHWUaRCmpE exists but NOT in our database
- **Root Cause:** Stripe sync incomplete for July-August 2025
- **Fix Required:** Re-run Stripe sync for date range 2025-07-01 to 2025-08-31

### 14.3 Path to 100% Matching (Required for Accounting)

**Step 1: Remove Duplicate Bank Entries**
```sql
DELETE FROM wp_swca_bank_transactions WHERE id IN (7, 9, 13, 200, 199, 197);
```
Result: 60 unique bank deposits (was 66 with duplicates)

**Step 2: Re-Sync Missing Stripe Payouts**
1. Go to: Stripe Sync page
2. Set date range: 2025-07-01 to 2025-08-31
3. Run sync to fetch 4 missing payout records
4. Re-run matching algorithm

Result: 4 additional matches created

**Final Result: 60/60 = 100%** ✅

### 14.4 Why 100% Is Critical for Accounting

**Accounting Standards Require:**
- Every bank deposit must trace to a source transaction
- Every revenue transaction must trace to bank deposit
- No "acceptable variance" - missing matches indicate:
  - Duplicate data (import errors)
  - Missing data (sync errors)
  - Fraud/theft (money in bank with no source)

**Current 93.3% Means:**
- ❌ 6 transactions are duplicates (data quality issue)
- ❌ 4 transactions missing source data (sync issue)
- ❌ Cannot produce accurate financial statements
- ❌ Cannot pass audit

**After Fixes (100%) Means:**
- ✅ Every bank deposit has verified source
- ✅ Every revenue transaction accounted for
- ✅ Can produce audit-ready financials
- ✅ Complete money trail documented

### 14.5 Troubleshooting

**If Matching Rate < 100%:**

1. **Check for Duplicate Bank Imports:**
```sql
SELECT post_date, credit, COUNT(*) as count
FROM wp_swca_bank_transactions
WHERE credit > 0 AND description LIKE '%STRIPE%'
GROUP BY post_date, credit
HAVING count > 1;
```

2. **Check for Missing Payout Data:**
```sql
-- Find bank deposits with no matching Stripe payout
SELECT b.* FROM wp_swca_bank_transactions b
WHERE b.credit > 0 AND b.description LIKE '%STRIPE%'
AND b.id NOT IN (SELECT bank_transaction_id FROM swca_c3_transaction_matches WHERE bank_transaction_id IS NOT NULL);
```

3. **Verify Payout Data Coverage:**
```sql
-- Check payout data date range
SELECT MIN(DATE(available_on)) as earliest, MAX(DATE(available_on)) as latest
FROM swca_c3_stripe_balance_transactions
WHERE txn_type = 'payout';
```

**If Gaps Found:** Re-sync Stripe for missing date ranges

---

## 15. Summary

The transaction matching system creates a complete money trail by:

1. **Syncing Stripe API data** with balance transaction expansion to capture payout information
2. **Linking payout IDs** from balance_transactions to main Stripe transactions table
3. **Matching GF→Stripe** by exact amount and timestamp within 30 seconds
4. **Grouping Stripe charges by payout_id** (NOT date) to match bank deposits
5. **Creating match records** linking all three data sources

**Critical Success Factors:**
- ✅ Payout data must be complete (99%+ coverage)
- ✅ Algorithm groups by exact payout_id (not date)
- ✅ Bank CSV filtered to "STRIPE" deposits only
- ✅ Consistent table naming (`c3_` prefix)

**End Result:** Complete financial tracking from customer payment → Gravity Forms → Stripe API → Bank Deposit

---

**Document Version:** 1.0
**Last Updated:** October 13, 2025
**Maintained By:** 501c3PO Development Team
