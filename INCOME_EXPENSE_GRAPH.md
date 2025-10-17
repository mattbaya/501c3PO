# Income & Expense Graph Feature

**Created:** October 11, 2025
**Status:** ✅ Deployed to Production

## Overview

Interactive visual graph showing income and expenses from bank statement data over time.

## Access

**URL:** https://southwilliamstown.org/wp-admin/admin.php?page=501c3po-income-expense-graph

**Navigation:** WordPress Admin → Membership Management → 📊 Income & Expense Graph

## Features

### Interactive Chart
- **Chart Types:** Toggle between Line Chart and Bar Chart
- **Data Views:**
  - All (Income & Expenses together)
  - Net Only
  - Income Only
  - Expenses Only
- **Technology:** Chart.js 4.4.0 (loaded from CDN)

### Summary Statistics Dashboard
- Date range covered
- Total transactions count
- Total income (green)
- Total expenses (red)
- Net amount (color-coded: green if positive, red if negative)

### Monthly Breakdown Table
- Month-by-month breakdown
- Income, Expenses, and Net for each month
- Transaction count per month
- Grand totals at bottom
- Color-coded values for easy reading

### Visual Features
- Smooth line/bar animations
- Hover tooltips showing exact dollar amounts
- Currency formatting with thousands separators
- Responsive design (works on all screen sizes)

## Data Source

Reads from `wp_swca_bank_transactions` table, which contains bank CSV import data.

**Current Data Range:** Based on imported bank statements (typically January 2025 - September 2025)

## Technical Details

### File Location
`/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/income-expense-graph.php`

### Database Query
```sql
SELECT
    DATE_FORMAT(transaction_date, '%Y-%m') as month,
    SUM(COALESCE(credit, 0)) as total_income,
    SUM(COALESCE(debit, 0)) as total_expenses,
    COUNT(*) as transaction_count
FROM wp_swca_bank_transactions
GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
ORDER BY month
```

### Dependencies
- WordPress admin interface
- Chart.js 4.4.0 (CDN: https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js)
- wp_swca_bank_transactions table with data

## Usage

1. **Log into WordPress Admin**
   - Go to https://southwilliamstown.org/wp-admin

2. **Navigate to Graph**
   - Click "Membership Management" in left sidebar (or your organization name if customized)
   - Click "📊 Income & Expense Graph"

3. **Interact with Chart**
   - Use "Chart Type" dropdown to switch between Line and Bar charts
   - Use "Show" dropdown to filter what data is displayed
   - Hover over chart elements to see exact values
   - Scroll down to see detailed monthly breakdown table

## Notes

- **No Data Message:** If no bank transactions exist, page displays instructions to import bank data
- **Real-time Calculation:** All totals calculated live from database (no caching)
- **Color Scheme:**
  - 🟢 Green: Income (positive)
  - 🔴 Red: Expenses (negative)
  - 🔵 Blue: Net (dashed line showing balance)

## Related Features

- **🏦 Bank Transactions** - Import bank CSV data
- **📒 Transaction Ledger** - Detailed transaction-by-transaction view
- **💰 Calculate Balances** - Running balance calculations

## Files Created

1. `/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/income-expense-graph.php` - Main feature file
2. `/home/swca/public_html/wp-content/plugins/501c3PO/501c3po.php` - Updated to load new feature
3. `/home/swca/scripts/501c3PO/INCOME_EXPENSE_GRAPH.md` - This documentation

## Validation

✅ PHP Syntax: No errors detected
✅ File Permissions: Readable by web server (644)
✅ Plugin Registration: Added to feature files array
✅ Security: Follows WordPress security best practices
✅ Database Access: Uses WordPress $wpdb API
✅ Chart Library: Loaded from official CDN

## Future Enhancements (Optional)

- Date range filter
- Export chart as image
- Comparison with previous year
- Fiscal year view
- Category breakdown (if bank transactions are categorized)
