<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index()
    {
        return view('admin.admins.index', [
            'admins' => User::query()->where('role', 'admin')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.admins.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'admin',
            'customer_id' => null,
        ]);

        return redirect()->route('admin.admins.index')
            ->with('status', 'Admin user created.');
    }

    public function edit(User $admin)
    {
        abort_unless($admin->role === 'admin', 404);

        return view('admin.admins.edit', [
            'admin' => $admin,
        ]);
    }

    public function update(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->role === 'admin', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $updates['password'] = $data['password'];
        }

        $admin->update($updates);

        return redirect()->route('admin.admins.index')
            ->with('status', 'Admin user updated.');
    }

    public function destroy(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->role === 'admin', 404);

        if ($request->user()?->id === $admin->id) {
            return redirect()->route('admin.admins.index')
                ->with('status', 'You cannot delete your own admin account.');
        }

        $adminCount = User::query()->where('role', 'admin')->count();
        if ($adminCount <= 1) {
            return redirect()->route('admin.admins.index')
                ->with('status', 'At least one admin account must remain.');
        }

        $admin->delete();

        return redirect()->route('admin.admins.index')
            ->with('status', 'Admin user deleted.');
    }
}
