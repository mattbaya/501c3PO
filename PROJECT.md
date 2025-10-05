# Non-Profit Management System - Stripe Integration

## Stripe Integration Requirements (Updated)

### Read-Only Access Requirements
The system requires **read-only access** to Stripe for downloading historical transaction data. No payment processing capabilities are needed.

### Key Requirements:
1. **Download Historical Transactions** - Retrieve past payments for financial reporting
2. **Auto-Pagination Support** - Automatically download all transactions beyond 100-transaction API limit
3. **Flexible Date Filtering** - Optional date ranges, defaults to all-time when "Download All" enabled
4. **Transaction Details** - Access amount, currency, description, customer email, status
5. **No Payment Processing** - System does not create charges or process new payments
6. **No Webhooks Required** - Only historical data download, no real-time notifications needed

### Implementation Details:

#### Stripe API Access
- **API Endpoint**: `/v1/charges` (read-only)
- **Authentication**: Bearer token with Secret Key
- **Required Permissions**: Read access to charges/transactions
- **API Version**: 2023-10-16 (or latest stable)

#### WordPress Plugin Features
- Simple admin interface for API key configuration
- Test/Live mode toggle
- Download transactions by date range (optional - defaults to all-time)
- Auto-pagination to download all transactions beyond 100-limit
- "Download All" checkbox for automatic pagination
- Display transactions in WordPress admin table
- Export capabilities (future enhancement)

#### Security Considerations
- API keys stored securely in WordPress options
- HTTPS required for admin access
- Role-based access control (admin only)
- No sensitive payment data stored locally

### Removed Requirements:
- ❌ Stripe PHP SDK (not needed for read-only access)
- ❌ Payment form integration
- ❌ Webhook endpoints
- ❌ Customer creation
- ❌ Subscription management
- ❌ Refund processing

### API Key Configuration:
```php
// Test Mode
$test_secret_key = 'sk_test_...';  // Read-only access

// Live Mode  
$live_secret_key = 'sk_live_...';  // Read-only access
```

### Sample API Call:
```php
// Download transactions for date range
GET https://api.stripe.com/v1/charges?limit=100&created[gte]=1609459200&created[lte]=1640995199
Authorization: Bearer sk_test_...
```

### Data Fields Retrieved:
- Transaction ID
- Amount (in cents)
- Currency
- Description
- Customer Email
- Created Date
- Status (succeeded, failed, etc.)

This simplified approach eliminates complex dependencies while providing the necessary transaction data for financial reporting and reconciliation.