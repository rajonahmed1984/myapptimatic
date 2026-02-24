<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LeaveRequestController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $employee = $request->attributes->get('employee');
        $leaveRequests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderByDesc('id')
            ->paginate(15);

        $leaveTypes = LeaveType::query()->orderBy('name')->get();

        return Inertia::render('Employee/LeaveRequests/Index', [
            'leave_requests' => $leaveRequests->getCollection()->map(function (LeaveRequest $leave) {
                return [
                    'id' => $leave->id,
                    'type_name' => $leave->leaveType?->name ?? '--',
                    'start_date_display' => $leave->start_date?->format(config('app.date_format', 'Y-m-d')) ?? '--',
                    'end_date_display' => $leave->end_date?->format(config('app.date_format', 'Y-m-d')) ?? '--',
                    'total_days' => (int) ($leave->total_days ?? 0),
                    'status_label' => ucfirst((string) $leave->status),
                    'approved_at_display' => $leave->approved_at?->format(config('app.date_format', 'Y-m-d').' H:i') ?? '--',
                ];
            })->values()->all(),
            'leave_types' => $leaveTypes->map(fn (LeaveType $type) => [
                'id' => $type->id,
                'name' => $type->name,
            ])->values()->all(),
            'pagination' => [
                'current_page' => $leaveRequests->currentPage(),
                'last_page' => $leaveRequests->lastPage(),
                'per_page' => $leaveRequests->perPage(),
                'total' => $leaveRequests->total(),
                'from' => $leaveRequests->firstItem(),
                'to' => $leaveRequests->lastItem(),
                'prev_page_url' => $leaveRequests->previousPageUrl(),
                'next_page_url' => $leaveRequests->nextPageUrl(),
            ],
            'routes' => [
                'index' => route('employee.leave-requests.index'),
                'store' => route('employee.leave-requests.store'),
            ],
        ]);
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
