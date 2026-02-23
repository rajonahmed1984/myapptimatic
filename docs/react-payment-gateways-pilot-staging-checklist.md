# React Pilot Staging Checklist: Payment Gateways Index

## Scope
- Route: `GET /admin/payment-gateways` (`admin.payment-gateways.index`)
- Flag: `FEATURE_ADMIN_PAYMENT_GATEWAYS_INDEX`
- Fallback: Blade view remains default when flag is `false`

## Enable
1. Set staging env:
   - `FEATURE_ADMIN_PAYMENT_GATEWAYS_INDEX=true`
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
   - ON returns Inertia page (`Admin/PaymentGateways/Index`).
3. Data parity:
   - Gateway rows, driver label, status badge, and empty state render correctly.
   - `Edit` links still route to existing edit endpoints.
4. Non-regression:
   - Update flows (`PUT /admin/payment-gateways/{paymentGateway}`) unchanged.

## Safety Gates
- `php artisan test --filter=PaymentGatewayUiParityTest`
- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback
1. Set `FEATURE_ADMIN_PAYMENT_GATEWAYS_INDEX=false`
2. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`

## Exit Criteria
- No ON/OFF parity drift in status code, auth behavior, and index rendering.
- No regression in payment gateway edit/update behavior.
