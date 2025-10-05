# 501c3PO - Non-Profit Membership Management System

🏛️ **A complete, organization-neutral membership management system for non-profit organizations built as a WordPress plugin.**

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://www.php.net/)

## Overview

501c3PO is a comprehensive membership management solution designed for neighborhood associations, community groups, and similar non-profit organizations. The plugin is **completely organization-neutral** and configures itself to your specific organization during an initial setup wizard.

Unlike other membership plugins, 501c3PO adapts to YOUR organization:
- ✅ **Custom Branding** - Uses your organization name throughout
- ✅ **Dynamic Database** - Table names based on your org prefix
- ✅ **Custom Roles** - WordPress roles named after your organization
- ✅ **Flexible Features** - Enable only what you need

## Key Features

### Core Features (Always Available)
- **Member Management** - Complete member profiles with categories and tags
- **Dashboard System** - Password-protected member dashboard
- **Individual Member Profiles** - Comprehensive member history and contact details
- **Data Export/Import** - Complete migration packages and CSV export
- **Web-Based Administration** - All tools accessible via WordPress admin
- **Role-Based Access Control** - Custom roles automatically created for your org

### Optional Modular Features
Enable/disable via Settings dashboard:

- 📧 **Email Management** - Bulk email with approval workflow and scheduling
- 🎉 **Event Management** - RSVP system with Google Calendar integration
- 🙋 **Volunteer Signups** - SignUpGenius-style volunteer coordination
- 💰 **Financial Management** - Income/expense tracking with Stripe integration
- 🏛️ **Officer Tools** - Agendas, minutes, and administrative documents
- 👥 **Committee Management** - Committee structure and membership tracking
- 📁 **Document Management** - File upload with Google Drive organization

## Quick Start

### Installation

1. **Download the Plugin**
   ```bash
   # Clone the repository
   git clone https://github.com/mattbaya/501c3PO.git
   cd 501c3PO
   ```

2. **Deploy to WordPress**
   - Upload `deployment-packages/nonprofit-membership-management.zip` to your WordPress site
   - Or install via: Plugins → Add New → Upload Plugin
   - Activate the plugin

3. **Complete Setup Wizard**
   - After activation, click "Setup NPO Plugin"
   - Enter your organization details:
     - Organization Full Name (e.g., "South Williamstown Community Association")
     - Organization Abbreviation (e.g., "SWCA")
     - Database Prefix (e.g., "swca")
     - Organization Slug (e.g., "swca")
     - Dashboard Password
   - Click "Complete Setup"

4. **Configure Your System**
   - Import your members via CRM → Data Import Tools
   - Enable features in Dashboard → Settings
   - Add API keys for integrations (optional)

## Configuration

### Organization Settings

The plugin stores organization-specific settings in WordPress options:

```php
// Example configuration (set via setup wizard)
'org_name' => 'Your Community Association',
'org_short_name' => 'YCA',
'org_prefix' => 'yca',  // Database tables: wp_yca_members
'org_slug' => 'yca',    // URLs: yoursite.com/yca/dashboard
'dashboard_password' => 'your-secure-password'
```

### Database Tables

The plugin creates 15+ tables with your custom prefix:

- `wp_{prefix}_members` - Member data
- `wp_{prefix}_financial_transactions` - Finances
- `wp_{prefix}_emails` - Email campaigns
- `wp_{prefix}_events` - Event management
- `wp_{prefix}_volunteer_slots` - Volunteers
- And more...

### WordPress Roles

Four custom roles are automatically created:

- **{ORG} Member** - Basic access
- **{ORG} Officer** - Manage emails, events, members
- **{ORG} Treasurer** - Full financial access
- **{ORG} Committee Chair** - Committee management

## API Integrations (Optional)

Configure via Dashboard → Settings:

- **Stripe** - Payment tracking and fee calculation
- **Google Calendar** - Automatic event creation
- **Gmail** - OAuth-based bulk email
- **Google Drive** - Document organization
- **Google Analytics** - Usage tracking

## Development

### Prerequisites

- Docker and Docker Compose
- PHP 7.4+
- WordPress 5.0+
- MariaDB or MySQL

### Development Environment

```bash
# Start development container
docker-compose up -d

# Access container
docker exec -it almalinux-dev-container bash

# Start WordPress development server
cd /home/developer/wordpress-test/wordpress
php -S 0.0.0.0:8080

# Access at http://localhost:8080
```

### Environment Variables

Create a `.env` file (not committed to git):

```bash
# Organization Configuration (for development/testing)
ORG_NAME="Your Organization Name"
ORG_SHORT_NAME="YON"
ORG_PREFIX="yon"
ORG_SLUG="your-org"
DASHBOARD_PASSWORD="your-password"

# API Keys
GEMINI_API_KEY=your_key
OPENAI_API_KEY=your_key
CLAUDE_API_KEY=your_key
```

## Project Structure

```
501c3PO/
├── deployment-packages/
│   └── nonprofit-membership-management/
│       ├── nonprofit-membership-management.php  # Main plugin file
│       ├── includes/
│       │   ├── config-helpers.php              # Configuration utilities
│       │   └── setup-wizard.php                # First-run setup
│       └── README.md
├── includes/
│   ├── config-helpers.php      # Configuration helper functions
│   └── setup-wizard.php        # WordPress setup wizard
├── refactor-to-generic.php     # Refactoring script
├── CLAUDE.md                   # Development environment docs
└── README.md                   # This file
```

## Deployment

### Production Deployment

1. **Package Location:** `deployment-packages/nonprofit-membership-management.zip`
2. **Size:** ~60KB
3. **Requirements:** WordPress 5.0+, PHP 7.4+, MySQL/MariaDB

### Web-Based Administration

All tools accessible via WordPress admin (no SSH required):

| Tool | Location | Purpose |
|------|----------|----------|
| Setup Wizard | Setup NPO Plugin | Initial configuration |
| Dashboard | /dashboard | Member portal |
| Data Import | CRM → Data Import | CSV uploads |
| Export/Import | CRM → Export & Import | Data migration |
| Settings | Dashboard → Settings | Feature toggles |

## Migration from SWCA

If migrating from an existing SWCA installation:

1. Setup wizard will detect existing SWCA tables
2. Form pre-fills with SWCA settings
3. Existing data is preserved
4. Plugin uses existing database tables

## Documentation

- **Installation Guide:** `deployment-packages/INSTALLATION_GUIDE.md`
- **Development Guide:** `CLAUDE.md`
- **API Documentation:** Coming soon

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v3 License - see the LICENSE file for details.

## Credits

**Author:** Matt Baya
**Version:** 2.0.0
**Repository:** https://github.com/mattbaya/501c3PO

## Support

- **Issues:** [GitHub Issues](https://github.com/mattbaya/501c3PO/issues)
- **Discussions:** [GitHub Discussions](https://github.com/mattbaya/501c3PO/discussions)

---

**Built with ❤️ for non-profit organizations**
