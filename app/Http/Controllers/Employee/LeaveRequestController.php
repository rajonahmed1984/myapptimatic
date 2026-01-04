<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->attributes->get('employee');
        $leaveRequests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderByDesc('id')
            ->paginate(15);

        $leaveTypes = LeaveType::query()->orderBy('name')->get();

        return view('employee.leave-requests.index', compact('leaveRequests', 'leaveTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $request->attributes->get('employee');

        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $days = (int) Carbon::parse($data['start_date'])->floatDiffInDays(Carbon::parse($data['end_date'])) + 1;

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Leave request submitted.');
    }
}
