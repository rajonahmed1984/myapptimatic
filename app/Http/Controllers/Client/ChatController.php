<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ChatController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        $projectsQuery = Project::query()
            ->select(['id', 'name', 'status', 'customer_id'])
            ->latest();

        if ($user?->isClientProject()) {
            $projectsQuery->whereKey($user->project_id ?? 0);
        } else {
            $projectsQuery->where('customer_id', $user?->customer_id ?? 0);
        }

        $projects = $projectsQuery->paginate(25)->withQueryString();

        $projectIds = $projects->getCollection()->pluck('id');
        $unreadCounts = collect();
        if ($projectIds->isNotEmpty() && $user) {
            $unreadCounts = DB::table('project_messages as pm')
                ->select('pm.project_id', DB::raw('COUNT(*) as unread'))
                ->leftJoin('project_message_reads as pmr', function ($join) use ($user) {
                    $join->on('pmr.project_id', '=', 'pm.project_id')
                        ->where('pmr.reader_type', 'user')
                        ->where('pmr.reader_id', $user->id);
                })
                ->whereIn('pm.project_id', $projectIds->all())
                ->whereRaw('pm.id > COALESCE(pmr.last_read_message_id, 0)')
                ->groupBy('pm.project_id')
                ->pluck('unread', 'pm.project_id')
                ->map(fn ($count) => (int) $count);

            foreach ($projectIds as $projectId) {
                $projectId = (int) $projectId;
                if (! $unreadCounts->has($projectId)) {
                    $unreadCounts->put($projectId, 0);
                }
            }
        }

        return Inertia::render('Client/Chats/Index', [
            'projects' => $projects->getCollection()->map(function (Project $project) use ($unreadCounts) {
                $projectId = (int) $project->id;

                return [
                    'id' => $projectId,
                    'name' => $project->name,
                    'status_label' => $project->status ? ucfirst(str_replace('_', ' ', (string) $project->status)) : '--',
                    'unread_count' => (int) $unreadCounts->get($projectId, 0),
                    'routes' => [
                        'chat' => route('client.projects.chat', $project),
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
            'routes' => [
                'projects' => route('client.projects.index'),
            ],
        ]);
    }
}
