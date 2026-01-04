<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimesheetController extends Controller
{
    public function index(): View
    {
        $timesheets = Timesheet::query()
            ->with('employee')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.hr.timesheets.index', compact('timesheets'));
    }

    public function approve(Request $request, Timesheet $timesheet): RedirectResponse
    {
        $timesheet->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'Timesheet approved.');
    }

    public function lock(Timesheet $timesheet): RedirectResponse
    {
        $timesheet->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        return back()->with('status', 'Timesheet locked.');
    }
}
