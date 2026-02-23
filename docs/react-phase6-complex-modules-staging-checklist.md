# React Phase 6 Staging Checklist (Complex Modules)

Use this checklist for one full release cycle before expanding migration scope.

## Feature Flags

- `FEATURE_ADMIN_PAYMENT_PROOFS_INDEX`
- `FEATURE_ADMIN_PAYMENT_GATEWAYS_INDEX`
- `FEATURE_ADMIN_CHATS_INDEX`

Record flag values at deploy time.

## Payments

- Verify manual payment proof list renders in both flag OFF/ON.
- Approve one pending proof and confirm:
  - Redirect path is unchanged.
  - Flash message is unchanged.
  - Attempt status becomes `paid`.
  - Invoice status becomes `paid`.
- Reject one pending proof and confirm:
  - Redirect path is unchanged.
  - Flash message is unchanged.
  - Attempt status becomes `failed`.
- Exercise callback URLs (sandbox) and confirm redirect target remains invoice pay page.

## PDF / Binary

- Download client invoice PDF and confirm:
  - `content-type` contains `application/pdf`
  - `content-disposition` contains `attachment`
- Open payment-proof receipt and support-ticket attachment:
  - Response is non-HTML binary
  - Filename in `content-disposition` is correct

## Uploads

- Upload attachment in at least one chat/task flow.
- Confirm stored file exists on expected disk path.
- Confirm attachment download URL returns non-HTML binary response.

## SSE / Chat

- Open project chat stream as admin and client.
- Confirm response headers:
  - `content-type: text/event-stream`
  - `cache-control: no-cache`
  - `x-accel-buffering: no`
- Validate no unauthorized access for unrelated client/project.

## Required Gates Per Deploy

- `php artisan test`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`

## Rollback Trigger

Rollback immediately if any of these occur:

- Redirect/validation/flash mismatch against Blade baseline
- Payment callback fails to update expected status
- Binary endpoint returns HTML or wrong headers
- SSE stream loses `text/event-stream` contract

Rollback action:

- Flip affected feature flags OFF.
- Run:
  - `php artisan optimize:clear`
  - `php artisan config:cache`
  - `php artisan route:cache`
