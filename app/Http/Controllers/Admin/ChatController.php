<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\HybridUiResponder;
use App\Support\UiFeature;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class ChatController extends Controller
{
    public function index(
        Request $request,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
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

        $payload = [
            'projects' => $projects,
            'pageUnreadTotal' => $pageUnreadTotal,
        ];

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_CHATS_INDEX,
            'admin.chats.index',
            $payload,
            'Admin/Chats/Index',
            $this->indexInertiaProps($projects, $pageUnreadTotal)
        );
    }

    private function indexInertiaProps(
        LengthAwarePaginator $projects,
        int $pageUnreadTotal
    ): array {
        return [
            'pageTitle' => 'Chat',
            'pageUnreadTotal' => $pageUnreadTotal,
            'routes' => [
                'projects_index' => route('admin.projects.index'),
            ],
            'projects' => [
                'data' => collect($projects->items())->map(function (Project $project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'status' => $project->status,
                        'unread_count' => (int) ($project->unread_count ?? 0),
                        'routes' => [
                            'chat' => route('admin.projects.chat', $project),
                        ],
                    ];
                })->values()->all(),
                'links' => $projects->toArray()['links'] ?? [],
            ],
        ];
    }
}
