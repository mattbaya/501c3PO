# Claude Flow Development Environment

## Overview
This container is configured for enterprise WordPress plugin development using Claude Flow v2.0.0-alpha.101 with multi-agent coordination capabilities.

## Environment Setup

### Node.js & Claude Flow
- **Node.js**: Upgraded from 16.20.2 to 20.19.4 (required for Claude Flow)
- **Claude Flow**: v2.0.0-alpha.101 installed globally
- **Dependencies**: better-sqlite3, selenium installed

### WordPress Development Stack
- **PHP**: 8.0.30 with development server capability
- **MariaDB**: Full installation (replaced MySQL conflicts)
- **Database**: `wordpress` (user: `wp_user`, pass: `wp_password`)
- **WordPress**: Latest version, auto-configured
- **WP-CLI**: Installed for automation
- **Chromium**: v139 for testing and validation

### Production Environment
- **Site URL**: https://southwilliamstown.org
- **WordPress Admin**: https://southwilliamstown.org/wp-admin
- **Plugin Location**: `/home/swca/public_html/wp-content/plugins/501c3PO/`
- **Server**: Shared hosting (lightning.svaha.com)
- **Database**: swca_swca2019 (user: swca_swca2019, password: 5Corners!)

**IMPORTANT**: This is a LIVE PRODUCTION site - always test carefully!

### Git/GitHub Configuration
- **GitHub Username**: mattbaya
- **SSH Key**: Configured and working on this system
- **Repository**: 501c3PO plugin (WordPress non-profit management system)
- **Location**: `/home/swca/public_html/wp-content/plugins/501c3PO/`
- **Remote**: Uses SSH for push/pull operations
- **Branch**: main (default)

**IMPORTANT**: Always use git push/pull - SSH key is configured and works!

### Temporary Files
- **Temp Directory**: `/home/swca/public_html/wp-content/plugins/501c3PO/temp/`
- **DO NOT use /tmp** - files may be cleaned up or not accessible by WordPress
- Use plugin temp directory for all temporary files and scripts

## API Configuration

### Multi-Provider Setup (.env)
```bash
# API Keys for Claude Flow (stored in .env - not committed to git)
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_API_KEY_2=your_second_gemini_api_key_here
OPENAI_API_KEY=your_openai_api_key_here
CLAUDE_API_KEY=your_claude_api_key_here

# Claude Flow Configuration
CLAUDE_FLOW_WORKSPACE=/home/developer/workspace
CLAUDE_FLOW_MAX_AGENTS=12
CLAUDE_FLOW_DEFAULT_TOPOLOGY=hierarchical
```

### Provider Launchers
- **claude-flow-gemini**: Load-balanced across 2 Gemini accounts (FREE tier first)
- **claude-flow-claude**: Claude API (PAID)
- **claude-flow-chatgpt**: OpenAI API (PAID)

## WordPress Plugin Development

### Main Plugin: SWCA Membership Management System
- **Development Location**: `/home/developer/wordpress-test/wordpress/wp-content/plugins/swca-membership-export-corrected/`
- **Deployment Package**: `deployment-packages/swca-membership-management.zip`
- **Purpose**: Complete non-profit membership management with modular features
- **Architecture**: Modular system with feature toggles and web-based administration

### Core Features (Always Available)
- **Member Management** - Complete member profiles with categories and tags
- **Dashboard System** - Password-protected dashboard (`/dashboard` with password "F1v3C0rn3rs")
- **Individual Member Profiles** - Comprehensive member history and contact details
- **Data Export/Import** - Complete migration packages and CSV export functionality
- **Web-Based Administration** - All tools accessible via WordPress admin interface
- **Role-Based Access Control** - Member, Officer, Treasurer, and Committee Chair roles

### Modular Feature System
Each feature can be enabled/disabled via the Settings dashboard:

#### 📧 **Email Management** (Optional)
- Bulk email system with approval workflow
- Email scheduling for advance sending
- Status tracking: draft → pending approval → approved → scheduled → sent
- WYSIWYG editor with letterhead template integration
- Member group targeting and filtering

#### 🎉 **Event Management** (Optional)
- Create and manage events with full details
- RSVP system with guest count and dietary restrictions
- Google Calendar integration for automatic event creation
- Event types: meeting, social, tasting, educational, fundraiser
- Member invitation system

#### 🙋 **Volunteer Signups** (Optional)
- SignUpGenius-style volunteer coordination
- Time-based volunteer slots with skills requirements
- Emergency contact collection
- Confirmation tracking and show-up recording
- Integration with event management

#### 💰 **Financial Management** (Optional)
- Income and expense tracking with categories
- Stripe integration for payment history and fee tracking
- **Web-based Stripe refund processing** with API key security
- Financial reports (monthly, quarterly, annual, custom)
- Net vs gross income differentiation
- **Historical data import via web interface**
- **Transaction Matching** - AI-powered matching across Stripe API, Gravity Forms, and Bank CSV data
  - 100% accuracy for Stripe ↔ Gravity Forms matching (30-second time window)
  - Automatic deduplication and batch payout detection
  - See [MATCHING_ALGORITHM.md](MATCHING_ALGORITHM.md) for detailed matching logic
- **Unified Transactions View** - Combined view of all transaction sources with match indicators

#### 🏛️ **Officer Tools** (Optional)
- Meeting agenda creation and management
- Meeting minutes recording with approval workflow
- Administrative document management
- Financial report generation
- Integration with Google Drive for document storage

#### 👥 **Committee Management** (Optional)
- Committee structure management
- Committee membership tracking
- Committee-specific agendas and minutes
- Committee reports for board meetings
- Budget allocation tracking

#### 📁 **Document Management** (Optional)
- File upload with automatic Google Drive organization
- Fiscal year folder structure (2024-2025/committees/events/repair-cafe/)
- Document categorization and tagging
- Integration with meeting agendas and minutes

#### 🛠️ **Administrative Tools** (Web Interface)
- **Stripe Refund Processor** - Secure API key input, transaction analysis, and refund application
- **Historical Data Import** - CSV upload with preview and multi-year tracking
- **Complete Export/Import** - Full database migration packages
- **Renewal Analysis** - Multi-year membership comparison and renewal tracking

### Database Schema (15+ Tables)
- `wp_swca_members`: Core member data with categories and tags
- `wp_swca_financial_transactions`: Income/expense tracking with Stripe fees
- `wp_swca_emails`: Email campaign management
- `wp_swca_email_recipients`: Email delivery tracking
- `wp_swca_events`: Event management with RSVP tracking
- `wp_swca_event_rsvps`: RSVP responses and dietary requirements
- `wp_swca_volunteer_slots`: SignUpGenius-style volunteer opportunities
- `wp_swca_volunteer_signups`: Volunteer coordination
- `wp_swca_agendas`: Meeting agenda management
- `wp_swca_minutes`: Meeting minutes with approval workflow
- `wp_swca_committees`: Committee structure and information
- `wp_swca_committee_members`: Committee membership tracking
- `wp_swca_committee_reports`: Committee reports for board meetings
- `wp_swca_documents`: Document management with Google Drive integration
- `wp_swca_calendar_settings`: API keys and configuration storage
- `wp_swca_bank_statements`: Monthly bank statement metadata for reconciliation (Oct 7, 2025)
- `wp_swca_bank_transactions`: Bank CSV transaction data with running balances (Oct 7, 2025)

### Transaction Matching Tables (Production Database: `swca_swca2019`)
**CRITICAL:** Table prefixes are INCONSISTENT! Bank uses `wp_`, Stripe/matches use `swca_`

Production tables:
- `swca_stripe_transactions`: 221 rows (NO wp_!) - Stripe API data with payout dates
- `swca_gf_addon_payment_transaction`: 210 rows - Gravity Forms payment records
- `wp_swca_bank_transactions`: 89 rows (WITH wp_!) - Bank CSV imports
- `swca_transaction_matches`: 305 rows (NO wp_!) - Match records
- `swca_stripe_balance_transactions`: 404 rows - Stripe balance data

**Bank Deposit Filtering Rule:**
- **ONLY match deposits with "STRIPE" in description** - these are ACH transfers from Stripe
- Deposits without "STRIPE" = cash/checks, ignore for Stripe matching
- Examples to ignore: "Deposit", "Interest Credit", "ACH Credit BILL PMT"

**Transaction Matching Status (Oct 6, 2025):**
✓ **Gravity Forms → Stripe: 100% match rate**
  - Logic: Exact amount + timestamp within 30 seconds
  - GF records payment 3-6 seconds AFTER Stripe webhook

❌ **Bank → Stripe Payouts: Major Issues**
  - 89 total bank deposits, 32 already matched
  - 57 unmatched deposits:
    - ~51 are non-Stripe (cash/checks/interest) - IGNORE
    - **6 are Stripe ACH transfers with NO matching payout data:**
      - 2025-09-18: $34.28 (ST-B8K2D9X6K2N3)
      - 2025-09-12: $99.99 (ST-R5F8G7P6O9Z9)
      - 2025-09-02: $48.55 (ST-R8X7T9E9J0L6)
      - 2025-08-20: $34.75 (ST-S4Y7R5T9P7A0)
      - 2025-07-30: $50.00 (ST-X6J2N4W0N6N7)
      - 2025-07-29: $49.60 (ST-E5G3K3D1E7L7)

**Root Cause: Payout Date Mismatch**
- Similar amounts exist in Stripe table ($35, $50, $100)
- BUT payout_arrival_date doesn't match bank deposit dates
- Example: Bank shows 2025-09-18, Stripe shows same charge with payout 2025-06-11
- **Issue:** Either payout dates are incorrect OR these are different transactions

**Known Data Quality Issues:**
1. `net_amount` field was empty ($0.00) for refunded transactions - FIXED Oct 6, 2025
   - Now calculates: net_amount = amount - stripe_fee - amount_refunded
   - Refund-only payouts show negative net_amount (correct)
2. Some payout_arrival_date values don't match actual bank deposit dates
3. Possible incomplete Stripe data sync (missing recent transactions)

**Diagnostic Scripts Created (Oct 6, 2025):**
- `debug-bank-matching-standalone.php` - Main diagnostic showing unmatched bank deposits
- `check-db-tables.php` - Shows table row counts (discovered prefix inconsistency)
- `check-stripe-fields.php` - Analyzes Stripe table structure and data
- `fix-net-amount.php` - Fixed net_amount calculation for 8 refunded transactions
- `stripe-only-matching.php` - Filters to ONLY Stripe-labeled bank deposits (6 found)
- `find-orphan-stripe-txns.php` - Searches for missing Stripe data by amount

**RESOLUTION (Oct 6, 2025):**
✅ Payout data IS correct - all 221 transactions have accurate `payout_arrival_date` from Stripe API
✅ The 6 "unmatched" deposits are **REFUNDED TRANSACTIONS** (negative net_amount)
- Bank CSV shows initial deposit
- Stripe data shows final net (negative after refund + fees lost)
- Current filtering `WHERE net_amount > 0` correctly excludes these from revenue matching
- These deposits were later withdrawn via refunds in different payouts

**Bank Matching is Working Correctly For Revenue!**
- Filters out refunds (net ≤ 0) - correct for revenue tracking
- Matches only actual revenue transactions
- 32 of 89 bank deposits matched (36%)
- Remaining 57 = 51 non-Stripe (cash/checks/interest) + 6 refunds

**For Complete Bank Reconciliation (optional future feature):**
- Would need to match refunds separately using gross amounts
- Track both deposits AND withdrawals
- More complex but would show complete cash flow

### Transaction Ledger (NEW - Oct 6, 2025)
**Complete Money Trail:** Customer → Stripe → Bank Deposit

**Access:** Membership Management > 📒 Transaction Ledger

**Deployment & Cleanup (Oct 6, 2025):**

Deployed to production:
- ✅ `transaction-ledger.php` copied to `/wp-content/plugins/501c3PO/includes/features/`
- ✅ Updated `501c3po.php` to load Transaction Ledger

Removed from production (debug features):
- ❌ 12 debug feature files (check-*, test-*, show-*, stripe-fee-analysis, etc.)
- ❌ 15 debug scripts moved to `/diagnostic-scripts-oct6/` folder

**Menu Cleanup (Oct 6, 2025):** Removed 3 redundant menu items:
- ❌ "All Transactions" (replaced by Ledger)
- ❌ "Transaction Viewer" (replaced by Ledger)
- ❌ "Grouped Transactions" (payout grouping now in Ledger)

**CRITICAL FIX (Oct 6, 2025 - Accounting Accuracy):**
- ❗ **Math Error Fixed**: Net amounts were displaying incorrectly ($5.00 - $0.45 = $5.00 ❌)
- ✅ **Solution**: Changed line 77 from `s.net_amount` to `(s.amount - s.stripe_fee - s.amount_refunded) as net_amount`
- ✅ **Result**: All net amounts now calculated in real-time with correct formula
- ✅ **Audit Trail**: Added direct links to Stripe Dashboard, Payout IDs, and Bank Transaction IDs
- ✅ **Documentation**: Added accounting standards notice showing calculation formula
- 📄 **Full Details**: See `/public_html/LEDGER_FIXES_OCT6.md`

**Formula:** `Net = Amount - Stripe Fee - Refunded`
- Example: $5.00 - $0.45 - $0.00 = **$4.55** ✅ (was showing $5.00 ❌)
- Refund: $36.35 - $1.35 - $36.35 = **-$1.35** ✅ (fee loss on refunded transaction)

**DUPLICATE ENTRY FIX (Oct 6, 2025):**
- ❌ **Problem**: Stripe #1 appeared twice in ledger (exact duplicate rows)
- 🔍 **Root Cause**: Database had duplicate match records (Match IDs 215 & 236 both pointing Stripe #1 → Bank #1)
- ✅ **Fix 1 - Query**: Changed bank JOIN to use subquery with GROUP BY to eliminate duplicates
- ✅ **Fix 2 - Database**: Removed 2 duplicate match records (kept IDs 215 & 214, deleted 236 & 235)
- 📊 **Affected**: Only 2 transactions had duplicates (now fixed)
- **Query Change**: Lines 108-119 in `transaction-ledger.php` - uses `GROUP BY s.id` and subquery deduplication

**UI IMPROVEMENTS (Oct 6, 2025 - Two-Row Layout + Detail Pages):**
- ✅ **Two-Row Layout**: Each transaction now uses 2 rows for better readability
  - Row 1: Date, Amount, Fee, Refunded, Net, Payout, Bank, Status
  - Row 2: Full-width customer info and reference links
- ✅ **Transaction Detail Pages**: Click any reference to see complete details
  - `📄 Stripe #123` → Full Stripe transaction viewer with payout info and matches
  - `🏦 Bank #45` → Full bank transaction viewer showing all included Stripe payouts
  - Bank detail page includes reconciliation: Stripe Net Total vs Bank Deposit Amount
- ✅ **Internal Database Links**: All Stripe/Bank IDs now link to database detail pages
- ✅ **Payout Links**: External Stripe Dashboard links for official records
- **New Files**:
  - `includes/features/view-stripe-transaction.php` - Stripe transaction detail viewer
  - `includes/features/view-bank-transaction.php` - Bank transaction detail viewer with match reconciliation

**Remaining Transaction Menu Items:**
1. 📒 **Transaction Ledger** - Main view (complete money trail with accurate calculations)
2. 💳 **Stripe Sync** - Import Stripe data
3. 🏦 **Bank Transactions** - Import bank CSV
4. 🔗 **Match Transactions** - Run matching algorithm

**Features:**
- Shows every transaction with complete financial flow
- Customer name/email from Stripe data
- Stripe fee breakdown
- Refund tracking
- Payout date with days-to-payout calculation
- Bank deposit matching with arrival date
- Visual status indicators:
  - ✓ **In Bank** - Money is in account
  - ⏳ **Paid Out** - Stripe processed, arriving soon
  - ⏸ **Pending** - Awaiting Stripe payout (2 days)
  - 🔄 **Refunded** - Transaction refunded (shows fee loss)

**Filters:**
- Date range selection
- Minimum amount
- Customer name/email search

**Summary Statistics:**
- Total transactions count
- Bank match percentage
- Total gross revenue
- Total Stripe fees
- Total refunds
- Net revenue (color-coded green/red)

**Example Transaction Flow:**
```
Sep 21, 2025 5:10am - Customer pays $5.00
  - Stripe fee: -$0.45
  - Net: $5.00
  ↓ +3 days
Sep 24, 2025 - Stripe processes payout
  ↓ +1 day
Sep 25, 2025 - Bank receives $4.51
  ✓ In Bank
```

**Technical Implementation:**
- Single SQL query joins: Stripe ↔ Gravity Forms ↔ Bank ↔ Matches
- No table prefix issues (uses correct `swca_` and `wp_` prefixes)
- Handles refunds with negative net_amount
- Calculates days between each step
- File: `includes/features/transaction-ledger.php`

### Bank Reconciliation System (NEW - Oct 7, 2025)
**Complete bank statement tracking with running balances and automated monthly statements**

**Access:** Membership Management > 💰 Calculate Balances

**Current Status (as of Oct 7, 2025):**
- ✅ `wp_swca_bank_statements` table created (empty - ready for data)
- ✅ `wp_swca_bank_transactions` has 89 transactions (Jan-Sep 2025)
- ⚠️ Balance column exists but not yet calculated (all zeros)
- ⚠️ Monthly statement records not yet created

**How to Populate Balances:**
1. Navigate to **Membership Management → 💰 Calculate Balances**
2. Enter starting balance from your bank statement (e.g., balance on Jan 1, 2025)
3. Click "Calculate Running Balances"
4. System will:
   - Calculate running balance for all 89 transactions
   - Create 9 monthly statement records (Jan-Sep 2025)
   - Enable full reconciliation in Transaction Ledger

**Features:**
- **Running Balance Calculation**: Starting balance + credits - debits for each transaction
- **Monthly Statement Generation**: Automatically creates statement records by month
- **Reconciliation Warnings**: Yellow indicator if statement >30 days old
- **User-Friendly Interface**: No command-line access required
- **Re-runnable**: Can recalculate if starting balance was incorrect

**Database Tables:**
- `wp_swca_bank_statements`: Monthly statement metadata
  - Columns: statement_period_start, statement_period_end, starting_balance, ending_balance, total_credits, total_debits
- `wp_swca_bank_transactions`: Individual transactions with balance column
  - New columns added Oct 7: notes, category, tags, recipient, balance

**Transaction Ledger Integration:**
- Shows bank statement reconciliation box when statements exist
- Displays current balance (statement ending + transactions since)
- Warning indicator when no statement data or statement >30 days old
- Direct link to Calculate Balances page when data missing

**Files:**
- `includes/features/calculate-balances.php` - Admin interface for balance calculation
- `includes/features/transaction-ledger.php` - Shows reconciliation status

**Documentation:**
- `/home/swca/public_html/BANK_RECONCILIATION_SYSTEM_OCT7.md` - Complete system documentation
- `/home/swca/public_html/CALCULATE_BALANCES_INSTRUCTIONS.md` - Step-by-step instructions

### Integration APIs (All Optional)
- **Stripe API**: Payment tracking and fee calculation
- **Google Calendar API**: Automatic event creation and .ics generation
- **Gmail API**: OAuth-based bulk email sending
- **Google Drive API**: Automatic document organization
- **Google Analytics**: Usage tracking and reporting

## Usage Commands

### Start WordPress Development Server
```bash
docker exec almalinux-dev-container bash -c "cd /home/developer/wordpress-test/wordpress && php -S 0.0.0.0:8080"
```

### Access WordPress & SWCA Dashboard
- **Homepage**: http://localhost:8080
- **Admin**: http://localhost:8080/wp-admin (admin / admin123)
- **SWCA Dashboard**: http://localhost:8080/dashboard (password: F1v3C0rn3rs)
- **Settings**: http://localhost:8080/dashboard/settings
- **Email Management**: http://localhost:8080/dashboard/email-dashboard
- **Event Management**: http://localhost:8080/dashboard/event-dashboard
- **Officer Tools**: http://localhost:8080/dashboard/officer-tools
- **Member Directory**: http://localhost:8080/dashboard/members

### Claude Flow Commands
```bash
# Launch Non-Profit Plugin Development Menu
./launch-nonprofit-plugin.sh

# Direct SPARC TDD workflow
./claude-flow-gemini sparc tdd "create WordPress feature"

# Swarm coordination
npx claude-flow@alpha swarm init --topology hierarchical --max-agents 12
npx claude-flow@alpha swarm monitor
```

### Testing & Validation
```bash
# Test WordPress with Chromium
chromium-browser --headless --no-sandbox --disable-dev-shm-usage --dump-dom http://localhost:8080

# Check plugin status
wp plugin list --allow-root

# View database tables
wp db query --allow-root 'SHOW TABLES LIKE "wp_stripe%";'
```

### CRITICAL: Plugin File Deployment
**IMPORTANT**: After any updates to plugin files in the host directory, you MUST copy them to the active WordPress installation:

```bash
# Copy updated SWCA plugin to WordPress directory
docker cp /Users/mjb9/scripts/almalinux-dev-container/swca-membership-export-corrected.php almalinux-dev-container:/var/www/html/wp-content/plugins/swca-membership-export-corrected/swca-membership-export-corrected.php

# Verify the copy succeeded
docker exec almalinux-dev-container ls -la /var/www/html/wp-content/plugins/swca-membership-export-corrected/
```

**Why this is required**: The WordPress installation runs inside the container at `/var/www/html/`, but development files are stored on the host at `/Users/mjb9/scripts/almalinux-dev-container/`. Any changes made to the host files will NOT be reflected in WordPress until copied to the container.

### WordPress User Roles Created
The plugin automatically creates custom roles with specific capabilities:

- **SWCA Member**: Basic access to dashboard and member directory
- **SWCA Officer**: Can approve emails, create events, manage member profiles
- **SWCA Treasurer**: Full access including financial data and API key management
- **SWCA Committee Chair**: Committee management, agendas, minutes, and reports

### Feature Management
Features can be enabled/disabled via `/dashboard/settings` → Features tab:
- Core features (member management, dashboard) are always enabled
- Optional features can be toggled on/off based on organization needs
- API integrations require proper credentials to function

## Project Specification
- **File**: `project_md_export.md` (979 lines)
- **Scope**: Enterprise Non-Profit Management System
- **Modules**: 15+ toggleable features
- **Integrations**: Stripe, Google Workspace, GravityForms
- **Security**: Financial-grade with AES-256 encryption

## Development Workflow

### SPARC Methodology (Used by Claude Flow)
1. **S**pecification - Requirements analysis
2. **P**seudocode - Algorithm design
3. **A**rchitecture - System design
4. **R**efinement - Code optimization
5. **C**ompletion - Testing & documentation

### Generated Deliverables
- Complete WordPress plugin with activation hooks
- Comprehensive test suite (8 test files)
- Architecture documentation (12 sections)
- Database schema with optimized indexes
- Admin interface with multiple pages
- Security implementation with audit logging

## Key Technical Decisions
- Hierarchical topology for enterprise development complexity
- Free Gemini API prioritized for cost efficiency
- MariaDB over SQLite for production compatibility
- Real database table creation vs manual SQL execution
- Multi-agent coordination for complex integrations

## Container Status
- **Image**: almalinux-dev:latest
- **Ports**: 8080:8080 (plus others)
- **Services**: MariaDB, PHP dev server
- **Tools**: Node.js 20, WP-CLI, Chromium, Claude Flow

## SSL/HTTPS Configuration

### Apache SSL Setup
- **SSL Certificate**: Self-signed certificate for localhost
- **Certificate Path**: `/etc/ssl/certs/wordpress-selfsigned.crt`
- **Private Key Path**: `/etc/ssl/private/wordpress-selfsigned.key`
- **Apache Config**: `/etc/httpd/conf.d/ssl.conf`

### SSL Configuration
```bash
# Generate self-signed certificate
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/wordpress-selfsigned.key \
  -out /etc/ssl/certs/wordpress-selfsigned.crt \
  -subj '/C=US/ST=State/L=City/O=Organization/CN=localhost'

# Start Apache with SSL proxy
sudo /usr/sbin/httpd
```

### HTTPS Access
- **Secure WordPress**: https://localhost:443
- **Admin Panel**: https://localhost:443/wp-admin
- **Stripe Plugin**: https://localhost:443/wp-admin/admin.php?page=stripe-payments

### Configuration Details
Apache runs as HTTPS proxy (port 443) → PHP dev server (port 8080)
- WordPress URLs configured for https://localhost
- Stripe plugin now recognizes secure connection
- Self-signed certificate bypasses SSL requirements

## Production Deployment Packages

### deployment-packages/
- **swca-membership-management.zip** - Complete WordPress plugin (58KB)
  - All features integrated into web interface
  - No command-line dependencies
  - Ready for production deployment
  - Includes: Main plugin file, readme.txt, installation guide

- **INSTALLATION_GUIDE.md** - Complete deployment documentation
  - Step-by-step installation instructions
  - Web-based tool usage guides
  - Troubleshooting and security notes

### Key Deployment Features
✅ **Web-Only Administration** - No SSH/CLI access required
✅ **Secure API Key Handling** - Stripe keys never stored
✅ **File Upload Interface** - CSV import via browser
✅ **Preview & Confirmation** - Review changes before applying
✅ **Complete Migration** - Export/import entire database
✅ **Multi-Year Tracking** - Historical membership data

## Success Metrics
✅ Complete non-profit membership management system deployed
✅ Modular architecture with feature toggles for customization
✅ 15+ database tables with comprehensive member and organization data
✅ Role-based access control with 4 custom WordPress roles
✅ Password-protected dashboard with multiple management interfaces
✅ Email management with approval workflow and scheduling
✅ Event management with RSVP tracking and volunteer coordination
✅ Financial management with Stripe integration and fee tracking
✅ Officer tools including agendas, minutes, and document management
✅ Committee management system with membership tracking
✅ Google Drive integration for document organization
✅ All features can be enabled/disabled via settings interface
✅ WordPress-native implementation leveraging built-in options system
✅ Responsive design compatible with mobile devices
✅ Secure API key storage and role-based permission system
✅ **Web-based administrative tools** - No command-line access required
✅ **Production-ready deployment package** - Single ZIP file installation
✅ **Complete data migration support** - Export/import between servers
✅ **Stripe integration with secure web interface** - Refund processing via admin panel

## SWCA Membership Management

### Current SWCA Database: wp_swca_members
- **181 members** imported from 2024-2025 season
- **114 members** imported from 2023-2024 season (matched existing where possible)
- **Total unique members**: 181 (some appear in both years)
- **Database table**: `wp_swca_members`

### SWCA Plugin Files & Scripts

#### Main Plugin: swca-membership-export-corrected.php
- **Location**: `/Users/mjb9/scripts/almalinux-dev-container/swca-membership-export-corrected.php`
- **Active Location**: `/var/www/html/wp-content/plugins/swca-membership-export-corrected.php`
- **Features**: Shortcodes [swca_stats] and [swca_list], export functionality under CRM menu
- **Status**: Working with manual shortcode replacement due to WordPress 6.8+ block theme issues

#### Deployment Command:
```bash
docker cp /Users/mjb9/scripts/almalinux-dev-container/swca-membership-export-corrected.php almalinux-dev-container:/var/www/html/wp-content/plugins/swca-membership-export-corrected.php
```

#### Historical Data Import: import_2023_members.php
- **Purpose**: Import 2023-2024 membership data from Excel file
- **Features**: Matches existing members, adds new historical members
- **Database Changes**: Adds columns `status_2023_2024` and `membership_month_2023`
- **Usage**: `docker exec -it almalinux-dev-container php /home/developer/import_2023_members.php`

#### Stripe Refund Processing: stripe_refund_safe.php
- **Purpose**: Download recent Stripe transactions and apply refunds to member totals
- **Security**: Prompts for API key manually, never stores it
- **Features**: 
  - Downloads charges and refunds from configurable date range
  - Matches transactions to members by email
  - Shows detailed analysis before database updates
  - Requires user confirmation before applying changes
- **Usage**: `docker exec -it almalinux-dev-container php /home/developer/stripe_refund_safe.php`

#### Alternative Refund Script: process_stripe_refunds.php
- **Purpose**: Simpler refund processing (less secure, hardcoded 7 days)
- **Note**: Use stripe_refund_safe.php instead for production

### SWCA Deployment & Administration

#### Production Deployment:
```bash
# Deploy the complete plugin package
zip -r swca-membership-management.zip deployment-packages/swca-membership-management/
# Upload to WordPress via admin interface: Plugins > Add New > Upload Plugin
```

#### Critical Production Fixes Applied:
- **Table Prefix Compatibility**: Fixed all hardcoded 'wp_' references to use dynamic `$wpdb->prefix`
- **Table Structure Repair**: Added complete 34-column schema creation on activation
- **Import/Export System**: Full data migration with ZIP packaging and CSV processing
- **Form Validation**: Fixed undefined array key warnings with proper `isset()` checks
- **Member Directory**: Functional display of all imported members with contact details

#### Database Table Structure Issues & Solutions:
- **Problem**: Production tables missing required columns (email_2, email_3, alternate_phone, etc.)
- **Solution**: Created `FIX_TABLE_STRUCTURE.php` to add missing columns to existing data
- **Result**: Complete 34-column schema matching plugin expectations
- **Status**: ✅ All 197 members now display correctly without warnings

#### Web-Based Administration (No CLI Required):
- **Data Import**: CRM > Data Import Tools (upload CSV via browser)
- **Stripe Refunds**: CRM > Stripe Refunds (secure API key input)
- **Export/Import**: CRM > Export & Import (complete migration packages)
- **Member Management**: /dashboard (password-protected interface)
- **Email Management**: /dashboard/email-dashboard (now fixed - no more warnings)
- **Event Management**: /dashboard/event-dashboard
- **Officer Tools**: /dashboard/officer-tools

#### Development-Only Commands (Docker Container):
```bash
# Copy development plugin to WordPress (dev environment only)
docker cp /Users/mjb9/scripts/almalinux-dev-container/swca-membership-export-corrected.php almalinux-dev-container:/var/www/html/wp-content/plugins/swca-membership-export-corrected.php
```

#### Access SWCA Data:
- **Member Dashboard**: https://yoursite.com/dashboard (password: F1v3C0rn3rs)
- **WordPress Admin**: CRM > SWCA Dashboard
- **Export/Import**: CRM > Export & Import
- **Stripe Tools**: CRM > Stripe Refunds
- **Data Import**: CRM > Data Import Tools
- **Stats Shortcode**: [swca_stats] - Shows membership statistics
- **Member Directory**: [swca_member_directory] - Complete member listing (now functional)

### SWCA Database Schema
```sql
-- Key columns in wp_swca_members table:
id, first_name, last_name, partner_first_name, partner_last_name, family_members,
email_1, email_2, email_3, email_4, phone, alternate_phone,
address, city, state, zip_code, alternate_address,
membership_type, status_2024_2025, status_2023_2024, 
membership_amount, donation_amount, total_amount, payment_type,
business_affiliation, on_swca_email_list, notes, 
membership_month, membership_month_2023
```

## CRITICAL TESTING RULE
**For web pages, always test using frontend before telling the user a job is complete. Do not rely on backend/PHP tests alone - verify actual browser output shows expected results.**

This rule was added because shortcode testing showed backend processing working correctly but frontend still displaying raw shortcode text instead of processed content. Always curl the actual webpage or use browser testing to verify functionality.