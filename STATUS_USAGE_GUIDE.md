# Status Update System - Usage Guide

## Quick Start

### Understanding Status Automation

The system automatically manages all statuses based on configured rules and timing. Here's what happens:

#### Daily Automatic Updates

Every day at the time configured in your cron/scheduler, the system:

1. **Generates invoices** for active subscriptions
2. **Marks unpaid invoices as overdue** if past due date
3. **Applies late fees** based on settings
4. **Suspends subscriptions** with old overdue invoices
5. **Terminates subscriptions** with very old overdue invoices
6. **Unsuspends subscriptions** when all invoices are paid
7. **Updates customer status** based on active subscriptions
8. **Revokes expired licenses**
9. **Auto-closes support tickets** that are inactive

---

## Status Hierarchy & Dependencies

```
Payment Received
    ↓
Invoice Paid
    ↓
Subscription Active (no unpaid invoices)
    ↓
License Active
    ↓
Customer Access Granted
    ↓
License Can Be Verified


Payment NOT Received & Due Date Passed
    ↓
Invoice Overdue
    ↓
(After grace period) Subscription Suspended
    ↓
License Suspended
    ↓
Customer Access Blocked
    ↓
License Verification Fails
```

---

## Common Scenarios

### Scenario 1: Customer Pays Invoice

**What Happens:**
1. Admin marks payment as received via `/admin/invoices/{id}/mark-paid` or approves manual payment
2. Invoice status → `paid`
3. System checks if customer has other unpaid invoices
4. If no other unpaid invoices: Subscription status → `active` (if suspended)
5. If subscription unsuspended: License status → `active` (if suspended)
6. Customer access immediately restored
7. Next license check will return status `active`

**Timeline:**
- Immediate: Invoice marked paid
- Immediate: Subscription/License/Customer updated
- Next check: Customer license works again

---

### Scenario 2: Invoice Becomes Overdue

**What Happens:**
1. Invoice due date passes
2. Next cron run: Invoice status → `overdue`
3. Customer can still access (grace period applies)

**After Grace Period Expires (default: 3 days):**
1. License verification fails with reason: `invoice_overdue`
2. Customer sees message: "Access temporarily blocked for due billing"
3. License still shows status `active` in admin (blocked at API level)

**After Suspension Threshold (default: days from settings):**
1. Next cron run: Subscription status → `suspended`
2. License status → `suspended`
3. Admin can manually unsuspend if needed

**After Termination Threshold (default: days from settings):**
1. Next cron run: Subscription status → `cancelled`
2. License status → `revoked`
3. Customer access is permanently blocked

---

### Scenario 3: Multiple Unpaid Invoices

**Situation:** Customer has Invoice A (30 days overdue) and Invoice B (5 days overdue)

**What Happens:**
1. Both invoices marked as overdue
2. Customer access blocked (Invoice A triggers it)
3. Customer pays Invoice A
4. Invoice A status → `paid`
5. **BUT:** Invoice B still unpaid/overdue
6. Customer access remains blocked (Invoice B blocks it)
7. Customer must pay Invoice B to get access

**Solution:** Show customer ALL unpaid invoices, not just one

---

### Scenario 4: Manual Access Override

**Situation:** Customer has billing issues but needs temporary access

**Admin Action:**
1. Go to `/admin/customers/{id}/edit`
2. Set `access_override_until` to tomorrow or next week
3. Customer gets full access regardless of billing status
4. Access automatically revoked on that date
5. Customer must pay to get permanent access

---

### Scenario 5: License Expires

**What Happens:**
1. License `expires_at` date arrives
2. Next cron run: License status → `revoked`
3. License verification fails: `license_expired`
4. Customer cannot use license anymore

**Note:** This is separate from billing - a paid license can still expire by date

---

## Configuration Guide

### Settings to Configure

Access `/admin/settings` to configure:

#### Billing Related
- **Late Fee Days** (0 = disabled): Days overdue before late fee applies
- **Late Fee Amount**: The fee (amount or percentage)
- **Late Fee Type**: 'fixed' (amount) or 'percent' (percentage)
- **Auto Cancellation Days** (0 = disabled): Auto-cancel old unpaid invoices
- **Invoice Due Days**: Default days until invoice due (e.g., 30 days)

#### Suspension Related
- **Enable Suspension**: Checkbox to enable/disable
- **Suspend Days** (0 = disabled): Days overdue before suspension
- **Enable Unsuspension**: Checkbox to auto-unsuspend when paid
- **Grace Period Days**: Days after due before blocking access

#### Termination Related
- **Enable Termination**: Checkbox to enable/disable
- **Termination Days** (0 = disabled): Days overdue before termination

#### Support Tickets
- **Ticket Auto Close**: Checkbox to enable/disable
- **Ticket Auto Close Days**: Days of inactivity before auto-closing

---

## Manual Status Changes

### For Invoices
1. Go to `/admin/invoices/{id}`
2. Click "Edit" button
3. Change status to: `unpaid`, `overdue`, `paid`, `cancelled`, or `refunded`
4. System automatically logs the change

### For Subscriptions
1. Go to `/admin/subscriptions/{id}/edit`
2. Change status to: `active`, `suspended`, or `cancelled`
3. Licenses update automatically

### For Licenses
1. Go to `/admin/licenses/{id}/edit`
2. Change status to: `active`, `suspended`, or `revoked`
3. System logs the change

### For Customers
1. Go to `/admin/customers/{id}/edit`
2. Change status to: `active` or `inactive`
3. Or set `access_override_until` date for temporary access

---

## Troubleshooting

### Issue: License Still Blocked After Paying

**Check These:**
1. Is the invoice status actually 'paid'? (Not just marked in comment)
2. Are there OTHER unpaid invoices for this customer?
3. Is the grace period still active?
4. Is customer still set as inactive?

**Fix:**
1. Verify invoice status in database
2. Get list of ALL invoices for customer
3. Pay any other unpaid invoices
4. Manually trigger sync: Click "Sync" button in license list
5. Or wait for next cron run

### Issue: Subscription Not Unsuspending

**Check These:**
1. Are ALL invoices for the subscription 'paid'?
2. Is `enable_unsuspension` enabled in settings?

**Fix:**
1. Manually unsuspend via edit page
2. Make sure all invoices are paid
3. Check cron logs: `php artisan billing:run`

### Issue: License Not Syncing

**Check These:**
1. Is the client calling the verify endpoint?
2. Is there a firewall/whitelist blocking requests?

**Fix:**
1. Click "Sync" button in admin for that license
2. Verify license key is correct
3. Check API endpoint is accessible: `/api/licenses/verify`

### Issue: Cron Not Running

**Check These:**
1. Is cron scheduled? (Use cPanel, Task Scheduler, etc.)
2. Check command: `php artisan billing:run`
3. Check logs: `/storage/logs/`

**Fix:**
1. Set up cron: `0 3 * * * php /path/to/artisan billing:run`
2. Or use Laravel Scheduler in `App\Console\Kernel`
3. Test manually: `php artisan billing:run`

---

## API Integration

### License Verification Endpoint

**Endpoint:** `POST /api/licenses/verify`

**Request:**
```json
{
    "license_key": "ABCD1234EFGH5678",
    "domain": "example.com"
}
```

**Response (Success):**
```json
{
    "status": "active",
    "blocked": false,
    "notice": null,
    "license_id": 123,
    "customer_id": 456
}
```

**Response (Blocked - Overdue):**
```json
{
    "status": "blocked",
    "blocked": true,
    "reason": "invoice_overdue",
    "grace_ends_at": "2026-01-05 23:59:59",
    "payment_url": "/client/invoices/789/pay",
    "invoice_id": 789,
    "invoice_number": "202601001"
}
```

**Possible Reasons:**
- `invoice_overdue`: Customer has overdue invoice beyond grace period
- `invoice_due`: Customer has unpaid invoice (for notifications)
- `license_not_found`: License key doesn't exist
- `license_inactive`: License status is not 'active'
- `license_expired`: License expiry date passed
- `customer_inactive`: Customer account disabled
- `subscription_inactive`: Parent subscription not active
- `domain_not_allowed`: Domain not registered to this license

---

## For Plugin/Software Integration

### How to Handle Blocked Status

When your customer's license is blocked, show:

1. **Message:** "Access temporarily blocked for due billing"
2. **Show:** Due invoice number and amount
3. **Action Button:** Link to payment page
4. **Auto-Retry:** Check license again after customer returns from payment

### Example Code (PHP)

```php
// Check license status
$response = Http::post('https://yourdomain.com/api/licenses/verify', [
    'license_key' => $licenseKey,
    'domain' => $_SERVER['HTTP_HOST']
]);

$data = $response->json();

if ($data['blocked']) {
    // Show payment message
    die("Your license is blocked for due billing. " .
        "Please pay invoice #{$data['invoice_number']} " .
        "to restore access. " .
        "<a href='{$data['payment_url']}'>Pay Now</a>");
} else {
    // Allow access
    // Use product normally
}
```

### Grace Period Behavior

- Customer can access for a few days (grace period)
- System shows warning: "Payment due by [date]"
- After grace period expires: Access blocked
- Payment URL provided automatically

---

## Monitoring & Reports

### Dashboard Metrics

Go to `/admin/automation-status` to see:
- Last cron run time
- Cron status (success/failed/running)
- Metrics summary:
  - Invoices generated
  - Invoices marked overdue
  - Subscriptions suspended/unsuspended
  - Subscriptions terminated
  - Customers updated
  - Late fees applied

### Status Summary

```php
$service = app(StatusUpdateService::class);
$summary = $service->getStatusSummary();

// Returns:
[
    'overdue_invoices' => 15,
    'unpaid_invoices' => 32,
    'suspended_subscriptions' => 8,
    'cancelled_subscriptions' => 23,
    'inactive_customers' => 12,
    'suspended_licenses' => 8,
    'revoked_licenses' => 5,
    'open_support_tickets' => 4,
]
```

### Audit Log

All status changes are logged in `status_audit_logs` table:
- Which status changed
- From what to what
- When it happened
- Why (reason)
- Who triggered it (admin user)

---

## Best Practices

1. **Test Settings First**: Start with long thresholds, reduce gradually
2. **Enable Notifications**: Set up email reminders
3. **Monitor Grace Period**: Match with your business policy
4. **Manual Overrides**: Use judiciously for special cases
5. **Regular Audits**: Check `/admin/invoices` for payment issues
6. **Test Cron**: Ensure it runs daily before relying on it
7. **Backup Data**: Before major automation changes

---

## Support & Issues

If status automation seems broken:

1. Check cron logs: `/storage/logs/`
2. Run manually: `php artisan billing:run`
3. Check settings are configured
4. Review system logs: `/admin/logs/activity`
5. Check status audit logs for change history
6. Verify database permissions
7. Check error logs: `php artisan logs`

For detailed debugging, enable debug mode in `.env` and check `storage/logs/`.
