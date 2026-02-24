<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\SalesRepresentative;
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
        $salesRep = $request->attributes->get('salesRep')
            ?? SalesRepresentative::where('user_id', $user?->id)->first();

        return Inertia::render('Rep/Profile/Edit', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_path' => $user->avatar_path,
            ] : null,
            'sales_rep' => $salesRep ? [
                'id' => $salesRep->id,
                'name' => $salesRep->name,
                'phone' => $salesRep->phone,
                'avatar_path' => $salesRep->avatar_path,
            ] : null,
            'form' => [
                'method' => 'PUT',
                'action' => route('rep.profile.update'),
            ],
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

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($basePath, $filename, 'public');

        $user->avatar_path = $path;
        $user->save();

        if ($salesRep) {
            $salesRep->avatar_path = $path;
            $salesRep->save();
        }
    }
}
