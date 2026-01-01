# Project Status Management Documentation

## Overview
This document outlines all status types, their meanings, transitions, and the automation that manages them.

---

## 1. INVOICE STATUS

### Status Values
- **unpaid**: Invoice created but payment not received
- **overdue**: Payment due date has passed
- **paid**: Payment has been received and confirmed
- **cancelled**: Invoice cancelled, no longer valid
- **refunded**: Payment refunded to customer

### Status Transitions
```
unpaid → overdue (when due_date < today)
unpaid → paid (when payment received)
unpaid → cancelled (manual or auto-cancellation)
paid → refunded (when payment refunded)
overdue → paid (when payment received)
overdue → cancelled (auto-cancellation after threshold)
```

### Automation Rules
- **Mark Overdue**: Runs daily - Changes unpaid invoices to overdue if past due date
- **Late Fees**: Applies late fees after configured days (see Settings)
- **Auto-Cancellation**: Cancels unpaid invoices older than `auto_cancellation_days`
- **Payment Received**: Automatically set when payment is confirmed

### Related Fields
- `due_date`: Date payment is due
- `overdue_at`: Timestamp when marked as overdue
- `paid_at`: Timestamp when marked as paid
- `late_fee`: Additional charges for late payment
- `late_fee_applied_at`: When late fee was applied

### Settings
- `invoice_due_days`: Default days until invoice due date
- `late_fee_days`: Days overdue before applying late fee
- `late_fee_amount`: Amount or percentage of late fee
- `late_fee_type`: 'fixed' or 'percent'
- `auto_cancellation_days`: Days overdue before auto-cancel

---

## 2. SUBSCRIPTION STATUS

### Status Values
- **active**: Subscription is active and in good standing
- **suspended**: Subscription paused due to billing issues or manual action
- **cancelled**: Subscription terminated

### Status Transitions
```
active → suspended (manual or auto-suspension due to overdue invoices)
active → cancelled (manual or auto-termination)
suspended → active (manual unsuspension or when billing is resolved)
suspended → cancelled (manual termination or auto-termination)
```

### Automation Rules
- **Auto-Suspension**: Suspends subscriptions when invoices are overdue past `suspend_days`
  - Triggers license suspension
  - Blocks customer access temporarily
  - Can be overridden via `access_override_until`

- **Auto-Unsuspension**: Reactivates suspended subscriptions when:
  - All outstanding invoices are paid
  - No unpaid/overdue invoices remain
  - Triggers license reactivation

- **Auto-Termination**: Cancels subscriptions when invoices are overdue past `termination_days`
  - Triggers license revocation
  - Permanently blocks customer access

- **Period-End Cancellation**: Automatically cancels `cancel_at_period_end` subscriptions

### Related Fields
- `current_period_start`: Start date of current billing period
- `current_period_end`: End date of current billing period
- `next_invoice_at`: When next invoice will be generated
- `cancelled_at`: Timestamp when cancelled
- `auto_renew`: Whether subscription auto-renews
- `cancel_at_period_end`: Flag to cancel at period end instead of immediately

### Settings
- `enable_suspension`: Enable/disable auto-suspension
- `suspend_days`: Days overdue before suspension
- `enable_unsuspension`: Enable/disable auto-unsuspension
- `enable_termination`: Enable/disable auto-termination
- `termination_days`: Days overdue before termination

---

## 3. LICENSE STATUS

### Status Values
- **active**: License is valid and can be used
- **suspended**: License temporarily disabled (subscription suspended)
- **revoked**: License permanently disabled

### Status Transitions
```
active → suspended (when subscription suspended)
active → revoked (when subscription cancelled or license expires)
suspended → active (when subscription unsuspended)
```

### Automation Rules
- **Suspend with Subscription**: Automatically suspended when parent subscription is suspended
- **Revoke with Subscription**: Automatically revoked when parent subscription is terminated
- **Expire by Date**: Automatically revoked when `expires_at` date is reached
- **License Verification**: API checks license status and blocks access if:
  - License status is not 'active'
  - Subscription is not active
  - Customer is inactive
  - Customer has unpaid/overdue invoices (beyond grace period)

### Related Fields
- `starts_at`: When license is valid from
- `expires_at`: When license expires
- `last_check_at`: Last time license was verified
- `last_check_ip`: IP address of last verification

### Settings
- `grace_period_days`: Days after due date before blocking access

---

## 4. CUSTOMER STATUS

### Status Values
- **active**: Customer has at least one active subscription
- **inactive**: Customer has no active subscriptions

### Status Transitions
```
inactive → active (when first subscription activated)
active → inactive (when all subscriptions cancelled)
```

### Automation Rules
- **Activate**: Automatically activated when customer gets active subscription
- **Deactivate**: Automatically deactivated when last active subscription is cancelled/suspended
- **Access Override**: `access_override_until` field allows temporary access even with billing issues

### Related Fields
- `access_override_until`: DateTime until which customer has full access regardless of billing

---

## 5. SUPPORT TICKET STATUS

### Status Values
- **open**: Ticket is active and waiting for response
- **closed**: Ticket is closed and no longer active

### Status Transitions
```
open → closed (manual or auto-close)
```

### Automation Rules
- **Auto-Close**: Closes open tickets where last reply was more than `ticket_auto_close_days` ago
  - Only if `ticket_auto_close` is enabled
  - Marks `auto_closed_at` timestamp

### Related Fields
- `last_reply_at`: When ticket was last replied to
- `last_reply_by`: User ID of last replier
- `closed_at`: When ticket was closed
- `auto_closed_at`: When ticket was auto-closed

### Settings
- `ticket_auto_close`: Enable/disable auto-close
- `ticket_auto_close_days`: Days of inactivity before auto-close

---

## 6. ORDER STATUS

### Status Values
- **pending**: Order awaiting approval
- **approved**: Order approved and active
- **cancelled**: Order cancelled

### Status Transitions
```
pending → approved (manual approval)
pending → cancelled (manual cancellation)
approved → cancelled (manual cancellation)
```

### Automation Rules
- Manual approval only - no automatic transitions
- When approved, creates subscription and initial invoice

---

## Status Update Automation

### Daily Cron Job: `php artisan billing:run`

This command executes the following status updates in order:

1. **Generate Invoices** - Creates invoices for subscriptions due
2. **Mark Overdue** - Unpaid → Overdue
3. **Apply Late Fees** - Adds fees to overdue invoices
4. **Auto-Cancellation** - Unpaid/Overdue → Cancelled (old invoices)
5. **Auto-Suspension** - Active → Suspended (with late billing)
6. **Auto-Termination** - Active → Cancelled (very old billing issues)
7. **Auto-Unsuspension** - Suspended → Active (when paid)
8. **Update Customer Status** - Inactive ↔ Active
9. **License Expiry Checks** - Revoke expired licenses
10. **Send Notifications** - Reminders and alerts

### StatusUpdateService API

Use the `StatusUpdateService` class for:

```php
$service = app(StatusUpdateService::class);

// Update specific statuses
$service->updateInvoiceOverdueStatus($today);
$service->updateSubscriptionSuspensionStatus($today);
$service->updateSubscriptionUnsuspensionStatus();
$service->updateSubscriptionTerminationStatus($today);
$service->updateCustomerStatus();
$service->updateLicenseExpiryStatus($today);
$service->updateSupportTicketAutoCloseStatus($today);

// Update all statuses
$metrics = $service->updateAllStatuses($today);

// Get summary
$summary = $service->getStatusSummary();
```

---

## Status Rules Priority

When multiple rules apply, they execute in this order:

1. **Termination** (most severe) - Cancels subscription and revokes license
2. **Suspension** - Pauses subscription and suspends license
3. **Unsuspension** - Reactivates when issues resolved
4. **Expiry** - Revokes expired licenses
5. **Activation** - Activates customer with active subscription

---

## Manual Status Overrides

Admins can manually override status updates:

- **Access Override**: Set `customer.access_override_until` to allow access despite billing issues
- **Invoice Status**: Manually mark invoice as paid, cancelled, or refunded
- **Subscription Status**: Manually suspend or cancel subscription
- **License Status**: Manually change license status
- **Ticket Status**: Manually close or reopen tickets

---

## Status Verification

### For Clients/API
License verification endpoint (`/api/licenses/verify`) checks:
1. License exists and is active
2. Customer is active
3. License not expired
4. Subscription is active
5. No unpaid/overdue invoices (beyond grace period)
6. Domain is registered for license

### For Admins
Dashboard shows:
- Overdue invoice count
- Unpaid invoice count
- Suspended/cancelled subscriptions
- Inactive customers
- Suspended/revoked licenses
- Open support tickets

---

## Configuration

All automation is controlled via Settings:

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `late_fee_days` | integer | 0 | Days overdue before applying fee |
| `late_fee_amount` | decimal | 0 | Fee amount or percentage |
| `late_fee_type` | string | fixed | 'fixed' or 'percent' |
| `auto_cancellation_days` | integer | 0 | Days overdue before auto-cancel |
| `enable_suspension` | boolean | true | Enable auto-suspension |
| `suspend_days` | integer | 0 | Days overdue before suspend |
| `enable_unsuspension` | boolean | true | Enable auto-unsuspension |
| `enable_termination` | boolean | true | Enable auto-termination |
| `termination_days` | integer | 0 | Days overdue before terminate |
| `grace_period_days` | integer | 3 | Days grace before blocking access |
| `ticket_auto_close` | boolean | true | Enable ticket auto-close |
| `ticket_auto_close_days` | integer | 30 | Days before auto-closing ticket |

---

## Troubleshooting

### License Still Blocked After Payment
1. Check invoice status is 'paid'
2. Verify subscription status is 'active'
3. Check customer status is 'active'
4. Wait for next cron run or manually sync license
5. If customer has other unpaid invoices, pay those too

### Subscription Not Unsuspended
1. Verify all invoices are 'paid'
2. Check `enable_unsuspension` is true in settings
3. Run cron: `php artisan billing:run`

### License Not Revoked Despite Expiry
1. Check license `expires_at` date
2. Verify it's actually in the past
3. Run cron: `php artisan billing:run`

### Customer Not Activated
1. Check subscription status is 'active'
2. Check customer status is not already 'active'
3. Run cron: `php artisan billing:run`

---

## Activity Logging

All status changes are logged to `system_logs` table with:
- Action type: 'activity'
- Description of change
- Related model IDs
- Timestamps
- Admin user ID (if manual)
