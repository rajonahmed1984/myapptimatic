import React from 'react';
import { Head } from '@inertiajs/react';

const initialsFor = (value) => {
    const parts = String(value || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (parts.length === 0) {
        return 'U';
    }

    return parts
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
};

export default function Edit({ user = {}, form = {}, routes = {} }) {
    const initials = initialsFor(user?.name);

    return (
        <>
            <Head title="Profile" />

            <div className="card p-6">
                <div className="section-label">Client profile</div>
                <h1 className="mt-2 text-2xl font-semibold text-slate-900">Account details</h1>
                <p className="mt-2 text-sm text-slate-500">Update your name, email, and password.</p>

                <form method="POST" action={routes.update} className="mt-6 space-y-6" encType="multipart/form-data" data-native="true">
                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                    <input type="hidden" name="_method" value="PUT" />

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 overflow-hidden rounded-full border border-slate-200 bg-white">
                                {user?.avatar_path ? (
                                    <img src={`/storage/${user.avatar_path}`} alt={user?.name || 'User avatar'} className="h-16 w-16 object-cover" />
                                ) : (
                                    <div className="grid h-16 w-16 place-items-center text-sm font-semibold text-slate-600">{initials}</div>
                                )}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Profile photo</label>
                                <input name="avatar" type="file" accept="image/*" className="mt-2 text-sm text-slate-600" />
                                <p className="text-xs text-slate-500">PNG/JPG up to 2MB.</p>
                            </div>
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Full name</label>
                            <input
                                name="name"
                                defaultValue={form?.name || ''}
                                required
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Email</label>
                            <input
                                name="email"
                                type="email"
                                defaultValue={form?.email || ''}
                                required
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="text-sm text-slate-600">Current password</label>
                            <input name="current_password" type="password" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">New password</label>
                            <input name="password" type="password" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Confirm new password</label>
                            <input
                                name="password_confirmation"
                                type="password"
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <button type="submit" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">
                            Save profile
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
