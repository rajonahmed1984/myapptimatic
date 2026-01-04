<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(): View
    {
        $leaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.hr.leave-requests.index', compact('leaveRequests'));
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
