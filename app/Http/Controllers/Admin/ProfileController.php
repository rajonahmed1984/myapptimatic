<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();

        return Inertia::render('Admin/Profile/Edit', [
            'pageTitle' => 'Profile',
            'form' => [
                'action' => route('admin.profile.update'),
                'method' => 'PUT',
                'fields' => [
                    'name' => (string) old('name', (string) ($user?->name ?? '')),
                    'email' => (string) old('email', (string) ($user?->email ?? '')),
                ],
                'avatar_url' => (is_string($user?->avatar_path) && $user->avatar_path !== '')
                    ? Storage::disk('public')->url($user->avatar_path)
                    : null,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', PasswordRule::defaults()],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $user->save();
        }

        if ($request->hasFile('avatar')) {
            $this->storeAvatar($request, $user);
        }

        return redirect()
            ->route('admin.profile.edit')
            ->with('status', 'Profile updated.');
    }

    private function storeAvatar(Request $request, $user): void
    {
        $file = $request->file('avatar');
        if (! $file) {
            return;
        }

        $disk = Storage::disk('public');
        if ($user->avatar_path && $disk->exists($user->avatar_path)) {
            $disk->delete($user->avatar_path);
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("avatars/users/{$user->id}", $filename, 'public');

        $user->avatar_path = $path;
        $user->save();
    }
}
