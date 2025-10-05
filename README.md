# 501c3PO - The Nonprofit Droid You're Looking For

A comprehensive WordPress plugin for managing nonprofit organizations with modular features. May the force be with your fundraising!

## Features

### Core Features (Always Enabled)
- **Member Management** - Complete member profiles with custom fields
- **Dashboard System** - Password-protected member dashboard
- **Data Export/Import** - Full database migration and CSV export
- **Role-Based Access Control** - Custom WordPress roles for different permission levels
- **Transaction Matching** - AI-powered matching across Stripe, Gravity Forms, and Bank data

### Optional Modular Features
Each feature can be enabled/disabled via the Settings dashboard:

- **📧 Email Management** - Bulk email system with approval workflow
- **🎉 Event Management** - Event creation with RSVP tracking
- **🙋 Volunteer Signups** - Coordinate volunteer opportunities
- **💰 Financial Management** - Track income, expenses, and payment processing
  - **Stripe Integration** - Sync all historical Stripe transactions with AES-256 encrypted API keys
  - **Bank Transaction Import** - CSV import for reconciliation
  - **Unified Transactions View** - See all transactions from all sources in one place
  - **Intelligent Matching** - Auto-match transactions across systems (100% accuracy for Stripe ↔ Gravity Forms)
- **🏛️ Officer Tools** - Meeting agendas, minutes, and document management
- **👥 Committee Management** - Committee structure and membership tracking
- **📁 Document Management** - File organization with cloud storage integration

## Installation

### Via Git (Recommended for easy updates)
```bash
cd wp-content/plugins
git clone https://github.com/mattbaya/501c3PO.git
```

### Traditional WordPress Installation
1. Download the plugin ZIP file
2. Upload via WordPress admin panel (Plugins > Add New > Upload)

## Updating

### Method 1: Automatic WordPress Updates
The plugin checks for updates from GitHub and integrates with WordPress's update system. You'll see update notifications in your WordPress admin panel.

### Method 2: WP-CLI Command
```bash
wp 501c3po update
```

### Method 3: Git Command
```bash
cd wp-content/plugins/501c3PO
git pull origin main
```

### Method 4: Update Script
```bash
cd wp-content/plugins/501c3PO
./update.sh
```

## Configuration

### Initial Setup
1. Go to **Membership > Settings**
2. Enter your organization name
3. Set a secure dashboard password
4. Enable/disable features as needed
5. Configure API integrations (if using)

### User Roles
The plugin creates four custom roles:
- **Member** - Basic dashboard and directory access
- **Officer** - Can manage members, events, and communications
- **Treasurer** - Full financial management access
- **Committee Chair** - Committee-specific management

## Usage

### Member Dashboard
- Accessible at `/dashboard` (password protected)
- Provides member directory, statistics, and feature access
- Customizable based on enabled features

### Admin Interface
- Access via WordPress admin menu under "Membership"
- Export/import tools for data management
- Settings for all modular features

### Shortcodes
- `[member_stats]` - Display membership statistics
- `[member_directory]` - Show member directory
- `[member_dashboard]` - Embed dashboard interface

## Development

### Database Tables
The plugin creates the following tables:
- `{prefix}members` - Core member data
- `{prefix}financial_transactions` - Financial records
- `{prefix}emails` - Email campaigns
- `{prefix}events` - Event management
- Additional tables for each enabled feature

### Hooks and Filters
- `mm_admin_dashboard_widgets` - Add custom dashboard widgets
- `mm_member_fields` - Customize member data fields
- `mm_export_columns` - Modify export data columns

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher

## Support

For issues, feature requests, or contributions, please use the GitHub repository.

## License

GPL v2 or later