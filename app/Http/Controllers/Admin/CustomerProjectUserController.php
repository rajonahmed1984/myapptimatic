<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectClientUserRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
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
}
