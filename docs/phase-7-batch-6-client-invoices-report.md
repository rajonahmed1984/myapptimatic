# Phase 7 Batch 6 Report: Client Invoices

Date: 2026-02-24
Status: Completed (module slice)

## Scope

Migrated these client invoice pages from Blade controller rendering to Inertia React:

- `client.invoices.index`
- `client.invoices.paid`
- `client.invoices.unpaid`
- `client.invoices.overdue`
- `client.invoices.cancelled`
- `client.invoices.refunded`
- `client.invoices.show`
- `client.invoices.pay`
- `client.invoices.manual` (GET)

Kept backend action logic unchanged:

- `client.invoices.checkout` (POST)
- `client.invoices.manual.store` (POST)
- `client.invoices.download` (GET, PDF binary)

## Files Added

- `resources/views/react-client.blade.php`
- `resources/js/react/Pages/Client/Invoices/Index.jsx`
- `resources/js/react/Pages/Client/Invoices/Pay.jsx`
- `resources/js/react/Pages/Client/Invoices/Manual.jsx`
- `tests/Feature/ClientInvoicesUiParityTest.php`

## Files Updated

- `app/Http/Middleware/HandleInertiaRequests.php` (client root view selection)
- `routes/web.php` (added `HandleInertiaRequests` to migrated client invoice GET routes)
- `app/Http/Controllers/Client/InvoiceController.php` (Inertia payload responses)
- `app/Http/Controllers/Client/ManualPaymentController.php` (Inertia payload response)
- `resources/js/ajax-engine.js` (critical-route bypass for `/client/invoices`)
- `tests/Feature/ClientInvoiceAccessTest.php` (updated assertion for Inertia response)
- `composer.json` (added `ClientInvoicesUiParityTest` to `phase7:verify`)

## Safety Checks

Executed and passed:

- `vendor/bin/pint --dirty`
- `php artisan test --filter=ClientInvoicesUiParityTest`
- `php artisan test --filter=ClientInvoiceAccessTest`
- `php artisan test --filter=ClientInvoiceCheckoutTest`
- `composer phase7:verify` (includes build/cache/full-suite checks)

## Result

- No auth/guard route behavior changed.
- Payment, upload, PDF, SSE regression gates remain green.
- Client invoice UI now runs through Inertia React with existing backend flows intact.
