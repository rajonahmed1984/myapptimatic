# Phase 8: Accounting form server-side lookups

## Goal

Keep Accounting create/edit forms fast as customer and invoice counts grow.

## Previous behaviour

Every form request loaded and serialized:

- every customer;
- every invoice and its customer;
- aggregate paid totals for every invoice.

This work happened before the user opened either searchable dropdown.

## Changes

- The initial form payload now contains only the currently selected customer
  and invoice, when applicable.
- Customer and invoice searches use authenticated JSON endpoints.
- Each lookup returns at most 20 rows.
- Search requests are debounced by the existing `SearchableSelect` component.
- Invoice lookup rows calculate paid and due amounts in SQL and still populate
  the customer, amount, reference, and description fields after selection.
- Payment gateways remain in the initial payload because that list is small and
  required directly by the form.

## Endpoints

- `admin.accounting.lookups.customers`
- `admin.accounting.lookups.invoices`

Both endpoints remain inside the existing admin route middleware and permission
boundary.

## Verification

```bash
php artisan test tests/Feature/AccountingUiParityTest.php
npm run build
```

The regression test creates 25 customers and invoices, confirms an empty
unselected initial payload, verifies the 20-row cap and search behaviour, checks
invoice due calculation, and verifies selected-invoice prefilling.
