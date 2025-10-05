=== SWCA Membership Management ===
Contributors: Claude Code
Tags: membership, non-profit, management, export, import
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.1.4
License: GPL v2 or later

Complete membership management system for non-profit organizations with export/import functionality.

== Description ==

SWCA Membership Management is a comprehensive WordPress plugin designed for non-profit organizations to manage their membership data, financial records, and organizational activities.

= Key Features =

* **Member Management** - Complete member profiles with categories and tags
* **Dashboard System** - Password-protected dashboard with role-based access
* **Data Export/Import** - CSV export and complete database migration tools
* **Financial Integration** - Stripe payment tracking and fee calculation
* **Email Management** - Bulk email system with approval workflow
* **Event Management** - RSVP tracking and volunteer coordination
* **Officer Tools** - Meeting agendas, minutes, and document management

= Recent Updates =

* Fixed JavaScript syntax error in import progress page
* Improved web-based administrative tools
* Enhanced export/import functionality for server migration
* Added comprehensive error handling and validation

= Dashboard Access =

After activation, access the SWCA Dashboard at: `/dashboard`
Default password: `F1v3C0rn3rs` (configurable in settings)

= Admin Menu =

The plugin adds several admin menu items:
- SWCA Export/Import
- Stripe Refunds
- Data Import Tools
- SWCA Dashboard

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/swca-membership-management/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. The plugin will automatically create required database tables
4. Access the dashboard at your-site.com/dashboard

== Frequently Asked Questions ==

= How do I migrate data from another server? =

1. Go to WordPress Admin > SWCA Export/Import
2. Click "Export Complete Database (ZIP)" to create a backup
3. On the new server, install this plugin
4. Use the import feature to upload and restore your data

= How do I change the dashboard password? =

The dashboard password can be configured in the plugin settings. Look for the password field in the SWCA settings page.

= Is this plugin secure? =

Yes, the plugin includes:
- Nonce verification for all forms
- Role-based access control
- Secure file upload handling
- Input sanitization and validation

== Changelog ==

= 2.1.4 =
* DIAGNOSTIC TOOLS: Added comprehensive diagnostic script for troubleshooting
* Fixed string interpolation bugs in import functions 
* Enhanced page creation to check for existing pages
* Added proper table prefix handling throughout plugin
* Improved error detection and logging
* Includes DIAGNOSTIC_SCRIPT.php for production troubleshooting

= 2.1.3 =
* CRITICAL FIX: Fixed all hardcoded table prefixes (89 instances)
* Fixed "No members found" error after import
* All database queries now use proper WordPress table prefixes
* Member directory, stats, and all pages now work correctly
* Compatible with any WordPress table prefix (not just wp_)
* Tested with custom table prefixes and confirmed working

= 2.1.2 =
* FULLY WORKING IMPORT - Import now actually processes and imports data
* Replaced complex AJAX workflow with immediate processing
* Real member data import with CSV parsing and database insertion
* Proper backup creation before import
* Settings import for plugin configuration
* Simple success/error pages with clear feedback
* Tested and verified: imports members successfully

= 2.1.1 =
* Fixed file input detection in import progress page 
* Resolved "Please select a file to import" error
* Enhanced AJAX import processing with proper error handling
* Added server-side import validation and progress tracking
* Improved frontend import workflow with better user feedback

= 2.1.0 =
* Fixed critical JavaScript syntax error in import progress page
* Enhanced export/import functionality for server migrations
* Added comprehensive browser testing and validation
* Improved error handling in import process
* Updated deployment package structure

= 2.0.0 =
* Complete rewrite with modular architecture
* Added web-based administrative tools
* Integrated Stripe refund processing
* Enhanced member data import capabilities
* Added role-based permission system

= 1.0.0 =
* Initial release
* Basic member management functionality
* CSV export capabilities

== Upgrade Notice ==

= 2.1.0 =
Critical JavaScript fix for import functionality. Recommended for all users planning server migrations.