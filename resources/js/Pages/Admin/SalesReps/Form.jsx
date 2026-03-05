import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const initials = (name = '') =>
    String(name)
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || '')
        .join('') || 'SR';

export default function Form({
    pageTitle = 'Sales Representative',
    is_edit = false,
    rep = null,
    employees = [],
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

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Sales</div>
                    <div className="text-2xl font-semibold text-slate-900">
                        {is_edit ? fields?.name || 'Edit sales representative' : 'Add sales representative'}
                    </div>
                    <div className="text-sm text-slate-500">
                        {is_edit ? 'Update contact details and status.' : 'Create profile details and set login credentials.'}
                    </div>
                </div>
                <a
                    href={routes?.index}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                >
                    Back
                </a>
            </div>

            <div className="card p-6">
                <form
                    action={form?.action}
                    method="POST"
                    encType="multipart/form-data"
                    data-native="true"
                    autoComplete="off"
                    className="grid gap-4 text-sm text-slate-700 lg:grid-cols-2"
                >
                    <input type="hidden" name="_token" value={csrf} />
                    {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                        <input type="hidden" name="_method" value={form?.method} />
                    ) : null}

                    {is_edit ? (
                        <div className="rounded-2xl border border-slate-300 bg-white/80 p-4 space-y-3">
                            <div>
                                <label className="text-xs text-slate-500">User</label>
                                <div className="mt-1 text-slate-900 font-semibold">{rep?.user_name || '--'}</div>
                                <div className="text-xs text-slate-500">{rep?.user_email || ''}</div>
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Employee link (optional)</label>
                                <select
                                    name="employee_id"
                                    defaultValue={fields?.employee_id || ''}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="">None</option>
                                    {employees.map((employee) => (
                                        <option key={employee.id} value={employee.id}>
                                            {employee.name}
                                        </option>
                                    ))}
                                </select>
                                {errors?.employee_id ? <p className="mt-1 text-xs text-rose-600">{errors.employee_id}</p> : null}
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Status</label>
                                <select
                                    name="status"
                                    defaultValue={fields?.status || 'active'}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                {errors?.status ? <p className="mt-1 text-xs text-rose-600">{errors.status}</p> : null}
                            </div>
                        </div>
                    ) : null}

                    <div className="rounded-2xl border border-slate-300 bg-white/80 p-4 space-y-3">
                        {!is_edit ? (
                            <div>
                                <label className="text-xs text-slate-500">Employee link (optional)</label>
                                <select
                                    name="employee_id"
                                    defaultValue={fields?.employee_id || ''}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="">None</option>
                                    {employees.map((employee) => (
                                        <option key={employee.id} value={employee.id}>
                                            {employee.name}
                                        </option>
                                    ))}
                                </select>
                                {errors?.employee_id ? <p className="mt-1 text-xs text-rose-600">{errors.employee_id}</p> : null}
                            </div>
                        ) : null}
                        <div>
                            <label className="text-xs text-slate-500">Name</label>
                            <input
                                name="name"
                                defaultValue={fields?.name || ''}
                                required
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            {errors?.name ? <p className="mt-1 text-xs text-rose-600">{errors.name}</p> : null}
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Email</label>
                            <input
                                name="email"
                                type="email"
                                defaultValue={fields?.email || ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            {errors?.email ? <p className="mt-1 text-xs text-rose-600">{errors.email}</p> : null}
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Phone</label>
                            <input
                                name="phone"
                                defaultValue={fields?.phone || ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            {errors?.phone ? <p className="mt-1 text-xs text-rose-600">{errors.phone}</p> : null}
                        </div>
                        {!is_edit ? (
                            <>
                                <div>
                                    <label className="text-xs text-slate-500">Password</label>
                                    <input name="user_password" type="password" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                    {errors?.user_password ? <p className="mt-1 text-xs text-rose-600">{errors.user_password}</p> : null}
                                    <p className="mt-1 text-xs text-slate-500">Set a password to create sales portal login.</p>
                                </div>
                                <div>
                                    <label className="text-xs text-slate-500">Status</label>
                                    <select
                                        name="status"
                                        defaultValue={fields?.status || 'active'}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    >
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    {errors?.status ? <p className="mt-1 text-xs text-rose-600">{errors.status}</p> : null}
                                </div>
                            </>
                        ) : null}
                    </div>

                    <div className="rounded-2xl border border-slate-300 bg-white/80 p-4 space-y-3 lg:col-span-2">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Documents</div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-xs text-slate-500">Avatar</label>
                                <input
                                    name="avatar"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                                {documents?.avatar_url ? (
                                    <img src={documents.avatar_url} alt="Avatar" className="mt-2 h-16 w-16 rounded-full border border-slate-200 object-cover" />
                                ) : (
                                    <div className="mt-2 flex h-16 w-16 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-sm font-semibold text-slate-700">
                                        {initials(fields?.name || '')}
                                    </div>
                                )}
                                {errors?.avatar ? <p className="mt-1 text-xs text-rose-600">{errors.avatar}</p> : null}
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">NID</label>
                                <input
                                    name="nid_file"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                                {documents?.nid_url ? (
                                    <div className="mt-2 flex items-center gap-3">
                                        {documents?.nid_is_image ? (
                                            <img src={documents.nid_url} alt="NID" className="h-16 w-20 rounded-lg border border-slate-200 object-cover" />
                                        ) : (
                                            <div className="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">
                                                PDF
                                            </div>
                                        )}
                                        <a href={documents.nid_url} data-native="true" className="text-xs text-teal-600 hover:text-teal-500">
                                            View current NID
                                        </a>
                                    </div>
                                ) : null}
                                {errors?.nid_file ? <p className="mt-1 text-xs text-rose-600">{errors.nid_file}</p> : null}
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">CV</label>
                                <input
                                    name="cv_file"
                                    type="file"
                                    accept=".pdf"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                                {documents?.cv_url ? (
                                    <div className="mt-2 flex items-center gap-3">
                                        <div className="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">
                                            PDF
                                        </div>
                                        <a href={documents.cv_url} data-native="true" className="text-xs text-teal-600 hover:text-teal-500">
                                            View current CV
                                        </a>
                                    </div>
                                ) : null}
                                {errors?.cv_file ? <p className="mt-1 text-xs text-rose-600">{errors.cv_file}</p> : null}
                            </div>
                        </div>
                    </div>

                    <div className="lg:col-span-2 flex items-center gap-3">
                        <button type="submit" className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                            {is_edit ? 'Update rep' : 'Create rep'}
                        </button>
                        {is_edit ? <div className="text-xs text-slate-500">Changes sync to rep dashboard immediately.</div> : null}
                    </div>
                </form>
            </div>
        </>
    );
}
