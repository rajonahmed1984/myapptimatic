# React Pilot Staging Checklist: Finance Payment Methods Index

## Scope
- Route: `GET /admin/finance/payment-methods` (`admin.finance.payment-methods.index`)
- Flag: `FEATURE_ADMIN_FINANCE_PAYMENT_METHODS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_FINANCE_PAYMENT_METHODS_INDEX=true`
2. Rebuild caches:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Validation (ON/OFF parity)
1. Guard parity:
   - Master admin can access route (`200`) with flag OFF and ON.
   - Client role stays forbidden (`403`) with flag OFF and ON.
2. UI parity:
   - OFF returns Blade page.
   - ON returns Inertia page (`Admin/Finance/PaymentMethods/Index`).
3. Data parity:
   - Add/Edit form fields map correctly from existing data.
   - Method list rows show name/code/amount/details/status/order.
   - View/Edit/Delete actions still hit existing endpoints.
4. Non-regression:
   - `payment-methods.show/store/update/destroy` behavior remains unchanged.

## Safety Gates
- `php artisan test --filter=FinancePaymentMethodsUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_FINANCE_PAYMENT_METHODS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in finance payment method CRUD behavior.
