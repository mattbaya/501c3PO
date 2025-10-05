# SWCA Supplementary Tools

These are administrative tools to be used alongside the main SWCA Membership Management plugin.

## Directory Structure

### stripe-tools/
- `stripe_refund_safe.php` - Process Stripe refunds and update member totals
  - Prompts for API key (never stores it)
  - Downloads recent transactions
  - Shows detailed analysis before updating
  - Requires confirmation before database changes

### import-tools/
- `import_2023_members.php` - Import historical membership data
  - Imports from CSV file format
  - Matches existing members by name/email
  - Adds historical status columns
  - Updates membership history

## Usage Instructions

### Stripe Refund Processing
```bash
# Run from WordPress root directory
php /path/to/stripe_refund_safe.php
```

### Historical Data Import
```bash
# First prepare your CSV file with columns:
# Month, Last Name, First Name, Partner Last, Partner First, Family Members,
# Address, City, State, Zip, Phone, Membership Amount, Donation Amount,
# Type, Email1, Email2, Email3, Email4

# Place CSV at: /home/developer/2023_members.csv
# Then run:
php /path/to/import_2023_members.php
```

## Security Notes

- Never commit these files with hardcoded API keys
- Run only from secure, authenticated sessions
- Always backup database before running import scripts
- These tools require WordPress admin capabilities

## Requirements

- WordPress 5.0+
- PHP 7.4+
- SWCA Membership Management plugin activated
- Database backup before running any scripts