# Status Management - Quick Reference Card

## Status Flow Overview

```
INVOICE FLOW:
unpaid → overdue → suspended → terminated
        ↓
        paid ✓

SUBSCRIPTION FLOW:
active → suspended → cancelled
   ↓        ↓
  paid    unsuspend
  
LICENSE FLOW:
active → suspended → revoked
  ↓        ↓
 paid    unsuspend

CUSTOMER FLOW:
inactive ↔ active
         (based on subscriptions)
```

---

## Daily Cron (php artisan billing:run)

### What Happens In Order
1. Generate invoices for subscriptions due
2. Mark unpaid → overdue (if past due date)
3. Apply late fees (if configured)
4. Auto-cancel old unpaid invoices
5. Suspend subscriptions with old overdue
6. Terminate subscriptions with very old overdue
7. Unsuspend subscriptions when invoices paid
8. Activate/deactivate customers
9. Revoke expired licenses
10. Auto-close inactive support tickets
11. Send notifications and reminders

---

## Key Thresholds (Configure in Settings)

| Setting | Default | Meaning |
|---------|---------|---------|
| `grace_period_days` | 3 | Days after due before API blocks |
| `suspend_days` | ? | Days overdue before suspension |
| `termination_days` | ? | Days overdue before cancellation |
| `late_fee_days` | 0 | Days before applying late fee |

---

## When Customer Loses Access

1. Invoice due date passes
2. Status changes to `overdue`
3. Grace period active (customer still has access)
4. Grace period expires (license verification fails)
5. Customer sees: "Access blocked for due billing"
6. Customer must pay within 24 hours
7. Payment received → Invoice paid
8. License verification returns `active`
9. **Access immediately restored**

---

## How to Fix Blocked License

### As Customer
1. Go to `/client/invoices`
2. Find overdue invoice
3. Click "Pay Now"
4. Complete payment
5. License automatically unblocks next check

### As Admin
1. Go to `/admin/invoices/{id}`
2. Click "Mark as Paid" OR
3. Approve manual payment proof
4. System automatically:
   - Updates invoice status
   - Updates subscription status
   - Updates customer status
   - License unblocks immediately

### Alternative: Override Access
1. Go to `/admin/customers/{id}/edit`
2. Set `access_override_until` to future date
3. Customer gets access temporarily
4. Must pay for permanent fix

---

## Status Service Usage

### In Controllers
```php
// Inject
public function __construct(
    private StatusUpdateService $statusUpdateService
) {}

// Use
$statusUpdateService->updateCustomerStatus();
$statusUpdateService->updateAllStatuses();
$summary = $statusUpdateService->getStatusSummary();
```

### In Commands
```php
// Already integrated in billing:run
// To manually trigger:
php artisan billing:run
```

### In Tests
```php
$service = app(StatusUpdateService::class);
$count = $service->updateInvoiceOverdueStatus();
$this->assertEquals(1, $count);
```

---

## Troubleshooting Checklist

### License Still Blocked After Payment?
- [ ] Is invoice status actually 'paid'?
- [ ] Are there OTHER unpaid invoices?
- [ ] Are we past grace period?
- [ ] Has cron run since payment?
- [ ] Try clicking "Sync" button

### Subscription Not Unsuspending?
- [ ] Are ALL invoices 'paid'?
- [ ] Is `enable_unsuspension` true?
- [ ] Run: `php artisan billing:run`

### Cron Not Working?
- [ ] Check cron is scheduled
- [ ] Check logs: `tail storage/logs/laravel.log`
- [ ] Test: `php artisan billing:run`

---

## API Endpoint (for Plugin Integration)

```
POST /api/licenses/verify

Request:
{
    "license_key": "ABCD1234EFGH5678",
    "domain": "example.com"
}

Response (Success):
{
    "status": "active",
    "blocked": false
}

Response (Blocked):
{
    "status": "blocked",
    "blocked": true,
    "reason": "invoice_overdue",
    "payment_url": "/client/invoices/123/pay"
}
```

---

## Automation Rules Summary

### Invoices
- Unpaid → Overdue (daily, when past due)
- Apply late fees (daily, after X days)
- Auto-cancel (daily, after X days)

### Subscriptions  
- Auto-suspend (when invoice is X days overdue)
- Auto-unsuspend (when all invoices paid)
- Auto-terminate (when invoice is X days overdue)

### Licenses
- Sync with subscription status
- Revoke on expiry date
- Block access via API if overdue

### Customers
- Activate (when has active subscription)
- Deactivate (when no active subscriptions)
- Override access (temporary)

### Tickets
- Auto-close (after X days inactivity)

---

## Manual Overrides

### Bypass Billing Block
```
Go to /admin/customers/{id}/edit
Set "access_override_until" = tomorrow/next week
Customer gets full access temporarily
```

### Manually Mark Invoice Paid
```
Go to /admin/invoices/{id}
Click "Mark as Paid"
System updates everything automatically
```

### Manually Update Status
```
Go to /admin/[invoices|subscriptions|licenses]/edit
Change status directly
System logs the change
```

---

## Status Summary Dashboard

Go to `/admin/automation-status` to see:
- Last cron run time
- Last cron status (success/failed)
- Automation metrics
- Payment statistics

---

## Activity Audit Log

All status changes logged to:
- **Admin Interface**: `/admin/logs/activity`
- **Database**: `status_audit_logs` table
- **System Logs**: `storage/logs/laravel.log`

Each entry includes:
- What changed (model type & ID)
- From/to status
- When it happened
- Why (reason)
- Who did it (admin user)

---

## Important Notes

1. **Cron is critical** - Must run daily for automation
2. **Settings matter** - Configure days/fees appropriately
3. **Grace period** - Balances customer experience with cash flow
4. **Notifications** - Send reminders before blocking
5. **Audit trail** - All changes logged
6. **Access override** - Use sparingly
7. **Payment confirmation** - Triggers immediate unblock
8. **License verification** - Checked by client software

---

## Performance Tips

- Cron runs once daily (ideally 3 AM)
- Use indexes on status columns
- Archive old status logs periodically
- Cache customer subscription statuses

---

## Where to Learn More

1. **Technical Reference**: Read `STATUS_MANAGEMENT.md`
2. **User Guide**: Read `STATUS_USAGE_GUIDE.md`
3. **Implementation Details**: Read `STATUS_IMPLEMENTATION_SUMMARY.md`
4. **Code**: Check `app/Services/StatusUpdateService.php`

---

## Contact & Support

For issues:
1. Check the logs at `/admin/logs/activity`
2. Review status audit: query `status_audit_logs`
3. Test manually: `php artisan billing:run --verbose`
4. Check configuration in `/admin/settings`
