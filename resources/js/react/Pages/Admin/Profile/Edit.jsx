import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const initials = (name = '') =>
    String(name)
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || '')
        .join('') || 'U';

export default function Edit({ pageTitle = 'Profile', form = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Admin profile</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Account details</h1>
                    <p className="mt-2 text-sm text-slate-500">Update your name, email, and password.</p>
                </div>
            </div>

            <div className="card p-6">
                <form method="POST" action={form?.action} className="mt-6 space-y-6" encType="multipart/form-data" data-native="true">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value={form?.method || 'PUT'} />

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 overflow-hidden rounded-full border border-slate-200 bg-white">
                                {form?.avatar_url ? (
                                    <img src={form.avatar_url} alt={fields?.name || 'Avatar'} className="h-16 w-16 object-cover" />
                                ) : (
                                    <div className="flex h-16 w-16 items-center justify-center text-sm font-semibold text-slate-700">
                                        {initials(fields?.name || '')}
                                    </div>
                                )}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Profile photo</label>
                                <input name="avatar" type="file" accept="image/*" className="mt-2 text-sm text-slate-600" />
                                <p className="text-xs text-slate-500">PNG/JPG up to 2MB.</p>
                                {errors?.avatar ? <p className="mt-1 text-xs text-rose-600">{errors.avatar}</p> : null}
                            </div>
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Full name</label>
                            <input
                                name="name"
                                defaultValue={fields?.name || ''}
                                required
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            />
                            {errors?.name ? <p className="mt-1 text-xs text-rose-600">{errors.name}</p> : null}
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Email</label>
                            <input
                                name="email"
                                type="email"
                                defaultValue={fields?.email || ''}
                                required
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            />
                            {errors?.email ? <p className="mt-1 text-xs text-rose-600">{errors.email}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="text-sm text-slate-600">Current password</label>
                            <input name="current_password" type="password" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            {errors?.current_password ? <p className="mt-1 text-xs text-rose-600">{errors.current_password}</p> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">New password</label>
                            <input name="password" type="password" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            {errors?.password ? <p className="mt-1 text-xs text-rose-600">{errors.password}</p> : null}
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
