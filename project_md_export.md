# Non-Profit Management System - WordPress Plugin

## Project Overview
A comprehensive, **organization-neutral** management system for non-profit organizations, built as a WordPress plugin for individual organization installations. Each organization installs and configures their own private, secured WordPress instance with the plugin customized to their specific needs. While designed to be universally applicable to neighborhood associations, community groups, and similar non-profits, it will be initially developed and tested using the South Williamstown Community Association (SWCA) as the primary use case. The system features comprehensive enable/disable toggles for all functionality, allowing organizations to activate only the features they need.

**Optional Future Enhancement:** WordPress Multisite capability for organizations that need to manage multiple chapters or subsidiary groups under one installation.

## Core Users & Access Levels
- **Administrator:** Full system access, user management
- **Treasurer:** Financial data, reporting, member financial records, read-only access to events
- **Secretary:** Member management, communications, read-only access to events
- **Event Coordinators:** Full event management, volunteer coordination, planning tools, event-specific reporting
- **Board Members:** Read-only access to reports and summaries
- **Volunteers:** Limited access to sign up for volunteer opportunities and view their assignments
- **General Members:** Limited access to their own membership information and public volunteer opportunities

## System Architecture & Integration Requirements

### Private WordPress Installation Architecture
- **Separate WordPress instance** - Private, secured installation isolated from public website
- **Dedicated hosting environment** - Enhanced security for financial and member data
- **VPN/Private access** - Restricted access to authorized personnel only
- **WordPress plugin framework** - Leverages WordPress architecture while maintaining security
- **Database isolation** - Financial data completely separate from public website
- **SSL/TLS encryption** - All communications encrypted in transit

### Organization-Neutral Design Principles

#### Single-Organization Installation Model
- **Individual deployments** - Each organization installs their own private WordPress instance with the plugin
- **Complete data isolation** - No shared hosting or multi-tenant complications
- **Custom configuration per installation** - Each organization configures their own settings, branding, and feature set
- **Independent security** - Each installation maintains its own security policies and access controls
- **Scalable hosting choice** - Organizations choose hosting appropriate to their size and needs

#### Comprehensive Feature Toggle System
- **Modular feature architecture** - Every major feature can be independently enabled or disabled
- **Admin settings interface** - Clear enable/disable toggles for all functionality
- **Progressive complexity** - Organizations can start simple and add features as they grow
- **Resource optimization** - Disabled features don't load unnecessary code or database tables
- **Role-based feature access** - Some features can be enabled but restricted to specific user roles

#### Feature Toggle Categories
- **Core Features** (always enabled): User management, basic member database, settings
- **Member Management** (toggle): Advanced CRM features, member relationships, segmentation
- **Financial Management** (toggle): Stripe read-only integration with auto-pagination, donation tracking, financial reporting  
- **Event Management** (toggle): Event creation, volunteer coordination, attendance tracking
- **Communication** (toggle): Email campaigns, SMS messaging, automated workflows
- **Document Management** (toggle): Google Drive integration, file storage, receipt management
- **Analytics & Reporting** (toggle): Advanced analytics, predictive scoring, custom dashboards
- **Advocacy & Community** (toggle): Municipal tracking, local business partnerships, issue management

#### WordPress Multisite Compatibility (Future Enhancement)
- **Multisite architecture support** - Plugin designed to work with WordPress Multisite if needed
- **Network admin controls** - Centralized feature management across multiple organization sites
- **Per-site configuration** - Each site maintains independent settings while sharing core functionality
- **Use cases**: Regional associations with local chapters, parent organizations with subsidiary groups

### Current State & Available Resources (SWCA as Primary Use Case)
- **Existing public WordPress website** at https://southwilliamstown.org with established GravityForms
- **Community-focused organization** - Neighborhood association serving South Williamstown, MA residents
- **Event-driven activities** - Annual BBQ, community meetings, newsletter distribution, local advocacy
- **GravityForms exports provided** showing form evolution and member data patterns
- **Stripe transaction export** (150+ transactions) - Complete payment history with customer details
- **Google Apps Script** - Working Stripe integration code for reference
- **Treasurer's spreadsheets** - Revenue/expense analysis and event financial tracking
- **Email master list** (248 contacts) - Current communication database
- **Active Stripe.com account** with 5+ years of transaction history serving as integration model
- **Google Workspace environment** already configured for document management

#### SWCA-Specific Features (Templates for Other Organizations)
- **Community newsletter integration** - Support for regular newsletter creation and distribution
- **Local advocacy tracking** - Tools for tracking community issues, zoning changes, municipal communications
- **Neighborhood event management** - Templates for recurring community events (annual BBQ model)
- **Municipal relationship management** - Track communications with town officials and local government
- **Volunteer community engagement** - Specialized volunteer coordination for neighborhood-scale activities
- **Local business partnership tracking** - Manage relationships with local sponsors and community partners

#### Generalization Strategy
- **Configuration system** - Admin interface to customize organization name, terminology, event types, membership structure
- **Template library** - Pre-built configurations for common organization types (HOAs, community groups, small non-profits)
- **Import/Export settings** - Allow organizations to share configuration templates
- **Multi-language support** - Internationalization framework for different communities
- **Flexible reporting** - Adaptable metrics and KPIs based on organization type and size
- **Scalable hosting** - Architecture that works for both small community groups and larger regional organizations

### External Service Integrations
- **Stripe.com** - Read-only transaction data access (secured API connection, no payment processing)
- **Public Website/GravityForms** - Remote API connection to pull form data
  - WordPress REST API integration with public site
  - Scheduled data synchronization from membership and donation forms
  - Webhook integration for real-time form submissions (optional)
- **Google Workspace** (via OAuth)
  - Gmail for email communications
  - Google Shared Drive for document storage and collaboration
  - Google Sheets for data exports and treasurer collaboration
  - Google Calendar for event scheduling and reminders
  - Google Docs for report generation and sharing
- **rclone** - File synchronization with Google Drive
- **Banking APIs** (if available) - Direct bank account integration for reconciliation
- **Email systems** - SMTP integration for secure email communications
- **Backup services** - Automated secure backups to cloud storage

---

## Feature Requirements

### 1. Donor & Membership Management (Toggleable Module)

**Enable/Disable:** Full CRM functionality can be toggled on/off. Basic member list always available.

#### Core Member Management (Always Available)
- **Basic member database** - Essential contact information and membership status
- **Simple member list** - View and edit member information
- **Basic communication** - Send individual emails to members
- **Import/Export** - CSV import/export functionality

#### Advanced CRM Features (Toggleable)
- **Member lifecycle tracking** - Prospect to active to lapsed member stages
- **Relationship mapping** - Family, business, and social connections between members
- **Contact preferences** - Communication channel and frequency preferences
- **Member segmentation** - Create custom member groups and categories
- **Advanced contact timeline** - Complete interaction history
- **Duplicate management** - Automatic detection and merging tools

#### SWCA-Template Configuration (Customizable)
- **Membership tiers** - Default: Individual ($35), Household ($50), Business (configurable)
- **Contact preference options** - "What's the best way to contact you?" integration
- **Business member fields** - Optional business name, website, contact information
- **Processing fee handling** - Track member preferences for covering payment fees
- **Historical data migration** - Tools for importing existing member data
- **Master email list sync** - Integration with external email lists

#### Stripe Integration (SWCA-Specific Requirements - Read-Only Access)
- **Transaction download** - Download historical transaction data for reporting
- **Transaction matching algorithm** - Link downloaded Stripe charges to GravityForms entries using Transaction ID
- **Processing fee analysis** - Analyze when donors covered fees vs. organization absorbed them
- **Recurring payment detection** - Identify repeat donors and subscription patterns from historical data
- **Multi-description parsing** - Parse various Stripe description formats:
  - "Products: Individual - $35/year, Annual Credit Card Processing Fees"
  - "Invoice HHGGRU6Z-0003" (recurring billing)
  - "Raffle tickets purchase" (event-specific)
- **Customer data enrichment** - Match Stripe customer info with local member profiles
- **Payment method analytics** - Analyze preferences (Visa, Mastercard, AMEX, ACH) from historical data
- **Revenue categorization** - Categorize past memberships, donations, event payments, and special programs
- **No payment processing** - System only reads transaction history, does not create new charges

### 2. Financial Management (Toggleable Module)

**Enable/Disable:** Complete financial tracking system can be toggled on/off for organizations that don't need payment processing.

#### Basic Financial Features (When Enabled)
- **Simple donation tracking** - Manual entry of donations and payments
- **Basic expense tracking** - Record organizational expenses
- **Simple reporting** - Income vs. expense summaries
- **Receipt storage** - Basic file upload and organization

#### Advanced Financial Features (Sub-toggles within Financial Module)
- **Stripe Read-Only Integration** (toggle) - Download historical transactions for reporting
- **Processing Fee Analysis** (toggle) - Analyze fee coverage from downloaded transactions
- **Multi-year Reporting** (toggle) - Historical comparisons and trend analysis
- **Budget Planning** (toggle) - Annual budget creation and variance tracking
- **Reimbursement System** (toggle) - Expense approval and reimbursement workflow
- **Tax Reporting** (toggle) - Generate donation receipts and 1099 forms

#### Transaction Data Integration (Sub-module)
- **Stripe API read-only connection** - Download historical transactions
- **Payment method analytics** - Analyze donor preferences from historical data
- **Transaction history review** - View past successful and failed transactions
- **Recurring payment detection** - Identify subscription patterns and repeat donors
- **Revenue categorization** - Categorize past memberships, donations, events, special programs

### 3. Event Management & Analytics (Toggleable Module)

**Enable/Disable:** Complete event management system can be toggled on/off for organizations that don't host events.

#### Basic Event Features (When Enabled)
- **Event creation** - Create and manage basic event information
- **Simple attendance tracking** - Record who attended events
- **Basic event calendar** - View upcoming and past events
- **Event communication** - Send updates to members about events

#### Advanced Event Features (Sub-toggles within Event Module)
- **Event Planning Tools** (toggle) - Checklists, timelines, task management
- **Volunteer Coordination** (toggle) - SignUpGenius-style volunteer management system
- **Event Analytics** (toggle) - ROI analysis, attendance patterns, success metrics
- **Multi-day Events** (toggle) - Support for complex, multi-session events
- **Event Templates** (toggle) - Reusable event configurations and annual event cloning
- **Event Integration** (toggle) - Sync with external calendar systems and promotion platforms

#### Volunteer Management System (Sub-module - SignUpGenius Clone)
- **Volunteer opportunity creation** - Define roles, time slots, requirements
- **Public volunteer portal** - Member-facing signup interface  
- **Volunteer communication** - Automated reminders and updates
- **Volunteer tracking** - Hours, reliability, and appreciation management
- **Skills and availability** - Match volunteers to appropriate opportunities

### Role-Based Access Control (WordPress Roles & Capabilities)

#### Custom WordPress Roles Extension
- **Event Coordinator Role:**
  - Full access to event creation, planning, and volunteer management
  - Can create and modify volunteer opportunities
  - Access to event-specific financial data (read/write)
  - Can generate event reports and analytics
  - Cannot access member financial records or organization-wide financial data
  
- **Volunteer Role:**
  - Can view available volunteer opportunities
  - Can sign up for and modify their own volunteer commitments
  - Can view their volunteer history and hours
  - Access to event details relevant to their volunteer roles
  - Cannot access financial data or member information
  
- **Treasurer Role (Enhanced):**
  - Full financial system access (existing capability)
  - Read-only access to all event data for financial reporting
  - Can generate cross-event financial reports
  - Cannot modify event planning or volunteer assignments
  
- **Secretary Role (Enhanced):**
  - Full member management access (existing capability)
  - Read-only access to event data for communication purposes
  - Can access volunteer contact information for event communications
  - Cannot modify event planning or financial data

#### Capability-Based Permissions
- **Event Management Capabilities:**
  - `create_events` - Create new events and event templates
  - `edit_events` - Modify existing events and planning details
  - `manage_volunteers` - Create and manage volunteer opportunities
  - `view_event_finances` - Access to event-specific financial data
  - `generate_event_reports` - Create event analytics and reports
  
- **Volunteer Management Capabilities:**
  - `signup_volunteers` - Sign up for volunteer opportunities
  - `manage_volunteer_schedules` - Modify volunteer assignments and schedules
  - `view_volunteer_profiles` - Access volunteer contact and history information
  - `send_volunteer_communications` - Email volunteers about events

#### Cross-Role Data Sharing
- **Financial Integration:** Event Coordinators can input event expenses and revenue, but Treasurer has oversight and final approval
- **Member Communication:** Event Coordinators can communicate with volunteers, but Secretary manages organization-wide member communications
- **Reporting Access:** All roles can generate reports relevant to their responsibilities, with appropriate data filtering

### 4. Communication & Marketing Automation (Toggleable Module)

**Enable/Disable:** Complete communication and marketing system can be toggled on/off for organizations that use external systems.

#### Hootsuite-Inspired Social Media Features

**Core Dashboard Features (Inspired by Hootsuite)**
- **Unified social media dashboard** - Manage all platforms from one interface like Hootsuite's main dashboard
- **Multi-platform posting** - Post to Facebook, Instagram, Twitter, LinkedIn simultaneously with platform-specific optimizations
- **Drag-and-drop content calendar** - Visual calendar showing all scheduled posts across networks (Hootsuite's signature feature)
- **Bulk scheduling** - Upload and schedule up to 350+ posts at once using CSV import
- **Best time to post recommendations** - AI-powered optimal posting time suggestions based on audience engagement
- **Post approval workflows** - Team collaboration with approval processes before content goes live

**AI-Powered Content Creation (Hootsuite OwlyWriter-inspired)**
- **AI caption generator** - Automated caption writing for different platforms and content types
- **AI content idea generator** - Suggest post ideas based on trending topics and organization activities
- **AI hashtag generator** - Recommend relevant hashtags for maximum reach and engagement
- **Content templates** - Pre-built templates for different post types (event announcements, member spotlights, fundraising)
- **Content repurposing** - Transform one piece of content into multiple platform-specific posts

**Social Listening & Monitoring (Hootsuite Streams)**
- **Brand mention monitoring** - Track mentions of organization name across social platforms
- **Keyword streams** - Monitor industry terms, competitor mentions, and community topics
- **Hashtag tracking** - Follow relevant hashtags and trending topics in your area
- **Sentiment analysis** - Understand how people feel about your organization and community issues
- **Competitor monitoring** - Track competitor social media activity and performance
- **Community issue tracking** - Monitor local government, zoning, and neighborhood discussions

**Unified Social Inbox (Hootsuite Inbox)**
- **Multi-platform messaging** - Respond to DMs, comments, and messages from one inbox
- **Message assignment** - Assign messages to different team members (secretary, president, etc.)
- **Auto-responder** - Automated responses for common inquiries
- **Saved replies** - Template responses for frequently asked questions
- **Message tagging** - Categorize messages by type (membership, events, complaints, etc.)
- **Response time tracking** - Monitor how quickly the organization responds to member inquiries

**Advanced Analytics & Reporting (Hootsuite Analytics-inspired)**
- **Comprehensive performance metrics** - Track engagement, reach, clicks, follower growth across all platforms
- **Custom report builder** - Create tailored reports for board meetings and stakeholder updates
- **Competitor benchmarking** - Compare organization's performance against similar community groups
- **ROI tracking** - Connect social media activities to membership sign-ups, event attendance, donations
- **Best performing content identification** - Automatically identify top-performing posts for content strategy
- **Automated report scheduling** - Email weekly/monthly performance reports to leadership
- **Industry benchmarking** - Compare against non-profit and community organization averages

**Team Collaboration Features (Hootsuite Team Tools)**
- **Multi-user access** - Different permission levels for various team members
- **Content approval workflows** - Prevent accidental posts with approval requirements
- **Team assignment** - Assign different social accounts or tasks to specific members
- **Activity logging** - Track who posted what and when for accountability
- **Team notifications** - Alert relevant team members about important mentions or messages
- **Shared content library** - Central repository for approved images, logos, templates

**Integration with Organization Features**
- **Event-driven social campaigns** - Automatically create social media campaigns when events are added to calendar
- **Member milestone celebrations** - Auto-generate posts celebrating member anniversaries, new members, volunteer contributions
- **Fundraising campaign integration** - Track social media's impact on donation goals with built-in analytics
- **Community advocacy coordination** - Organize social media around local issues, town meetings, zoning changes
- **Cross-platform crisis communication** - Quickly push urgent community updates across all platforms simultaneously

**Enhanced Content Features (Beyond Basic Hootsuite)**
- **Local community focus** - Templates specifically for neighborhood associations, local events, municipal issues
- **QR code integration in posts** - Automatically include QR codes in event posts for easy registration
- **Member-generated content curation** - Tools to collect and share member photos, testimonials, community highlights  
- **Event countdown timers** - Automated countdown posts for upcoming events like the Annual BBQ
- **Weather-aware posting** - Automatically adjust event posts based on weather forecasts for outdoor events
- **Seasonal content suggestions** - AI recommendations based on time of year and local community calendar

**Advanced Automation Workflows**
- **Email-to-social integration** - When membership emails are sent, automatically create corresponding social posts
- **Event lifecycle campaigns** - Complete social media sequences: save the date → reminders → live updates → thank you posts
- **New member welcome sequences** - Automated social media welcome posts when new members join
- **Renewal reminder campaigns** - Social media components of membership renewal campaigns
- **Board meeting transparency** - Automated posts about board meetings, agendas, and community involvement opportunities

#### Visual Content Creation & Automation
- **Built-in QR code generator** - Create QR codes for events, memberships, donations, and contact info
- **Canva API integration** (toggle) - Automated poster and graphic creation using Canva's design tools
  - Template-based design generation using event/organization data
  - Automated poster creation for events (pull event details, dates, locations)
  - Brand-consistent graphics with organization colors, logos, fonts
  - Multiple format generation (Facebook post, Instagram story, Twitter banner, print poster)
- **Alternative design tools** (toggle) - Integration with other design platforms if Canva unavailable
- **Template library** - Pre-designed templates for common organizational needs
- **Brand asset management** - Store and manage logos, colors, fonts for consistent branding

#### Marketing Campaign Integration
- **Multi-channel campaigns** - Coordinate email, social media, and physical marketing from one interface
- **Campaign scheduling** - Plan entire marketing campaigns with email, social posts, and poster creation
- **Event promotion automation** - Automatically generate marketing materials when events are created
- **Member engagement workflows** - Automated welcome campaigns, renewal reminders, thank you sequences
- **A/B testing** - Test different poster designs, social media posts, and email subject lines

#### Print Marketing Materials
- **Event poster generation** - Automated creation of physical posters for events
  - Pull event details (date, time, location, description) automatically
  - Generate multiple sizes (11x17, 8.5x11, social media formats)
  - Include QR codes linking to event registration or organization website
- **Flyer creation** - Membership drives, fundraising campaigns, community announcements
- **Print-ready formatting** - High-resolution output suitable for professional printing
- **Template customization** - Organization-specific templates with consistent branding

#### Content Creation API Integrations
- **Canva Connect APIs** (toggle) - Full integration with Canva's design platform
  - Automated template-based design generation
  - Brand asset synchronization (logos, colors, fonts)
  - Bulk design creation for events and campaigns
  - Multi-format export (social media, print, web)
  - **Note**: Requires Canva Enterprise for full autofill functionality
- **Alternative design APIs** (toggle) - Options for organizations without Canva Enterprise
  - Templated.io - Canva-like API alternative for smaller organizations
  - CraftMyPDF - PDF and image generation from templates
  - Adobe Creative SDK - Professional-grade design automation
  - Custom HTML/CSS poster generation - Simple template-based design system

#### QR Code Generation & Management
- **Dynamic QR codes** - Update destination URLs without changing the QR code
- **Event QR codes** - Link to event registration, membership signup, donation pages
- **Contact QR codes** - Organization contact information, social media links
- **Analytics tracking** - Monitor QR code scan rates and user engagement
- **Custom styling** - Branded QR codes with organization colors and logos
- **Bulk generation** - Create multiple QR codes for events, campaigns, fundraising

#### Marketing Workflow Automation Examples
1. **Event Creation Triggers**:
   - Email announcement to members
   - Facebook event creation and post
   - Instagram story with event poster
   - Twitter announcement with QR code
   - Physical poster generation for local distribution

2. **Membership Campaign**:
   - Email series to prospects and lapsed members
   - Social media posts with membership benefits
   - QR codes for easy signup
   - Print flyers for community distribution
   - Follow-up sequences based on engagement

3. **Fundraising Campaigns**:
   - Multi-platform announcement coordination
   - Visual content showing donation impact
   - QR codes linking to secure donation forms
   - Thank you sequences across all channels
   - Progress updates with visual charts and graphs

#### Social Media Platform Integrations
- **Facebook Integration** (toggle) - Page management, event creation, post scheduling
- **Instagram Integration** (toggle) - Story and post creation, hashtag management
- **Twitter Integration** (toggle) - Tweet scheduling, thread creation, engagement tracking
- **LinkedIn Integration** (toggle) - Professional networking and community updates
- **Nextdoor Integration** (toggle) - Neighborhood-specific social networking (ideal for community associations)
- **Facebook Groups** (toggle) - Manage private community groups and member communications

#### Advanced Social Media Features (Sub-toggles)
- **Content Calendar** (toggle) - Visual calendar showing all scheduled posts across platforms
- **Social Media Analytics** (toggle) - Engagement metrics, reach analysis, optimal posting times
- **Hashtag Research** (toggle) - Trending hashtag suggestions and performance tracking
- **Social Listening** (toggle) - Monitor mentions of organization and community topics
- **Cross-platform Campaigns** (toggle) - Coordinate messaging across multiple platforms
- **User-generated Content** (toggle) - Curate and share member-submitted content

#### Built-in Email Marketing (Mailchimp-like Features)
- **Drag-and-drop email editor** - Visual email builder with professional templates
- **Contact list management** - Segmented mailing lists with tags and categories
- **Campaign management** - Create, schedule, and track email campaigns
- **Email automation** - Welcome series, renewal reminders, event follow-ups
- **A/B testing** - Test subject lines, send times, and content variations
- **Performance analytics** - Open rates, click rates, bounce rates, unsubscribe tracking
- **Template library** - Pre-built templates for newsletters, announcements, fundraising
- **Personalization** - Dynamic content based on member data and preferences
- **GDPR compliance** - Subscription management, consent tracking, data privacy controls

#### External Email Platform Integrations (Alternative to Built-in)
- **Mailchimp Integration** (toggle) - Full API integration with Mailchimp accounts
  - Sync member lists and segments automatically
  - Trigger Mailchimp campaigns from member actions
  - Import campaign statistics back into the system
  - Maintain unified member communication history
- **Constant Contact Integration** (toggle) - API integration with Constant Contact
- **ConvertKit Integration** (toggle) - Creator-focused email marketing platform
- **Campaign Monitor Integration** (toggle) - Enterprise email marketing solution
- **Sendinblue/Brevo Integration** (toggle) - European email marketing platform
- **Generic Email API** (toggle) - Custom integration with other email services via API

#### Integration Strategy Options
- **Hybrid Approach** - Use built-in system for simple communications, external platform for complex campaigns
- **Full External** - Disable built-in email marketing, use only API integrations
- **Full Internal** - Use only built-in Mailchimp-like features
- **Data Sync Only** - Keep member data synchronized but send all campaigns externally

#### Communication Analytics & Reporting
- **Unified reporting** - Combined analytics whether using built-in or external platforms
- **Member engagement scoring** - Track email engagement across all channels
- **Communication preferences** - Member opt-in/opt-out management for different message types
- **Deliverability monitoring** - Track bounce rates, spam complaints, reputation scores
- **ROI tracking** - Connect email campaigns to donations, memberships, and event attendance

### 5. Document Management & File Storage (Toggleable Module)

**Enable/Disable:** Complete document management system can be toggled on/off for organizations that use external file storage.

#### Basic File Management (When Enabled)
- **File uploads** - Basic receipt and document storage
- **File organization** - Simple folder structure for documents
- **File viewing** - View uploaded PDFs and images within the system
- **Download/export** - Basic file retrieval functionality

#### Advanced Document Features (Sub-toggles within Document Module)
- **Google Drive Integration** (toggle) - Automatic sync with Google Shared Drive
- **Advanced File Organization** (toggle) - Custom folder structures, tagging, search
- **Document Generation** (toggle) - Create reports and letters directly as Google Docs/PDFs
- **Version Control** (toggle) - Track document changes and revisions
- **Document Templates** (toggle) - Standardized report templates and form letters
- **Backup Systems** (toggle) - Automated backup to multiple cloud storage providers

### 6. Analytics & Reporting (Toggleable Module)

**Enable/Disable:** Advanced analytics can be toggled on/off for organizations that only need basic reporting.

#### Basic Reporting (When Enabled)
- **Simple member reports** - Basic member lists and counts
- **Financial summaries** - Income and expense totals
- **Event attendance** - Basic attendance tracking and lists
- **Export capabilities** - CSV export of basic data

#### Advanced Analytics Features (Sub-toggles within Analytics Module)
- **Predictive Analytics** (toggle) - Donor behavior prediction and trend analysis
- **Custom Dashboards** (toggle) - Real-time KPI monitoring and visual displays
- **Advanced Reporting** (toggle) - Complex multi-variable reports with filtering
- **Benchmarking** (toggle) - Compare performance against similar organizations
- **Automated Report Delivery** (toggle) - Schedule and email reports automatically
- **Data Visualization** (toggle) - Interactive charts, graphs, and heat maps

### 7. External Integrations (Toggleable Module)

**Enable/Disable:** Third-party integrations can be individually toggled based on organization needs.

#### Transaction Data Integrations
- **Stripe Transaction Download** (toggle) - Read-only access to historical transactions
- **Square Data Export** (toggle) - Import transaction history from Square
- **PayPal History Import** (toggle) - Import PayPal transaction records
- **Banking Statement Import** (toggle) - CSV/QIF import for reconciliation

#### Email Marketing Platform Integrations
- **Mailchimp API** (toggle) - Full integration with Mailchimp accounts
  - Automatic member list synchronization
  - Campaign trigger integration
  - Performance data import
  - Unified member communication history
- **Constant Contact API** (toggle) - Professional email marketing integration
- **ConvertKit API** (toggle) - Creator-focused email platform integration  
- **Campaign Monitor API** (toggle) - Enterprise email marketing solution
- **Sendinblue/Brevo API** (toggle) - European email platform with SMS capabilities
- **ActiveCampaign API** (toggle) - Advanced automation and CRM email platform
- **Generic SMTP/API** (toggle) - Custom integration with other email services

#### Productivity & Collaboration Integrations
- **Google Workspace** (toggle) - Gmail, Drive, Calendar, Docs integration
- **Microsoft 365** (toggle) - Outlook, OneDrive, Teams integration
- **Slack Integration** (toggle) - Team communication and notifications
- **Zapier Integration** (toggle) - Connect with 3000+ apps via Zapier workflows

#### Form & Website Integrations
- **GravityForms Sync** (toggle) - WordPress form integration for public website
- **Contact Form 7** (toggle) - Alternative WordPress form integration
- **Typeform Integration** (toggle) - Advanced form building and response collection
- **JotForm Integration** (toggle) - Comprehensive form solution integration

#### Accounting & Financial Integrations
- **QuickBooks Integration** (toggle) - Accounting software integration for financial data
- **Xero Integration** (toggle) - Cloud-based accounting platform
- **Wave Accounting** (toggle) - Free accounting software for small organizations
- **Freshbooks Integration** (toggle) - Invoicing and time tracking integration

#### Social Media & Marketing Integrations
- **Facebook Integration** (toggle) - Page management and event promotion
- **Instagram Integration** (toggle) - Content posting and engagement tracking
- **Twitter Integration** (toggle) - Tweet scheduling and social monitoring
- **LinkedIn Integration** (toggle) - Professional networking and content sharing
- **Eventbrite Integration** (toggle) - Event ticketing and promotion

#### Communication & Outreach Integrations
- **Twilio SMS** (toggle) - Text messaging service integration
- **WhatsApp Business** (toggle) - WhatsApp messaging for organizations
- **Zoom Integration** (toggle) - Virtual meeting and webinar management
- **GoToMeeting Integration** (toggle) - Alternative virtual meeting platform

#### Integration Management Features
- **API Key Management** - Secure storage and configuration of integration credentials
- **Webhook Management** - Set up and monitor incoming data webhooks
- **Sync Status Monitoring** - Real-time status of all active integrations
- **Error Handling & Logging** - Comprehensive logging of integration issues and failures
- **Data Mapping Configuration** - Custom field mapping between systems
- **Integration Testing Tools** - Test connections and data flow before going live

### 8. Advocacy & Community Features (Toggleable Module)

**Enable/Disable:** Community-specific features can be toggled on/off based on organization type.

#### Community Organization Features (When Enabled)
- **Issue tracking** - Monitor local issues, zoning changes, municipal communications
- **Government relationship management** - Track communications with officials
- **Local business partnerships** - Manage sponsor and partner relationships
- **Community resource directory** - Maintain lists of local services and contacts
- **Advocacy campaign management** - Organize community action initiatives
- **Public meeting tracking** - Schedule and document community meetings

### 9. Data Import & Export System (Always Enabled)

**Core Philosophy:** Complete data portability - users should always be able to export their data and import from other systems.

#### Comprehensive Export Features
- **Complete data export** - Export ALL organizational data in multiple formats
- **Selective exports** - Choose specific data sets (members only, events only, financial data, etc.)
- **Multiple format support** - CSV, JSON, XML, Excel, PDF formats
- **Scheduled automated exports** - Regular backups sent to email or cloud storage
- **Export templates** - Pre-configured export formats for common use cases
- **Data integrity verification** - Checksums and validation for exported data

#### Export Categories & Formats
- **Member/Donor Data** - Complete contact information, membership history, communication preferences
- **Financial Data** - All transactions, payments, fees, revenue/expense categorization
- **Event Data** - Event details, attendance records, volunteer assignments, financial performance
- **Communication History** - Email campaigns, social media posts, member interactions
- **Document Archive** - All uploaded files, receipts, reports, generated materials
- **System Configuration** - Settings, templates, workflows, user roles and permissions

#### Import System for Common Non-Profit Tools
- **CRM System Imports** - Support for major non-profit CRM platforms
  - Salesforce Nonprofit Success Pack
  - Bloomerang donor management
  - DonorPerfect data imports  
  - Little Green Light exports
  - Network for Good data
  - EveryAction/NGP VAN imports
- **Email Marketing Platform Imports** - Contact lists and campaign data
  - Mailchimp lists and segments
  - Constant Contact data
  - ConvertKit subscriber imports
  - Campaign Monitor data
- **Financial System Imports** - Transaction and accounting data
  - QuickBooks non-profit exports
  - PayPal transaction history
  - Square payment records
  - Xero accounting data
- **Event Management Imports** - Event and registration data
  - Eventbrite attendee lists
  - SignUpGenius volunteer data
  - Facebook Events exports
  - Google Calendar imports
- **Generic Imports** - Standard file format support
  - CSV files with field mapping interface
  - Excel spreadsheets with data validation
  - vCard contact imports
  - JSON data from APIs
  - XML structured data

#### Advanced Import Features
- **Intelligent field mapping** - AI-assisted mapping between different data structures
- **Data validation and cleaning** - Automatic detection and correction of common issues
- **Duplicate detection and merging** - Smart identification of duplicate records across imports
- **Bulk processing** - Handle large datasets with progress tracking and error reporting
- **Import preview and testing** - Preview imports before committing to database
- **Rollback capability** - Undo imports if issues are discovered
- **Incremental imports** - Add new data without duplicating existing records

#### Migration Tools for Major Platforms
- **WordPress Plugin Migration** - Move from other WordPress management plugins
  - Events Manager plugin data
  - WP User Frontend data
  - Ultimate Member exports
  - MemberPress membership data
- **Popular SaaS Platform Migration** - Direct migration tools
  - Wild Apricot membership data
  - MemberClicks exports
  - ClubExpress data migration
  - Mighty Networks community data
- **Legacy System Support** - Import from older or discontinued systems
  - Microsoft Access databases
  - FileMaker Pro exports
  - Custom database exports via SQL
  - Spreadsheet-based systems

#### Data Transformation & Standardization
- **Address standardization** - Clean and standardize address data during import
- **Phone number formatting** - Consistent phone number formats across all data
- **Date/time normalization** - Handle different date formats and time zones
- **Currency conversion** - Support for different currencies with historical exchange rates
- **Name parsing and cleaning** - Separate first/last names, handle prefixes and suffixes
- **Custom field mapping** - Map unique fields from other systems to custom fields

#### Export Customization Tools
- **Report builder exports** - Export data in formatted report layouts
- **Template-based exports** - Create custom export templates for specific needs
- **Filtered exports** - Export based on complex criteria (date ranges, member types, etc.)
- **Relationship preservation** - Maintain connections between related data (members, events, donations)
- **Audit trail exports** - Include change history and modification timestamps
- **GDPR compliance exports** - Individual member data exports for privacy requests

#### API Integrations for Common Non-Profit Tools
- **Automated data synchronization** - Keep data in sync with external systems
- **Real-time imports** - Scheduled imports from connected systems
- **Webhook support** - Receive data updates from external platforms automatically
- **Two-way synchronization** - Changes in either system update the other
- **Conflict resolution** - Handle data conflicts when same record is modified in multiple systems
- **Import logging and monitoring** - Track all import activities with detailed logs

#### GDPR & Privacy Compliance
- **Right to data portability** - Complete individual member data exports
- **Data anonymization tools** - Remove or mask personal information for exports
- **Consent tracking exports** - Include consent history and permissions
- **Data retention compliance** - Automated deletion and archival based on retention policies
- **Privacy audit trails** - Track all data access and modifications for compliance

#### Backup and Disaster Recovery
- **Automated full system backups** - Complete data and configuration backups
- **Incremental backup system** - Daily changes backup with full system restore capability
- **Cloud storage integration** - Automatic backups to Google Drive, Dropbox, AWS S3
- **Backup verification** - Test backup integrity and restoration procedures
- **One-click restore** - Simple restoration process for system recovery
- **Backup encryption** - All backup data encrypted for security

#### Data Quality and Maintenance
- **Data health monitoring** - Regular checks for data integrity issues
- **Duplicate detection across modules** - Find and merge duplicate records system-wide
- **Data standardization tools** - Clean and standardize data formats
- **Orphaned data cleanup** - Remove data records that no longer have valid relationships
- **Performance optimization** - Database maintenance and optimization tools

---

## Development Context for Claude Flow

### Technical Environment
- **Primary Language:** PHP (WordPress plugin development)
- **Database:** MySQL/MariaDB (WordPress standard)
- **Frontend:** WordPress admin interface + custom admin pages
- **APIs to integrate:** Stripe API, Google Workspace APIs, WordPress REST API
- **File handling:** rclone for Google Drive synchronization
- **Security requirements:** Private hosting, encrypted data, role-based access

### Development Environment & Testing Setup

#### Local Development Requirements (Claude Flow Container)
- **Local MySQL server** running within Claude Flow container
- **Local Apache server** with PHP support within Claude Flow container  
- **WordPress installation** in local directory (e.g., `/var/www/nonprofit-mgmt`)
- **Database setup** with dedicated MySQL database for plugin testing
- **Virtual host configuration** for local WordPress access
- **WP-CLI installation** for WordPress command-line operations
- **Git repository initialization** for version control

#### Final Deployment
- **Plugin will be deployed remotely** to private WordPress installation
- **Development environment is for testing only**
- **Production deployment** will be on separate, secured hosting environment

#### Git & GitHub Integration
- **GitHub repository** for version control and collaboration
- **Branch strategy** with main, develop, and feature branches
- **Commit message conventions** for clear development history
- **Release tagging** for version management
- **README.md** with installation and setup instructions
- **Issue tracking** for bug reports and feature requests
- **.gitignore** configured for WordPress development (exclude wp-config.php, uploads, etc.)

#### Development Workflow
1. **Initialize Git repository** in plugin development directory
2. **Create GitHub repository** for remote tracking
3. **Set up local WordPress** with plugin development structure
4. **Database seeding** with sample data for testing
5. **Iterative development** with regular commits and testing
6. **Final plugin packaging** for distribution (.zip file)

### Development Priorities (in order)
1. **Git repository setup** and WordPress plugin framework
2. **Database design and migration scripts** for existing SWCA data (215+ forms, 150+ Stripe transactions)
3. **Stripe read-only integration** for downloading historical transactions with auto-pagination support
4. **GravityForms data synchronization** with multi-form support and field evolution handling
5. **Core member management** with SWCA-specific fields and business member support
6. **Processing fee analysis** from downloaded Stripe transaction data
7. **User authentication and role management** system with custom capabilities
8. **Member segmentation and analytics** including renewal tracking and donor patterns
9. **Communication system** with master email list integration (248 contacts)
10. **Event creation and management** system using BBQ financial breakdown as template
11. **Volunteer coordination system** (SignUpGenius clone) with public volunteer portal
12. **Financial reporting system** matching current treasurer spreadsheet formats
13. **Advanced donor analytics** including geographic and payment method analysis
14. **Automated stewardship workflows** for member renewals and donor engagement
15. **Google Drive integration** for report storage and document management

---

## Additional Development Requirements

### Admin Settings Interface Requirements

#### Feature Toggle Management
- **Main Settings Dashboard** - Central interface showing all available modules and their status
- **Module Dependencies** - Clear indication when features depend on others (e.g., Financial Reporting requires Financial Management)
- **Progressive Disclosure** - Simple organizations see basic toggles; advanced organizations can enable sub-features
- **Feature Impact Warnings** - Alert admins when disabling features that contain important data
- **Quick Setup Wizards** - Templates for common organization types (Community Association, Small Non-profit, HOA, etc.)

#### Organization Configuration
- **Branding Settings** - Organization name, logo, colors, terminology customization
- **Membership Structure** - Configure membership tiers, pricing, renewal cycles
- **User Role Customization** - Rename and configure permissions for different roles
- **Feature Terminology** - Customize language throughout the system (e.g., "Members" vs "Residents" vs "Donors")
- **Regional Settings** - Currency, date formats, address formats, tax requirements

#### WordPress Multisite Considerations (Future Enhancement)
- **Multisite Detection** - Plugin automatically detects if running on WordPress Multisite
- **Network Admin Integration** - Additional settings panel for network administrators
- **Site-Specific Configuration** - Each site maintains independent feature toggles and settings
- **Network-Wide Templates** - Option to create organization templates that can be applied to new sites
- **Shared Resources** - Optional sharing of certain features across network sites (user management, templates)

### Code Quality & Standards
- **WordPress Coding Standards** - Follow official WordPress PHP coding standards
- **PSR-4 Autoloading** - Proper namespace and class structure
- **Code documentation** - PHPDoc comments for all functions and classes
- **Security best practices** - WordPress nonces, data sanitization, SQL injection prevention
- **Performance optimization** - Efficient database queries and caching where appropriate

### API Integration Requirements
- **Stripe API credentials** - Read-only access keys for transaction download
- **Google Workspace API setup** - OAuth 2.0 configuration and service account keys
- **WordPress REST API** - Custom endpoints for external service communication
- **rclone configuration** - Google Drive integration setup and authentication
- **Webhook endpoints** - Secure endpoints for real-time data synchronization

### Database Design Requirements (SWCA-Specific)
- **WordPress prefix compatibility** - Use $wpdb->prefix for all custom tables
- **Historical data migration** - Import existing 215+ GravityForms entries and 150+ Stripe transactions
- **Form field evolution handling** - Support changing field structures over time
- **Transaction linking** - Match downloaded Stripe charges with form entries
- **Member relationship mapping** - Household members, business contacts, family connections
- **Processing fee tracking** - Separate fee amounts from donation/membership amounts
- **Event-specific tables** - BBQ event structure as template for all events
- **Communication preference storage** - Track preferred contact methods per member
- **Index optimization** - Performance indexes for member lookups, transaction searches, reporting queries
- **Backup compatibility** - Tables compatible with WordPress backup plugins

### Security Implementation
- **Role-based permissions** - Custom WordPress capabilities and roles
- **Data encryption** - Sensitive financial data encrypted at rest
- **API key security** - Secure storage of external service credentials
- **Input validation** - All user inputs properly sanitized and validated
- **Audit logging** - Track all financial data modifications with user attribution

### Testing & Quality Assurance
- **Unit testing framework** - PHP testing for core functionality
- **Integration testing** - API connections and data flow validation
- **User acceptance testing** - Role-based access and workflow testing
- **Performance testing** - Database query optimization and load testing
- **Security testing** - Vulnerability assessment and penetration testing

### Documentation Requirements
- **Code documentation** - Inline comments and function documentation
- **API documentation** - Integration guides for external services
- **User documentation** - Role-specific user manuals with screenshots
- **Installation guides** - Step-by-step setup for both development and production
- **Troubleshooting guides** - Common issues and resolution procedures

### Version Control & Release Management
- **Semantic versioning** - Standard version numbering (MAJOR.MINOR.PATCH)
- **Release notes** - Detailed changelog for each version
- **Branch protection** - Main branch requires pull request reviews
- **Automated testing** - GitHub Actions for continuous integration
- **Release packaging** - Automated .zip file creation for distribution

### External Service Dependencies
- **WordPress HTTP API** - For Stripe API read-only access (no SDK required)
- **Google Client Library** - PHP library for Google Workspace APIs
- **rclone binary** - File synchronization with Google Drive
- **PHPMailer** - Email functionality (WordPress core dependency)

### Development Tools & Environment
- **Composer** - Dependency management for PHP libraries
- **WP-CLI** - WordPress command-line interface for development tasks
- **MySQL/MariaDB** - Database server for local development
- **Apache/Nginx** - Web server for local WordPress installation
- **Git** - Version control with GitHub integration
- **Text editor/IDE** - Support for PHP development and debugging

---

## Technical Specifications

### Organization-Neutral Architecture
- **Configuration-driven design** - Database structure supports multiple organization types and configurations
- **Theme/template system** - Customizable UI elements, terminology, and branding per organization
- **Modular feature system** - Enable/disable features based on organization needs (events, advocacy, business partnerships)
- **Multi-tenant capability** - Architecture supports future expansion to serve multiple organizations
- **API standardization** - Consistent APIs that work across different organizational structures
- **Localization framework** - Support for different languages, currencies, and regional requirements

### Private WordPress Installation (Scalable Security Model)
- **Environment flexibility** - Deployable on various hosting configurations (shared, VPS, dedicated)
- **Security layers** - Configurable security based on organization sensitivity and compliance needs
- **Backup strategies** - Multiple backup options suitable for different organizational requirements
- **Performance optimization** - Scalable from 50-member organizations to 1000+ member groups
- **Integration capabilities** - Flexible API connections for different payment processors, email services, cloud storage

#### Authentication & Security
- **Independent user system** - No connection to public website users
- **Multi-factor authentication** - TOTP, SMS, or hardware token support
- **Capability-based permissions** - WordPress-native permission structure with custom roles
- **API key management** - Secure token system for external service connections
- **OAuth integration** - Google services integration with secure token storage
- **Financial data encryption** - AES-256 encryption for all sensitive information

### Data Management
- Local database with external service synchronization
- Data backup and recovery procedures
- Import/export capabilities for existing data

#### Reporting Engine
- **Customizable report generation** with local and Google Drive storage
- **Visual chart creation** embedded in Google Docs/Sheets
- **Export capabilities** - PDF, Excel, Google Sheets formats
- **Automated report scheduling** with email distribution and Drive sharing
- **Board presentation mode** - Clean, professional reports for meetings
- **Real-time dashboards** - Live KPI monitoring accessible via Google Sheets

---

## Private WordPress Installation Advantages

### Security Benefits
- **Complete isolation** - Financial data never exposed on public website
- **Enhanced access control** - Restrict access to authorized personnel only
- **Reduced attack surface** - Private installation not discoverable by search engines
- **Financial compliance** - Meets banking and payment processor security requirements
- **Data sovereignty** - Full control over sensitive member and financial information

### Operational Benefits
- **WordPress familiarity** - Staff can still use familiar WordPress interface
- **Plugin ecosystem** - Access to WordPress security and backup plugins
- **Scalability** - Can handle growing data loads without affecting public site
- **Independent updates** - Update management system without touching public website
- **Backup isolation** - Financial data backups separate from public content

### Integration Benefits
- **API-driven connections** - Secure, controlled data flow from public forms
- **Flexible hosting** - Can choose hosting optimized for security and performance
- **VPN compatibility** - Easy to restrict access via VPN or IP allowlisting
- **Audit compliance** - Clear separation of public content and private financial data

---

## Implementation Phases (Recommended)

### Phase 1: Core Foundation
- Google Workspace integration and authentication
- Basic member and donor database
- Stripe integration and transaction matching
- Essential financial tracking and reporting

### Phase 2: Document Management
- Google Drive file organization and sync
- Receipt upload and categorization
- Basic report generation to Drive
- Email communication system

### Phase 3: Enhanced Features
- Event management and attendance tracking
- Advanced reporting with visualizations
- Reimbursement workflow
- Calendar integration

### Phase 4: Advanced Analytics
- Predictive analytics and donor segmentation
- Compliance and governance tools
- Third-party integrations (banking, marketing)
- Advanced automation features

---

## Expected Deliverables
1. **GitHub repository** with complete plugin source code
2. **WordPress plugin** (.zip file ready for installation)
3. **Installation documentation** with setup instructions for both development and production
4. **Database migration scripts** and schema documentation
5. **API configuration guide** for external service connections
6. **User manual** for different role levels (treasurer, secretary, board members)
7. **Security hardening checklist** for production hosting setup
8. **Backup and maintenance procedures** documentation
9. **Test scripts and procedures** for functionality validation
10. **Sample data sets** for development and testing purposes
11. **Release documentation** with version history and upgrade procedures

---

## Success Metrics & Acceptance Criteria

### Functional Requirements
- **User can log in** with role-based access to appropriate features based on their role
- **Donor segmentation works** with engagement scoring and lifecycle tracking
- **Automated donor journeys function** with thank you sequences and stewardship workflows
- **Event Coordinators can create events** with planning checklists and volunteer opportunities
- **Volunteer system functions** like SignUpGenius with time slots, role requirements, and notifications
- **Communication system sends** personalized emails, SMS, and automated campaigns
- **Donor analytics provide** comprehensive insights including retention rates, lifetime value, and predictive scoring
- **Planning tools track** task completion, timeline milestones, and team coordination
- **Stripe transactions download** for reporting and reconciliation
- **GravityForms data imports** in real-time with donor profile updates and campaign tracking
- **Financial reports generate** with visual charts and export to Google Drive
- **Member database manages** annual renewals, addresses, communication preferences, and relationship mapping
- **Event tracking captures** attendance, expenses, revenue, and volunteer participation with historical comparisons
- **File management syncs** receipts and documents to Google Shared Drive

### Performance Requirements
- **Page load times** under 3 seconds for admin pages
- **Database queries** optimized for reporting functions
- **File uploads** handle PDF receipts up to 10MB
- **Concurrent users** support at least 10 simultaneous admin users
- **Data synchronization** completes within 30 minutes for full sync

### Security Requirements
- **All financial data encrypted** at rest and in transit
- **Multi-factor authentication** required for treasurer/admin roles
- **Audit logging** tracks all data modifications with user attribution
- **API keys stored securely** with proper WordPress encryption methods
- **Session management** with automatic timeout for inactive users

### Documentation Requirements
- **Code documentation** with inline comments and function descriptions
- **User guides** for each role level with screenshots
- **API integration guides** with step-by-step setup instructions
- **Troubleshooting guides** for common issues and error messages
- **Update/maintenance procedures** for ongoing system management