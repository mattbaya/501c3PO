# Database Backup Information

## Location
Database backups are stored in this directory with the naming pattern:
```
database-backup-YYYYMMDD-HHMMSS.sql.gz
```

## Latest Backup
- **Date**: October 13, 2025
- **File**: `database-backup-20251013-072751.sql.gz`
- **Size**: 6.0 MB (compressed)
- **Database**: swca_swca2019

## What's Included
This backup contains the complete 501c3PO financial management system:

### Transaction Tables
- `swca_stripe_transactions` - 222 Stripe transactions (Jul 2019 - Oct 2025)
- `swca_gf_addon_payment_transaction` - 211 Gravity Forms payments
- `wp_swca_bank_transactions` - 203 bank transactions with running balances
- `wp_swca_bank_statements` - 31 monthly bank statements
- `swca_transaction_matches` - 343 transaction matches (100% Stripe matched)
- `swca_stripe_balance_transactions` - 404 Stripe balance records

### Member & Organization Tables
- `wp_swca_members` - Member database
- All other WordPress and plugin tables

## Matching Status (as of Oct 13, 2025)
- **Stripe Transactions**: 222/222 matched (100%)
- **Bank Deposits**: 57/130 matched (43.8% - all Stripe deposits)
- **Gravity Forms**: Matched via transaction_matches table

## Creating a New Backup
Use the backup script:
```bash
bash /home/swca/scripts/501c3PO/create-database-backup.sh
```

## Restoring from Backup
```bash
gunzip -c database-backup-YYYYMMDD-HHMMSS.sql.gz | mysql -u swca_swca2019 -p swca_swca2019
```

**⚠️ WARNING**: Database backups contain sensitive financial data. Do NOT commit to version control!

## Backup Schedule
- Manual backups via script
- Recommended: Create backup before major changes
- Backups are automatically excluded from git (.gitignore)

## Notes
- Backups use `--single-transaction` for consistency
- Compressed with gzip for space efficiency
- All tables included (WordPress + 501c3PO plugin)
