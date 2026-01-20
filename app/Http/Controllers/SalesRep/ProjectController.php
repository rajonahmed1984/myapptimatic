<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\ProjectMessageRead;
use App\Support\TaskSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request)
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

        return view('rep.projects.index', [
            'projects' => $projects,
            'commissionMap' => $commissionMap,
        ]);
    }

    public function show(Request $request, Project $project)
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

        return view('rep.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'maintenances' => $maintenances,
            'initialInvoice' => $initialInvoice,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
            'salesRepAmount' => $repAmount !== null ? (float) $repAmount : null,
            'chatMessages' => $chatMessages,
            'chatMeta' => $chatMeta,
            'taskStats' => [
                'total' => (int) $statusCounts->values()->sum(),
                'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                'completed' => (int) (($statusCounts['completed'] ?? 0) + ($statusCounts['done'] ?? 0)),
                'unread' => (int) $unreadCount,
            ],
        ]);
    }
}
