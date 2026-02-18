<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
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

        $unreadCounts = $projects->getCollection()
            ->mapWithKeys(fn ($project) => [$project->id => (int) ($project->unread_count ?? 0)]);

        return view('employee.chats.index', [
            'projects' => $projects,
            'unreadCounts' => $unreadCounts,
        ]);
    }
}
