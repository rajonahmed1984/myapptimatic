import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const initials = (name = '') =>
    String(name)
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || '')
        .join('') || 'U';

export default function Form({
    pageTitle = 'User',
    is_edit = false,
    selected_role_label = '',
    roles = [],
    form = {},
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};
    const documents = form?.documents || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex items-center justify-between gap-4">
                <h1 className="text-2xl font-semibold text-slate-900">{pageTitle}</h1>
                <a href={routes?.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to users
                </a>
            </div>

            <div className="card p-6">
                <form
                    method="POST"
                    action={form?.action}
                    encType="multipart/form-data"
                    data-native="true"
                    className="grid gap-6 md:grid-cols-2"
                >
                    <input type="hidden" name="_token" value={csrf} />
                    {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                        <input type="hidden" name="_method" value={form?.method} />
                    ) : null}

                    <div>
                        <label className="text-sm text-slate-600">Name</label>
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

                    <div>
                        <label className="text-sm text-slate-600">
                            {is_edit ? 'Password (leave blank to keep)' : 'Password'}
                        </label>
                        <input
                            name="password"
                            type="password"
                            required={!is_edit}
                            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                        />
                        {errors?.password ? <p className="mt-1 text-xs text-rose-600">{errors.password}</p> : null}
                    </div>

                    <div>
                        <label className="text-sm text-slate-600">Confirm Password</label>
                        <input
                            name="password_confirmation"
                            type="password"
                            required={!is_edit}
                            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                        />
                    </div>

                    {is_edit ? (
                        <div>
                            <label className="text-sm text-slate-600">Role</label>
                            <select
                                name="role"
                                defaultValue={fields?.role || ''}
                                required
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            >
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                            {errors?.role ? <p className="mt-1 text-xs text-rose-600">{errors.role}</p> : null}
                        </div>
                    ) : (
                        <div>
                            <label className="text-sm text-slate-600">Role</label>
                            <div className="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-700">
                                {selected_role_label}
                            </div>
                        </div>
                    )}

                    <div className="md:col-span-2 rounded-2xl border border-slate-200 bg-white/70 p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Documents</div>
                        <div className="mt-3 grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm text-slate-600">Avatar</label>
                                <input
                                    name="avatar"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                />
                                {documents?.avatar_url ? (
                                    <div className="mt-2">
                                        <img
                                            src={documents.avatar_url}
                                            alt="Avatar"
                                            className="h-16 w-16 rounded-full border border-slate-200 object-cover"
                                        />
                                    </div>
                                ) : (
                                    <div className="mt-2 flex h-16 w-16 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-sm font-semibold text-slate-700">
                                        {initials(fields?.name || '')}
                                    </div>
                                )}
                                {errors?.avatar ? <p className="mt-1 text-xs text-rose-600">{errors.avatar}</p> : null}
                            </div>

                            <div>
                                <label className="text-sm text-slate-600">NID</label>
                                <input
                                    name="nid_file"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                />
                                {documents?.nid_url ? (
                                    <div className="mt-1 text-xs text-slate-500">
                                        <a href={documents.nid_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                            View current NID
                                        </a>
                                    </div>
                                ) : null}
                                {errors?.nid_file ? <p className="mt-1 text-xs text-rose-600">{errors.nid_file}</p> : null}
                            </div>

                            <div>
                                <label className="text-sm text-slate-600">CV</label>
                                <input
                                    name="cv_file"
                                    type="file"
                                    accept=".pdf"
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                />
                                {documents?.cv_url ? (
                                    <div className="mt-1 text-xs text-slate-500">
                                        <a href={documents.cv_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                            View current CV
                                        </a>
                                    </div>
                                ) : null}
                                {errors?.cv_file ? <p className="mt-1 text-xs text-rose-600">{errors.cv_file}</p> : null}
                            </div>
                        </div>
                    </div>

                    <div className="md:col-span-2 flex justify-end">
                        <button type="submit" className="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">
                            {is_edit ? 'Save Changes' : 'Create User'}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
