<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportUserRequest;
use App\Models\User;
use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(string $role)
    {
        $role = $this->normalizeRole($role);
        $roles = $this->adminRoles();

        return view('admin.users.index', [
            'users' => User::query()
                ->whereIn('role', array_keys($roles))
                ->when($role, fn ($q) => $q->where('role', $role))
                ->orderBy('name')
                ->get(),
            'selectedRole' => $role,
            'roles' => $roles,
        ]);
    }

    public function create(string $role)
    {
        $role = $this->normalizeRole($role);

        return view('admin.users.create', [
            'selectedRole' => $role,
            'roles' => $this->adminRoles(),
        ]);
    }

    public function store(Request $request, string $role): RedirectResponse
    {
        $role = $this->normalizeRole($role);

        $data = $this->validateStoreRequest($request, $role);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $role,
            'customer_id' => null,
        ]);

        $uploadPaths = $this->handleUploads($request, $user);
        if (! empty($uploadPaths)) {
            $user->update($uploadPaths);
        }

        return redirect()->route('admin.users.index', $role)
            ->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        $this->abortIfNotAdminRole($user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->adminRoles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->abortIfNotAdminRole($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_keys($this->adminRoles()))],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
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

        $uploadPaths = $this->handleUploads($request, $user);
        if (! empty($uploadPaths)) {
            $user->update($uploadPaths);
        }

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
        if (! array_key_exists($role, $this->adminRoles())) {
            abort(404);
        }

        return $role;
    }

    private function abortIfNotAdminRole(User $user): void
    {
        if (! array_key_exists($user->role, $this->adminRoles())) {
            abort(404);
        }
    }

    private function adminRoles(): array
    {
        return [
            Role::MASTER_ADMIN => 'Master Admin',
            Role::SUB_ADMIN => 'Sub Admin',
            Role::SUPPORT => 'Support',
        ];
    }

    private function validateStoreRequest(Request $request, string $role): array
    {
        if ($role === Role::SUPPORT) {
            return $this->resolveFormRequest(StoreSupportUserRequest::class, $request);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
    }

    private function resolveFormRequest(string $class, Request $request): array
    {
        /** @var \Illuminate\Foundation\Http\FormRequest $formRequest */
        $formRequest = $class::createFrom($request);
        $formRequest->setContainer(app())->setRedirector(app('redirect'));
        $formRequest->setUserResolver($request->getUserResolver());
        $formRequest->setRouteResolver($request->getRouteResolver());
        $formRequest->validateResolved();

        return $formRequest->validated();
    }

    private function handleUploads(Request $request, User $user): array
    {
        $paths = [];

        if ($request->hasFile('avatar')) {
            $paths['avatar_path'] = $request->file('avatar')
                ->store('avatars/users/' . $user->id, 'public');
        }

        if ($request->hasFile('nid_file')) {
            $paths['nid_path'] = $request->file('nid_file')
                ->store('nid/users/' . $user->id, 'public');
        }

        if ($request->hasFile('cv_file')) {
            $paths['cv_path'] = $request->file('cv_file')
                ->store('cv/users/' . $user->id, 'public');
        }

        return $paths;
    }
}
