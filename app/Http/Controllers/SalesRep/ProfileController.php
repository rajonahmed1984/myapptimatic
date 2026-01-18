<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\SalesRepresentative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $salesRep = $request->attributes->get('salesRep')
            ?? SalesRepresentative::where('user_id', $user?->id)->first();

        return view('rep.profile.edit', [
            'user' => $user,
            'salesRep' => $salesRep,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $salesRep = $request->attributes->get('salesRep')
            ?? SalesRepresentative::where('user_id', $user?->id)->first();

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

        if ($salesRep) {
            $salesRep->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);
        }

        if ($request->hasFile('avatar') && $user) {
            $this->storeAvatar($request, $user, $salesRep);
        }

        return redirect()
            ->route('rep.profile.edit')
            ->with('status', 'Profile updated.');
    }

    private function storeAvatar(Request $request, $user, ?SalesRepresentative $salesRep): void
    {
        $file = $request->file('avatar');
        if (! $file) {
            return;
        }

        $disk = Storage::disk('public');

        foreach ([$user, $salesRep] as $model) {
            if ($model && $model->avatar_path && $disk->exists($model->avatar_path)) {
                $disk->delete($model->avatar_path);
            }
        }

        $basePath = $salesRep
            ? "avatars/sales-reps/{$salesRep->id}"
            : "avatars/users/{$user->id}";

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($basePath, $filename, 'public');

        $user->avatar_path = $path;
        $user->save();

        if ($salesRep) {
            $salesRep->avatar_path = $path;
            $salesRep->save();
        }
    }
}
