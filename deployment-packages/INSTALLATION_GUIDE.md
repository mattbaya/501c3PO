# SWCA Membership Management - Installation Guide

## Package Contents

You should have received two zip files:

1. **swca-membership-management.zip** - Main WordPress plugin
2. **swca-supplementary-tools.zip** - Administrative tools for data management

## Installation Steps

### Step 1: Install the Main Plugin

1. Log into your WordPress admin panel
2. Navigate to **Plugins > Add New > Upload Plugin**
3. Choose `swca-membership-management.zip`
4. Click **Install Now** then **Activate**

### Step 2: Initial Configuration

1. After activation, go to **CRM > SWCA Dashboard**
2. You'll see the main dashboard interface
3. Visit `/dashboard/settings` to:
   - Change the dashboard password (default: `F1v3C0rn3rs`)
   - Enable/disable modular features
   - Configure API keys if needed

### Step 3: Import Existing Data

If you have data to import from the development server:

1. On the old server, go to **CRM > Export & Import**
2. Click **Export Complete Database (ZIP)**
3. Download the generated ZIP file
4. On the new server, go to **CRM > Export & Import**
5. Upload the ZIP file in the Import section
6. Select import options:
   - ✓ Create backup before import (recommended)
   - ✓ Import plugin settings
   - Only check "Overwrite existing data" if you want to replace all data
7. Click **Import Data Package**

### Step 4: Configure User Access

The plugin creates these WordPress roles:
- **SWCA Member** - Basic dashboard access
- **SWCA Officer** - Can manage events and approve emails
- **SWCA Treasurer** - Full access including financial data
- **SWCA Committee Chair** - Committee management access

Assign these roles to users via **Users > All Users > Edit User**

### Step 5: Test Core Features

1. **Member Dashboard**: Visit `/dashboard`
   - Enter password when prompted
   - Verify member list displays correctly

2. **Export Function**: Go to **CRM > Export & Import**
   - Test the export functionality
   - Verify CSV downloads work

3. **Member Profiles**: Click on any member name
   - Verify individual profiles load
   - Check that notes and history display

## Using Administrative Tools

All administrative tools are now integrated into the web interface:

### Processing Stripe Refunds
1. Go to **CRM > Stripe Refunds**
2. Enter your Stripe API key (not stored)
3. Set the date range for analysis
4. Click **Analyze Refunds** to review changes
5. Confirm to apply refunds to member totals

### Importing Historical Data
1. Go to **CRM > Data Import Tools**
2. Upload your CSV file
3. Enter the year label (e.g., "2023-2024")
4. Select import options
5. Preview changes before importing
6. Confirm to apply the import

## Dashboard URLs

After installation, these pages will be available:

- **Main Dashboard**: `https://yoursite.com/dashboard`
- **Settings**: `https://yoursite.com/dashboard/settings`
- **Member Directory**: `https://yoursite.com/dashboard/members`
- **Email Management**: `https://yoursite.com/dashboard/email-dashboard` (if enabled)
- **Event Management**: `https://yoursite.com/dashboard/event-dashboard` (if enabled)
- **Officer Tools**: `https://yoursite.com/dashboard/officer-tools` (if enabled)

## Troubleshooting

### Dashboard Shows Raw Shortcodes
- This is a known issue with some themes
- The plugin includes fallback rendering
- Content will display correctly even if shortcodes show

### Can't Access Dashboard
- Ensure you're logged into WordPress
- Check that your user has appropriate SWCA role
- Verify the dashboard page exists in **Pages**

### Import Fails
- Check file upload limits in PHP settings
- Ensure the uploads directory is writable
- Try importing in smaller batches if needed

### Missing Features
- Go to `/dashboard/settings`
- Check that desired features are enabled
- Save settings and refresh the page

## Security Notes

1. **Change Default Password**: Immediately change the dashboard password
2. **API Keys**: Store securely, never commit to version control
3. **Database Backups**: Always backup before major imports
4. **User Roles**: Only assign treasurer role to trusted users
5. **Supplementary Tools**: Keep these scripts in a secure location

## Support

For issues or questions:
1. Check the WordPress error logs
2. Verify all plugin files were uploaded correctly
3. Ensure WordPress and PHP versions meet requirements
4. Document any error messages for troubleshooting

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- SSL certificate (recommended for secure data handling)