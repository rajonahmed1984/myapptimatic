<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class TimesheetController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->attributes->get('employee');

        $timesheets = Timesheet::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->paginate(15);

        return view('employee.timesheets.index', compact('timesheets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $request->attributes->get('employee');

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'total_hours' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'submit' => ['sometimes', 'boolean'],
        ]);

        $start = Carbon::parse($data['period_start'])->startOfDay();
        $end = Carbon::parse($data['period_end'])->startOfDay();

        if (! $start->isMonday()) {
            return back()->withErrors(['period_start' => 'Timesheet must start on Monday.'])->withInput();
        }

        $expectedEnd = $start->copy()->endOfWeek();
        if (! $end->isSameDay($expectedEnd)) {
            return back()->withErrors(['period_end' => 'Timesheet must end on Sunday (same week as start).'])->withInput();
        }

        $alreadyExists = Timesheet::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period_start', $start)
            ->whereDate('period_end', $expectedEnd)
            ->whereIn('status', ['draft', 'submitted', 'approved', 'locked'])
            ->exists();

        if ($alreadyExists) {
            return back()->withErrors(['period_start' => 'A timesheet for this week already exists.'])->withInput();
        }

        $status = $request->boolean('submit') ? 'submitted' : 'draft';

        Timesheet::create([
            'employee_id' => $employee->id,
            'period_start' => $start->toDateString(),
            'period_end' => $expectedEnd->toDateString(),
            'total_hours' => $data['total_hours'],
            'notes' => $data['notes'] ?? null,
            'status' => $status,
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);

        return back()->with('status', $status === 'submitted' ? 'Timesheet submitted.' : 'Timesheet saved as draft.');
    }
}
