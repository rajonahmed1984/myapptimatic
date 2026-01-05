<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const ROLES = [
        'master_admin' => 'Master Admin',
        'sub_admin' => 'Sub Admin',
        'sales' => 'Sales',
        'support' => 'Support',
    ];

    public function index(string $role)
    {
        $role = $this->normalizeRole($role);

        return view('admin.users.index', [
            'users' => User::query()
                ->whereIn('role', array_keys(self::ROLES))
                ->when($role, fn ($q) => $q->where('role', $role))
                ->orderBy('name')
                ->get(),
            'selectedRole' => $role,
            'roles' => self::ROLES,
        ]);
    }

    public function create(string $role)
    {
        $role = $this->normalizeRole($role);

        return view('admin.users.create', [
            'selectedRole' => $role,
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request, string $role): RedirectResponse
    {
        $role = $this->normalizeRole($role);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'customer_id' => null,
        ]);

        return redirect()->route('admin.users.index', $data['role'])
            ->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        $this->abortIfNotAdminRole($user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => self::ROLES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->abortIfNotAdminRole($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
        ]);

        // Prevent self from downgrading own role to avoid lockout.
        if ($request->user()?->id === $user->id && $data['role'] !== $user->role) {
            return back()->withInput()->with('status', 'You cannot change your own role.');
        }

        if ($user->role === 'master_admin' && $data['role'] !== 'master_admin') {
            $otherMasters = User::query()
                ->where('role', 'master_admin')
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherMasters === 0) {
                return back()->withInput()->with('status', 'At least one master admin must remain.');
            }
        }

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ];

        if (! empty($data['password'])) {
            $updates['password'] = $data['password'];
        }

        $user->update($updates);

        return redirect()->route('admin.users.index', $data['role'])
            ->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->abortIfNotAdminRole($user);

        if ($request->user()?->id === $user->id) {
            return back()->with('status', 'You cannot delete your own account.');
        }

        if ($user->role === 'master_admin') {
            $otherMasters = User::query()
                ->where('role', 'master_admin')
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherMasters === 0) {
                return back()->with('status', 'At least one master admin must remain.');
            }
        }

        $user->delete();

        return redirect()->route('admin.users.index', $user->role)
            ->with('status', 'User deleted.');
    }

    private function normalizeRole(string $role): string
    {
        if (! array_key_exists($role, self::ROLES)) {
            abort(404);
        }

        return $role;
    }

    private function abortIfNotAdminRole(User $user): void
    {
        if (! array_key_exists($user->role, self::ROLES)) {
            abort(404);
        }
    }
}
