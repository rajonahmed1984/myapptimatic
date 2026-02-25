<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMessageRead;
use App\Models\SalesRepresentative;
use App\Support\TaskSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $repId = SalesRepresentative::where('user_id', $request->user()->id)->value('id');

        $projects = Project::query()
            ->with([
                'customer',
                'salesRepresentatives' => fn ($q) => $q->whereKey($repId),
            ])
            ->whereHas('salesRepresentatives', fn ($q) => $q->whereKey($repId))
            ->latest()
            ->paginate(20);

        $commissionMap = $projects->getCollection()
            ->mapWithKeys(function (Project $project) {
                $rep = $project->salesRepresentatives->first();
                $amount = $rep?->pivot?->amount;

                return [$project->id => $amount !== null ? (float) $amount : null];
            })
            ->all();

        return Inertia::render('Rep/Projects/Index', [
            'projects' => $projects->getCollection()->map(function (Project $project) use ($commissionMap) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'customer_name' => $project->customer?->name ?? '--',
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                    'commission_amount' => $commissionMap[$project->id] ?? null,
                    'currency' => $project->currency,
                    'routes' => [
                        'show' => route('rep.projects.show', $project),
                    ],
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
                'prev_page_url' => $projects->previousPageUrl(),
                'next_page_url' => $projects->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, Project $project): InertiaResponse
    {
        $repId = SalesRepresentative::where('user_id', $request->user()->id)->value('id');
        $this->authorize('view', $project);

        $project->load([
            'customer',
            'salesRepresentatives' => fn ($q) => $q->whereKey($repId),
        ]);
        $repAmount = $project->salesRepresentatives->first()?->pivot?->amount;

        $tasks = $project->tasks()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $chatMessages = $project->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $salesRep = $request->attributes->get('salesRep');
        $currentAuthorType = $salesRep ? 'sales_rep' : 'user';
        $currentAuthorId = $salesRep?->id ?? $request->user()?->id;

        $lastReadId = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->where('reader_type', $currentAuthorType)
            ->where('reader_id', $currentAuthorId)
            ->value('last_read_message_id');

        $unreadCount = $project->messages()
            ->when($lastReadId, fn ($query) => $query->where('id', '>', $lastReadId))
            ->count();

        $chatMeta = [
            'messagesUrl' => route('rep.projects.chat.messages', $project),
            'postMessagesUrl' => route('rep.projects.chat.messages.store', $project),
            'postRoute' => route('rep.projects.chat.store', $project),
            'readUrl' => route('rep.projects.chat.read', $project),
            'attachmentRouteName' => 'rep.projects.chat.messages.attachment',
            'currentAuthorType' => $currentAuthorType,
            'currentAuthorId' => $currentAuthorId,
            'canPost' => Gate::forUser($request->user())->check('view', $project),
        ];

        $maintenances = $project->maintenances()
            ->where('sales_rep_visible', true)
            ->with(['invoices' => fn ($query) => $query->latest('issue_date')])
            ->orderBy('next_billing_date')
            ->get();

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        $statusCounts = $project->tasks()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $dateFormat = config('app.date_format', 'd-m-Y');

        return Inertia::render('Rep/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status_label' => ucfirst(str_replace('_', ' ', (string) $project->status)),
                'customer_name' => $project->customer?->name ?? '--',
                'start_date_display' => $project->start_date?->format($dateFormat) ?? '--',
                'expected_end_date_display' => $project->expected_end_date?->format($dateFormat) ?? '--',
                'due_date_display' => $project->due_date?->format($dateFormat) ?? '--',
                'budget' => $project->total_budget,
                'initial_payment_amount' => $project->initial_payment_amount,
                'currency' => $project->currency,
            ],
            'tasks' => $tasks->map(function ($task) use ($project, $dateFormat) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'task_type' => $task->task_type,
                    'customer_visible' => (bool) $task->customer_visible,
                    'progress' => (int) ($task->progress ?? 0),
                    'start_date_display' => $task->start_date?->format($dateFormat) ?? '--',
                    'due_date_display' => $task->due_date?->format($dateFormat) ?? '--',
                    'completed_at_display' => $task->completed_at?->format($dateFormat),
                    'routes' => [
                        'show' => route('rep.projects.tasks.show', [$project, $task]),
                    ],
                ];
            })->values()->all(),
            'maintenances' => $maintenances->map(function ($maintenance) use ($dateFormat) {
                return [
                    'id' => $maintenance->id,
                    'title' => $maintenance->title,
                    'billing_cycle_label' => ucfirst((string) $maintenance->billing_cycle),
                    'next_billing_date_display' => $maintenance->next_billing_date?->format($dateFormat) ?? '--',
                    'status' => $maintenance->status,
                    'status_label' => ucfirst((string) $maintenance->status),
                    'amount' => (float) $maintenance->amount,
                    'currency' => $maintenance->currency,
                    'invoice_count' => (int) ($maintenance->invoices?->count() ?? 0),
                ];
            })->values()->all(),
            'initial_invoice' => $initialInvoice ? [
                'label' => '#'.($initialInvoice->number ?? $initialInvoice->id),
                'status_label' => ucfirst((string) $initialInvoice->status),
            ] : null,
            'task_type_options' => TaskSettings::taskTypeOptions(),
            'priority_options' => TaskSettings::priorityOptions(),
            'sales_rep_amount' => $repAmount !== null ? (float) $repAmount : null,
            'chat_messages' => $chatMessages->map(function ($message) use ($dateFormat) {
                return [
                    'id' => $message->id,
                    'author_name' => $message->authorName(),
                    'message' => $message->message,
                    'created_at_display' => $message->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                ];
            })->values()->all(),
            'chat_meta' => $chatMeta,
            'task_stats' => [
                'total' => (int) $statusCounts->values()->sum(),
                'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                'completed' => (int) (($statusCounts['completed'] ?? 0) + ($statusCounts['done'] ?? 0)),
                'unread' => (int) $unreadCount,
            ],
            'routes' => [
                'task_store' => route('rep.projects.tasks.store', $project),
            ],
        ]);
    }
}
