# SWCA Financial Transactions Table - Deployment Summary

## ✅ What Was Created

### 1. Combined Transaction Data
- **File**: `treasurer-docs/COMBINED_Transactions_Jan-Sept_2025.csv`
- **Records**: 143 total transactions (89 Bank + 54 Stripe)
- **Time Period**: January - September 2025
- **Content**: All bank and Stripe transactions merged and sorted chronologically

### 2. Interactive HTML Table
- **File**: `transactions-jan-sept-2025.html`
- **Location**: `/home/swca/public_html/transactions-jan-sept-2025.html`
- **Features**:
  - ✓ Sortable columns (click any header)
  - ✓ Search across all transactions
  - ✓ Filter by source (Bank/Stripe)
  - ✓ Filter by status (Paid/Posted/Refunded)
  - ✓ Summary statistics dashboard
  - ✓ Responsive design for mobile/desktop
  - ✓ Print-friendly layout

### 3. WordPress Admin Integration
- **Plugin Modified**: `swca-simple-export-import`
- **New Menu Item**: "SWCA Data" → "💰 Transactions"
- **Access Level**: Administrators only

## 🌐 How to Access

### Option 1: Direct URL
Visit: `https://yourdomain.com/transactions-jan-sept-2025.html`

Replace `yourdomain.com` with your actual domain name.

### Option 2: WordPress Admin
1. Log into WordPress admin panel
2. Click "SWCA Data" in the left sidebar
3. Click "💰 Transactions" submenu
4. Click the "Open Transactions Table →" button
5. Or scroll down to view the embedded table

### Option 3: Share with Officers/Treasurer
The HTML file is a standalone page that can be:
- Bookmarked for quick access
- Shared via direct link (password-protected by WordPress if needed)
- Embedded in other pages via iframe

## 📊 Summary Statistics

### Bank Summary
- **Total Credits (Income)**: $10,453.84
- **Total Debits (Expenses)**: $7,318.03
- **Net**: $3,135.81

### Stripe Summary
- **Gross Payments**: $1,316.36
- **Stripe Fees**: $63.47
- **Net Payments**: $1,266.09
- **Total Refunded**: $383.19

## 🔧 Updating the Data

To update the transaction table with new data:

1. **Export new CSVs** from bank and Stripe
2. **Place them** in `/home/swca/scripts/501c3PO/treasurer-docs/`
3. **Edit the PHP script** `combine_transactions.php` with new filenames
4. **Run the script**: `php combine_transactions.php`
5. **Regenerate HTML**: `php generate_html_transactions.php`
6. **Copy to website**: `cp transactions-table.html /home/swca/public_html/transactions-jan-sept-2025.html`

Or create a new file with a different date range (e.g., `transactions-oct-dec-2025.html`)

## 📁 File Locations

### Source Files (Development)
```
/home/swca/scripts/501c3PO/
├── combine_transactions.php          # Merge bank + Stripe CSVs
├── generate_html_transactions.php    # Generate HTML table
├── transactions-table.html           # Generated HTML (staging)
└── treasurer-docs/
    ├── MoutainOne Bank AccountHistory_Jan - Sept 2025.csv
    ├── STRIPE unified_payments_Jan - Sept 2025.csv
    └── COMBINED_Transactions_Jan-Sept_2025.csv
```

### Production Files (Website)
```
/home/swca/public_html/
├── transactions-jan-sept-2025.html   # Live HTML table
└── wp-content/plugins/swca-simple-export-import/
    └── swca-simple-export-import.php # Plugin with menu integration
```

## 🎨 Table Features

### Sorting
Click any column header to sort ascending/descending:
- Date
- Source (Bank/Stripe)
- Description
- Amount
- Fee
- Net Amount
- Status

### Searching
Type in the search box to filter transactions by any field:
- Transaction descriptions
- Amounts
- Dates
- Email addresses

### Filtering
Use the dropdown filters:
- **Source**: Show only Bank or Stripe transactions
- **Status**: Show only Paid, Posted, or Refunded

### Color Coding
- 🟢 Green: Positive amounts (income)
- 🔴 Red: Negative amounts (expenses)
- 🔵 Blue badge: Bank transactions
- 🟣 Purple badge: Stripe transactions

## 🔒 Security Notes

- The HTML file is accessible to anyone with the direct URL
- Consider using WordPress page protection if needed
- The admin menu item is restricted to administrators only
- CSV source files are NOT web-accessible (stored outside public_html)

## 💡 Future Enhancements

Possible improvements:
1. Add date range filters (by month/quarter)
2. Export filtered results to CSV
3. Add charts/graphs for visual analysis
4. Integrate with QuickBooks/accounting software
5. Add category tagging for expenses
6. Create monthly/quarterly summary reports
7. Add reconciliation tools to match Stripe deposits to bank records

---

**Created**: October 4, 2025
**By**: Claude Code Assistant
**For**: SWCA Treasurer's Office
