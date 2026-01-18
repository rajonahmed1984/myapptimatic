<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('client.profile.edit', [
            'user' => $request->user(),
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

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($basePath, $filename, 'public');

        if ($customer) {
            $customer->avatar_path = $path;
            $customer->save();
        }

        $user->avatar_path = $path;
        $user->save();
    }
}
