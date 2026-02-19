<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LicenseDomain;
use App\Models\Project;
use App\Models\ProjectMessageRead;
use App\Models\ProjectMaintenance;
use App\Models\Setting;
use App\Services\TaskQueryService;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, TaskQueryService $taskQueryService)
    {
        $user = $request->user();
        
        // Show project-specific dashboard for project users
        if ($user->isClientProject() && $user->project_id) {
            return $this->projectSpecificDashboard($request, $user, $taskQueryService);
        }

        $customer = $user->customer;
        $subscriptions = $customer?->subscriptions()->with('plan.product')->get() ?? collect();
        $invoices = $customer?->invoices()->latest('issue_date')->limit(5)->get() ?? collect();
        $licenses = $customer?->licenses()
            ->with(['product', 'subscription.plan', 'domains'])
            ->get() ?? collect();
        $openInvoices = $customer
            ? $customer->invoices()
                ->whereIn('status', ['unpaid', 'overdue'])
                ->orderBy('due_date')
                ->get()
            : collect();
        $overdueInvoices = $customer
            ? $customer->invoices()
                ->where('status', 'overdue')
                ->orderBy('due_date')
                ->get()
            : collect();
        $openInvoiceCount = $openInvoices->count();
        $overdueInvoiceCount = $overdueInvoices->count();
        $openInvoiceBalance = $openInvoices->sum('total');
        $overdueInvoiceBalance = $overdueInvoices->sum('total');
        $nextOverdueInvoice = $overdueInvoices->first();
        $nextOpenInvoice = $openInvoices->first();
        $ticketOpenCount = $customer
            ? $customer->supportTickets()
                ->whereIn('status', ['open', 'customer_reply'])
                ->count()
            : 0;
        $recentTickets = $customer
            ? $customer->supportTickets()
                ->latest('updated_at')
                ->limit(4)
                ->get()
            : collect();
        $domainCount = $customer
            ? LicenseDomain::query()
                ->where('status', 'active')
                ->whereHas('license.subscription', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->id);
                })
                ->count()
            : 0;
        $expiringLicenses = $customer
            ? $customer->licenses()
                ->with('product')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [Carbon::today(), Carbon::today()->addDays(45)])
                ->orderBy('expires_at')
                ->limit(4)
                ->get()
            : collect();
        $projects = collect();
        $projectCount = 0;
        if ($customer) {
            $projectQuery = Project::where('customer_id', $customer->id)
                ->withCount([
                    'tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['todo', 'in_progress', 'blocked']),
                    'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done'),
                ]);

            if ($request->user()->isClientProject() && $request->user()->project_id) {
                $projectQuery->whereKey($request->user()->project_id);
            }

            $projectCount = (clone $projectQuery)->count();

            $projects = $projectQuery
                ->latest()
                ->limit(5)
                ->get();
        }
        $maintenanceRenewal = $customer
            ? ProjectMaintenance::query()
                ->with('project')
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->orderByRaw('COALESCE(next_billing_date, start_date)')
                ->first()
            : null;

        $showTasksWidget = $taskQueryService->canViewTasks($user);
        $tasksWidget = $showTasksWidget ? $taskQueryService->dashboardTasksForUser($user) : null;

        return view('client.dashboard', [
            'customer' => $customer,
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'licenses' => $licenses,
            'serviceCount' => $subscriptions->count(),
            'projectCount' => $projectCount,
            'domainCount' => $domainCount,
            'ticketOpenCount' => $ticketOpenCount,
            'openInvoiceCount' => $openInvoiceCount,
            'openInvoiceBalance' => $openInvoiceBalance,
            'overdueInvoiceCount' => $overdueInvoiceCount,
            'overdueInvoiceBalance' => $overdueInvoiceBalance,
            'nextOverdueInvoice' => $nextOverdueInvoice,
            'nextOpenInvoice' => $nextOpenInvoice,
            'recentTickets' => $recentTickets,
            'expiringLicenses' => $expiringLicenses,
            'currency' => strtoupper((string) Setting::getValue('currency', Currency::DEFAULT)),
            'projects' => $projects,
            'maintenanceRenewal' => $maintenanceRenewal,
            'showTasksWidget' => $showTasksWidget,
            'taskSummary' => $tasksWidget['summary'] ?? null,
            'openTasks' => $tasksWidget['openTasks'] ?? collect(),
            'inProgressTasks' => $tasksWidget['inProgressTasks'] ?? collect(),
        ]);
    }

    private function projectSpecificDashboard(Request $request, $user, TaskQueryService $taskQueryService)
    {
        if (! $user->project_id) {
            abort(404, 'Project assignment not found.');
        }

        $project = Project::with([
            'customer',
            'tasks' => function ($query) {
                $query->latest()->limit(10);
            },
            'tasks.assignments',
            'messages' => function ($query) {
                $query->latest('id')->limit(8);
            },
        ])->findOrFail($user->project_id);

        $this->authorize('view', $project);

        // Task statistics
        $totalTasks = $project->tasks()->count();
        $todoTasks = $project->tasks()->where('status', 'todo')->count();
        $inProgressTaskCount = $project->tasks()->where('status', 'in_progress')->count();
        $completedTasks = $project->tasks()->where('status', 'completed')->count();
        $blockedTasks = $project->tasks()->where('status', 'blocked')->count();

        // Recent activity (last 10 tasks)
        $recentTasks = $project->tasks()
            ->with('assignments')
            ->latest('updated_at')
            ->limit(10)
            ->get();

        // Unread chat count for project-specific client user
        $lastReadId = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->where('reader_type', 'user')
            ->where('reader_id', $user->id)
            ->value('last_read_message_id');

        $unreadMessagesCount = $project->messages()
            ->when($lastReadId, fn ($query) => $query->where('id', '>', $lastReadId))
            ->count();

        // Recent chat messages
        $recentMessages = $project->messages()
            ->latest('id')
            ->limit(8)
            ->get();

        $showTasksWidget = $taskQueryService->canViewTasks($user);
        $tasksWidget = $showTasksWidget ? $taskQueryService->dashboardTasksForUser($user) : null;

        return view('client.project-dashboard-minimal', [
            'project' => $project,
            'user' => $user,
            'totalTasks' => $totalTasks,
            'todoTasks' => $todoTasks,
            'inProgressTaskCount' => $inProgressTaskCount,
            'completedTasks' => $completedTasks,
            'blockedTasks' => $blockedTasks,
            'recentTasks' => $recentTasks,
            'unreadMessagesCount' => $unreadMessagesCount,
            'recentMessages' => $recentMessages,
            'showTasksWidget' => $showTasksWidget,
            'taskSummary' => $tasksWidget['summary'] ?? null,
            'openTasks' => $tasksWidget['openTasks'] ?? collect(),
            'inProgressTasks' => $tasksWidget['inProgressTasks'] ?? collect(),
        ]);
    }
}
