<?php

namespace App\Services;

use App\Enums\Role;
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
use App\Models\UserSession;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    private const CACHE_TTL_SECONDS = 120;
    private const WHMCS_DEFAULT_START = '2026-01-01';
    private const WHMCS_PAGE_SIZE = 100;
    private const WHMCS_MAX_PAGES = 50;
    private array $carrotHostDailyIncomeCache = [];

    public function __construct(
        private readonly WhmcsClient $whmcsClient
    ) {
    }

    public function getMetrics(): array
    {
        return Cache::remember('dashboard.metrics.v2', self::CACHE_TTL_SECONDS, function () {
            $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
            if (! Currency::isAllowed($currency)) {
                $currency = Currency::DEFAULT;
            }

            return [
                'counts' => $this->counts(),
                'businessPulse' => $this->businessPulse(),
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

    private function businessPulse(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->subDays(29)->startOfDay();
        $monthEnd = now()->endOfDay();
        $previousStart = now()->subDays(59)->startOfDay();
        $previousEnd = now()->subDays(30)->endOfDay();

        $monthIncome = $this->incomeTotalBetween($monthStart, $monthEnd);
        $prevMonthIncome = $this->incomeTotalBetween($previousStart, $previousEnd);
        $todayIncome = $this->incomeTotalBetween($todayStart, $todayEnd);

        $monthExpense = (float) AccountingEntry::where('type', 'expense')
            ->whereBetween('entry_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $overdueCount = Invoice::where('status', 'overdue')->count();
        $unpaidCount = Invoice::where('status', 'unpaid')->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $openTickets = SupportTicket::where('status', 'open')->count();
        $customerReplyTickets = SupportTicket::where('status', 'customer_reply')->count();
        $openSupportLoad = $openTickets + $customerReplyTickets;

        $incomeGrowthPercent = null;
        if ($prevMonthIncome > 0) {
            $incomeGrowthPercent = (($monthIncome - $prevMonthIncome) / $prevMonthIncome) * 100;
        } elseif ($monthIncome > 0) {
            $incomeGrowthPercent = 100.0;
        }

        $net30d = $monthIncome - $monthExpense;
        $receivableBase = max(1, $unpaidCount + $overdueCount);
        $overdueShare = ($overdueCount / $receivableBase) * 100;

        $healthScore = 100;
        $healthScore -= min(35, $overdueCount * 3);
        $healthScore -= min(20, $pendingOrders * 2);
        $healthScore -= min(20, $openSupportLoad);
        if ($net30d < 0) {
            $healthScore -= 15;
        }
        if (($incomeGrowthPercent ?? 0) < 0) {
            $healthScore -= 10;
        }
        $healthScore = max(0, min(100, $healthScore));

        $health = [
            'label' => 'Healthy',
            'classes' => 'bg-emerald-100 text-emerald-700',
        ];
        if ($healthScore < 75) {
            $health = [
                'label' => 'Watch',
                'classes' => 'bg-amber-100 text-amber-700',
            ];
        }
        if ($healthScore < 50) {
            $health = [
                'label' => 'Critical',
                'classes' => 'bg-rose-100 text-rose-700',
            ];
        }

        return [
            'today_income' => $todayIncome,
            'income_30d' => $monthIncome,
            'previous_income_30d' => $prevMonthIncome,
            'expense_30d' => $monthExpense,
            'net_30d' => $net30d,
            'income_growth_percent' => $incomeGrowthPercent,
            'pending_orders' => $pendingOrders,
            'unpaid_invoices' => $unpaidCount,
            'overdue_invoices' => $overdueCount,
            'overdue_share_percent' => $overdueShare,
            'open_tickets' => $openTickets,
            'customer_reply_tickets' => $customerReplyTickets,
            'support_load' => $openSupportLoad,
            'health_score' => $healthScore,
            'health_label' => $health['label'],
            'health_classes' => $health['classes'],
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
            $carrotHostIncome = $this->carrotHostIncomeBetween($start, $end);
            $totalIncome = $this->incomeTotalBetween($start, $end) + $carrotHostIncome;
            $metrics[$key] = [
                'new_orders' => Order::whereBetween('created_at', [$start, $end])->count(),
                'active_orders' => Order::where('status', 'accepted')->whereBetween('created_at', [$start, $end])->count(),
                'income' => $totalIncome,
                'hosting_income' => $carrotHostIncome,
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
        $todayCarrotHostIncome = $this->carrotHostIncomeBetween($todayStart, $todayEnd);
        if ($todayCarrotHostIncome !== 0.0) {
            $hourlyIncome[0] = (float) ($hourlyIncome[0] ?? 0) + $todayCarrotHostIncome;
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
        $dailyIncomeHosting = $this->carrotHostDailyIncomeMap($monthStart, $monthEnd);

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
            $monthIncome[] = (float) ($dailyIncomeSystem[$key] ?? 0)
                + (float) ($dailyIncomeManual[$key] ?? 0)
                + (float) ($dailyIncomeHosting[$key] ?? 0);
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
        $yearlyHostingDaily = $this->carrotHostDailyIncomeMap($yearStart, $yearEnd);
        $monthlyIncomeHosting = [];
        foreach ($yearlyHostingDaily as $date => $amount) {
            $bucket = Carbon::parse($date)->format('Y-m');
            $monthlyIncomeHosting[$bucket] = (float) ($monthlyIncomeHosting[$bucket] ?? 0) + (float) $amount;
        }

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
            $yearIncome[] = (float) ($monthlyIncomeSystem[$key] ?? 0)
                + (float) ($monthlyIncomeManual[$key] ?? 0)
                + (float) ($monthlyIncomeHosting[$key] ?? 0);
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
            $carrotHostIncome = $this->carrotHostIncomeBetween($start, $end);
            $payments = (float) ($totals['payment'] ?? 0) + $manualIncome + $carrotHostIncome;
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

    private function carrotHostIncomeBetween(Carbon $start, Carbon $end): float
    {
        $dailyMap = $this->carrotHostDailyIncomeMap($start, $end);
        if ($dailyMap === []) {
            return 0.0;
        }

        return (float) array_sum($dailyMap);
    }

    private function carrotHostDailyIncomeMap(Carbon $start, Carbon $end): array
    {
        $rangeStart = $start->copy()->startOfDay();
        $rangeEnd = $end->copy()->endOfDay();
        $defaultStart = Carbon::parse(self::WHMCS_DEFAULT_START)->startOfDay();

        if ($rangeEnd->lt($defaultStart)) {
            return [];
        }

        if ($rangeStart->lt($defaultStart)) {
            $rangeStart = $defaultStart->copy();
        }

        $cacheKey = $rangeStart->toDateString() . ':' . $rangeEnd->toDateString();
        if (array_key_exists($cacheKey, $this->carrotHostDailyIncomeCache)) {
            return $this->carrotHostDailyIncomeCache[$cacheKey];
        }

        if (! $this->whmcsClient->isConfigured()) {
            $this->carrotHostDailyIncomeCache[$cacheKey] = [];
            return [];
        }

        $storageKey = 'dashboard:carrothost:daily:' . $cacheKey;
        $dailyIncome = Cache::remember($storageKey, now()->addMinutes(10), function () use ($rangeStart, $rangeEnd) {
            $errors = [];
            $transactions = $this->fetchAllWhmcs(
                'GetTransactions',
                [
                    'startdate' => $rangeStart->toDateString(),
                    'enddate' => $rangeEnd->toDateString(),
                    'orderby' => 'date',
                    'order' => 'desc',
                ],
                'transactions',
                'transaction',
                $errors
            );

            $transactions = $this->filterWhmcsByDate(
                $transactions,
                'date',
                $rangeStart->toDateString(),
                $rangeEnd->toDateString()
            );

            $totals = [];
            foreach ($transactions as $transaction) {
                $dateValue = $transaction['date'] ?? null;
                if (! $dateValue) {
                    continue;
                }

                try {
                    $bucket = Carbon::parse((string) $dateValue)->toDateString();
                } catch (\Throwable) {
                    continue;
                }

                $totals[$bucket] = (float) ($totals[$bucket] ?? 0) + $this->normalizeMoney($transaction['amountin'] ?? 0);
            }

            return $totals;
        });

        $this->carrotHostDailyIncomeCache[$cacheKey] = $dailyIncome;

        return $dailyIncome;
    }

    private function fetchAllWhmcs(
        string $action,
        array $params,
        string $rootKey,
        ?string $itemKey,
        array &$errors
    ): array {
        $items = [];
        $offset = 0;

        for ($page = 0; $page < self::WHMCS_MAX_PAGES; $page++) {
            $result = $this->whmcsClient->call($action, array_merge($params, [
                'limitstart' => $offset,
                'limitnum' => self::WHMCS_PAGE_SIZE,
            ]));

            if (! $result['ok']) {
                $errors[] = $action . ': ' . $result['error'];
                break;
            }

            $data = $result['data'] ?? [];
            $container = $data[$rootKey] ?? [];
            $batch = $this->normalizeWhmcsList($container, $itemKey);

            if (empty($batch)) {
                break;
            }

            $items = array_merge($items, $batch);

            $total = (int) ($data['totalresults'] ?? 0);
            $offset += self::WHMCS_PAGE_SIZE;

            if ($total > 0 && count($items) >= $total) {
                break;
            }

            if (count($batch) < self::WHMCS_PAGE_SIZE) {
                break;
            }
        }

        return $items;
    }

    private function normalizeWhmcsList($container, ?string $itemKey): array
    {
        if (! is_array($container)) {
            return [];
        }

        $items = $container;
        if ($itemKey && array_key_exists($itemKey, $container)) {
            $items = $container[$itemKey];
        }

        if ($items === null || $items === '') {
            return [];
        }

        if (is_array($items) && array_is_list($items)) {
            return $items;
        }

        return is_array($items) ? [$items] : [];
    }

    private function filterWhmcsByDate(array $items, string $dateKey, string $start, string $end): array
    {
        return array_values(array_filter($items, function ($item) use ($dateKey, $start, $end) {
            $value = $item[$dateKey] ?? null;
            if (! $value) {
                return true;
            }

            try {
                $date = Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return true;
            }

            return $date >= $start && $date <= $end;
        }));
    }

    private function normalizeMoney($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $clean = preg_replace('/[^\d\.\-]/', '', (string) $value);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return 0.0;
        }

        return (float) $clean;
    }

    private function billingAmounts(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfDay();
        $yearStart = now()->startOfYear();
        $yearEnd = now()->endOfDay();
        $allTimeStart = Carbon::parse(self::WHMCS_DEFAULT_START)->startOfDay();
        $allTimeEnd = now()->endOfDay();

        $todayBase = (float) AccountingEntry::where('type', 'payment')
            ->whereDate('entry_date', $todayStart->toDateString())
            ->sum('amount');
        $monthBase = (float) AccountingEntry::where('type', 'payment')
            ->whereBetween('entry_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');
        $yearBase = (float) AccountingEntry::where('type', 'payment')
            ->whereBetween('entry_date', [$yearStart->toDateString(), $yearEnd->toDateString()])
            ->sum('amount');
        $allTimeBase = (float) AccountingEntry::where('type', 'payment')->sum('amount');

        $todayHosting = $this->carrotHostIncomeBetween($todayStart, $todayEnd);
        $monthHosting = $this->carrotHostIncomeBetween($monthStart, $monthEnd);
        $yearHosting = $this->carrotHostIncomeBetween($yearStart, $yearEnd);
        $allTimeHosting = $this->carrotHostIncomeBetween($allTimeStart, $allTimeEnd);

        return [
            'today' => $todayBase + $todayHosting,
            'month' => $monthBase + $monthHosting,
            'year' => $yearBase + $yearHosting,
            'all_time' => $allTimeBase + $allTimeHosting,
        ];
    }

    private function clientActivity(): array
    {
        $activeCustomerCount = Customer::where('status', 'active')->count();
        $clientRoleValues = [Role::CLIENT, Role::CLIENT_PROJECT];
        $clientUserIds = User::query()
            ->whereIn('role', $clientRoleValues)
            ->pluck('id');

        if (! Schema::hasTable('sessions') && ! Schema::hasTable('user_sessions')) {
            return [
                'activeCount' => $activeCustomerCount,
                'onlineCount' => 0,
                'recentClients' => collect(),
            ];
        }

        $onlineUsersCount = 0;
        if (Schema::hasTable('sessions')) {
            $onlineThreshold = Carbon::now()->subHour()->timestamp;
            $onlineUsersCount = (int) DB::table('sessions')
                ->whereIn('user_id', $clientUserIds)
                ->where('last_activity', '>=', $onlineThreshold)
                ->distinct('user_id')
                ->count('user_id');
        }

        $recentClients = collect();
        if (Schema::hasTable('user_sessions')) {
            $latestSessionPerUser = UserSession::query()
                ->selectRaw('user_id, MAX(id) as latest_id')
                ->where('user_type', User::class)
                ->whereIn('user_id', $clientUserIds)
                ->where('guard', 'web')
                ->whereNotNull('login_at')
                ->groupBy('user_id');

            $latestLoginRows = UserSession::query()
                ->from('user_sessions as us')
                ->joinSub($latestSessionPerUser, 'latest', function ($join) {
                    $join->on('us.id', '=', 'latest.latest_id');
                })
                ->orderByDesc('us.login_at')
                ->limit(300)
                ->get(['us.user_id', 'us.ip_address', 'us.login_at']);

            $users = User::query()
                ->with('customer:id,name')
                ->whereIn('id', $latestLoginRows->pluck('user_id')->unique()->values())
                ->get()
                ->keyBy('id');

            $recentClients = $latestLoginRows
                ->map(function (UserSession $sessionRow) use ($users) {
                    $user = $users->get((int) $sessionRow->user_id);
                    $customerId = $user?->customer?->id;
                    $customerName = $user?->customer?->name;
                    $displayName = $customerName ?: ($user->name ?? 'Unknown User');
                    $loginTimestamp = $sessionRow->login_at?->timestamp ?? 0;

                    return [
                        'user_id' => $user?->id,
                        'name' => $displayName,
                        'customer_id' => $customerId,
                        'last_login' => $sessionRow->login_at?->diffForHumans() ?? 'Unknown',
                        'ip' => $sessionRow->ip_address,
                        '_login_ts' => $loginTimestamp,
                        '_client_key' => $customerId ? ('customer:'.$customerId) : ('user:'.($user?->id ?? '0')),
                    ];
                })
                ->sortByDesc('_login_ts')
                ->unique('_client_key')
                ->take(30)
                ->values()
                ->map(function (array $row) {
                    unset($row['_login_ts'], $row['_client_key']);
                    return $row;
                });
        } elseif (Schema::hasTable('sessions')) {
            $latestSessions = DB::table('sessions as s')
                ->select('s.user_id', 's.ip_address', 's.last_activity')
                ->join(DB::raw('(select user_id, max(last_activity) as last_activity from sessions where user_id is not null group by user_id) as latest'), function ($join) {
                    $join->on('s.user_id', '=', 'latest.user_id')
                        ->on('s.last_activity', '=', 'latest.last_activity');
                })
                ->whereIn('s.user_id', $clientUserIds)
                ->orderByDesc('s.last_activity')
                ->limit(300)
                ->get();

            $users = User::query()
                ->with('customer:id,name')
                ->whereIn('id', $latestSessions->pluck('user_id')->filter()->unique()->values())
                ->get()
                ->keyBy('id');

            $recentClients = $latestSessions
                ->map(function ($sessionRow) use ($users) {
                    $user = $users->get((int) $sessionRow->user_id);
                    $customerId = $user?->customer?->id;
                    $customerName = $user?->customer?->name;
                    $displayName = $customerName ?: ($user->name ?? 'Unknown User');
                    $loginTimestamp = (int) ($sessionRow->last_activity ?? 0);

                    return [
                        'user_id' => $user?->id,
                        'name' => $displayName,
                        'customer_id' => $customerId,
                        'last_login' => $sessionRow->last_activity
                            ? Carbon::createFromTimestamp($sessionRow->last_activity)->diffForHumans()
                            : 'Unknown',
                        'ip' => $sessionRow->ip_address,
                        '_login_ts' => $loginTimestamp,
                        '_client_key' => $customerId ? ('customer:'.$customerId) : ('user:'.($user?->id ?? '0')),
                    ];
                })
                ->sortByDesc('_login_ts')
                ->unique('_client_key')
                ->take(30)
                ->values()
                ->map(function (array $row) {
                    unset($row['_login_ts'], $row['_client_key']);
                    return $row;
                });
        }

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
