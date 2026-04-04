# 501c3PO Documentation Index

**Last Updated:** April 4, 2026

## Primary Documentation
| File | Purpose |
|------|---------|
| [CLAUDE.md](CLAUDE.md) | Development reference: environment, architecture, conventions |
| [README.md](README.md) | Plugin overview, features, installation, configuration |
| [TODO.md](TODO.md) | Project roadmap and task tracking |

## Technical Documentation
| File | Purpose |
|------|---------|
| [TRANSACTION_MATCHING_SYSTEM.md](TRANSACTION_MATCHING_SYSTEM.md) | Matching algorithm: Stripe/GF/Bank reconciliation logic |
| [LEDGER_IMPROVEMENTS_OCT17.md](LEDGER_IMPROVEMENTS_OCT17.md) | Ledger overhaul: color scheme, print/export, sticky header |

## Historical Fix Logs
These document one-time fixes that have been applied. Kept for reference only.

| File | What it documents |
|------|-------------------|
| [TABLE_NAMING_FIX_COMPLETE.md](TABLE_NAMING_FIX_COMPLETE.md) | Migration to c3_ table prefix (Oct 2025) |
| [DUPLICATE_FIX_COMPLETE.md](DUPLICATE_FIX_COMPLETE.md) | Duplicate transaction cleanup (Oct 2025) |
| [MERGE_SUMMARY.md](MERGE_SUMMARY.md) | Feature merge from swca-simple-export-import |
| [DATABASE_BACKUP_INFO.md](DATABASE_BACKUP_INFO.md) | Backup procedures and locations |
| [RUN_MATCHING_INSTRUCTIONS.md](RUN_MATCHING_INSTRUCTIONS.md) | How to run the transaction matching algorithm |
| [ledger-improvements-plan.md](ledger-improvements-plan.md) | Original planning doc for ledger improvements |

## Code Structure
```
501c3po.php                    # Main plugin, feature loading, admin menu, settings
includes/core/                 # Always-loaded core files
  database.php                 # Table schemas (dbDelta)
  shortcodes.php               # Board portal frontend shortcodes
  roles.php                    # User roles + password protection
  dashboard.php                # Board portal page creation on activation
includes/features/             # Independently toggleable feature modules
  member-management.php        # Member CRUD (WP_List_Table)
  transaction-ledger.php       # Master financial ledger
  stripe-integration.php       # Stripe API sync
  bank-transactions.php        # Bank CSV import
  transaction-matching.php     # Cross-system matching
  calculate-balances.php       # Running balance calculator
  data-export-import.php       # CSV import/export
  ...                          # See README.md for full list
```

## Quick Reference
- **Production site**: https://southwilliamstown.org
- **GitHub repo**: https://github.com/mattbaya/501c3PO
- **Plugin location**: `/home/swca/public_html/wp-content/plugins/501c3PO/`
- **Dev repo**: `/home/swca/scripts/501c3PO/`
