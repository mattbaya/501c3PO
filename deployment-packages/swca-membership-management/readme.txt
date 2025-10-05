=== SWCA Membership Management ===
Contributors: SWCA
Tags: membership, crm, nonprofit, member-management
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete membership management system for non-profit organizations with modular features.

== Description ==

SWCA Membership Management System is a comprehensive WordPress plugin designed for non-profit organizations to manage their membership data, events, communications, and financial records.

= Core Features (Always Available) =
* Member Management - Complete member profiles with categories and tags
* Dashboard System - Password-protected dashboard
* Individual Member Profiles - Comprehensive member history
* Data Export/Import - Full database migration support
* Role-Based Access Control - Member, Officer, Treasurer, and Committee Chair roles

= Modular Features (Enable as Needed) =
* Email Management - Bulk email with approval workflow
* Event Management - Create events with RSVP tracking
* Volunteer Signups - SignUpGenius-style coordination
* Financial Management - Income/expense tracking with Stripe integration
* Officer Tools - Meeting agendas and minutes
* Committee Management - Committee structure and reports
* Document Management - File uploads with Google Drive integration

== Installation ==

1. Upload the `swca-membership-management` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to CRM > SWCA Dashboard to begin setup
4. Configure your dashboard password and enable desired features

== Configuration ==

= Initial Setup =
1. Go to /dashboard/settings after activation
2. Set your dashboard password (default: F1v3C0rn3rs)
3. Enable/disable modular features as needed
4. Configure API keys for integrations (Stripe, Google)

= User Roles =
The plugin creates custom roles:
* SWCA Member - Basic dashboard access
* SWCA Officer - Approve emails, create events
* SWCA Treasurer - Full access including financials
* SWCA Committee Chair - Committee management

== Frequently Asked Questions ==

= How do I migrate data to a new server? =
Use the Export & Import feature in CRM > Export & Import to create a complete data package.

= Can I disable features I don't need? =
Yes, visit /dashboard/settings to toggle individual features on/off.

= Is the financial data secure? =
Yes, all sensitive data is encrypted and access is role-based.

== Changelog ==

= 2.0.0 =
* Complete rewrite with modular architecture
* Added comprehensive import/export functionality
* Improved dashboard interface
* Added multi-year membership tracking
* Enhanced security and role management

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Major update with new features and improved architecture. Backup your data before upgrading.