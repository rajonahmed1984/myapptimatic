<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
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

        return Inertia::render('Client/Profile/Edit', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'avatar_path' => $user->avatar_path,
            ],
            'form' => [
                'name' => old('name', $user->name),
                'email' => old('email', $user->email),
            ],
            'routes' => [
                'update' => route('client.profile.update'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $customer = $user?->customer;

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

        if ($customer) {
            $customer->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $user->save();
        }

        if ($request->hasFile('avatar')) {
            $this->storeAvatar($request, $user, $customer);
        }

        return redirect()
            ->route('client.profile.edit')
            ->with('status', 'Profile updated.');
    }

    private function storeAvatar(Request $request, $user, $customer): void
    {
        $file = $request->file('avatar');
        if (! $file) {
            return;
        }

        $disk = Storage::disk('public');

        foreach ([$user, $customer] as $model) {
            if ($model && $model->avatar_path && $disk->exists($model->avatar_path)) {
                $disk->delete($model->avatar_path);
            }
        }

        $basePath = $customer
            ? "avatars/customers/{$customer->id}"
            : "avatars/users/{$user->id}";

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($basePath, $filename, 'public');

        if ($customer) {
            $customer->avatar_path = $path;
            $customer->save();
        }

        $user->avatar_path = $path;
        $user->save();
    }
}
