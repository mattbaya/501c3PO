# 501c3PO Documentation Index

**Last Updated:** October 17, 2025

This document provides a comprehensive index of all documentation for the 501c3PO WordPress plugin.

## Quick Navigation

### Recent Updates (October 2025)
- **[LEDGER_IMPROVEMENTS_OCT17.md](LEDGER_IMPROVEMENTS_OCT17.md)** - Comprehensive ledger overhaul (Oct 17, 2025)
- **[TABLE_NAMING_FIX_COMPLETE.md](TABLE_NAMING_FIX_COMPLETE.md)** - Standardization of c3_ table prefix (Oct 13, 2025)
- **[DUPLICATE_FIX_COMPLETE.md](DUPLICATE_FIX_COMPLETE.md)** - Duplicate transaction cleanup (Oct 17, 2025)

### Core Documentation
- **[CLAUDE.md](/home/swca/scripts/501c3PO/CLAUDE.md)** - Main development knowledge base (primary reference)
- **[README.md](README.md)** - Plugin overview and basic setup
- **[TRANSACTION_MATCHING_SYSTEM.md](TRANSACTION_MATCHING_SYSTEM.md)** - Detailed matching algorithm documentation

### Planning & Future Work
- **[ledger-improvements-plan.md](ledger-improvements-plan.md)** - Implementation roadmap for ledger features
- **[docs/EXECUTIVE_SUMMARY.md](/home/swca/scripts/501c3PO/docs/EXECUTIVE_SUMMARY.md)** - High-level reorganization plan
- **[docs/PHASE1_REORGANIZATION_PLAN.md](/home/swca/scripts/501c3PO/docs/PHASE1_REORGANIZATION_PLAN.md)** - Detailed Phase 1 plan
- **[docs/DETAILED_TASK_LIST.md](/home/swca/scripts/501c3PO/docs/DETAILED_TASK_LIST.md)** - Task-by-task breakdown

### Historical Documentation
- **[DATABASE_BACKUP_INFO.md](DATABASE_BACKUP_INFO.md)** - Database backup procedures
- **[MERGE_SUMMARY.md](MERGE_SUMMARY.md)** - Code merge documentation
- **[RUN_MATCHING_INSTRUCTIONS.md](RUN_MATCHING_INSTRUCTIONS.md)** - How to run transaction matching

---

## Documentation by Topic

### Transaction Management

#### Transaction Ledger
**Primary Files:**
- `includes/features/transaction-ledger.php` - Main ledger implementation
- `LEDGER_IMPROVEMENTS_OCT17.md` - Complete change log for Oct 17 overhaul

**Key Features (as of Oct 17, 2025):**
- ✅ Intuitive color scheme (green=deposits, blue=expenses, yellow=unmatched)
- ✅ Sticky header for easy scrolling
- ✅ Print & CSV export functionality
- ✅ Default 13-month date filter
- ✅ Stripe transaction links
- ✅ Bank statement reconciliation
- ✅ Inline editing for notes/categories/tags
- ✅ Running balance calculations

**Access:** WordPress Admin > Membership Management > 📒 Transaction Ledger

#### Transaction Matching
**Primary Files:**
- `includes/features/transaction-matching.php` - Matching algorithm
- `TRANSACTION_MATCHING_SYSTEM.md` - Detailed algorithm documentation

**Match Types:**
1. **Stripe ↔ Gravity Forms** - 100% accuracy (30-second time window)
2. **Bank ↔ Stripe Payouts** - Amount + date matching
3. **Batch Payout Detection** - Groups multiple charges

**Access:** WordPress Admin > Membership Management > 🔗 Match Transactions

#### Bank Transactions
**Primary Files:**
- `includes/features/bank-transactions.php` - CSV import and management
- `includes/features/calculate-balances.php` - Running balance calculation

**Features:**
- CSV import with duplicate prevention
- Running balance calculation
- Monthly statement generation
- Bank reconciliation

**Access:** WordPress Admin > Membership Management > 🏦 Bank Transactions

#### Stripe Integration
**Primary Files:**
- `includes/features/stripe-sync.php` - Stripe API sync
- `includes/features/view-stripe-transaction.php` - Detail viewer

**Features:**
- Automatic Stripe data sync
- Payout tracking
- Fee calculation
- Refund handling

**Access:** WordPress Admin > Membership Management > 💳 Stripe Sync

### Database Schema

#### Table Naming Convention (as of Oct 13, 2025)
**Standard:** All tables use `$wpdb->prefix . 'c3_'` format

**Current Tables:**
- `swca_c3_stripe_transactions` (223 rows) - Stripe API data
- `swca_c3_stripe_balance_transactions` (407 rows) - Stripe balance history
- `swca_c3_gf_payment_transaction` (211 rows) - Gravity Forms payments
- `swca_c3_transaction_matches` (343 rows) - Match records
- `swca_c3_bank_transactions` (197 rows) - Bank CSV imports with running balances
- `swca_c3_bank_statements` (21 rows) - Monthly statement metadata

**Migration Status:**
- ✅ Phase 1 Complete - All transaction tables migrated to c3_ prefix
- ⏳ Phase 2 Pending - Member/Committee tables (future work)

**Documentation:** See `TABLE_NAMING_FIX_COMPLETE.md` for full migration details

### Development Guidelines

#### Git Workflow
**Repositories:**
1. **Plugin Repository:** `/home/swca/public_html/wp-content/plugins/501c3PO/`
   - Branch: `main`
   - Remote: `git@github.com:mattbaya/501c3PO.git`

2. **Scripts/Documentation Repository:** `/home/swca/scripts/501c3PO/`
   - Branch: `master`
   - Remote: `git@github.com:mattbaya/501c3PO.git`
   - Contains: CLAUDE.md and planning docs

**Commit Format:**
- Clear, descriptive title
- Detailed summary of changes
- Include file paths and line numbers when relevant
- Use emoji for commit type (optional): ✅ ⚠️ ❌ 📄 🔧

#### Code Standards
- Use WordPress coding standards
- Prefix all functions with `five01c3po_`
- Use `$wpdb->prefix . 'c3_'` for all plugin tables
- Always sanitize input, escape output
- Add nonce verification for forms
- Document complex logic with inline comments

#### Testing Checklist
Before committing major changes:
1. ✅ PHP syntax validation (`php -l filename.php`)
2. ✅ Test on production site
3. ✅ Verify no JavaScript console errors
4. ✅ Check mobile responsiveness
5. ✅ Test print output if applicable
6. ✅ Verify CSV export if applicable
7. ✅ Update documentation

### Troubleshooting

#### Common Issues

**Problem:** Duplicate transactions in ledger
- **Solution:** See `DUPLICATE_FIX_COMPLETE.md`
- **Prevention:** Duplicate checks in import code, UNIQUE database constraints

**Problem:** Balance calculations don't match
- **Solution:** Run "Calculate Balances" with correct starting balance
- **Tool:** WordPress Admin > Membership Management > 💰 Calculate Balances

**Problem:** Stripe transactions not matching to bank
- **Cause:** Payout dates may not align with bank deposit dates
- **Solution:** Check payout_arrival_date in Stripe data, may need manual matching

**Problem:** Missing Stripe data
- **Solution:** Run Stripe Sync to fetch latest transactions
- **Access:** WordPress Admin > Membership Management > 💳 Stripe Sync

#### Debug Mode
To enable debug output:
1. Edit `wp-config.php`
2. Set `define('WP_DEBUG', true);`
3. Set `define('WP_DEBUG_LOG', true);`
4. Check `/wp-content/debug.log` for errors

---

## Recent Changes Timeline

### October 17, 2025 - Major Ledger Improvements
**Commits:**
- `8cd2ccf` - MAJOR IMPROVEMENT: Comprehensive Transaction Ledger Overhaul
- `c49d523` - Update documentation with October 17 ledger improvements

**Changes:**
- Fixed balance display order consistency
- Implemented intuitive color scheme
- Added Stripe transaction links
- Added sticky header
- Added print & export functionality
- Set default 13-month filter
- Improved empty cell handling
- Updated legend and UI

**Documentation:**
- ✅ `LEDGER_IMPROVEMENTS_OCT17.md` created
- ✅ `ledger-improvements-plan.md` created
- ✅ `CLAUDE.md` updated with Oct 17 section
- ✅ All changes committed and pushed

### October 13, 2025 - Table Naming Standardization
**Commits:**
- `c051930` - FINAL FIX: Standardize all code to use c3_ table prefix

**Changes:**
- Migrated all transaction tables to c3_ prefix
- Updated all code references
- Dropped obsolete wp_swca_ tables
- Added database constraints

**Documentation:**
- ✅ `TABLE_NAMING_FIX_COMPLETE.md` created
- ✅ `CLAUDE.md` updated

### October 6, 2025 - Transaction Ledger Launch
**Initial deployment of unified transaction ledger**

---

## Contact & Support

**Developer:** Claude (AI Assistant via Claude Code)
**Repository:** https://github.com/mattbaya/501c3PO
**Documentation Location:** `/home/swca/scripts/501c3PO/` (main docs)
**Plugin Location:** `/home/swca/public_html/wp-content/plugins/501c3PO/`

**For Questions:**
1. Check CLAUDE.md first (comprehensive knowledge base)
2. Review specific topic documentation above
3. Check git commit history for context: `git log --oneline`
4. Review inline code comments in relevant files

---

## Quick Reference Commands

```bash
# Check plugin git status
cd /home/swca/public_html/wp-content/plugins/501c3PO && git status

# Check scripts git status
cd /home/swca/scripts/501c3PO && git status

# View recent commits
git log --oneline -10

# Push changes
git push origin main  # (or master for scripts repo)

# Check PHP syntax
php -l /path/to/file.php

# Database queries
mysql -u swca_swca2019 -p'5Corners!' swca_swca2019 -e "YOUR QUERY"

# WordPress CLI
wp plugin list --allow-root
wp db query --allow-root "YOUR QUERY"
```

---

**This index is maintained to help future developers (human or AI) quickly navigate the 501c3PO documentation.**

**Last Major Update:** October 17, 2025 - Added comprehensive ledger improvements documentation
