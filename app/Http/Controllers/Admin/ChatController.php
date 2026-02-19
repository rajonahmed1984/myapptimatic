<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $projectsQuery = Project::query()
            ->select(['projects.id', 'projects.name', 'projects.status']);

        if ($user) {
            $projectsQuery->selectSub(function ($query) use ($user) {
                $query->from('project_messages as pm')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('pm.project_id', 'projects.id')
                    ->whereRaw(
                        'pm.id > COALESCE((SELECT MAX(pmr.last_read_message_id) FROM project_message_reads as pmr WHERE pmr.project_id = projects.id AND pmr.reader_type = ? AND pmr.reader_id = ?), 0)',
                        ['user', $user->id]
                    );
            }, 'unread_count');
        } else {
            $projectsQuery->selectRaw('0 as unread_count');
        }

        $projects = $projectsQuery
            ->orderByDesc('unread_count')
            ->orderByRaw("CASE WHEN projects.status = 'ongoing' THEN 0 ELSE 1 END")
            ->orderByDesc('projects.created_at')
            ->paginate(25)
            ->withQueryString();
        $pageUnreadTotal = (int) $projects->getCollection()->sum(
            fn ($project) => (int) ($project->unread_count ?? 0)
        );

        return view('admin.chats.index', [
            'projects' => $projects,
            'pageUnreadTotal' => $pageUnreadTotal,
        ]);
    }
}
