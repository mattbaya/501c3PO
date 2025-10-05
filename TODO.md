# 501c3PO Modularization TODO List

## Project Overview
Modularizing the monolithic SWCA plugin (6,547 lines) into the new 501c3PO modular architecture.

**Monolithic File**: `swca-membership-export-corrected.php` (280KB)
**Modular Location**: `wordpress-membership-management/`
**Goal**: Extract features into separate modules under `includes/features/`

---

## ✅ COMPLETED

### 1. Analysis & Planning
- [x] Analyzed monolithic plugin structure (6,547 lines)
- [x] Identified feature boundaries and section markers
- [x] Mapped out module dependencies

### 2. Core Infrastructure
- [x] Created modular plugin structure in `wordpress-membership-management/`
- [x] Built core functionality:
  - `501c3po.php` - Main plugin file with auto-loading
  - `includes/core/database.php` - Member tables & schema
  - `includes/core/roles.php` - Custom WordPress roles
  - `includes/core/dashboard.php` - Dashboard system
  - `includes/core/shortcodes.php` - Shortcode handlers
  - `includes/class-update-checker.php` - GitHub auto-updates
  - `includes/class-cli-commands.php` - WP-CLI integration

### 3. Feature Modules Extracted
- [x] **Email Management** (`includes/features/email-management.php`)
  - Database tables: swca_emails, swca_email_recipients, swca_email_templates
  - Bulk email campaigns with approval workflow
  - Email scheduling and tracking
  - Shortcodes: `mm_email_dashboard`, backward compatible with `swca_email_dashboard`

- [x] **Event Management** (`includes/features/event-management.php`)
  - Database tables: swca_events, swca_event_rsvps, swca_volunteer_slots, swca_volunteer_signups, swca_calendar_settings
  - Event creation and RSVP system
  - Volunteer coordination (SignUpGenius-style)
  - Google Calendar integration placeholder
  - Shortcodes: `mm_event_dashboard`, backward compatible with `swca_event_dashboard`

- [x] **Financial Management** (`includes/features/financial-management.php`)
  - Database tables: swca_financial_transactions, swca_financial_reports
  - Income/expense tracking with Stripe integration
  - Financial reports and analytics
  - Fee calculation and tracking
  - Shortcodes: `mm_financial_dashboard`, backward compatible with `swca_financial_transactions`

---

## 🚧 IN PROGRESS

### 4. Officer Tools Module
**Status**: Need to complete extraction
**Source**: Lines 4318-4900+ in monolithic file
**Target**: `includes/features/officer-tools.php`

**Tables to create**:
- `swca_agendas` - Meeting agendas
- `swca_minutes` - Meeting minutes with approval workflow
- `swca_documents` - Document management
- `swca_drive_folders` - Google Drive folder mapping
- `swca_committees` - Committee structure
- `swca_committee_members` - Committee membership
- `swca_committee_reports` - Committee reports

**Functions to extract**:
- `mm_create_officer_tools_tables()`
- `mm_officer_tools_dashboard_handler()`
- `mm_create_agenda()`
- `mm_create_minutes()`
- `mm_generate_financial_report()`
- `mm_upload_to_google_drive()`
- `mm_initialize_drive_folders()`
- `mm_initialize_default_committees()`
- `mm_get_current_fiscal_year()`

---

## 📋 REMAINING TASKS

### 5. Volunteer Management Module
**File**: `includes/features/volunteer-management.php`
**Note**: Currently integrated into event-management.php, may need separate module for non-event volunteer tracking

### 6. Update Database Schema
**File**: `wordpress-membership-management/includes/core/database.php`

**Actions needed**:
- Add function `mm_initialize_feature_toggles()` - Feature enable/disable system
- Add function `mm_is_feature_enabled($feature_name)` - Check if feature is active
- Ensure all feature table creation is called on activation
- Add migration support for upgrading from monolithic version

### 7. Update Main Plugin File
**File**: `wordpress-membership-management/501c3po.php`

**Actions needed**:
- Update activation hooks to call all feature table creation functions:
  ```php
  register_activation_hook(__FILE__, 'mm_create_email_tables');
  register_activation_hook(__FILE__, 'mm_create_event_tables');
  register_activation_hook(__FILE__, 'mm_create_financial_tables');
  register_activation_hook(__FILE__, 'mm_create_officer_tools_tables');
  ```
- Ensure feature files are loaded conditionally based on feature toggles
- Add backward compatibility for old shortcode names

### 8. Settings & Feature Toggle System
**Source**: Lines 3765-3900 in monolithic file
**Target**: `includes/core/settings.php` or integrate into existing core files

**Functions to extract**:
- `mm_initialize_feature_toggles()` - Set default enabled features
- `mm_is_feature_enabled($feature_name)` - Check feature status
- `mm_update_feature_toggles($features)` - Save feature settings
- `mm_get_all_features()` - Return all available features with metadata
- `mm_settings_dashboard_handler()` - Settings page shortcode

**Feature list**:
- email_management
- event_management
- financial_management
- volunteer_management
- officer_tools
- committee_management
- document_management

### 9. Additional Shortcodes to Migrate
From monolithic file, ensure these are in modular version:
- `swca_stats_handler()` → `mm_stats_handler()` (Line 654)
- `swca_list_handler()` → `mm_list_handler()` (Line 690)
- `swca_fiscal_table_handler()` → `mm_fiscal_table_handler()` (Line 1188)
- `swca_current_membership_handler()` → `mm_current_membership_handler()` (Line 1474)
- `swca_historical_membership_handler()` → `mm_historical_membership_handler()` (Line 1669)
- `swca_member_profile_handler()` → `mm_member_profile_handler()` (Line 2144)
- `swca_member_directory_handler()` → `mm_member_directory_handler()` (Line 2552)
- `swca_dashboard_grid_handler()` → `mm_dashboard_grid_handler()` (Line 3304)
- `swca_renewal_graph_handler()` → `mm_renewal_graph_handler()` (Line 5000)
- `swca_data_migration_handler()` → `mm_data_migration_handler()` (Line 5253)

**Target**: `includes/core/shortcodes.php`

### 10. Admin Menu Pages
**Source**: Lines 771-6547 (various admin functions)
**Target**: `includes/core/admin-pages.php` or similar

**Functions to extract**:
- `mm_add_export_menu()` - Admin menu creation
- `mm_admin_dashboard()` - Main dashboard page
- `mm_export_page()` - Export functionality
- `mm_financial_page()` - Financial management page
- `mm_stripe_page()` - Stripe integration page
- `mm_member_tools_page()` - Member tools page

### 11. Data Export/Import System
**Source**: Lines 5253-6417 in monolithic file
**Target**: `includes/features/data-migration.php` or integrate into core

**Functions to extract**:
- `mm_export_complete_database()`
- `mm_generate_sql_backup()`
- `mm_export_sql_backup()`
- `mm_export_complete_data_with_progress()`
- `mm_import_data_with_progress()`
- AJAX handlers for export/import

### 12. Testing & Validation
- [ ] Test plugin activation from scratch
- [ ] Test feature toggle system (enable/disable modules)
- [ ] Test all shortcodes render correctly
- [ ] Test database table creation
- [ ] Test admin menu pages
- [ ] Test data export/import functionality
- [ ] Test backward compatibility with old shortcode names
- [ ] Test role-based permissions
- [ ] Verify no undefined function errors
- [ ] Test on fresh WordPress installation

### 13. Documentation Updates
- [ ] Update README.md with new architecture
- [ ] Document feature module API
- [ ] Document how to add new features
- [ ] Update deployment guide
- [ ] Create migration guide from monolithic to modular version

### 14. Deployment Preparation
- [ ] Create production ZIP package
- [ ] Test installation on clean WordPress
- [ ] Verify database migration from old version
- [ ] Update version numbers
- [ ] Tag release in Git repository
- [ ] Update GitHub releases

---

## 📝 IMPORTANT NOTES

### Naming Convention
- **Old prefix**: `swca_` (monolithic)
- **New prefix**: `mm_` (membership management / modular)
- **Maintain backward compatibility**: Keep old shortcode names as aliases

### Database Table Prefixes
- Use `$wpdb->prefix` for dynamic prefix support
- All tables use prefix: `{$prefix}swca_` (e.g., `wp_swca_members`)
- Fixed hardcoded `wp_` references in monolithic version - use `{$prefix}` in modular

### Feature Toggle System
All features check if enabled before rendering:
```php
if (!mm_is_feature_enabled('email_management')) {
    return '<div>Feature disabled message</div>';
}
```

### Code Quality Standards
- All input sanitized: `sanitize_text_field()`, `sanitize_email()`, `wp_kses_post()`
- All output escaped: `esc_html()`, `esc_attr()`, `esc_url()`
- Use prepared statements: `$wpdb->prepare()`
- Check capabilities before executing privileged actions

### Git Status
**Repository**: `wordpress-membership-management/`
**Branch**: main
**Status**: Clean working tree (as of last check)
**Commits**: 6 commits completed

---

## 🔍 REFERENCE FILES

**Monolithic plugin**: `/home/swca/scripts/501c3PO/swca-membership-export-corrected.php`
**Modular plugin**: `/home/swca/scripts/501c3PO/wordpress-membership-management/`
**Deployment packages**: `/home/swca/scripts/501c3PO/deployment-packages/`
**Project docs**: `/home/swca/scripts/501c3PO/CLAUDE.md`, `PROJECT.md`, `README.md`

---

## ⚡ NEXT STEPS (Priority Order)

1. **Complete Officer Tools module** - Extract remaining functions from lines 4318-4900
2. **Create Settings module** - Extract feature toggle system (lines 3765-3900)
3. **Update core/shortcodes.php** - Add all missing shortcode handlers
4. **Update 501c3po.php** - Add all activation hooks and conditional feature loading
5. **Test activation** - Ensure plugin activates without errors
6. **Verify all features load** - Test each module independently
7. **Create production package** - Build deployment ZIP
8. **Document changes** - Update README and migration guide

---

## 📊 PROGRESS METRICS

**Total Lines**: 6,547 lines in monolithic file
**Lines Extracted**: ~3,500 lines (email, events, financial modules)
**Completion**: ~53% complete
**Remaining**: Officer tools, settings, shortcodes, admin pages, export/import

**Files Created**: 7 feature files
**Database Tables**: 15+ tables across all features
**Shortcodes**: 15+ shortcodes to migrate

---

*Last Updated: 2025-10-03*
*Generated during modularization effort from monolithic SWCA plugin to 501c3PO modular architecture*