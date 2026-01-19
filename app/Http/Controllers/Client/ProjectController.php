<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMessageRead;
use App\Support\TaskSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Redirect project-specific users directly to their assigned project
        if ($user->isClientProject() && $user->project_id) {
            return redirect()->route('client.projects.show', $user->project_id);
        }

        $query = Project::query()
            ->with(['customer', 'maintenances'])
            ->where('customer_id', $user->customer_id);

        $projects = $query->latest()->paginate(20);

        return view('client.projects.index', compact('projects'));
    }

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $project->load(['customer', 'overheads']);

        $tasks = $project->tasks()
            ->where('customer_visible', true)
            ->orderBy('id')
            ->get();

        $chatMessages = $project->messages()
            ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $currentAuthorType = 'user';
        $currentAuthorId = $request->user()?->id;

        $lastReadId = ProjectMessageRead::query()
            ->where('project_id', $project->id)
            ->where('reader_type', $currentAuthorType)
            ->where('reader_id', $currentAuthorId)
            ->value('last_read_message_id');

        $unreadCount = $project->messages()
            ->when($lastReadId, fn ($query) => $query->where('id', '>', $lastReadId))
            ->count();

        $chatMeta = [
            'messagesUrl' => route('client.projects.chat.messages', $project),
            'postMessagesUrl' => route('client.projects.chat.messages.store', $project),
            'postRoute' => route('client.projects.chat.store', $project),
            'readUrl' => route('client.projects.chat.read', $project),
            'attachmentRouteName' => 'client.projects.chat.messages.attachment',
            'currentAuthorType' => $currentAuthorType,
            'currentAuthorId' => $currentAuthorId,
            'canPost' => Gate::forUser($request->user())->check('view', $project),
            'unreadCount' => (int) $unreadCount,
        ];

        $maintenances = $project->maintenances()
            ->with(['invoices' => fn ($query) => $query->latest('issue_date')])
            ->orderBy('next_billing_date')
            ->get();

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        $isProjectSpecificUser = $request->user()->isClientProject();

        return view('client.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'maintenances' => $maintenances,
            'initialInvoice' => $initialInvoice,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
            'chatMessages' => $chatMessages,
            'chatMeta' => $chatMeta,
            'isProjectSpecificUser' => $isProjectSpecificUser,
        ]);
    }
}
