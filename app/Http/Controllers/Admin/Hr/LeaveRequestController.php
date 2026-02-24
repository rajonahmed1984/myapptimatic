<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LeaveRequestController extends Controller
{
    public function index(): InertiaResponse
    {
        $leaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('Admin/Hr/LeaveRequests/Index', [
            'pageTitle' => 'Leave Requests',
            'leaveRequests' => $leaveRequests->through(fn (LeaveRequest $leave) => [
                'id' => $leave->id,
                'employee_name' => $leave->employee?->name ?? '--',
                'leave_type_name' => $leave->leaveType?->name ?? '--',
                'start_date' => $leave->start_date?->format('Y-m-d') ?? '--',
                'end_date' => $leave->end_date?->format('Y-m-d') ?? '--',
                'total_days' => $leave->total_days,
                'status' => ucfirst((string) $leave->status),
                'is_pending' => $leave->status === 'pending',
                'routes' => [
                    'approve' => route('admin.hr.leave-requests.approve', $leave),
                    'reject' => route('admin.hr.leave-requests.reject', $leave),
                ],
            ])->values(),
            'pagination' => [
                'previous_url' => $leaveRequests->previousPageUrl(),
                'next_url' => $leaveRequests->nextPageUrl(),
                'has_pages' => $leaveRequests->hasPages(),
            ],
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $leaveRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'] ?? null,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'Leave request rejected.');
    }
}
