<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\SalesRepresentative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $salesRep = $request->attributes->get('salesRep');
        if (! ($salesRep instanceof SalesRepresentative)) {
            $salesRep = SalesRepresentative::where('user_id', $request->user()?->id)->first();
        }

        if (! $salesRep) {
            abort(403);
        }

        $projects = $salesRep->projects()
            ->select(['projects.id', 'projects.name', 'projects.status'])
            ->orderByDesc('projects.created_at')
            ->paginate(25)
            ->withQueryString();

        $projectIds = $projects->getCollection()->pluck('id');
        $unreadCounts = collect();
        if ($projectIds->isNotEmpty()) {
            $unreadCounts = DB::table('project_messages as pm')
                ->select('pm.project_id', DB::raw('COUNT(*) as unread'))
                ->leftJoin('project_message_reads as pmr', function ($join) use ($salesRep) {
                    $join->on('pmr.project_id', '=', 'pm.project_id')
                        ->where('pmr.reader_type', 'sales_rep')
                        ->where('pmr.reader_id', $salesRep->id);
                })
                ->whereIn('pm.project_id', $projectIds->all())
                ->whereRaw('pm.id > COALESCE(pmr.last_read_message_id, 0)')
                ->groupBy('pm.project_id')
                ->pluck('unread', 'project_id');
        }

        return view('rep.chats.index', [
            'projects' => $projects,
            'unreadCounts' => $unreadCounts,
        ]);
    }
}
