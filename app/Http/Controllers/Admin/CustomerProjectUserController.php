<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectClientUserRequest;
use App\Http\Requests\UpdateProjectClientUserRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Support\StatusColorHelper;
use App\Support\SystemLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerProjectUserController extends Controller
{
    public function store(StoreProjectClientUserRequest $request, Customer $customer)
    {
        $data = $request->validated();
        $projectIds = array_values(array_filter(array_map('intval', (array) ($data['project_ids'] ?? (isset($data['project_id']) ? [$data['project_id']] : [])))));

        if (empty($projectIds)) {
            abort(404);
        }

        $projects = Project::where('customer_id', $customer->id)
            ->whereIn('id', $projectIds)
            ->get();

        if ($projects->count() !== count($projectIds)) {
            abort(404);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => Role::CLIENT_PROJECT,
            'status' => 'active',
            'customer_id' => $customer->id,
            'project_id' => $projectIds[0] ?? null,
        ]);

        $user->projects()->sync($projectIds);

        SystemLogger::write('activity', 'Project client login created.', [
            'customer_id' => $customer->id,
            'project_ids' => $projectIds,
            'user_id' => $user->id,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.customers.show', ['customer' => $customer, 'tab' => 'project-specific'])
            ->with('status', 'Project client user created.');
    }

    public function show(Customer $customer, User $user)
    {
        if ($user->customer_id !== $customer->id || $user->role !== Role::CLIENT_PROJECT) {
            abort(404);
        }

        $user->load('projects', 'project');

        return response()->json([
            'ok' => true,
            'data' => $this->formatPayload($user),
        ]);
    }

    public function update(UpdateProjectClientUserRequest $request, Customer $customer, User $user)
    {
        $this->ensureProjectClientBelongsToCustomer($customer, $user);

        $data = $request->validated();
        $projectIds = array_values(array_filter(array_map('intval', (array) ($data['project_ids'] ?? (isset($data['project_id']) ? [$data['project_id']] : [])))));

        if (empty($projectIds)) {
            abort(404);
        }

        $projects = Project::where('customer_id', $customer->id)
            ->whereIn('id', $projectIds)
            ->get();

        if ($projects->count() !== count($projectIds)) {
            abort(404);
        }

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'project_id' => $projectIds[0] ?? null,
            'status' => $data['status'],
        ];

        // Only update password if provided
        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);
        $user->projects()->sync($projectIds);

        SystemLogger::write('activity', 'Project client login updated.', [
            'customer_id' => $customer->id,
            'project_ids' => $projectIds,
            'user_id' => $user->id,
        ], $request->user()?->id, $request->ip());

        if ($request->expectsJson()) {
            $user->load('projects', 'project');

            return response()->json([
                'ok' => true,
                'message' => 'Project client user updated.',
                'data' => $this->formatPayload($user),
            ]);
        }

        return redirect()->route('admin.customers.show', ['customer' => $customer, 'tab' => 'project-specific'])
            ->with('status', 'Project client user updated.');
    }

    public function updateStatus(Request $request, Customer $customer, User $user)
    {
        $this->ensureProjectClientBelongsToCustomer($customer, $user);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $user->update([
            'status' => $data['status'],
        ]);

        SystemLogger::write('activity', 'Project client login status updated.', [
            'customer_id' => $customer->id,
            'project_id' => $user->project_id,
            'user_id' => $user->id,
            'status' => $data['status'],
        ], $request->user()?->id, $request->ip());

        if ($request->expectsJson()) {
            $user->load('projects', 'project');

            return response()->json([
                'ok' => true,
                'message' => 'Project client user status updated.',
                'data' => $this->formatPayload($user),
            ]);
        }

        return redirect()->route('admin.customers.show', ['customer' => $customer, 'tab' => 'project-specific'])
            ->with('status', 'Project client user status updated.');
    }

    public function destroy(Customer $customer, User $user)
    {
        $this->ensureProjectClientBelongsToCustomer($customer, $user);

        SystemLogger::write('activity', 'Project client login deleted.', [
            'customer_id' => $customer->id,
            'project_id' => $user->project_id,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ], request()->user()?->id, request()->ip());

        $user->delete();

        return redirect()->route('admin.customers.show', ['customer' => $customer, 'tab' => 'project-specific'])
            ->with('status', 'Project client user deleted.');
    }

    private function formatPayload(User $user): array
    {
        $user->loadMissing('projects', 'project');
        $dateFormat = config('app.date_format', 'd-m-Y');
        $status = $user->status ?: 'active';

        $projectIds = $user->assignedProjectIds();
        $projectNames = $user->projects->pluck('name')->all();
        $projectName = $user->projects->pluck('name')->implode(', ');
        if (empty($projectName) && $user->project) {
            $projectName = $user->project->name;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $status,
            'status_label' => ucfirst($status),
            'status_classes' => StatusColorHelper::getBadgeClasses($status),
            'project_id' => $user->project_id,
            'project_ids' => $projectIds,
            'project_name' => $projectName ?: '--',
            'project_names' => $projectNames,
            'created_at' => $user->created_at?->format($dateFormat),
            'updated_at' => $user->updated_at?->format($dateFormat),
        ];
    }

    private function ensureProjectClientBelongsToCustomer(Customer $customer, User $user): void
    {
        if ($user->customer_id !== $customer->id || $user->role !== Role::CLIENT_PROJECT) {
            abort(404);
        }
    }
}
