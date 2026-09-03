<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\License;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\Mail\ImapInboxService;
use App\Services\Mail\MailSessionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sidebar badge counts for every portal.
 *
 * These used to be computed inside `View::composer('app', ...)`, which only
 * fires when the Blade root view renders. The Inertia middleware reads its
 * props before that happens, so `view()->shared('adminHeaderStats')` was always
 * empty and no badge ever appeared. The numbers live here now so the composer
 * and the Inertia middleware can both ask for them, and they are memoised per
 * request because both do.
 */
class HeaderStatsService
{
    /** @var array<string, array<string, int>> */
    private array $cache = [];

    /**
     * @return array<string, int>
     */
    public function admin(?Request $request = null): array
    {
        return $this->remember('admin', function () use ($request) {
            $user = auth()->user();
            $taskBadge = 0;
            $unreadChat = 0;

            if ($user && $user->isAdmin()) {
                $taskQueryService = app(TaskQueryService::class);

                if ($taskQueryService->canViewTasks($user)) {
                    $summary = $taskQueryService->tasksSummaryForUser($user);
                    $taskBadge = (int) (($summary['open'] ?? 0) + ($summary['in_progress'] ?? 0));
                }

                $unreadChat = (int) DB::table('project_messages as pm')
                    ->whereRaw(
                        'pm.id > COALESCE((SELECT MAX(pmr.last_read_message_id) FROM project_message_reads as pmr WHERE pmr.project_id = pm.project_id AND pmr.reader_type = ? AND pmr.reader_id = ?), 0)',
                        ['user', $user->id]
                    )
                    ->count();
            }

            return [
                'pending_orders' => Order::where('status', 'pending')->count(),
                'overdue_invoices' => Invoice::where('status', 'overdue')->count(),
                'tickets_waiting' => SupportTicket::where('status', 'customer_reply')->count(),
                'open_support_tickets' => SupportTicket::where('status', 'open')->count(),
                'pending_manual_payments' => PaymentProof::where('status', 'pending')->count(),
                'pending_leave_requests' => LeaveRequest::where('status', 'pending')->count(),
                'tasks_badge' => $taskBadge,
                'unread_chat' => $unreadChat,
                'apptimatic_email_unread' => $this->apptimaticEmailUnread($request),
                'verified_active_synced_licenses' => License::query()
                    ->where('status', 'active')
                    ->whereNotNull('last_check_at')
                    ->whereNotNull('last_verified_at')
                    ->where('last_check_at', '>=', now()->subHours(48))
                    ->whereColumn('last_verified_at', '>=', 'last_check_at')
                    ->count(),
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function employee(): array
    {
        return $this->remember('employee', function () {
            $stats = ['task_badge' => 0, 'unread_chat' => 0];
            $user = auth()->user();

            if (! $user || ! $user->isEmployee()) {
                return $stats;
            }

            $employee = request()->attributes->get('employee');
            if (! ($employee instanceof Employee)) {
                $employee = $user->employee;
            }

            if (! $employee) {
                return $stats;
            }

            $taskQueryService = app(TaskQueryService::class);
            if ($taskQueryService->canViewTasks($user)) {
                $summary = $taskQueryService->tasksSummaryForUser($user);
                $stats['task_badge'] = (int) (($summary['open'] ?? 0) + ($summary['in_progress'] ?? 0));
            }

            // Keep the sidebar count aligned with the employee chat listing by
            // using the same relation-scoped project set.
            $projectIds = $employee->projects()->pluck('projects.id');

            if ($projectIds->isNotEmpty()) {
                $stats['unread_chat'] = (int) DB::table('project_messages as pm')
                    ->select('pm.project_id', DB::raw('COUNT(*) as unread'))
                    ->whereIn('pm.project_id', $projectIds->all())
                    ->whereRaw(
                        'pm.id > COALESCE((SELECT MAX(pmr.last_read_message_id) FROM project_message_reads as pmr WHERE pmr.project_id = pm.project_id AND pmr.reader_type = ? AND pmr.reader_id = ?), 0)',
                        ['employee', $employee->id]
                    )
                    ->groupBy('pm.project_id')
                    ->pluck('unread', 'pm.project_id')
                    ->map(fn ($count) => (int) $count)
                    ->sum();
            }

            return $stats;
        });
    }

    /**
     * @return array<string, int>
     */
    public function client(): array
    {
        return $this->remember('client', function () {
            $user = auth()->user();
            $customer = $user?->customer;
            $unreadChat = 0;
            $taskBadge = 0;
            $unpaidInvoices = 0;

            if ($user) {
                $projectIds = collect();

                if ($user->isClientProject()) {
                    $projectIds = collect($user->assignedProjectIds());
                } elseif ($user->isClient()) {
                    $projectIds = Project::where('customer_id', $user->customer_id)->pluck('id');
                }

                if ($projectIds->isNotEmpty()) {
                    $unreadChat = (int) DB::table('project_messages as pm')
                        ->leftJoin('project_message_reads as pmr', function ($join) use ($user) {
                            $join->on('pmr.project_id', '=', 'pm.project_id')
                                ->where('pmr.reader_type', 'user')
                                ->where('pmr.reader_id', $user->id);
                        })
                        ->whereIn('pm.project_id', $projectIds->all())
                        ->whereRaw('pm.id > COALESCE(pmr.last_read_message_id, 0)')
                        ->count();
                }

                $taskQueryService = app(TaskQueryService::class);
                if ($taskQueryService->canViewTasks($user)) {
                    $summary = $taskQueryService->tasksSummaryForUser($user);
                    $taskBadge = (int) (($summary['open'] ?? 0) + ($summary['in_progress'] ?? 0));
                }
            }

            if ($customer) {
                // Count only invoices that still owe money, so a part-paid
                // invoice does not keep nagging for its full balance.
                $unpaidInvoices = Invoice::query()
                    ->where('customer_id', $customer->id)
                    ->whereIn('status', ['unpaid', 'overdue'])
                    ->whereRaw("(COALESCE(invoices.total, 0) - COALESCE((SELECT SUM(CASE WHEN type IN ('payment', 'credit') THEN amount ELSE 0 END) FROM accounting_entries WHERE accounting_entries.invoice_id = invoices.id), 0)) > 0.009")
                    ->count();
            }

            return [
                'pending_admin_replies' => $customer
                    ? SupportTicket::where('customer_id', $customer->id)->where('status', 'answered')->count()
                    : 0,
                'unpaid_invoices' => $unpaidInvoices,
                'unread_chat' => $unreadChat,
                'task_badge' => $taskBadge,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function salesRep(): array
    {
        return $this->remember('rep', function () {
            $stats = ['task_badge' => 0, 'unread_chat' => 0];
            $user = auth()->user();

            if (! $user || ! $user->isSales()) {
                return $stats;
            }

            $salesRep = request()->attributes->get('salesRep');
            if (! ($salesRep instanceof SalesRepresentative)) {
                $salesRep = SalesRepresentative::where('user_id', $user->id)->first();
            }

            $taskQueryService = app(TaskQueryService::class);
            if ($taskQueryService->canViewTasks($user)) {
                $summary = $taskQueryService->tasksSummaryForUser($user);
                $stats['task_badge'] = (int) (($summary['open'] ?? 0) + ($summary['in_progress'] ?? 0));
            }

            if ($salesRep) {
                $projectIds = $salesRep->projects()->pluck('projects.id');

                if ($projectIds->isNotEmpty()) {
                    $stats['unread_chat'] = (int) DB::table('project_messages as pm')
                        ->leftJoin('project_message_reads as pmr', function ($join) use ($salesRep) {
                            $join->on('pmr.project_id', '=', 'pm.project_id')
                                ->where('pmr.reader_type', 'sales_rep')
                                ->where('pmr.reader_id', $salesRep->id);
                        })
                        ->whereIn('pm.project_id', $projectIds->all())
                        ->whereRaw('pm.id > COALESCE(pmr.last_read_message_id, 0)')
                        ->count();
                }
            }

            return $stats;
        });
    }

    /**
     * Empty counts for every portal, used when the database is unreachable.
     *
     * @return array<string, array<string, int>>
     */
    public static function empty(): array
    {
        return [
            'admin' => [
                'pending_orders' => 0,
                'overdue_invoices' => 0,
                'tickets_waiting' => 0,
                'open_support_tickets' => 0,
                'pending_manual_payments' => 0,
                'pending_leave_requests' => 0,
                'tasks_badge' => 0,
                'unread_chat' => 0,
                'apptimatic_email_unread' => 0,
                'verified_active_synced_licenses' => 0,
            ],
            'employee' => ['task_badge' => 0, 'unread_chat' => 0],
            'client' => [
                'pending_admin_replies' => 0,
                'unpaid_invoices' => 0,
                'unread_chat' => 0,
                'task_badge' => 0,
            ],
            'rep' => ['task_badge' => 0, 'unread_chat' => 0],
        ];
    }

    private function apptimaticEmailUnread(?Request $request): int
    {
        return $this->resolveApptimaticEmailUnreadCount($request ?? request());
    }

    private function resolveApptimaticEmailUnreadCount(Request $request): int
    {
        $fallback = app(ApptimaticEmailStubRepository::class)->unreadCount();

        try {
            if (! $request->hasSession()) {
                return $fallback;
            }

            $mailSessionService = app(MailSessionService::class);
            $imapInboxService = app(ImapInboxService::class);

            if (! $imapInboxService->isAvailable()) {
                return $fallback;
            }

            $session = $mailSessionService->validateSession($request);
            $mailAccount = $session?->mailAccount;
            if (! $session || ! $mailAccount) {
                return $fallback;
            }

            $password = $mailSessionService->decryptPassword($request);
            if (! is_string($password) || $password === '') {
                return $fallback;
            }

            $token = (string) $request->session()->get(MailSessionService::SESSION_TOKEN_KEY, '');
            $cacheKey = 'apptimatic_email_unread:' . $mailAccount->id . ':' . substr(hash('sha256', $token), 0, 16);

            return (int) Cache::remember($cacheKey, now()->addSeconds(20), function () use ($imapInboxService, $mailAccount, $password): int {
                return $imapInboxService->unreadCount($mailAccount, $password);
            });
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @param  callable(): array<string, int>  $resolver
     * @return array<string, int>
     */
    private function remember(string $key, callable $resolver): array
    {
        if (! array_key_exists($key, $this->cache)) {
            try {
                $this->cache[$key] = $resolver();
            } catch (\Throwable) {
                $this->cache[$key] = self::empty()[$key] ?? [];
            }
        }

        return $this->cache[$key];
    }
}
