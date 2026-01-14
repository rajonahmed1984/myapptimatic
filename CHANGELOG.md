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

## Deploy / Notes
1. Run `php artisan migrate` to apply new tables/indexes (including `invoice_sequences`).
2. Ensure a queue worker is running (`php artisan queue:work`) for queued notifications and license sync.
3. If calling `/cron/billing` directly, use the signed URL shown in Admin Settings.
