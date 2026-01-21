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
use Illuminate\Support\Facades\Hash;

class CustomerProjectUserController extends Controller
{
    public function store(StoreProjectClientUserRequest $request, Customer $customer)
    {
        $data = $request->validated();
        $project = Project::findOrFail($data['project_id']);

        if ($project->customer_id !== $customer->id) {
            abort(404);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => Role::CLIENT_PROJECT,
            'status' => 'active',
            'customer_id' => $customer->id,
            'project_id' => $project->id,
        ]);

        SystemLogger::write('activity', 'Project client login created.', [
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.customers.edit', $customer)
            ->with('status', 'Project client user created.');
    }

    public function show(Customer $customer, User $user)
    {
        if ($user->customer_id !== $customer->id || $user->role !== Role::CLIENT_PROJECT) {
            abort(404);
        }

        $user->load('project');

        return response()->json([
            'ok' => true,
            'data' => $this->formatPayload($user),
        ]);
    }

    public function update(UpdateProjectClientUserRequest $request, Customer $customer, User $user)
    {
        // Verify user belongs to this customer and is a project client
        if ($user->customer_id !== $customer->id || $user->role !== Role::CLIENT_PROJECT) {
            abort(404);
        }

        $data = $request->validated();
        $project = Project::findOrFail($data['project_id']);

        if ($project->customer_id !== $customer->id) {
            abort(404);
        }

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'project_id' => $project->id,
            'status' => $data['status'],
        ];

        // Only update password if provided
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        SystemLogger::write('activity', 'Project client login updated.', [
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
        ], $request->user()?->id, $request->ip());

        if ($request->expectsJson()) {
            $user->load('project');
            return response()->json([
                'ok' => true,
                'message' => 'Project client user updated.',
                'data' => $this->formatPayload($user),
            ]);
        }

        return redirect()->route('admin.customers.edit', $customer)
            ->with('status', 'Project client user updated.');
    }

    public function destroy(Customer $customer, User $user)
    {
        // Verify user belongs to this customer and is a project client
        if ($user->customer_id !== $customer->id || $user->role !== Role::CLIENT_PROJECT) {
            abort(404);
        }

        SystemLogger::write('activity', 'Project client login deleted.', [
            'customer_id' => $customer->id,
            'project_id' => $user->project_id,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ], request()->user()?->id, request()->ip());

        $user->delete();

        return redirect()->route('admin.customers.edit', $customer)
            ->with('status', 'Project client user deleted.');
    }

    private function formatPayload(User $user): array
    {
        $dateFormat = config('app.date_format', 'd-m-Y');
        $status = $user->status ?: 'active';

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $status,
            'status_label' => ucfirst($status),
            'status_classes' => StatusColorHelper::getBadgeClasses($status),
            'project_id' => $user->project_id,
            'project_name' => $user->project?->name,
            'created_at' => $user->created_at?->format($dateFormat),
            'updated_at' => $user->updated_at?->format($dateFormat),
        ];
    }
}
