<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeWorkSummary;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Order;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Product;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    private const CACHE_TTL_SECONDS = 120;

    public function getMetrics(): array
    {
        return Cache::remember('dashboard.metrics.v1', self::CACHE_TTL_SECONDS, function () {
            $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
            if (! Currency::isAllowed($currency)) {
                $currency = Currency::DEFAULT;
            }

            return [
                'counts' => $this->counts(),
                'automation' => $this->automationMetrics(),
                'automationRuns' => $this->automationRuns(),
                'automationMetrics' => $this->automationMetricCards(),
                'periodMetrics' => $this->periodMetrics(),
                'periodSeries' => $this->periodSeries(),
                'incomeStatement' => $this->incomeStatementMetrics(),
                'billingAmounts' => $this->billingAmounts(),
                'currency' => $currency,
                'clientActivity' => $this->clientActivity(),
                'projectMaintenance' => $this->projectMaintenance(),
                'hrStats' => $this->hrStats(),
            ];
        });
    }

    private function counts(): array
    {
        return [
            'customerCount' => Customer::count(),
            'productCount' => Product::count(),
            'subscriptionCount' => Subscription::count(),
            'licenseCount' => License::count(),
            'pendingInvoiceCount' => Invoice::where('status', 'unpaid')->count(),
            'overdueCount' => Invoice::where('status', 'overdue')->count(),
            'pendingOrderCount' => Order::where('status', 'pending')->count(),
            'openTicketCount' => SupportTicket::where('status', 'open')->count(),
            'customerReplyTicketCount' => SupportTicket::where('status', 'customer_reply')->count(),
        ];
    }

    private function automationMetrics(): array
    {
        return [
            'invoices_created' => Invoice::whereDate('created_at', now()->toDateString())->count(),
            'overdue_suspensions' => Subscription::where('status', 'suspended')->count(),
            'tickets_closed' => SupportTicket::where('status', 'closed')->count(),
            'overdue_reminders' => Invoice::where('status', 'overdue')->count(),
        ];
    }

    private function automationRuns(): array
    {
        $startDate = now()->subDays(7)->startOfDay();
        $endDate = now()->endOfDay();
        $dailyInvoiceCounts = Invoice::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('day')
            ->pluck('total', 'day');

        $runs = [];
        for ($i = 7; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $runs[] = (int) ($dailyInvoiceCounts[$day] ?? 0);
        }

        return $runs;
    }

    private function automationMetricCards(): array
    {
        $automation = $this->automationMetrics();
        $seriesCount = max(1, count($this->automationRuns()));

        $metrics = [
            ['label' => 'Invoices Created', 'value' => $automation['invoices_created'], 'color' => 'emerald', 'stroke' => '#10b981'],
            ['label' => 'Overdue Suspensions', 'value' => $automation['overdue_suspensions'], 'color' => 'amber', 'stroke' => '#f59e0b'],
            ['label' => 'Inactive Tickets Closed', 'value' => $automation['tickets_closed'], 'color' => 'sky', 'stroke' => '#0ea5e9'],
            ['label' => 'Overdue Reminders', 'value' => $automation['overdue_reminders'], 'color' => 'rose', 'stroke' => '#f43f5e'],
        ];

        foreach ($metrics as &$metric) {
            $metric['series'] = array_fill(0, $seriesCount, $metric['value']);
        }
        unset($metric);

        return $metrics;
    }

    private function periodMetrics(): array
    {
        $periods = [
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'month' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'year' => [now()->subYear()->startOfDay(), now()->endOfDay()],
        ];

        $metrics = [];
        foreach ($periods as $key => [$start, $end]) {
            $metrics[$key] = [
                'new_orders' => Order::whereBetween('created_at', [$start, $end])->count(),
                'active_orders' => Order::where('status', 'accepted')->whereBetween('created_at', [$start, $end])->count(),
                'income' => $this->incomeTotalBetween($start, $end),
            ];
        }

        return $metrics;
    }

    private function periodSeries(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $hourlyOrders = Order::selectRaw('HOUR(created_at) as bucket, COUNT(*) as total')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
        $hourlyActiveOrders = Order::selectRaw('HOUR(created_at) as bucket, COUNT(*) as total')
            ->where('status', 'accepted')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
        $hourlyIncome = AccountingEntry::selectRaw('HOUR(entry_date) as bucket, SUM(amount) as total')
            ->where('type', 'payment')
            ->whereBetween('entry_date', [$todayStart, $todayEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
        $manualTodayIncome = (float) Income::whereDate('income_date', $todayStart->toDateString())->sum('amount');
        if ($manualTodayIncome !== 0.0) {
            $hourlyIncome[0] = (float) ($hourlyIncome[0] ?? 0) + $manualTodayIncome;
        }

        $todayLabels = [];
        $todayNew = [];
        $todayActive = [];
        $todayIncome = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $todayLabels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $todayNew[] = (int) ($hourlyOrders[$hour] ?? 0);
            $todayActive[] = (int) ($hourlyActiveOrders[$hour] ?? 0);
            $todayIncome[] = (float) ($hourlyIncome[$hour] ?? 0);
        }

        $monthStart = now()->subDays(29)->startOfDay();
        $monthEnd = now()->endOfDay();
        $dailyOrders = Order::selectRaw('DATE(created_at) as bucket, COUNT(*) as total')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
        $dailyActiveOrders = Order::selectRaw('DATE(created_at) as bucket, COUNT(*) as total')
            ->where('status', 'accepted')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
        $dailyIncomeSystem = AccountingEntry::selectRaw('DATE(entry_date) as bucket, SUM(amount) as total')
            ->where('type', 'payment')
            ->whereBetween('entry_date', [$monthStart, $monthEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
        $dailyIncomeManual = Income::selectRaw('DATE(income_date) as bucket, SUM(amount) as total')
            ->whereBetween('income_date', [$monthStart, $monthEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();

        $monthLabels = [];
        $monthNew = [];
        $monthActive = [];
        $monthIncome = [];
        for ($day = 0; $day < 30; $day++) {
            $date = $monthStart->copy()->addDays($day);
            $key = $date->toDateString();
            $monthLabels[] = $date->format('d M');
            $monthNew[] = (int) ($dailyOrders[$key] ?? 0);
            $monthActive[] = (int) ($dailyActiveOrders[$key] ?? 0);
            $monthIncome[] = (float) ($dailyIncomeSystem[$key] ?? 0) + (float) ($dailyIncomeManual[$key] ?? 0);
        }

        $yearStart = now()->startOfMonth()->subMonths(11);
        $yearEnd = now()->endOfMonth();
        $monthlyOrders = Order::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as bucket, COUNT(*) as total")
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
        $monthlyActiveOrders = Order::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as bucket, COUNT(*) as total")
            ->where('status', 'accepted')
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
        $monthlyIncomeSystem = AccountingEntry::selectRaw("DATE_FORMAT(entry_date, '%Y-%m') as bucket, SUM(amount) as total")
            ->where('type', 'payment')
            ->whereBetween('entry_date', [$yearStart, $yearEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
        $monthlyIncomeManual = Income::selectRaw("DATE_FORMAT(income_date, '%Y-%m') as bucket, SUM(amount) as total")
            ->whereBetween('income_date', [$yearStart, $yearEnd])
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();

        $yearLabels = [];
        $yearNew = [];
        $yearActive = [];
        $yearIncome = [];
        for ($month = 0; $month < 12; $month++) {
            $date = $yearStart->copy()->addMonths($month);
            $key = $date->format('Y-m');
            $yearLabels[] = $date->format('M');
            $yearNew[] = (int) ($monthlyOrders[$key] ?? 0);
            $yearActive[] = (int) ($monthlyActiveOrders[$key] ?? 0);
            $yearIncome[] = (float) ($monthlyIncomeSystem[$key] ?? 0) + (float) ($monthlyIncomeManual[$key] ?? 0);
        }

        return [
            'today' => [
                'labels' => $todayLabels,
                'new_orders' => $todayNew,
                'active_orders' => $todayActive,
                'income' => $todayIncome,
            ],
            'month' => [
                'labels' => $monthLabels,
                'new_orders' => $monthNew,
                'active_orders' => $monthActive,
                'income' => $monthIncome,
            ],
            'year' => [
                'labels' => $yearLabels,
                'new_orders' => $yearNew,
                'active_orders' => $yearActive,
                'income' => $yearIncome,
            ],
        ];
    }

    private function incomeStatementMetrics(): array
    {
        $periods = [
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'month' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'year' => [now()->subYear()->startOfDay(), now()->endOfDay()],
        ];

        $statement = [];

        foreach ($periods as $key => [$start, $end]) {
            $totals = AccountingEntry::query()
                ->select('type', DB::raw('SUM(amount) as total'))
                ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('type', ['payment', 'refund', 'credit', 'expense'])
                ->groupBy('type')
                ->pluck('total', 'type')
                ->all();

            $manualIncome = (float) Income::whereDate('income_date', '>=', $start->toDateString())
                ->whereDate('income_date', '<=', $end->toDateString())
                ->sum('amount');
            $payments = (float) ($totals['payment'] ?? 0) + $manualIncome;
            $refunds = (float) ($totals['refund'] ?? 0);
            $credits = (float) ($totals['credit'] ?? 0);
            $expenses = (float) ($totals['expense'] ?? 0);

            $statement[$key] = [
                'payments' => $payments,
                'refunds' => $refunds,
                'credits' => $credits,
                'expenses' => $expenses,
                'net' => $payments - $refunds - $credits - $expenses,
            ];
        }

        return $statement;
    }

    private function incomeTotalBetween(Carbon $start, Carbon $end): float
    {
        $manual = (float) Income::whereDate('income_date', '>=', $start->toDateString())
            ->whereDate('income_date', '<=', $end->toDateString())
            ->sum('amount');
        $system = (float) AccountingEntry::where('type', 'payment')
            ->whereDate('entry_date', '>=', $start->toDateString())
            ->whereDate('entry_date', '<=', $end->toDateString())
            ->sum('amount');

        return $manual + $system;
    }

    private function billingAmounts(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();
        $yearEnd = now()->endOfYear()->toDateString();

        return [
            'today' => (float) AccountingEntry::where('type', 'payment')
                ->whereDate('entry_date', $today)
                ->sum('amount'),
            'month' => (float) AccountingEntry::where('type', 'payment')
                ->whereBetween('entry_date', [$monthStart, $monthEnd])
                ->sum('amount'),
            'year' => (float) AccountingEntry::where('type', 'payment')
                ->whereBetween('entry_date', [$yearStart, $yearEnd])
                ->sum('amount'),
            'all_time' => (float) AccountingEntry::where('type', 'payment')->sum('amount'),
        ];
    }

    private function clientActivity(): array
    {
        $activeCustomerCount = Customer::where('status', 'active')->count();

        if (! Schema::hasTable('sessions')) {
            return [
                'activeCount' => $activeCustomerCount,
                'onlineCount' => 0,
                'recentClients' => collect(),
            ];
        }

        $onlineThreshold = Carbon::now()->subHour()->timestamp;
        $onlineUsersCount = (int) DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $onlineThreshold)
            ->distinct('user_id')
            ->count('user_id');

        $latestSessions = DB::table('sessions as s')
            ->select('s.user_id', 's.ip_address', 's.last_activity')
            ->join(DB::raw('(select user_id, max(last_activity) as last_activity from sessions where user_id is not null group by user_id) as latest'), function ($join) {
                $join->on('s.user_id', '=', 'latest.user_id')
                    ->on('s.last_activity', '=', 'latest.last_activity');
            })
            ->orderByDesc('s.last_activity')
            ->limit(30)
            ->get();

        $userIds = array_filter(array_map(fn ($row) => $row->user_id, $latestSessions->all()));
        $users = User::with('customer')->whereIn('id', $userIds)->get()->keyBy('id');

        $recentClients = $latestSessions->map(function ($sessionRow) use ($users) {
            $user = $users[$sessionRow->user_id] ?? null;
            $customerName = $user?->customer?->name;
            $displayName = $customerName ?: ($user->name ?? 'Unknown User');

            return [
                'user_id' => $user?->id,
                'name' => $displayName,
                'customer_id' => $user?->customer?->id,
                'last_login' => $sessionRow->last_activity
                    ? Carbon::createFromTimestamp($sessionRow->last_activity)->diffForHumans()
                    : 'Unknown',
                'ip' => $sessionRow->ip_address,
            ];
        });

        return [
            'activeCount' => $activeCustomerCount,
            'onlineCount' => $onlineUsersCount,
            'recentClients' => $recentClients,
        ];
    }

    private function projectMaintenance(): array
    {
        $projectsProfitable = 0;
        $projectsLoss = 0;

        return [
            'projects_active' => Project::where('status', 'ongoing')->count(),
            'projects_on_hold' => Project::where('status', 'hold')->count(),
            'subscriptions_blocked' => Subscription::where('status', 'suspended')->count(),
            'renewals_30d' => Subscription::where('status', 'active')
                ->whereNotNull('next_invoice_at')
                ->whereBetween('next_invoice_at', [now(), now()->addDays(30)])
                ->count(),
            'projects_profitable' => $projectsProfitable,
            'projects_loss' => $projectsLoss,
        ];
    }

    private function hrStats(): array
    {
        $windowStart = now()->subDays(6)->toDateString();
        $workSummaryQuery = EmployeeWorkSummary::query()
            ->whereDate('work_date', '>=', $windowStart);
        $workLogDays = (clone $workSummaryQuery)->count();
        $onTargetDays = (clone $workSummaryQuery)
            ->whereColumn('active_seconds', '>=', 'required_seconds')
            ->count();

        return [
            'active_employees' => Employee::where('status', 'active')->count(),
            'pending_timesheets' => $workLogDays,
            'approved_timesheets' => $onTargetDays,
            'draft_payroll_periods' => PayrollPeriod::where('status', 'draft')->count(),
            'finalized_payroll_periods' => PayrollPeriod::where('status', 'finalized')->count(),
            'payroll_items_to_pay' => PayrollItem::where('status', 'approved')->count(),
        ];
    }
}
