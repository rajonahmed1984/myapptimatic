import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const avatarUrl = (path) => {
    if (!path) return null;
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) return path;
    return `/storage/${path}`;
};

export default function Edit({ user = {}, sales_rep = {}, form = {} }) {
    const page = usePage();
    const csrfToken = page?.props?.csrf_token || '';
    const errors = page?.props?.errors || {};
    const image = avatarUrl(sales_rep?.avatar_path || user?.avatar_path);

    return (
        <>
            <Head title="Profile" />

            <div className="card p-6">
                <div className="section-label">Account</div>
                <h1 className="mt-2 text-2xl font-semibold text-slate-900">Profile & security</h1>
                <p className="mt-2 text-sm text-slate-500">Update your name, contact details, profile photo, and password.</p>

                <form method="POST" action={form?.action} className="mt-6 space-y-6" encType="multipart/form-data" data-native="true">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <input type="hidden" name="_method" value={form?.method || 'PUT'} />

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 overflow-hidden rounded-full border border-slate-200 bg-white">
                                {image ? <img src={image} alt="Avatar" className="h-16 w-16 object-cover" /> : <div className="flex h-full w-full items-center justify-center text-xs text-slate-400">No photo</div>}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Profile photo</label>
                                <input name="avatar" type="file" accept="image/*" className="mt-2 text-sm text-slate-600" />
                                {errors?.avatar ? <div className="mt-1 text-xs text-rose-600">{errors.avatar}</div> : null}
                            </div>
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Full name</label>
                            <input name="name" defaultValue={user?.name || ''} required className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            {errors?.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Email</label>
                            <input name="email" type="email" defaultValue={user?.email || ''} required className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            {errors?.email ? <div className="mt-1 text-xs text-rose-600">{errors.email}</div> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Phone</label>
                            <input name="phone" type="text" defaultValue={sales_rep?.phone || ''} className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            {errors?.phone ? <div className="mt-1 text-xs text-rose-600">{errors.phone}</div> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div><label className="text-sm text-slate-600">Current password</label><input name="current_password" type="password" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                        <div><label className="text-sm text-slate-600">New password</label><input name="password" type="password" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                        <div><label className="text-sm text-slate-600">Confirm new password</label><input name="password_confirmation" type="password" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" /></div>
                    </div>

                    <div className="flex justify-end">
                        <button type="submit" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">Save profile</button>
                    </div>
                </form>
            </div>
        </>
    );
}
