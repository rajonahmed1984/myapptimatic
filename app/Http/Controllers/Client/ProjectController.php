<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\TaskSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::query()
            ->with(['customer', 'maintenances'])
            ->where('customer_id', $request->user()->customer_id)
            ->latest()
            ->paginate(20);

        return view('client.projects.index', compact('projects'));
    }

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $project->load('customer');

        $tasks = $project->tasks()
            ->where('customer_visible', true)
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

            $currentAuthorType = 'user';
            $currentAuthorId = $request->user()?->id;

            $chatMeta = [
                'messagesUrl' => route('client.projects.tasks.chat.messages', [$project, $chatTask]),
                'postMessagesUrl' => route('client.projects.tasks.chat.messages.store', [$project, $chatTask]),
                'postRoute' => route('client.projects.tasks.chat.store', [$project, $chatTask]),
                'readUrl' => route('client.projects.tasks.chat.read', [$project, $chatTask]),
                'attachmentRouteName' => 'client.projects.tasks.messages.attachment',
                'currentAuthorType' => $currentAuthorType,
                'currentAuthorId' => $currentAuthorId,
                'canPost' => Gate::forUser($request->user())->check('comment', $chatTask),
            ];
        }

        $maintenances = $project->maintenances()
            ->with(['invoices' => fn ($query) => $query->latest('issue_date')])
            ->orderBy('next_billing_date')
            ->get();

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        return view('client.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'maintenances' => $maintenances,
            'initialInvoice' => $initialInvoice,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
            'chatTask' => $chatTask,
            'chatMessages' => $chatMessages,
            'chatMeta' => $chatMeta,
        ]);
    }
}
