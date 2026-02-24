<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        $employee = $user?->employee;

        return Inertia::render('Employee/Profile/Edit', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_path' => $user->avatar_path,
            ] : null,
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'photo_path' => $employee->photo_path,
            ] : null,
            'form' => [
                'method' => 'PUT',
                'action' => route('employee.profile.update'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $employee = $user?->employee;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', PasswordRule::defaults()],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($user) {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->save();
            }
        }

        if ($employee) {
            $employee->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);
        }

        if ($request->hasFile('avatar') && $user) {
            $this->storeAvatar($request, $user, $employee);
        }

        return redirect()
            ->route('employee.profile.edit')
            ->with('status', 'Profile updated.');
    }

    private function storeAvatar(Request $request, $user, ?Employee $employee): void
    {
        $file = $request->file('avatar');
        if (! $file) {
            return;
        }

        $disk = Storage::disk('public');
        foreach ([$user, $employee] as $model) {
            if ($model && $model->avatar_path && $disk->exists($model->avatar_path)) {
                $disk->delete($model->avatar_path);
            }
            if ($model && $model->photo_path && $disk->exists($model->photo_path)) {
                $disk->delete($model->photo_path);
            }
        }

        $basePath = $employee
            ? "avatars/employees/{$employee->id}"
            : "avatars/users/{$user->id}";

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($basePath, $filename, 'public');

        $user->avatar_path = $path;
        $user->save();

        if ($employee) {
            $employee->photo_path = $path;
            $employee->save();
        }
    }
}
