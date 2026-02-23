# React Pilot Staging Checklist: Accounting Ledger Index

## Scope
- Routes:
  - `GET /admin/accounting` (`admin.accounting.index`)
  - `GET /admin/accounting/ledger` (`admin.accounting.ledger`)
- Flag: `FEATURE_ADMIN_ACCOUNTING_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_ACCOUNTING_INDEX=true`
2. Rebuild caches:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Validation (ON/OFF parity)
1. Guard parity:
   - Master admin can access ledger routes (`200`) with flag OFF and ON.
   - Client role stays forbidden (`403`) with flag OFF and ON.
2. UI parity:
   - OFF returns Blade page.
   - ON returns Inertia page (`Admin/Accounting/Index`).
3. Data parity:
   - Search input and create links are visible.
   - Ledger rows show date/type/customer/invoice/gateway/amount/reference.
   - Edit and delete actions continue to target existing endpoints.
4. Non-regression:
   - `accounting/transactions` remains Blade-only.
   - create/store/edit/update/destroy behaviors remain unchanged.

## Safety Gates
- `php artisan test --filter=AccountingUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_ACCOUNTING_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in accounting create/store/edit/update/destroy and transactions flows.
