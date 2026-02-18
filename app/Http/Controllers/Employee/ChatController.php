<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->attributes->get('employee');
        if (! ($employee instanceof Employee)) {
            $employee = $request->user()?->employee;
        }

        if (! $employee) {
            abort(403);
        }

        $projects = $employee->projects()
            ->select(['projects.id', 'projects.name', 'projects.status'])
            ->selectSub(function ($query) use ($employee) {
                $query->from('project_messages as pm')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('pm.project_id', 'projects.id')
                    ->whereRaw(
                        'pm.id > COALESCE((SELECT MAX(pmr.last_read_message_id) FROM project_message_reads as pmr WHERE pmr.project_id = projects.id AND pmr.reader_type = ? AND pmr.reader_id = ?), 0)',
                        ['employee', $employee->id]
                    );
            }, 'unread_count')
            ->orderByDesc('unread_count')
            ->orderByRaw("CASE WHEN projects.status = 'ongoing' THEN 0 ELSE 1 END")
            ->orderByDesc('projects.created_at')
            ->paginate(25)
            ->withQueryString();

        $pageProjectIds = $projects->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();
        $unreadCounts = collect();

        if ($pageProjectIds->isNotEmpty()) {
            $unreadCounts = DB::table('project_messages as pm')
                ->select('pm.project_id', DB::raw('COUNT(*) as unread'))
                ->whereIn('pm.project_id', $pageProjectIds->all())
                ->whereRaw(
                    'pm.id > COALESCE((SELECT MAX(pmr.last_read_message_id) FROM project_message_reads as pmr WHERE pmr.project_id = pm.project_id AND pmr.reader_type = ? AND pmr.reader_id = ?), 0)',
                    ['employee', $employee->id]
                )
                ->groupBy('pm.project_id')
                ->pluck('unread', 'pm.project_id')
                ->map(fn ($count) => (int) $count);
        }

        foreach ($pageProjectIds as $projectId) {
            if (! $unreadCounts->has($projectId)) {
                $unreadCounts->put($projectId, 0);
            }
        }

        return view('employee.chats.index', [
            'projects' => $projects,
            'unreadCounts' => $unreadCounts,
        ]);
    }
}
