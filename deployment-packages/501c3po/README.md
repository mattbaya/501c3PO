# 501c3PO - Non-Profit Membership Management Plugin

**A complete, organization-neutral membership management system for non-profit organizations built as a WordPress plugin.**

## Overview

501c3PO is a comprehensive membership management solution designed for neighborhood associations, community groups, and similar non-profit organizations. The plugin is **completely organization-neutral** and configures itself to your specific organization during the initial setup wizard.

## Features

### Core Features (Always Available)
- **Member Management** - Complete member profiles with categories and tags
- **Dashboard System** - Password-protected member dashboard
- **Individual Member Profiles** - Comprehensive member history and contact details
- **Data Export/Import** - Complete migration packages and CSV export functionality
- **Web-Based Administration** - All tools accessible via WordPress admin interface
- **Role-Based Access Control** - Custom roles for members, officers, treasurers, and committee chairs

### Optional Modular Features
Each feature can be enabled/disabled via the Settings dashboard:

- 📧 **Email Management** - Bulk email with approval workflow and scheduling
- 🎉 **Event Management** - RSVP system with Google Calendar integration
- 🙋 **Volunteer Signups** - SignUpGenius-style volunteer coordination
- 💰 **Financial Management** - Income/expense tracking with Stripe integration
- 🏛️ **Officer Tools** - Agendas, minutes, and administrative documents
- 👥 **Committee Management** - Committee structure and membership tracking
- 📁 **Document Management** - File upload with Google Drive organization

## Installation

1. **Upload Plugin**
   - Download the plugin ZIP file
   - In WordPress admin, go to Plugins → Add New → Upload Plugin
   - Upload the ZIP file and click "Install Now"
   - Activate the plugin

2. **Complete Setup Wizard**
   - After activation, you'll see a notice to complete setup
   - Click "Setup NPO Plugin" or go to the setup wizard
   - Enter your organization details:
     - **Organization Full Name** (e.g., "South Williamstown Community Association")
     - **Organization Abbreviation** (e.g., "SWCA")
     - **Database Table Prefix** (e.g., "swca") - used for database tables
     - **Organization Slug** (e.g., "swca") - used in URLs and file names
     - **Dashboard Password** - password to access the member dashboard
   - Click "Complete Setup & Configure Plugin"

3. **Post-Setup Configuration**
   - Import your member data via CRM → Data Import Tools
   - Enable/disable features in Dashboard → Settings
   - Add API keys for optional integrations (Stripe, Google Calendar, Gmail, etc.)

## Configuration

### Organization Settings

The plugin stores all organization-specific settings in the WordPress options table. These settings control:

- Organization name and branding
- Database table prefixes (`wp_{prefix}_members`, `wp_{prefix}_events`, etc.)
- WordPress role names ("{ORG} Member", "{ORG} Officer", etc.)
- Dashboard password
- All user-facing text and display names

### Database Tables

The plugin creates 15+ tables based on your organization prefix:

- `wp_{prefix}_members` - Core member data
- `wp_{prefix}_financial_transactions` - Income/expense tracking
- `wp_{prefix}_emails` - Email campaign management
- `wp_{prefix}_events` - Event management with RSVPs
- `wp_{prefix}_volunteer_slots` - Volunteer opportunities
- And more...

### WordPress Roles

Four custom roles are created based on your organization name:

- **{ORG} Member** - Basic access to dashboard and member directory
- **{ORG} Officer** - Can approve emails, create events, manage member profiles
- **{ORG} Treasurer** - Full access including financial data and API key management
- **{ORG} Committee Chair** - Committee management, agendas, minutes, and reports

## API Integrations

All integrations are optional and can be configured in Settings:

- **Stripe API** - Payment tracking and fee calculation
- **Google Calendar API** - Automatic event creation
- **Gmail API** - OAuth-based bulk email sending
- **Google Drive API** - Automatic document organization
- **Google Analytics** - Usage tracking and reporting

## Migration from SWCA

If you're migrating from an existing SWCA installation:

1. The setup wizard will detect existing SWCA tables
2. Form fields will be pre-filled with SWCA settings
3. Your existing data will be preserved
4. The plugin will use your existing database tables

## Support

For issues, feature requests, or questions:
- GitHub: https://github.com/mattbaya/501c3PO
- Documentation: See INSTALLATION_GUIDE.md for detailed setup instructions

## License

This plugin is licensed under GPL v3 or later.

## Credits

**Plugin Name:** 501c3PO
**Description:** Non-Profit Membership Management
**Version:** 2.0.0
**Author:** Matt Baya
**Requires:** WordPress 5.0+
**Tested up to:** WordPress 6.8
**PHP Version:** 7.4+

---

**Built with ❤️ for non-profit organizations**
