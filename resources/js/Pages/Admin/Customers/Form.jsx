import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import useObjectUrlPreview from '../../../hooks/useObjectUrlPreview';

export default function Form({
    pageTitle = 'Customer',
    is_edit = false,
    customer_id = null,
    created_at = null,
    effective_status = null,
    sales_reps = [],
    form = {},
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};
    const [avatarFile, setAvatarFile] = useState(null);
    const previewUrl = useObjectUrlPreview(avatarFile, { enabled: String(avatarFile?.type || '').startsWith('image/') });
    const avatarUrl = previewUrl || form?.avatar_url || '';
    const statusLabel = String(effective_status || fields?.status || 'active');

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Customers</div>
                    <h1 className="text-2xl font-semibold text-slate-900">{is_edit ? fields?.name || 'Edit Customer' : 'Create Customer'}</h1>
                    {is_edit ? (
                        <div className="mt-1 text-sm text-slate-500">
                            Client ID: {customer_id || '--'}
                        </div>
                    ) : null}
                </div>
                <div className="text-sm text-slate-600">
                    {is_edit ? (
                        <>
                            <div>Status: {statusLabel.charAt(0).toUpperCase() + statusLabel.slice(1)}</div>
                            <div>Created: {created_at || '--'}</div>
                        </>
                    ) : (
                        <a
                            href={routes?.index}
                            data-native="true"
                            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Back to customers
                        </a>
                    )}
                </div>
            </div>

            <div className="card p-6">
                <form method="POST" action={form?.action} encType="multipart/form-data" data-native="true" className="mt-6 space-y-6">
                    <input type="hidden" name="_token" value={csrf} />
                    {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                        <input type="hidden" name="_method" value={form?.method} />
                    ) : null}

                    {is_edit ? (
                        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="flex items-center gap-4 md:col-span-1">
                                    <div className="h-20 w-20 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                        {avatarUrl ? (
                                            <img src={avatarUrl} alt={fields?.name || 'Customer logo'} className="h-full w-full object-cover" />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center text-xs font-semibold text-slate-500">No logo</div>
                                        )}
                                    </div>
                                    <div className="text-xs text-slate-500">
                                        Upload logo and files during customer creation or update.
                                    </div>
                                </div>
                                <div className="grid gap-4 md:col-span-2 md:grid-cols-2">
                                    <div>
                                        <label className="text-sm text-slate-600">Customer logo</label>
                                        <input
                                            name="avatar"
                                            type="file"
                                            accept="image/*"
                                            onChange={(event) => setAvatarFile(event.target.files?.[0] || null)}
                                            className="mt-2 block w-full text-sm text-slate-600"
                                        />
                                        <p className="mt-1 text-xs text-slate-500">PNG/JPG/WEBP, max 2MB.</p>
                                        {errors?.avatar ? <p className="mt-1 text-xs text-rose-500">{errors.avatar}</p> : null}
                                    </div>
                                    <div>
                                        <label className="text-sm text-slate-600">NID file</label>
                                        <input name="nid_file" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" className="mt-2 block w-full text-sm text-slate-600" />
                                        <p className="mt-1 text-xs text-slate-500">JPG/PNG/WEBP/PDF, max 10MB.</p>
                                        {errors?.nid_file ? <p className="mt-1 text-xs text-rose-500">{errors.nid_file}</p> : null}
                                    </div>
                                    <div className="md:col-span-2">
                                        <label className="text-sm text-slate-600">CV file</label>
                                        <input name="cv_file" type="file" accept=".pdf,.doc,.docx" className="mt-2 block w-full text-sm text-slate-600" />
                                        <p className="mt-1 text-xs text-slate-500">PDF/DOC/DOCX, max 10MB.</p>
                                        {errors?.cv_file ? <p className="mt-1 text-xs text-rose-500">{errors.cv_file}</p> : null}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : null}

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="text-sm text-slate-600">Full Name</label>
                            <input name="name" defaultValue={fields?.name || ''} required className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                            {errors?.name ? <p className="mt-1 text-xs text-rose-500">{errors.name}</p> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Company Name</label>
                            <input
                                name="company_name"
                                defaultValue={fields?.company_name || ''}
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            />
                            {errors?.company_name ? <p className="mt-1 text-xs text-rose-500">{errors.company_name}</p> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Email Address</label>
                            <input
                                name="email"
                                type="email"
                                defaultValue={fields?.email || ''}
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            />
                            {errors?.email ? <p className="mt-1 text-xs text-rose-500">{errors.email}</p> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Phone Number</label>
                            <input name="phone" defaultValue={fields?.phone || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                            {errors?.phone ? <p className="mt-1 text-xs text-rose-500">{errors.phone}</p> : null}
                        </div>

                        {!is_edit ? (
                            <>
                                <div>
                                    <label className="text-sm text-slate-600">Default Sales Rep</label>
                                    <select
                                        name="default_sales_rep_id"
                                        defaultValue={fields?.default_sales_rep_id || ''}
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                    >
                                        <option value="">None</option>
                                        {sales_reps.map((rep) => (
                                            <option key={rep.id} value={rep.id}>
                                                {rep.name} {rep.status !== 'active' ? `(${String(rep.status).charAt(0).toUpperCase() + String(rep.status).slice(1)})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Profile Image</label>
                                    <input
                                        name="avatar"
                                        type="file"
                                        accept="image/*"
                                        onChange={(event) => setAvatarFile(event.target.files?.[0] || null)}
                                        className="mt-2 block w-full text-sm text-slate-600"
                                    />
                                    <p className="mt-1 text-xs text-slate-500">PNG/JPG/WEBP, max 2MB.</p>
                                    {avatarUrl ? (
                                        <div className="mt-2 h-16 w-16 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                            <img src={avatarUrl} alt={fields?.name || 'Customer logo'} className="h-full w-full object-cover" />
                                        </div>
                                    ) : null}
                                    {errors?.avatar ? <p className="mt-1 text-xs text-rose-500">{errors.avatar}</p> : null}
                                </div>
                                <div className="md:col-span-2">
                                    <label className="text-sm text-slate-600">Address</label>
                                    <textarea
                                        name="address"
                                        rows={2}
                                        defaultValue={fields?.address || ''}
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Notes</label>
                                    <textarea name="notes" rows={2} defaultValue={fields?.notes || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">New Password</label>
                                    <input
                                        name="user_password"
                                        type="password"
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                    />
                                    {errors?.user_password ? <p className="mt-1 text-xs text-rose-500">{errors.user_password}</p> : null}
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Confirm Password</label>
                                    <input
                                        name="user_password_confirmation"
                                        type="password"
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Status</label>
                                    <select name="status" defaultValue={fields?.status || 'active'} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    {errors?.status ? <p className="mt-1 text-xs text-rose-500">{errors.status}</p> : null}
                                </div>
                                <div className="md:col-span-3 flex items-center gap-2 text-sm text-slate-600">
                                    <input type="hidden" name="send_account_message" value="0" />
                                    <input
                                        type="checkbox"
                                        name="send_account_message"
                                        value="1"
                                        defaultChecked={Boolean(fields?.send_account_message)}
                                        className="rounded border-slate-300 text-teal-500"
                                    />
                                    Send a New Account Information Message
                                </div>
                            </>
                        ) : (
                            <>
                                <div>
                                    <label className="text-sm text-slate-600">Address</label>
                                    <textarea
                                        name="address"
                                        rows={2}
                                        defaultValue={fields?.address || ''}
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                    />
                                    {errors?.address ? <p className="mt-1 text-xs text-rose-500">{errors.address}</p> : null}
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Status</label>
                                    <select name="status" defaultValue={fields?.status || 'active'} required className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    <p className="mt-1 text-xs text-slate-500">Status is auto-active when services, projects, or maintenance are active.</p>
                                    {errors?.status ? <p className="mt-1 text-xs text-rose-500">{errors.status}</p> : null}
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Default sales rep</label>
                                    <select
                                        name="default_sales_rep_id"
                                        defaultValue={fields?.default_sales_rep_id || ''}
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                    >
                                        <option value="">None</option>
                                        {sales_reps.map((rep) => (
                                            <option key={rep.id} value={rep.id}>
                                                {rep.name} {rep.status !== 'active' ? `(${String(rep.status).charAt(0).toUpperCase() + String(rep.status).slice(1)})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Access Override Until</label>
                                    <input
                                        name="access_override_until"
                                        type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                        defaultValue={fields?.access_override_until || ''}
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                    />
                                    <p className="mt-1 text-xs text-slate-500">Grant temporary access even if status is inactive</p>
                                    {errors?.access_override_until ? <p className="mt-1 text-xs text-rose-500">{errors.access_override_until}</p> : null}
                                </div>
                            </>
                        )}
                    </div>

                    {is_edit ? (
                        <div>
                            <label className="text-sm text-slate-600">Notes</label>
                            <textarea name="notes" rows={3} defaultValue={fields?.notes || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                            {errors?.notes ? <p className="mt-1 text-xs text-rose-500">{errors.notes}</p> : null}
                        </div>
                    ) : null}

                    <div className="flex items-center gap-3">
                        <button type="submit" className="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">
                            {is_edit ? 'Update customer' : 'Save customer'}
                        </button>
                        <a href={routes?.index} data-native="true" className="text-sm text-slate-600 hover:text-teal-600">
                            Cancel
                        </a>
                    </div>
                </form>

                {is_edit ? (
                    <div className="mt-8 border-t border-slate-300 pt-6">
                        <form
                            method="POST"
                            action={routes?.destroy}
                            data-native="true"
                            onSubmit={(event) => {
                                if (!window.confirm(`Delete customer ${fields?.name || ''}? This will remove related subscriptions and invoices.`)) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            <input type="hidden" name="_token" value={csrf} />
                            <input type="hidden" name="_method" value="DELETE" />
                            <button
                                type="submit"
                                className="rounded-full border border-rose-200 px-5 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300 hover:text-rose-500"
                            >
                                Delete Clients Account
                            </button>
                        </form>
                    </div>
                ) : null}
            </div>
        </>
    );
}
