# New Graph & Analysis Features

**Created:** October 11, 2025
**Status:** ✅ Deployed to Production

## Overview

Two new financial analysis features have been added to help visualize and understand your organization's finances.

---

## 1. 📈 Year-over-Year Comparison

**URL:** https://southwilliamstown.org/wp-admin/admin.php?page=501c3PO-year-over-year

**Navigation:** WordPress Admin → Membership Management → 📈 Year-over-Year

### Features

#### Annual Totals Table
- Compare total income, expenses, and net across all years
- Growth percentage calculations (vs previous year)
- Transaction counts per year
- Color-coded growth indicators (▲ green for positive, ▼ red for negative)

#### Interactive Grouped Bar Chart
- Monthly comparison across years side-by-side
- Toggle between three views:
  - **Income Comparison** - See which months brought in more money each year
  - **Expenses Comparison** - Compare spending patterns across years
  - **Net Comparison** - See which months were most profitable

#### Monthly Breakdown Tables
- Separate table for each year showing all 12 months
- Income, Expenses, and Net for each month
- Faded display for months with no data
- Annual totals at bottom of each table

### Use Cases

- **Board Reports**: "Income increased 15% year-over-year"
- **Seasonal Analysis**: "August is consistently our strongest month"
- **Trend Spotting**: "Expenses have grown faster than income"
- **Budget Planning**: "Based on last year's June, we should expect..."

---

## 2. 🥧 Expense Category Breakdown

**URL:** https://southwilliamstown.org/wp-admin/admin.php?page=501c3PO-expense-breakdown

**Navigation:** WordPress Admin → Membership Management → 🥧 Expense Breakdown

### Features

#### Side-by-Side Donut Charts
- **Expenses by Category** - Visual breakdown of where money goes
- **Income by Category** - Visual breakdown of revenue sources
- Interactive tooltips showing dollar amounts and percentages
- Color-coded segments for easy identification

#### Summary Dashboard
- Total Expenses (red)
- Total Income (green)
- Net Amount (color-coded)
- Number of categories

#### Category Tables
Separate tables for expenses and income showing:
- Category name
- Total amount
- Number of transactions
- Percentage of total
- Average transaction amount

#### Recent Transactions List
- Last 100 expenses
- Shows date, description, category, amount, and notes
- Direct link to Transaction Ledger for categorizing

#### Date Range Filtering
- Filter by date range to focus on specific periods
- "Apply Filters" or "Clear Filters"

### Important Notes

**Categories must be added manually!** To categorize transactions:
1. Go to **Transaction Ledger** (📒 Transaction Ledger)
2. Click on any **Category** field
3. Type a category name (e.g., "Utilities", "Insurance", "Events")
4. Press Enter to save

**Suggested Categories:**
- **Expenses**: Utilities, Insurance, Rent, Supplies, Events, Marketing, Repairs, Fees, Payroll
- **Income**: Memberships, Donations, Event Revenue, Grants, Merchandise, Interest

### Use Cases

- **Board Meetings**: "30% of our expenses go to utilities"
- **Budget Planning**: "We should allocate more for events based on last year"
- **Grant Applications**: "Here's how we spent the last grant money"
- **Tax Preparation**: Easy category totals for accounting

---

## Technical Details

### Files Created

1. `/includes/features/year-over-year-comparison.php` (14KB)
2. `/includes/features/expense-breakdown.php` (16KB)
3. Updated: `/501c3po.php` (added to feature loading array)

### Dependencies

- WordPress Admin Interface
- Chart.js 4.4.0 (CDN)
- `wp_swca_bank_transactions` table with data
- For categories: Manual categorization via Transaction Ledger

### Database Queries

**Year-over-Year:**
```sql
-- Gets data grouped by year and month
SELECT
    YEAR(post_date) as year,
    MONTH(post_date) as month,
    SUM(credit) as income,
    SUM(debit) as expenses
FROM wp_swca_bank_transactions
GROUP BY YEAR(post_date), MONTH(post_date)
```

**Expense Breakdown:**
```sql
-- Gets data grouped by category
SELECT
    category,
    SUM(debit) as total_expenses,
    COUNT(*) as transaction_count
FROM wp_swca_bank_transactions
WHERE debit > 0
GROUP BY category
```

### Validation

✅ PHP Syntax: No errors detected
✅ File Permissions: Readable by web server (644)
✅ Plugin Registration: Added to feature files array
✅ Menu Integration: Properly registered with WordPress admin_menu hook
✅ Chart Library: Loaded from official CDN
✅ Security: Uses WordPress nonces and sanitization

---

## Complete List of Graph Features

After today's additions, your system now has:

1. **📊 Income & Expense Graph** - Monthly income vs expenses over time
2. **📈 Year-over-Year Comparison** - Compare performance across multiple years (NEW!)
3. **🥧 Expense Breakdown** - Pie charts showing category breakdowns (NEW!)

Plus related analysis tools:
- **📒 Transaction Ledger** - Complete transaction details with editing
- **🏦 Bank Transactions** - Import and manage bank CSV data
- **💰 Calculate Balances** - Running balance calculations
- **💳 Stripe Sync** - Pull payment data from Stripe API
- **🔗 Match Transactions** - AI-powered transaction matching

---

## Next Steps

### For Year-over-Year to be useful:
- Import bank data for multiple years (currently have 2025)
- Consider importing 2024 bank statements for comparison

### For Expense Breakdown to be useful:
- Categorize your transactions in the Transaction Ledger
- Develop a consistent category naming scheme
- Recommended: Spend 30 minutes categorizing your top 50 transactions

---

## Screenshots

*Access the actual pages to see the live charts in action!*

### Year-over-Year Features:
- Annual totals table with growth percentages
- Grouped bar chart (switch between income/expenses/net)
- Monthly breakdown tables for each year

### Expense Breakdown Features:
- Side-by-side donut charts (expenses and income)
- Category tables with percentages
- Recent transactions list
- Date range filtering

---

## Support

All features follow WordPress security best practices:
- Require `manage_options` capability (admin only)
- Use WordPress $wpdb API for database access
- Sanitize all user inputs
- Use nonces for form submissions

**File Location:** `/home/swca/public_html/wp-content/plugins/501c3PO/includes/features/`
