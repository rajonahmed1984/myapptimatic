<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SalesRepresentative;
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
            ->orderBy('id')
            ->get();

        $chatTaskId = (int) $request->query('chat_task');
        $chatTask = $tasks->first();
        if ($chatTaskId > 0) {
            $chatTask = $tasks->firstWhere('id', $chatTaskId) ?? $chatTask;
        }

        $chatMessages = collect();
        $chatMeta = null;
        if ($chatTask) {
            $chatMessages = $chatTask->messages()
                ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor'])
                ->latest('id')
                ->limit(30)
                ->get()
                ->reverse()
                ->values();

            $salesRep = $request->attributes->get('salesRep');
            $currentAuthorType = $salesRep ? 'sales_rep' : 'user';
            $currentAuthorId = $salesRep?->id ?? $request->user()?->id;

            $chatMeta = [
                'messagesUrl' => route('rep.projects.tasks.chat.messages', [$project, $chatTask]),
                'postMessagesUrl' => route('rep.projects.tasks.chat.messages.store', [$project, $chatTask]),
                'postRoute' => route('rep.projects.tasks.chat.store', [$project, $chatTask]),
                'readUrl' => route('rep.projects.tasks.chat.read', [$project, $chatTask]),
                'attachmentRouteName' => 'rep.projects.tasks.messages.attachment',
                'currentAuthorType' => $currentAuthorType,
                'currentAuthorId' => $currentAuthorId,
                'canPost' => Gate::forUser($request->user())->check('comment', $chatTask),
            ];
        }

        $maintenances = $project->maintenances()
            ->where('sales_rep_visible', true)
            ->with(['invoices' => fn ($query) => $query->latest('issue_date')])
            ->orderBy('next_billing_date')
            ->get();

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        return view('rep.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'maintenances' => $maintenances,
            'initialInvoice' => $initialInvoice,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
            'salesRepAmount' => $repAmount !== null ? (float) $repAmount : null,
            'chatTask' => $chatTask,
            'chatMessages' => $chatMessages,
            'chatMeta' => $chatMeta,
        ]);
    }
}
