# Changelog

## Unreleased
- Added dashboard metrics caching and removed heavy session scans from runtime queries.
- Added performance indexes for invoices, subscriptions, tasks, licenses, and accounting tables.
- Paginated project tasks and task activity/message feeds to reduce memory usage.
- Added AJAX endpoints for task chat/activity and task CRUD with feature tests.
- Introduced `invoice_sequences` to generate invoice numbers safely under concurrency.
- Billing cron now chunks large queries and queues notifications instead of sending inline.
- License sync now queues jobs, exposes a sync-status endpoint, and updates UI via AJAX.
- Enforced subtask permissions via policy checks in the controller.
- Cron endpoint now requires signed URLs (token still required); settings show the signed URL.
- Added chat read-state endpoint and infinite-scroll loading for chat/activity history.
- Fixed signed time-delta accumulation for work session/activity tracking under Carbon 3.
- Added `diagnostics:integrity` command for orphaned data scans with tests.
- Added client task detail view test to ensure subtask completion controls stay hidden.
- Tightened employee task view permissions so unassigned employees cannot access other projects' visible tasks (with test coverage).
- Added client invoice access tests to confirm same-customer restrictions.
- Added commission payout idempotency coverage for project completion.
- Added client checkout workflow tests for paid and unpaid invoices.
- Added admin invoice status update tests for paid/overdue timestamps.
- Added sales rep negative-access coverage for unassigned project tasks.
- Added license verification tests for expiry, inactive status, domain binding, and signature enforcement.
- Added project chat API stability tests and stale session closure command coverage.
- Added upload coverage for project chat attachments and support ticket attachments.
- Added expense validation coverage for inactive categories.

## Deploy / Notes
1. Run `php artisan migrate` to apply new tables/indexes (including `invoice_sequences`).
2. Ensure a queue worker is running (`php artisan queue:work`) for queued notifications and license sync.
3. If calling `/cron/billing` directly, use the signed URL shown in Admin Settings.
