# WordPress Membership Management System

A comprehensive WordPress plugin for managing organizational memberships with modular features.

## Features

### Core Features (Always Enabled)
- **Member Management** - Complete member profiles with custom fields
- **Dashboard System** - Password-protected member dashboard
- **Data Export/Import** - Full database migration and CSV export
- **Role-Based Access Control** - Custom WordPress roles for different permission levels

### Optional Modular Features
Each feature can be enabled/disabled via the Settings dashboard:

- **📧 Email Management** - Bulk email system with approval workflow
- **🎉 Event Management** - Event creation with RSVP tracking
- **🙋 Volunteer Signups** - Coordinate volunteer opportunities
- **💰 Financial Management** - Track income, expenses, and payment processing
- **🏛️ Officer Tools** - Meeting agendas, minutes, and document management
- **👥 Committee Management** - Committee structure and membership tracking
- **📁 Document Management** - File organization with cloud storage integration

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin panel
3. Configure your organization settings under Membership > Settings
4. Set up your dashboard password for member access

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