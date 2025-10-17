# 501c3PO Phase 1 - Detailed Task List
**Generated:** October 16, 2025
**Status:** Ready to Execute

---

## Task 1: Code Audit & Cleanup

### 1.1 Audit All PHP Scripts (Est: 2 hours)
**Complexity:** Simple
**Dependencies:** None

#### Action Items
- [ ] Create `/scripts/501c3PO/FILE_AUDIT.csv` with columns: filename, category, purpose, keep/move/delete
- [ ] Review all 141 PHP files in scripts directory
- [ ] Categorize each file as:
  - `PRODUCTION` - Needed for plugin operation
  - `DIAGNOSTIC` - Used for debugging/analysis
  - `DEPRECATED` - Old versions or superseded
  - `UNKNOWN` - Need further investigation
- [ ] Document purpose of each file in audit spreadsheet

#### Acceptance Criteria
- [x] FILE_AUDIT.csv created and complete
- [x] Every file categorized
- [x] Purpose documented for each file

---

### 1.2 Create Archive Directories (Est: 15 minutes)
**Complexity:** Simple
**Dependencies:** 1.1

#### Action Items
- [ ] Create `/scripts/501c3PO/diagnostic-scripts-archive/`
- [ ] Create `/scripts/501c3PO/deprecated-scripts/`
- [ ] Create README.md in each directory explaining purpose

#### Acceptance Criteria
- [x] Directories created
- [x] README.md files present

---

### 1.3 Move Diagnostic Scripts (Est: 30 minutes)
**Complexity:** Simple
**Dependencies:** 1.2

#### Action Items
- [ ] Move all `check-*.php` files to `/diagnostic-scripts-archive/`
- [ ] Move all `investigate-*.php` files to `/diagnostic-scripts-archive/`
- [ ] Move all `test-*.php` files to `/diagnostic-scripts-archive/`
- [ ] Move all `verify-*.php` files to `/diagnostic-scripts-archive/`
- [ ] Move all `analyze-*.php` files to `/diagnostic-scripts-archive/`
- [ ] Keep `diagnostic-scripts-oct6/` in archive directory
- [ ] Update any documentation references

#### Acceptance Criteria
- [x] All diagnostic scripts moved
- [x] Root directory cleaner
- [x] Scripts still accessible if needed

---

### 1.4 Move Deprecated Scripts (Est: 30 minutes)
**Complexity:** Simple
**Dependencies:** 1.2

#### Action Items
- [ ] Move all `*.backup*` files to `/deprecated-scripts/`
- [ ] Move old versions to `/deprecated-scripts/`
- [ ] Move superseded scripts to `/deprecated-scripts/`
- [ ] Document what replaced each deprecated script

#### Acceptance Criteria
- [x] All deprecated scripts archived
- [x] Replacement documented for each

---

### 1.5 Add Inline Documentation (Est: 2-3 hours)
**Complexity:** Moderate
**Dependencies:** 1.1

#### Action Items
For each PRODUCTION script:
- [ ] Add comprehensive header comment (purpose, dependencies, documentation refs)
- [ ] Add WHY comments explaining decisions
- [ ] Document edge cases
- [ ] Note limitations
- [ ] Reference external documentation

#### Header Template
```php
<?php
/**
 * [Script Name]
 *
 * PURPOSE:
 * [Clear explanation of what this script does and WHY it exists]
 *
 * DEPENDENCIES:
 * - WordPress functions: [list]
 * - Database tables: [list]
 * - External APIs: [list]
 *
 * DOCUMENTATION:
 * See /docs/[RELEVANT_DOC].md for detailed explanation
 *
 * EDGE CASES:
 * - [List known edge cases and how they're handled]
 *
 * LIMITATIONS:
 * - [List known limitations]
 *
 * LAST MODIFIED: [Date]
 * MODIFIED BY: [Person/AI]
 * CHANGES: [What changed and WHY]
 */
```

#### Acceptance Criteria
- [x] All production scripts have comprehensive headers
- [x] WHY comments added where decisions made
- [x] Edge cases documented
- [x] External documentation referenced

---

## Task 2: Comprehensive Documentation

### 2.1 Create Documentation Directory Structure (Est: 15 minutes)
**Complexity:** Simple
**Dependencies:** None

#### Action Items
- [ ] Create `/scripts/501c3PO/docs/` directory
- [ ] Create subdirectories:
  - `/docs/workflows/` - User workflows
  - `/docs/technical/` - Technical details
  - `/docs/api/` - API references
  - `/docs/diagrams/` - Flow diagrams
- [ ] Create master `/docs/README.md` index

#### Acceptance Criteria
- [x] Directory structure created
- [x] Master README.md with index of all docs

---

### 2.2 Bank CSV Import Documentation (Est: 2 hours)
**Complexity:** Moderate
**Dependencies:** 2.1

**File:** `/docs/technical/BANK_CSV_IMPORT.md`

#### Action Items
- [ ] Document exact CSV format requirements
  - Column names expected
  - Date formats accepted
  - Amount format (decimals, currency symbols)
- [ ] Document import process flow
  - Step 1: File upload
  - Step 2: Validation
  - Step 3: Duplicate check
  - Step 4: Database insert
  - Step 5: Balance calculation
- [ ] Document validation rules
  - Required fields
  - Data type validation
  - Range checks
- [ ] Document duplicate detection logic
  - How duplicates are identified
  - What happens to duplicates
- [ ] Document balance calculation
  - Running balance algorithm
  - Starting balance handling
- [ ] Document error handling
  - Invalid CSV format
  - Missing columns
  - Invalid data types
  - Duplicate records
- [ ] Document edge cases
  - Multi-day batch processing
  - Negative amounts (refunds, fees)
  - Zero amounts
  - Missing dates
- [ ] Explain WHY decisions were made
  - Why these specific columns?
  - Why this date format?
  - Why this duplicate detection logic?

#### Sample CSV Format to Document
```csv
Post Date,Description,Debit,Credit,Balance
2025-09-18,STRIPE TRANSFER,0.00,34.28,5234.56
2025-09-17,CHECK #1234,50.00,0.00,5200.28
```

#### Acceptance Criteria
- [x] Complete CSV format specification
- [x] Import process flow documented
- [x] All validation rules listed
- [x] Edge cases covered
- [x] WHY explanations included

---

### 2.3 Stripe Sync Documentation (Est: 2 hours)
**Complexity:** Moderate
**Dependencies:** 2.1

**File:** `/docs/technical/STRIPE_SYNC.md`

#### Action Items
- [ ] Document Stripe API endpoints used
  - `/charges` - retrieve charges
  - `/refunds` - retrieve refunds
  - `/balance_transactions` - retrieve balance history
  - `/payouts` - retrieve payout information
- [ ] Document data retrieved
  - Charge objects (fields used)
  - Refund objects (fields used)
  - Balance transaction objects (fields used)
  - Payout objects (fields used)
- [ ] Document sync frequency recommendations
  - Daily for active organizations
  - Weekly for less active
  - Monthly minimum
- [ ] Document deduplication logic
  - How existing records detected
  - What triggers an update vs skip
  - Payout data update handling
- [ ] Document payout relationship mapping
  - How charges link to payouts
  - balance_transactions as source of truth
  - Payout ID population process
- [ ] Document rate limiting
  - Stripe API limits
  - How plugin handles limits
  - Retry logic
- [ ] Document error recovery
  - Network failures
  - API errors
  - Invalid API key
  - Partial sync recovery
- [ ] Document API key encryption/storage
  - AES-256-CBC encryption
  - Passphrase hashing (bcrypt)
  - Storage location (wp_options)
  - Security considerations

#### Acceptance Criteria
- [x] All API endpoints documented
- [x] Sync process flow clear
- [x] Deduplication logic explained
- [x] Security measures documented

---

### 2.4 Transaction Matching Documentation (Est: 3 hours)
**Complexity:** Complex
**Dependencies:** 2.1

**File:** `/docs/technical/TRANSACTION_MATCHING.md`

#### Action Items
- [ ] Document matching algorithm overview
  - High-level flow
  - Data sources (Bank, Stripe, Gravity Forms)
  - Match types created
- [ ] Document match types
  - `gravity_stripe` - Gravity Forms → Stripe (timestamp + amount)
  - `bank_stripe_payout` - Bank → Stripe (payout date + amount)
  - `bank_stripe_payout_part` - Additional charges in same payout
- [ ] Document confidence levels
  - `auto_high` - Exact matches (GF timestamp within 30s)
  - `auto_medium` - Good matches (amounts match, dates close)
  - `auto_low` - Possible matches (amounts similar, dates within range)
- [ ] Document matching criteria
  - Gravity Forms → Stripe:
    - Exact amount match
    - Timestamp within 30 seconds
    - GF records payment 3-6 seconds AFTER Stripe
  - Bank → Stripe Payout:
    - Payout date matches bank deposit date
    - Payout amount matches bank deposit amount (within $0.50)
    - Uses balance_transactions as source
- [ ] Document edge cases
  - Refunded transactions (negative net)
  - Partial refunds (amount vs net)
  - Batch payouts (multiple charges in one deposit)
  - Failed transactions (excluded from matching)
- [ ] Explain WHY algorithm works
  - Why 30-second window for GF?
  - Why $0.50 tolerance for bank matches?
  - Why use balance_transactions not stripe_transactions?
- [ ] Document known limitations
  - Cannot match cash/check transactions
  - Cannot match non-Stripe bank deposits
  - Requires Stripe integration for payout data
- [ ] Document future improvements
  - Manual match interface
  - Match confidence adjustment
  - Unmatch functionality
  - Match history tracking

#### Algorithm Pseudocode
```
MATCHING ALGORITHM:

Step 1: Match Gravity Forms → Stripe
  For each GF payment:
    Find Stripe charge where:
      - Amount matches exactly
      - Timestamp within 30 seconds
      - GF timestamp >= Stripe timestamp (GF records after)
    Create match: gravity_stripe, confidence: auto_high

Step 2: Match Bank → Stripe Payouts
  For each unmatched bank deposit (STRIPE in description):
    Find payout in balance_transactions where:
      - Date(payout.available_on) = Date(bank.post_date)
      - |payout.net| ≈ bank.credit (within $0.50)
    Get all charges for this payout:
      - Query balance_transactions for charges with this payout_id
      - Match charge source_id to stripe_transactions.stripe_charge_id
    For first charge:
      Create match: bank_stripe_payout, confidence: auto_high
    For additional charges:
      Create match: bank_stripe_payout_part, confidence: auto_high

Step 3: Report Unmatched
  List unmatched Stripe charges (no GF match)
  List unmatched bank deposits (no payout match)
```

#### Acceptance Criteria
- [x] Algorithm clearly explained
- [x] All match types defined
- [x] Confidence levels justified
- [x] Edge cases covered
- [x] Pseudocode included
- [x] WHY explanations thorough

---

### 2.5 Data Flow Documentation (Est: 1.5 hours)
**Complexity:** Moderate
**Dependencies:** 2.1

**File:** `/docs/technical/DATA_FLOW.md`

#### Action Items
- [ ] Create data flow diagram (ASCII or Markdown)
- [ ] Document complete transaction lifecycle
  - Customer pays via Gravity Forms
  - Stripe processes payment
  - GF records payment
  - Stripe sends webhook
  - Treasurer syncs Stripe API
  - Stripe data stored in database
  - Treasurer uploads bank CSV
  - Bank data stored in database
  - Matching algorithm runs
  - Match records created
  - Ledger displays complete flow
- [ ] Document table relationships
  - `swca_c3_stripe_transactions` (charges)
  - `swca_c3_stripe_balance_transactions` (Stripe ledger)
  - `swca_c3_gf_payment_transaction` (Gravity Forms payments)
  - `wp_swca_bank_transactions` (bank CSV imports)
  - `swca_c3_transaction_matches` (match records)
- [ ] Document data transformations
  - Stripe cents → dollars
  - Date format conversions
  - Net amount calculations
- [ ] Document validation points
  - CSV upload validation
  - Stripe API response validation
  - Match criteria validation

#### ASCII Data Flow Diagram
```
CUSTOMER
   |
   | (1) Pays via Gravity Forms
   v
GRAVITY FORMS
   |
   | (2) Sends to Stripe
   v
STRIPE API
   |
   |----> (3) Webhook → GF (records payment)
   |
   |----> (4) Treasurer syncs via API
   v
swca_c3_stripe_transactions
swca_c3_stripe_balance_transactions
   |
   |
TREASURER
   |
   | (5) Downloads bank CSV
   |
   | (6) Uploads to plugin
   v
wp_swca_bank_transactions
   |
   |
   | (7) MATCHING ALGORITHM runs
   |
   |----> Matches GF → Stripe (timestamp + amount)
   |
   |----> Matches Bank → Stripe (payout date + amount)
   v
swca_c3_transaction_matches
   |
   |
   | (8) TRANSACTION LEDGER displays
   v
COMPLETE MONEY TRAIL:
Customer → GF → Stripe → Bank Deposit
```

#### Acceptance Criteria
- [x] Data flow diagram created
- [x] Complete lifecycle documented
- [x] Table relationships clear
- [x] Transformations listed

---

### 2.6 Database Schema Documentation (Est: 1.5 hours)
**Complexity:** Moderate
**Dependencies:** 2.1

**File:** `/docs/technical/DATABASE_SCHEMA.md`

#### Action Items
- [ ] Document all table definitions
  - Table name
  - All columns (name, type, purpose)
  - Primary key
  - Indexes
  - Foreign keys (implicit)
- [ ] Document naming conventions
  - `swca_c3_` prefix (why c3?)
  - Table name patterns
- [ ] Document table relationships
  - Which tables reference others
  - Join patterns
- [ ] Document migration history
  - Phase 1 migration (Oct 13, 2025)
  - Old table names vs new
- [ ] Document WHY decisions
  - Why c3_ prefix?
  - Why these indexes?
  - Why these column types?

#### Tables to Document

**Transaction Tables:**
1. `swca_c3_stripe_transactions` (223 rows)
   - Stripe API charge data
   - Fields: id, stripe_charge_id, amount, stripe_fee, amount_refunded, net_amount, payout_id, payout_arrival_date, customer_email, customer_name, stripe_created, etc.

2. `swca_c3_stripe_balance_transactions` (407 rows)
   - Stripe balance ledger
   - Fields: id, balance_txn_id, txn_type, source_id, amount, fee, net, available_on, payout_id, etc.

3. `swca_c3_gf_payment_transaction` (211 rows)
   - Gravity Forms payment records
   - Fields: id, lead_id, transaction_id, transaction_type, amount, date_created, payment_method, etc.

4. `wp_swca_bank_transactions` (203 rows)
   - Bank CSV imports
   - Fields: id, post_date, description, debit, credit, balance, etc.

5. `swca_c3_transaction_matches` (350 rows)
   - Match records linking above tables
   - Fields: id, stripe_transaction_id, bank_transaction_id, gf_transaction_id, match_type, match_confidence, notes, matched_by, matched_at

**Bank Statement Tables:**
6. `wp_swca_bank_statements` (31 rows)
   - Monthly statement metadata
   - Fields: id, statement_period_start, statement_period_end, starting_balance, ending_balance, total_credits, total_debits

#### Acceptance Criteria
- [x] All tables documented
- [x] Column purposes explained
- [x] Relationships clear
- [x] Migration history included

---

### 2.7 Treasurer Workflow Documentation (Est: 1 hour)
**Complexity:** Simple
**Dependencies:** 2.2, 2.3, 2.4

**File:** `/docs/workflows/TREASURER_WORKFLOW.md`

#### Action Items
- [ ] Document monthly workflow
  1. Download bank CSV
  2. Upload to WordPress admin
  3. Run Stripe sync
  4. Verify matching results
  5. Review Transaction Ledger
  6. Generate reports for board meeting
- [ ] Create monthly checklist
- [ ] Document what to do when things go wrong
  - CSV won't upload → Check format
  - Stripe sync fails → Check API key
  - Matching incomplete → Review unmatched transactions
- [ ] Document troubleshooting steps
- [ ] Include screenshots/examples

#### Acceptance Criteria
- [x] Complete workflow documented
- [x] Checklist provided
- [x] Troubleshooting guide included

---

### 2.8-2.11 Additional Documentation (Est: 2-3 hours total)

**Files to Create:**
- `/docs/technical/KNOWN_ISSUES.md` - Current limitations and workarounds
- `/docs/DEVELOPMENT_GUIDELINES.md` - Code style, testing, standards
- `/docs/api/API_REFERENCE.md` - All public functions, actions, filters
- `/docs/README.md` - Master index of all documentation

#### Acceptance Criteria
- [x] All documentation files created
- [x] Master index complete
- [x] Cross-references working

---

## Task 3: Dashboard Restructuring

### 3.1 Create Treasurer's Tools Feature File (Est: 2 hours)
**Complexity:** Moderate
**Dependencies:** None

**File:** `/public_html/wp-content/plugins/501c3PO/includes/features/treasurer-tools-landing.php`

#### Action Items
- [ ] Create new PHP file for Treasurer's Tools
- [ ] Implement menu registration (priority 21)
- [ ] Create landing page function
- [ ] Implement 3-section layout:
  - Data Import (Bank CSV, Stripe Sync)
  - Transaction Management (Matching, Annotations)
  - Reports & Ledger (Ledger, Reports, Statements)
- [ ] Style with consistent buttons and cards
- [ ] Add breadcrumb navigation
- [ ] Test in WordPress admin

#### Acceptance Criteria
- [x] New menu item appears in admin
- [x] Landing page displays correctly
- [x] All links work
- [x] Styling consistent with WordPress admin

---

### 3.2 Update Main Plugin File (Est: 30 minutes)
**Complexity:** Simple
**Dependencies:** 3.1

**File:** `/public_html/wp-content/plugins/501c3PO/501c3po.php`

#### Action Items
- [ ] Add `treasurer-tools-landing.php` to includes list
- [ ] Ensure it loads before feature modules
- [ ] Test plugin activation

#### Acceptance Criteria
- [x] Treasurer's Tools loads correctly
- [x] No PHP errors
- [x] Plugin still activates

---

### 3.3 Update Feature Files (Remove Direct Menu Items) (Est: 1-2 hours)
**Complexity:** Moderate
**Dependencies:** 3.1

**Files to Modify:**
- `/includes/features/unified-transactions.php` - Comment out menu registration
- `/includes/features/grouped-transactions.php` - Comment out menu registration

#### Action Items
- [ ] Comment out (don't delete) menu registrations
- [ ] Add comment explaining accessed via Treasurer's Tools
- [ ] Ensure functionality still works when accessed via direct URL
- [ ] Update any hardcoded links to use new structure

#### Acceptance Criteria
- [x] Removed items don't appear in main menu
- [x] Functionality still works via Treasurer's Tools
- [x] No broken links

---

### 3.4 Create Membership Management Stub (Est: 30 minutes)
**Complexity:** Simple
**Dependencies:** None

**File:** `/public_html/wp-content/plugins/501c3PO/includes/features/membership-stub.php`

#### Action Items
- [ ] Create placeholder menu item
- [ ] Create simple page explaining "Coming in Phase 2"
- [ ] Add to plugin includes

#### Acceptance Criteria
- [x] Menu item present
- [x] Page explains future feature
- [x] No broken functionality

---

### 3.5 Test Complete Menu Structure (Est: 1 hour)
**Complexity:** Simple
**Dependencies:** All 3.x tasks

#### Action Items
- [ ] Navigate through all menu items
- [ ] Verify all links work
- [ ] Check breadcrumb navigation
- [ ] Test on different screen sizes
- [ ] Verify no console errors
- [ ] Check for PHP warnings

#### Acceptance Criteria
- [x] All menu items functional
- [x] Navigation intuitive
- [x] No errors or warnings
- [x] Responsive design works

---

## Task 4: Future Additions Page

### 4.1 Create Future Additions Feature File (Est: 1 hour)
**Complexity:** Simple
**Dependencies:** None

**File:** `/public_html/wp-content/plugins/501c3PO/includes/features/future-additions.php`

#### Action Items
- [ ] Create new PHP file
- [ ] Implement menu registration (priority 23)
- [ ] Create informational page with:
  - Planned features list
  - Status for each (Phase 2, Phase 3, Evaluating)
  - Suggestion form
- [ ] Style consistently
- [ ] Add to plugin includes

#### Acceptance Criteria
- [x] Menu item appears
- [x] Page displays correctly
- [x] Suggestion form works
- [x] Consistent styling

---

## Task 5: Master Development Plan

### 5.1 Create Phased Roadmap (Est: 2 hours)
**Complexity:** Moderate
**Dependencies:** All previous tasks

**File:** `/docs/DEVELOPMENT_ROADMAP.md`

#### Action Items
- [ ] Document Phase 1 (Treasurer's Tools)
  - All tasks from above
  - Estimated timeline
  - Deliverables
- [ ] Outline Phase 2 (Membership Management)
  - High-level features
  - Dependencies on Phase 1
  - Estimated timeline
- [ ] Outline Phase 3 (Extended Features)
  - High-level features
  - Optional vs required
  - Estimated timeline
- [ ] Create Gantt chart or timeline visual
- [ ] Document success metrics

#### Acceptance Criteria
- [x] Complete Phase 1 plan
- [x] Phase 2 and 3 outlined
- [x] Timeline realistic
- [x] Success metrics defined

---

### 5.2 Create Issue Tracking System (Est: 1 hour)
**Complexity:** Simple
**Dependencies:** 5.1

#### Action Items
- [ ] Set up GitHub Issues with labels:
  - `phase-1`, `phase-2`, `phase-3`
  - `bug`, `enhancement`, `documentation`
  - `priority-high`, `priority-medium`, `priority-low`
- [ ] Create issues for all Phase 1 tasks
- [ ] Assign to milestones
- [ ] Link related issues

#### Acceptance Criteria
- [x] GitHub Issues configured
- [x] All Phase 1 tasks tracked
- [x] Labels applied consistently

---

## Summary: All Tasks at a Glance

| Task | Complexity | Est. Time | Dependencies |
|------|------------|-----------|--------------|
| 1.1 Audit Scripts | Simple | 2h | None |
| 1.2 Create Directories | Simple | 15m | 1.1 |
| 1.3 Move Diagnostic | Simple | 30m | 1.2 |
| 1.4 Move Deprecated | Simple | 30m | 1.2 |
| 1.5 Add Documentation | Moderate | 2-3h | 1.1 |
| 2.1 Doc Directory | Simple | 15m | None |
| 2.2 Bank CSV Doc | Moderate | 2h | 2.1 |
| 2.3 Stripe Sync Doc | Moderate | 2h | 2.1 |
| 2.4 Matching Doc | Complex | 3h | 2.1 |
| 2.5 Data Flow Doc | Moderate | 1.5h | 2.1 |
| 2.6 Schema Doc | Moderate | 1.5h | 2.1 |
| 2.7 Workflow Doc | Simple | 1h | 2.2-2.4 |
| 2.8-2.11 Additional Docs | Moderate | 2-3h | Various |
| 3.1 Treasurer's Tools File | Moderate | 2h | None |
| 3.2 Update Main Plugin | Simple | 30m | 3.1 |
| 3.3 Update Features | Moderate | 1-2h | 3.1 |
| 3.4 Membership Stub | Simple | 30m | None |
| 3.5 Test Menu | Simple | 1h | All 3.x |
| 4.1 Future Additions | Simple | 1h | None |
| 5.1 Roadmap | Moderate | 2h | All |
| 5.2 Issue Tracking | Simple | 1h | 5.1 |

**Total Estimated Time:** 28-33 hours
**Recommended Timeline:** 3 weeks (part-time) or 1 week (full-time)

---

## Next Steps

1. **Review this task list**
2. **Approve approach**
3. **Begin Task 1.1: Audit Scripts**
4. **Create progress tracking spreadsheet**
5. **Schedule daily check-ins**

---

**Document Status:** Ready for Execution
**Last Updated:** October 16, 2025
**Next Action:** Begin Task 1.1
