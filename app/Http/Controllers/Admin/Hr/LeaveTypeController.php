<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LeaveTypeController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $types = LeaveType::query()->orderBy('name')->paginate(20);
        $editingType = null;

        if ($request->filled('edit')) {
            $editingType = LeaveType::query()->find($request->integer('edit'));
        }

        return Inertia::render('Admin/Hr/LeaveTypes/Index', [
            'pageTitle' => 'Leave Types',
            'types' => $types->through(fn (LeaveType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'is_paid' => (bool) $type->is_paid,
                'default_allocation' => $type->default_allocation,
                'routes' => [
                    'edit' => route('admin.hr.leave-types.index', ['edit' => $type->id]),
                    'destroy' => route('admin.hr.leave-types.destroy', $type),
                ],
            ])->values(),
            'pagination' => [
                'previous_url' => $types->previousPageUrl(),
                'next_url' => $types->nextPageUrl(),
                'has_pages' => $types->hasPages(),
            ],
            'editingType' => $editingType ? [
                'id' => $editingType->id,
                'name' => $editingType->name,
                'code' => $editingType->code,
                'is_paid' => (bool) $editingType->is_paid,
                'default_allocation' => $editingType->default_allocation,
                'routes' => [
                    'update' => route('admin.hr.leave-types.update', $editingType),
                ],
            ] : null,
            'routes' => [
                'index' => route('admin.hr.leave-types.index'),
                'store' => route('admin.hr.leave-types.store'),
            ],
        ]);
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
            'code' => ['required', 'string', 'max:50', 'unique:leave_types,code,'.$leaveType->id],
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
