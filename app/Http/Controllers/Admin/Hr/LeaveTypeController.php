<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(Request $request): View
    {
        $types = LeaveType::query()->orderBy('name')->paginate(20);
        $editingType = null;

        if ($request->filled('edit')) {
            $editingType = LeaveType::query()->find($request->integer('edit'));
        }

        return view('admin.hr.leave-types.index', compact('types', 'editingType'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:leave_types,code'],
            'is_paid' => ['sometimes', 'boolean'],
            'default_allocation' => ['nullable', 'numeric'],
        ]);

        $data['is_paid'] = (bool) ($data['is_paid'] ?? false);

        LeaveType::create($data);

        return back()->with('status', 'Leave type saved.');
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:leave_types,code,' . $leaveType->id],
            'is_paid' => ['sometimes', 'boolean'],
            'default_allocation' => ['nullable', 'numeric'],
        ]);

        $data['is_paid'] = (bool) ($data['is_paid'] ?? false);

        $leaveType->update($data);

        return redirect()
            ->route('admin.hr.leave-types.index')
            ->with('status', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $leaveType->delete();

        return back()->with('status', 'Leave type deleted.');
    }
}
