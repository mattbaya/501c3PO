# Transaction Ledger Improvements Plan

## Critical Fixes
1. ✅ Balance display order - Fix sort order to (date DESC, id DESC) for consistent display
2. ✅ Add Stripe transaction links to matched rows
3. ✅ Implement new color scheme:
   - Green background: Matched deposits (in bank)
   - Blue background: Matched debits/expenses (in bank)
   - Yellow background: Unmatched transactions
   - Red background: Cannot balance/errors
   - Light grey: Description data for matched transactions

## Display Improvements
4. ✅ Sticky header row (stays visible when scrolling)
5. ✅ Add Print & Export buttons at top
6. ✅ Fix print output:
   - Remove "(click to add)" text from empty fields
   - B&W friendly (optional color mode)
   - Preserve row colors optionally

## Filtering & Data Management
7. ✅ Default filter: Current month + prior 12 months
8. ✅ Add pagination ("turn the page" links)
9. ✅ Add tag/category filtering to filter section

## New Features
10. ⏳ Create separate Bank Transactions page (sortable/filterable)
11. ⏳ Create separate Stripe Transactions page (sortable/filterable)
12. ⏳ Create Check Register feature:
    - Fields: check #, date, recipient, amount, purpose
    - Match to bank statements when checks clear
    - Show cleared vs uncleared status
    - Sortable/filterable table

## Implementation Order
- Phase 1: Critical fixes (balance, colors, links) - TODAY
- Phase 2: Display improvements (sticky header, print, export) - TODAY
- Phase 3: Filtering & pagination - TODAY
- Phase 4: New pages (Bank, Stripe, Check Register) - NEXT SESSION
