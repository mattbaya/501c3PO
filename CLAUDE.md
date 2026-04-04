# 501c3PO - Development Reference

## Production Environment
- **Site**: https://southwilliamstown.org
- **Admin**: https://southwilliamstown.org/wp-admin
- **Plugin**: `/home/swca/public_html/wp-content/plugins/501c3PO/`
- **Dev Repo**: `/home/swca/scripts/501c3PO/` (branch: `main`)
- **Server**: Shared hosting (lightning.svaha.com), CageFS/CloudLinux
- **PHP**: 8.3 (set via `cloudlinux-selector` for CLI and web)
- **Database**: `swca_swca2019` (user: `swca_swca2019`, password: `5Corners!`)
- **WordPress Multisite**: Table prefix is `swca_` (NOT `wp_`)
- **GitHub**: `git@github.com:mattbaya/501c3PO.git` (SSH, user: mattbaya)

**IMPORTANT**: This is a LIVE PRODUCTION site. Always test carefully.

## Deploying Changes
Both repos point to the same GitHub remote. After editing in the dev repo:
```bash
# Copy changed files to production
cp /home/swca/scripts/501c3PO/path/to/file.php /home/swca/public_html/wp-content/plugins/501c3PO/path/to/file.php

# Or sync everything
rsync -av --exclude='.git' --exclude='node_modules' --exclude='temp' \
  /home/swca/scripts/501c3PO/ /home/swca/public_html/wp-content/plugins/501c3PO/
```

## WP-CLI
```bash
# WP-CLI uses PHP 8.3 via CloudLinux selector (configured 2026-04-04)
wp eval "echo phpversion();" --allow-root --path=/home/swca/public_html
```

## Board Portal Password
- **Password**: `POBox432`
- **Stored in**: `five01c3po_organization_settings` > `dashboard_password`
- **Protects**: `/board-portal` and all descendant pages
- **Code**: `includes/core/roles.php` > `five01c3po_dashboard_password_protection()`
- **NEVER CHANGE WITHOUT EXPLICIT USER PERMISSION**

## Stripe API Key (Encrypted)
- **Table**: `swca_options` (NOT `wp_options` - multisite!)
- **Option**: `five01c3po_organization_settings`
- **Fields**: `stripe_api_key_encrypted` (AES-256-CBC), `stripe_passphrase_hash` (bcrypt)
- **Format**: `base64(IV::encrypted_data)`

## Architecture

### Design Principles
- **Modular features**: Each feature is an independent file in `includes/features/`
- **No cross-dependencies**: Features must work independently - enabling/disabling one feature must NOT break another
- **Single plugin**: No splitting into sub-plugins. Feature toggles handle enable/disable
- **All admin via WordPress**: No CLI required for day-to-day operations
- **Convention**: Functions prefixed `five01c3po_`, tables use `$wpdb->prefix . 'c3_'` for new tables

### Plugin Structure
```
501c3po.php                          # Main plugin file, loads features
includes/
  core/
    database.php                     # Table creation schemas
    shortcodes.php                   # Frontend shortcodes for board portal
    roles.php                        # User roles + board portal password protection
    dashboard.php                    # Board portal page creation
  features/
    member-management.php            # Member CRUD admin UI (WP_List_Table)
    data-export-import.php           # CSV import/export
    stripe-integration.php           # Stripe API sync
    transaction-ledger.php           # Master financial ledger
    transaction-matching.php         # Auto-match Stripe/GF/Bank records
    bank-transactions.php            # Bank CSV import
    calculate-balances.php           # Running balance calculator
    bank-statements.php              # Monthly statement generation
    view-stripe-transaction.php      # Stripe transaction detail page
    view-bank-transaction.php        # Bank transaction detail page
    income-expense-graph.php         # Financial charts
    year-over-year-comparison.php    # Annual comparison reports
    expense-breakdown.php            # Expense categorization
    unified-transactions.php         # Combined transaction view
    grouped-transactions.php         # Payout-grouped view
    future-improvements.php          # Roadmap dashboard page
    email-management.php             # (placeholder - being built)
    event-management.php             # (placeholder - being built)
    financial-management.php         # (placeholder)
    officer-tools.php                # (placeholder)
    volunteer-management.php         # (placeholder)
```

### Database Tables

**Member Data:**
- `swca_swca_members` (197 rows) - Core member records with multi-year status tracking

**Financial Data (c3_ prefix):**
- `swca_c3_stripe_transactions` (223 rows) - Stripe charges with payout dates
- `swca_c3_stripe_balance_transactions` (407 rows) - Stripe balance history
- `swca_c3_gf_payment_transaction` (211 rows) - Gravity Forms payments
- `swca_c3_transaction_matches` (343 rows) - Cross-system match records
- `swca_c3_bank_transactions` (203 rows) - Bank CSV imports with running balances
- `swca_c3_bank_statements` (31 rows) - Monthly statement metadata

**Other Tables (defined in database.php, use `$wpdb->prefix`):**
- `{prefix}financial_transactions` - Income/expense tracking
- `{prefix}emails` / `{prefix}email_recipients` - Email campaigns (schema ready, UI pending)
- `{prefix}events` / `{prefix}event_rsvps` - Events (schema ready, UI pending)
- `{prefix}volunteer_slots` / `{prefix}volunteer_signups` - Volunteers (schema ready, UI pending)
- `{prefix}committees` / `{prefix}committee_members` / `{prefix}committee_reports` - Committees
- `{prefix}agendas` / `{prefix}minutes` - Meeting management
- `{prefix}documents` - Document management
- `{prefix}calendar_settings` - API config storage

### Feature Toggles
Stored in `five01c3po_enabled_features` option. Core features (member management, dashboard) are always on. Optional features toggled via Settings page.

### User Roles
- **SWCA Member** - Dashboard and directory access
- **SWCA Officer** - Manage members, events, communications
- **SWCA Treasurer** - Full financial access
- **SWCA Committee Chair** - Committee management

## Current Membership Data
- **Table**: `swca_swca_members`
- **197 members**: 128 Family, 68 Individual
- **Status tracking**: `status_2024_2025`, `status_2023_2024`
- **Email list**: `on_swca_email_list` (179 opted in, 18 opted out)
- **Admin UI**: WordPress Admin > 501c3PO > Members (add/edit/delete/search/filter)

## Transaction Ledger
- **Access**: Admin > 501c3PO > Transaction Ledger
- **Shows**: Complete money trail (Customer > Stripe > Bank)
- **Formula**: `Net = Amount - Stripe Fee - Refunded`
- **Color scheme**: Green=matched deposits, Blue=matched expenses, Yellow=unmatched
- **Features**: Sticky header, print/CSV export, inline editing, 13-month default filter

## Board Portal (Frontend)
- **URL**: `/board-portal` (password protected)
- **Pages**: current-membership, historical-membership, member-directory, stats, fiscal-table, financial/, settings, email-dashboard, event-dashboard, officer-tools
- **Shortcodes**: `[member_stats]`, `[member_directory]`, `[member_current_list]`, `[member_historical_list]`, `[member_fiscal_table]`, `[member_dashboard_grid]`

## What's Being Built (see TODO.md)
1. **Member CRUD** - Done (deployed 2026-04-04)
2. **Mailing List Management** - Named lists, member assignment
3. **Bulk Email System** - Standalone (not dependent on Mailerpress or any 3rd party)
4. **Email Inbox Monitor** - AI-powered inbound email processing
5. **REST API + MCP** - For AI agent access
6. **Unsubscribe/Bounce handling**
7. **Comprehensive documentation**

## Testing Rules
- **Always test frontend** - Don't rely on backend/PHP tests alone
- **Verify browser output** - curl the actual page or use browser testing
- **Syntax check**: `php -l /path/to/file.php`
- **Check WP loads**: `wp eval "echo 'OK';" --allow-root --path=/home/swca/public_html`

## Temp Files
Use `/home/swca/public_html/wp-content/plugins/501c3PO/temp/` for temporary scripts. Do NOT use `/tmp`.

## Security Warnings
- 4 temp scripts in `temp/` contain hardcoded Stripe passphrase (should be removed)
- Never commit `.env` or API keys to git
- Always use nonces for form submissions
- Sanitize all input, escape all output
