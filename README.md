<p align="center">
  <img src="501c3POlogo.png" alt="501c3PO Logo" width="400">
</p>

# 501c3PO - The Nonprofit Droid You're Looking For

A comprehensive WordPress plugin for managing nonprofit organizations. Modular features can be independently enabled or disabled to fit your organization's needs.

## Features

### Core (Always Enabled)
- **Member Management** - Full CRUD: add, edit, delete, search, and filter members from WordPress admin
- **Member Directory** - Password-protected board portal with member listings and statistics
- **Data Export/Import** - CSV import/export for member data migration
- **Role-Based Access** - Custom WordPress roles: Member, Officer, Treasurer, Committee Chair
- **Dashboard System** - Password-protected board portal with configurable URL slug

### Financial Management
- **Transaction Ledger** - Complete money trail: Customer > Stripe > Bank with color-coded status
- **Stripe Integration** - Sync charges, payouts, and refunds with AES-256 encrypted API key storage
- **Bank Reconciliation** - Import bank CSV, calculate running balances, monthly statements
- **Transaction Matching** - Auto-match across Stripe, Gravity Forms, and bank records (100% accuracy for Stripe/GF)
- **Financial Reports** - Income/expense graphs, year-over-year comparison, expense breakdown
- **Print & Export** - Print-optimized B&W output, CSV export of filtered data

### Communication (In Development)
- **Bulk Email** - Standalone email system via `wp_mail()` with optional SMTP configuration
- **Mailing Lists** - Named lists (All Members, Board, Press Release) with per-member assignment
- **Email Inbox Monitor** - AI-powered: check a dedicated inbox, parse requests from approved senders, execute actions
- **Unsubscribe Handling** - CAN-SPAM compliant with signed unsubscribe links

### Additional Modules
- **Event Management** - Events with RSVP tracking and volunteer coordination
- **Officer Tools** - Meeting agendas, minutes, document management
- **Committee Management** - Committee structure, membership, and reporting
- **Document Management** - File organization with cloud storage integration

### Planned
- **REST API** - API endpoints for all plugin features with key-based authentication
- **MCP Server** - Model Context Protocol integration for AI agent access

## Installation

### Via Git (Recommended)
```bash
cd wp-content/plugins
git clone https://github.com/mattbaya/501c3PO.git
```

### WordPress Upload
1. Download the latest release ZIP
2. Go to Plugins > Add New > Upload Plugin

## Updating

```bash
# Option 1: Git pull
cd wp-content/plugins/501c3PO
git pull origin main

# Option 2: WP-CLI
wp 501c3po update

# Option 3: Automatic
# The plugin checks GitHub for updates and integrates with WordPress's update system
```

## Configuration

1. Go to **501c3PO > Settings** in WordPress admin
2. Set your organization name and board portal password
3. Enable/disable features as needed
4. Configure Stripe API key (encrypted storage) if using financial features

### Feature Independence
Each feature module operates independently. Enabling or disabling one feature has no effect on others. There are no cross-dependencies between modules.

## Board Portal

The plugin creates a password-protected frontend portal for board members:
- `/board-portal` - Main dashboard with navigation grid
- `/board-portal/member-directory` - Contact directory
- `/board-portal/current-membership` - Current year members (color-coded paid/unpaid)
- `/board-portal/historical-membership` - Multi-year tracking
- `/board-portal/stats` - Membership statistics
- `/board-portal/financial/` - Financial reports and analysis

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[member_stats]` | Membership statistics summary |
| `[member_directory]` | Member contact directory |
| `[member_current_list]` | Current year member table |
| `[member_historical_list]` | Multi-year status table |
| `[member_fiscal_table]` | Fiscal year revenue analysis |
| `[member_dashboard_grid]` | Feature navigation grid |

## Database

All new plugin tables use the `{prefix}c3_` naming convention for consistency across environments.

### Table Naming
- New tables: `$wpdb->prefix . 'c3_tablename'` (e.g., `swca_c3_stripe_transactions`)
- Legacy member table: `$wpdb->prefix . 'swca_members'`

## Requirements

- WordPress 5.0+
- PHP 8.0+ (8.3 recommended)
- MySQL 5.7+ / MariaDB 10.3+

## Development

See [CLAUDE.md](CLAUDE.md) for development environment details and [TODO.md](TODO.md) for the project roadmap.

### Code Conventions
- Function prefix: `five01c3po_`
- Table prefix: `$wpdb->prefix . 'c3_'`
- WordPress coding standards
- Nonce verification on all forms
- Input sanitization and output escaping

## License

GPL v2 or later
