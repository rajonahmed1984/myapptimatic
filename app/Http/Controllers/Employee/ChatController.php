<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->orderByDesc('projects.created_at')
            ->paginate(25)
            ->withQueryString();

        $projectIds = $projects->getCollection()->pluck('id');
        $unreadCounts = collect();
        if ($projectIds->isNotEmpty()) {
            $unreadCounts = DB::table('project_messages as pm')
                ->select('pm.project_id', DB::raw('COUNT(*) as unread'))
                ->leftJoin('project_message_reads as pmr', function ($join) use ($employee) {
                    $join->on('pmr.project_id', '=', 'pm.project_id')
                        ->where('pmr.reader_type', 'employee')
                        ->where('pmr.reader_id', $employee->id);
                })
                ->whereIn('pm.project_id', $projectIds->all())
                ->whereRaw('pm.id > COALESCE(pmr.last_read_message_id, 0)')
                ->groupBy('pm.project_id')
                ->pluck('unread', 'project_id');
        }

        return view('employee.chats.index', [
            'projects' => $projects,
            'unreadCounts' => $unreadCounts,
        ]);
    }
}
