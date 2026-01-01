# Status Color Migration Checklist

This checklist tracks the migration of all status displays to use standardized colors.

## ✅ Completed

- [x] **StatusColorHelper class** - `app/Support/StatusColorHelper.php`
- [x] **Status Badge Component** - `resources/views/components/status-badge.blade.php`
- [x] **Documentation** - `STATUS_COLOR_GUIDE.md`
- [x] **Implementation Guide** - `STATUS_COLOR_IMPLEMENTATION.md`
- [x] **Licenses List View** - `resources/views/admin/licenses/index.blade.php`
- [x] **Dashboard Automation Status** - `resources/views/admin/dashboard.blade.php`

## 📋 Priority Views (To Update)

### High Priority (Common Admin Views)

- [ ] **Invoices List** - `resources/views/admin/invoices/index.blade.php`
  - Status: paid, unpaid, overdue, cancelled
  - Component: `<x-status-badge :status="$invoice->status" />`
  
- [ ] **Subscriptions List** - `resources/views/admin/subscriptions/index.blade.php`
  - Status: active, suspended, terminated
  - Component: `<x-status-badge :status="$subscription->status" />`
  
- [ ] **Orders List** - `resources/views/admin/orders/index.blade.php`
  - Status: May need custom mapping (pending, processing, completed, cancelled, failed)
  - Need to add order statuses to helper first
  
- [ ] **Customers List** - `resources/views/admin/customers/index.blade.php`
  - Status: active, inactive
  - Component: `<x-status-badge :status="$customer->status ?? 'inactive'" />`

- [ ] **Support Tickets List** - `resources/views/admin/support-tickets/index.blade.php`
  - Status: open, closed
  - Component: `<x-status-badge :status="$ticket->status" />`

### Medium Priority (Client Portal Views)

- [ ] **Client Invoices** - `resources/views/client/invoices/index.blade.php`
  - Status: paid, unpaid, overdue
  - Component: `<x-status-badge :status="$invoice->status" />`
  
- [ ] **Client Subscriptions** - `resources/views/client/subscriptions/index.blade.php`
  - Status: active, suspended
  - Component: `<x-status-badge :status="$subscription->status" />`
  
- [ ] **Client Licenses** - `resources/views/client/licenses/index.blade.php`
  - Status: active, suspended, revoked
  - Component: `<x-status-badge :status="$license->status" />`

### Low Priority (Detail & Edit Views)

- [ ] **Invoice Detail** - `resources/views/admin/invoices/show.blade.php`
- [ ] **Subscription Detail** - `resources/views/admin/subscriptions/show.blade.php`
- [ ] **License Edit** - `resources/views/admin/licenses/edit.blade.php`
- [ ] **Customer Edit** - `resources/views/admin/customers/edit.blade.php`
- [ ] **Order Detail** - `resources/views/admin/orders/show.blade.php`
- [ ] **Ticket Detail** - `resources/views/admin/support-tickets/show.blade.php`

## 📊 Status Types to Support

### Add to Helper (if not already present)

- [ ] Order statuses: `pending`, `processing`, `completed`, `failed`, `refunded`
- [ ] Payment statuses: `authorized`, `captured`, `declined`, `chargeback`
- [ ] Refund statuses: `requested`, `approved`, `refunded`, `declined`
- [ ] Customer statuses: `active`, `inactive`, `banned`
- [ ] Domain statuses: `pending`, `active`, `expired`, `suspended`
- [ ] Affiliate statuses: `active`, `suspended`, `terminated`

## 🔄 Update Pattern

For each view, follow this pattern:

### Step 1: Identify Status Fields
```blade
<!-- Find all places where status is displayed -->
{{ $invoice->status }}
{{ $subscription->status }}
{{ $license->status }}
```

### Step 2: Replace with Component
```blade
<!-- Replace with -->
<x-status-badge :status="$invoice->status" />
<x-status-badge :status="$subscription->status" />
<x-status-badge :status="$license->status" />
```

### Step 3: Test
- Check that colors match the guide
- Verify all status types display correctly
- Test responsive design on mobile

### Step 4: Mark Complete
- [ ] Check off in this list
- Add a note if special handling was needed

## 📝 View Update Log

### Example Format:
```
- [x] Invoices List - Updated 2026-01-02
  - Statuses: paid (emerald), unpaid (amber), overdue (rose)
  - Special handling: None needed
  - Notes: Works with 5 invoice states
```

---

## 🎨 Color Reference

| Status | Color | Classes |
|--------|-------|---------|
| active/paid/success | Emerald | `bg-emerald-100 text-emerald-700` |
| pending/unpaid | Amber | `bg-amber-100 text-amber-700` |
| blocked/overdue/failed | Rose | `bg-rose-100 text-rose-700` |
| running/in-progress | Blue | `bg-blue-100 text-blue-700` |
| inactive/closed/archived | Slate | `bg-slate-100 text-slate-700` |

## 🚀 Deployment Checklist

Before pushing changes:

- [ ] All views updated to use standardized colors
- [ ] No hardcoded color classes in templates
- [ ] Component imports work correctly
- [ ] Colors match STATUS_COLOR_GUIDE.md
- [ ] Tested on desktop and mobile
- [ ] Existing functionality not broken
- [ ] Documentation updated

## 📞 Support

If a status type isn't in the helper:

1. Check `app/Support/StatusColorHelper.php` for full list
2. Add new status to `getStatusColors()` method
3. Update this checklist
4. Test in relevant views

## 🏁 Completion Target

**Goal:** All status displays using standardized colors by end of January 2026

**Progress:** 2/12 (17%) - In Progress

Last updated: January 2, 2026
