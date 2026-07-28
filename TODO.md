# 501c3PO Project To-Do List

> Last updated: 2026-04-04 (rev 3)

## Architecture Rule
**Feature Independence:** Each feature module must operate independently. Enabling or disabling
one feature must NOT break another. No cross-dependencies between modules. We are NOT splitting
into separate sub-plugins - the single plugin with feature toggles is the correct approach.

---

## Phase 1: Member CRUD Admin Interface -- COMPLETE
- [x] Create `includes/features/member-management.php` with WP_List_Table
- [x] Add member form (add new / edit existing)
- [x] Delete member with confirmation
- [x] Search by name/email
- [x] Filter by membership type, status, on-email-list
- [x] Sortable columns and pagination
- [x] Register as submenu under membership-management
- [x] Add to `$feature_files` array in `501c3po.php`
- [x] Deployed to production (2026-04-04)

## Phase 2: Mailing List Management -- COMPLETE
- [x] Create `{prefix}c3_mailing_lists` table (name, slug, description, is_default)
- [x] Create `{prefix}c3_mailing_list_members` table (list_id, member_id, status, subscribed_at, unsubscribed_at)
- [x] Create `includes/features/mailing-lists.php` admin UI
- [x] Seed default lists on activation: "All Members", "Board", "Press Release"
- [x] Migrate existing `on_email_list` data into new list structure
- [x] Add list assignment to member edit form (Phase 1 integration)
- [x] Bulk add/remove members from lists via checkboxes
- [x] Deployed to production (2026-04-04)

## Phase 3: Email Compose, Queue, and Send
- [ ] Create `{prefix}c3_email_templates` table
- [ ] Create `{prefix}c3_email_queue` table
- [ ] Modify existing `{prefix}emails` table (add from_name, from_email, reply_to, template_id, list_id)
- [ ] Modify existing `{prefix}email_recipients` table (add sent_at, error_message, bounce_type)
- [ ] Create `includes/features/email-campaigns.php` - compose UI, queue, send
- [ ] Create `includes/features/email-templates.php` - template CRUD + letterhead wrapper
- [ ] Create `includes/features/email-smtp-settings.php` - plugin's own SMTP config
- [ ] WYSIWYG editor (wp_editor) for composing emails
- [ ] Select target mailing list when composing
- [ ] Preview and test send to own address
- [ ] Queue-based sending: batch N emails via WP-Cron, self-chaining
- [ ] AJAX "manual push" button for unreliable cron environments
- [ ] Progress bar on admin page during send
- [ ] Throttling settings: emails per batch (default 10), seconds between batches (default 60), max per hour (default 500)
- [ ] SMTP settings page: host, port, encryption, username, password, from name/email
- [ ] Auto-detect existing SMTP plugins (WP Mail SMTP, Post SMTP, FluentSMTP) and defer to them
- [ ] Display detected SMTP status on settings page
- [ ] Replace email_dashboard shortcode placeholder with real implementation
- [ ] Add `send_emails` capability to officer and admin roles

## Phase 4: Unsubscribe Handling
- [ ] Create `includes/features/email-unsubscribe.php`
- [ ] Auto-append unsubscribe footer to every outgoing email
- [ ] Signed unsubscribe URLs using HMAC with `wp_salt('auth')`
- [ ] Unsubscribe endpoint via `init` action hook
- [ ] Confirmation page on unsubscribe
- [ ] "Unsubscribe from all lists" option
- [ ] Add `List-Unsubscribe` header to outgoing emails
- [ ] Update `c3_mailing_list_members.status` on unsubscribe

## Phase 5: Bounce Tracking and Campaign Reporting
- [ ] Track `wp_mail()` failures via `wp_mail_failed` action hook
- [ ] Mark recipients as failed/bounced after 3 attempts
- [ ] Campaign detail view: sent, delivered, failed, unsubscribed counts
- [ ] Problem address list for admin review
- [ ] Manual "mark as bounced" from campaign detail view

## Phase 6: Mailerpress Sync (Optional, Nice-to-Have)
- [ ] Create `includes/features/mailerpress-sync.php`
- [ ] Detect if Mailerpress is active
- [ ] One-way sync: push 501c3PO lists to Mailerpress subscriber lists
- [ ] Sync button on each mailing list admin page

---

## Email Inbox Monitor (AI-Powered Command Interface)

### Overview
The plugin monitors a dedicated email inbox via IMAP on a WP-Cron schedule. Emails from approved
senders are parsed by an AI API to extract intent and structured commands, then executed against
plugin features. Results are emailed back to the sender.

### Setup & Configuration
- [ ] Create `includes/features/email-inbox-monitor.php` - main feature file
- [ ] Admin settings page: IMAP connection (host, port, encryption, username, password)
- [ ] Support common providers: Gmail (IMAP), Outlook/365, generic IMAP
- [ ] "Test Connection" button to verify IMAP credentials
- [ ] Store credentials encrypted in `five01c3po_inbox_settings` option (AES-256-CBC, same pattern as Stripe key)
- [ ] Configure check frequency: every 5/10/15/30/60 minutes (WP-Cron schedule)

### AI Integration
- [ ] Admin settings: AI provider selection (Claude, OpenAI, or other)
- [ ] AI API key storage (encrypted, same pattern as SMTP/Stripe credentials)
- [ ] System prompt defining available actions and expected response format
- [ ] AI parses email body → returns structured JSON command (action, parameters, confidence)
- [ ] Confidence threshold setting (default 0.8) - low-confidence requests get a clarification reply instead of execution
- [ ] Token/cost tracking per request for budget awareness

### Security & Authorization
- [ ] Approved sender whitelist (admin-managed list of email addresses)
- [ ] Per-sender permission levels: read-only (queries), read-write (modifications), admin (all actions)
- [ ] Reject and optionally notify admin of emails from unapproved senders
- [ ] Rate limiting per sender (max requests per hour/day)
- [ ] All actions logged to `{prefix}c3_inbox_audit_log` table
- [ ] Destructive actions (delete member, send bulk email) require confirmation via reply

### Supported Actions (ties into existing plugin features)
- [ ] **Query members**: "How many members are paid?", "List all family memberships"
- [ ] **Add/edit members**: "Add John Smith john@example.com as Individual member"
- [ ] **Membership status**: "Mark these members as paid: [list]"
- [ ] **Financial queries**: "What's our net revenue this quarter?", "Show Stripe fees for March"
- [ ] **Mailing list ops**: "Add jane@example.com to the Board list"
- [ ] **Trigger email sends**: "Send the March newsletter to the SWCA list" (requires confirmation reply)
- [ ] **Report generation**: "Send me the fiscal year comparison report"

### Processing Pipeline
- [ ] WP-Cron hook: `five01c3po_check_inbox`
- [ ] Fetch unread messages via IMAP (php-imap extension or fallback to `imap_*` functions)
- [ ] Validate sender against whitelist
- [ ] Extract subject + body (plain text preferred, strip HTML)
- [ ] Send to AI API with system prompt + conversation context (for multi-turn)
- [ ] Parse AI response as JSON action
- [ ] Execute action via internal plugin functions (same functions used by admin UI and REST API)
- [ ] Compose result email and send back via wp_mail()
- [ ] Mark original message as read/processed in IMAP
- [ ] Log everything to audit table

### Database
- [ ] Create `{prefix}c3_inbox_audit_log` table (id, sender, subject, body_excerpt, ai_response, action_taken, result, confidence, tokens_used, created_at)
- [ ] Create `{prefix}c3_approved_senders` table (id, email, display_name, permission_level, rate_limit, is_active, created_at)

### Admin UI
- [ ] Inbox monitor dashboard: connection status, last check time, messages processed today
- [ ] Approved senders management (add/edit/remove, set permission level)
- [ ] Audit log viewer with filters (sender, date, action type, success/failure)
- [ ] Manual "Check Now" button
- [ ] AI settings: provider, API key, model, system prompt customization, confidence threshold

---

## API & MCP Integration
- [ ] Create REST API endpoints for plugin features (members, lists, emails, financials)
- [ ] API key authentication system for external agents
- [ ] Rate limiting and permission scoping per API key
- [ ] MCP (Model Context Protocol) server implementation for AI agent access
- [ ] MCP tools: query members, manage lists, trigger email sends, pull financial reports
- [ ] API key management admin page (create, revoke, view usage)
- [ ] Audit logging for all API/MCP actions

## Documentation
- [ ] Comprehensive feature documentation with examples for each module
- [ ] API endpoint reference with request/response examples
- [ ] MCP tool catalog with parameter descriptions and usage examples
- [ ] Admin user guide: member management workflows
- [ ] Admin user guide: email campaign creation and sending
- [ ] Admin user guide: mailing list management
- [ ] Admin user guide: SMTP configuration
- [ ] Developer guide: extending the plugin, hooks and filters

---

## Cleanup & Technical Debt
- [x] Rename `{prefix}members` and `{prefix}committees`/`{prefix}committee_members` tables to `c3_` prefix (2026-07-27) - `swca_swca_members` (197 rows, live) renamed to `swca_c3_members` via `RENAME TABLE`; backed up first to `temp/pre-rename-backup-2026-07-27/`. Updated all 16 code references across `data-export-import.php`, `stripe-integration.php`, `mailing-lists.php`, `member-management.php`, `shortcodes.php`, and `transaction-ledger.php` (the latter had 2 hardcoded raw-SQL joins against the *old, always-empty* `swca_members` orphan table - a pre-existing latent bug, now fixed to join the real data). Corrected `database.php`'s `five01c3po_create_tables()` members schema to match production reality (it previously defined a different, unused schema) and renamed it plus `committees`/`committee_members` to `c3_` naming. Dropped the now-superseded empty orphaned tables (`swca_members`, `swca_committees`, `swca_committee_members`) plus unrelated dead cruft discovered along the way (`swca_swca_committees`, `swca_swca_committee_reports`, 0 rows, unreferenced by any code). Verified via wp-cli: row counts intact, no PHP errors, `create_tables()` idempotent against renamed tables. Deployed to production.
- [x] Remove `wp_swca_committees` (2026-07-27) - legacy wrong-prefix table (6 rows: Events, Membership, Finance, Communications, Education, Volunteer Coordination committee seed data from 2025-09-22), unreferenced by any current plugin/theme code and no FK constraints. Backed up to `temp/pre-rename-backup-2026-07-27/wp_swca_committees-backup.sql` before dropping. Note: that seed data isn't in `swca_c3_committees` (currently empty) - worth reseeding manually if/when Committee Management UI gets built.
- [x] Update `bank-transactions.php` to use c3_ table names (2026-07-27) - already done, verified via grep
- [x] Update `transaction-matching.php` to use c3_ table names (2026-07-27) - already done, verified via grep
- [x] Update `grouped-transactions.php` to use c3_ table names (2026-07-27) - already done, verified via grep
- [x] Update `unified-transactions.php` to use c3_ table names (2026-07-27) - already done, verified via grep
- [x] Audit `temp/` scripts for hardcoded passphrases (2026-07-27) - none found; all prompt via STDIN and verify against the stored bcrypt hash
- [x] Merge `main` and `master` branches (2026-07-27) - `master` had a disjoint root history from `main` (28 commits, no common ancestor), so a literal merge wasn't meaningful. Tagged its tip as `archive/master` (pushed to origin, permanent) and deleted the `master` branch locally and on GitHub.
- [x] Clean up untracked utility scripts in scripts repo (2026-07-27) - removed stale `wordpress-membership-management/` (old `master`-branch leftover, predates current plugin structure), 3 root-level scripts (`sync-stripe-latest.php`, `sync-stripe-simple.php`, `view-latest-stripe.php`) that hardcoded the live officer passphrase in plaintext (never committed to git), and an old redundant plugin backup dir (`backups-2025-10-05-13-14-14/`)

## Completed
- [x] Repos synced: production, scripts/main, and GitHub all at commit 511ad51 (2026-04-04)
- [x] Board portal password protection improved for descendant pages (2026-04-04)
- [x] Transaction Ledger with full money trail (Oct 2025)
- [x] Stripe sync and bank reconciliation (Oct 2025)
- [x] Transaction matching algorithm - 100% accuracy (Oct 2025)
- [x] Phase 1 table migration to c3_ prefix (Oct 2025)
- [x] Database indexes for bank_transactions performance (Oct 2025)
