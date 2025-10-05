-- SQL Script to fix table prefixes on production site
-- Your site uses 'swca_' prefix but import created 'wp_swca_' tables
-- This script renames all wp_swca_* tables to swca_swca_*

-- CRITICAL: Backup your database before running this!

-- Rename tables to match your site's prefix
RENAME TABLE wp_swca_agendas TO swca_swca_agendas;
RENAME TABLE wp_swca_committee_reports TO swca_swca_committee_reports;
RENAME TABLE wp_swca_committees TO swca_swca_committees;
RENAME TABLE wp_swca_documents TO swca_swca_documents;
RENAME TABLE wp_swca_drive_folders TO swca_swca_drive_folders;
RENAME TABLE wp_swca_email_templates TO swca_swca_email_templates;
RENAME TABLE wp_swca_emails TO swca_swca_emails;
RENAME TABLE wp_swca_events TO swca_swca_events;
RENAME TABLE wp_swca_financial_reports TO swca_swca_financial_reports;
RENAME TABLE wp_swca_minutes TO swca_swca_minutes;
RENAME TABLE wp_swca_volunteer_slots TO swca_swca_volunteer_slots;

-- Check if member tables exist and rename them
-- Note: These may not exist yet, but if they do:
-- RENAME TABLE wp_swca_members TO swca_swca_members;
-- RENAME TABLE wp_swca_member_notes TO swca_swca_member_notes;
-- RENAME TABLE wp_swca_financial_transactions TO swca_swca_financial_transactions;

-- After running this, your member directory should work!