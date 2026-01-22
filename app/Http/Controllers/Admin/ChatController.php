<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $projects = Project::query()
            ->select(['id', 'name', 'status'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

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
                ->pluck('unread', 'project_id');
        }

        return view('admin.chats.index', [
            'projects' => $projects,
            'unreadCounts' => $unreadCounts,
        ]);
    }
}
