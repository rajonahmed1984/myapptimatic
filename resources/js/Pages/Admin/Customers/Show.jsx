import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import DatePickerField from '../../../Components/DatePickerField';
import useObjectUrlPreview from '../../../hooks/useObjectUrlPreview';

const statusClass = (status) => {
    const key = String(status || '').toLowerCase();
    if (['active', 'paid', 'accepted', 'open'].includes(key)) return 'bg-emerald-100 text-emerald-700';
    if (['pending', 'draft', 'unpaid', 'overdue'].includes(key)) return 'bg-amber-100 text-amber-700';
    if (['cancelled', 'inactive', 'closed', 'reversed', 'refunded'].includes(key)) return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-600';
};

const asMoney = (amount, currency = 'USD') => `${currency} ${Number(amount || 0).toFixed(2)}`;

const formatCtx = (value) => {
    if (!value) return '--';
    try {
        return JSON.stringify(value);
    } catch {
        return '--';
    }
};

const proration = (plan, startDateValue) => {
    if (!plan) return { subtotal: 0, label: '--', period: '--' };
    const start = new Date(`${startDateValue}T00:00:00`);
    if (Number.isNaN(start.getTime())) return { subtotal: 0, label: '--', period: '--' };

    let end = new Date(start.getTime());
    if (plan.interval === 'monthly') {
        end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
    } else {
        end.setFullYear(end.getFullYear() + 1);
    }

    const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const days = Math.floor((end.getTime() - start.getTime()) / 86400000) + 1;
    const cycleDays = plan.interval === 'monthly' ? new Date(start.getFullYear(), start.getMonth() + 1, 0).getDate() : 365;
    const showProration = plan.interval === 'monthly' && start.getDate() !== 1;
    const subtotal = showProration
        ? Math.round((Number(plan.price || 0) * Math.min(1, days / cycleDays)) * 100) / 100
        : Math.round(Number(plan.price || 0) * 100) / 100;

    return {
        subtotal,
        label: showProration ? `Prorated for ${days}/${cycleDays} days` : '',
        period: `${ymd(start)} to ${ymd(end)}`,
    };
};

const initialsFromName = (name) => {
    const parts = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);
    if (parts.length === 0) return 'CU';
    return parts.map((part) => part.charAt(0).toUpperCase()).join('');
};

export default function Show({
    pageTitle = 'Customer Details',
    tab = 'summary',
    tabs = [],
    customer = {},
    currency = {},
    metrics = {},
    sales_rep_summaries = [],
    subscriptions = [],
    project_clients = [],
    project_options = [],
    projects = [],
    project_maintenances = [],
    project_task_summary = {},
    project_subtask_summary = {},
    project_task_progress = {},
    invoices = [],
    tickets = [],
    email_logs = [],
    activity_logs = [],
    service_plans = [],
    service_sales_reps = [],
    profile_sales_reps = [],
    forms = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const errors = props?.errors || {};

    const serviceDefaults = forms?.service || {};
    const profileDefaults = forms?.profile || {};
    const projectUserDefaults = forms?.project_user || {};

    const [showServiceForm, setShowServiceForm] = useState(Boolean(serviceDefaults?.plan_id || errors?.plan_id));
    const [planId, setPlanId] = useState(String(serviceDefaults?.plan_id || ''));
    const [startDate, setStartDate] = useState(String(serviceDefaults?.start_date || new Date().toISOString().slice(0, 10)));
    const [salesRepId, setSalesRepId] = useState(String(serviceDefaults?.sales_rep_id || ''));
    const [avatarFile, setAvatarFile] = useState(null);
    const [avatarLoadFailed, setAvatarLoadFailed] = useState(false);

    const plan = useMemo(
        () => service_plans.find((item) => String(item.id) === String(planId)) || null,
        [service_plans, planId]
    );
    const summary = useMemo(() => proration(plan, startDate), [plan, startDate]);
    const projectProgressPercent = Math.max(0, Math.min(100, Number(project_task_progress?.percent || 0)));
    const customerInitials = useMemo(() => initialsFromName(customer?.name), [customer?.name]);
    const customerStatusClass = statusClass(customer?.effective_status || customer?.status);
    const avatarPreviewUrl = useObjectUrlPreview(avatarFile, { enabled: String(avatarFile?.type || '').startsWith('image/') });
    const customerAvatarUrl = avatarPreviewUrl || (avatarLoadFailed ? '' : String(customer?.avatar_url || ''));
    const invoiceStatusSummary = Array.isArray(metrics?.invoice_status_summary) ? metrics.invoice_status_summary : [];
    const summaryCards = useMemo(
        () => ([
            { key: 'services', label: 'Products / Services', value: customer?.subscriptions_count || 0, href: `${routes?.show}?tab=services`, tone: 'border-sky-200 bg-sky-50 text-sky-700' },
            { key: 'active-services', label: 'Active Services', value: customer?.active_subscriptions_count || 0, href: `${routes?.show}?tab=services`, tone: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
            { key: 'projects', label: 'Projects', value: customer?.projects_count || 0, href: `${routes?.show}?tab=projects`, tone: 'border-violet-200 bg-violet-50 text-violet-700' },
            { key: 'invoices', label: 'Invoices', value: customer?.invoices_count || 0, href: `${routes?.show}?tab=invoices`, tone: 'border-amber-200 bg-amber-50 text-amber-700' },
            { key: 'tickets', label: 'Tickets', value: customer?.tickets_count || 0, href: `${routes?.show}?tab=tickets`, tone: 'border-rose-200 bg-rose-50 text-rose-700' },
        ]),
        [customer?.invoices_count, customer?.projects_count, customer?.subscriptions_count, customer?.active_subscriptions_count, customer?.tickets_count, routes?.show]
    );
    const financialCards = useMemo(
        () => ([
            { key: 'gross', label: 'Gross Revenue', value: asMoney(metrics?.gross_revenue, currency?.code), hint: 'Total received payments' },
            { key: 'expense', label: 'Client Expenses', value: asMoney(metrics?.client_expenses, currency?.code), hint: 'Expenses tracked against client' },
            { key: 'net', label: 'Net Income', value: asMoney(metrics?.net_income, currency?.code), hint: 'Gross revenue minus expenses' },
            { key: 'credit', label: 'Credit Balance', value: asMoney(metrics?.credit_balance, currency?.code), hint: 'Available credits on account' },
        ]),
        [metrics?.gross_revenue, metrics?.client_expenses, metrics?.net_income, metrics?.credit_balance, currency?.code]
    );

    return (
        <>
            <Head title={pageTitle} />

            <div className="relative mb-6 overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-teal-800 p-6 text-white shadow-sm">
                <div className="pointer-events-none absolute -right-20 -top-20 h-48 w-48 rounded-full bg-white/10" />
                <div className="pointer-events-none absolute -bottom-24 left-1/2 h-56 w-56 -translate-x-1/2 rounded-full bg-teal-300/20 blur-2xl" />
                <div className="relative flex flex-wrap items-center justify-between gap-4">
                    <div className="flex min-w-[260px] items-start gap-4">
                        <div>                            
                            <div className="mt-1 text-2xl font-semibold text-white">{customer?.name || 'Customer'}</div>
                            <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                <span className="rounded-full border border-white/20 bg-white/10 px-2.5 py-1">ID #{customer?.id || '--'}</span>
                                <span className="rounded-full border border-white/20 bg-white/10 px-2.5 py-1">Created {customer?.created_at_display || '--'}</span>
                                <span className={`rounded-full border px-2.5 py-1 font-semibold ${customerStatusClass}`}>
                                    {String(customer?.effective_status || customer?.status || '--').toUpperCase()}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <a href={routes?.index} data-native="true" className="inline-flex h-10 items-center rounded-full border border-white/30 bg-white/10 px-4 text-sm font-semibold text-white transition hover:bg-white/20">Back to Customers</a>
                        <a href={routes?.create_invoice} data-native="true" className="inline-flex h-10 items-center rounded-full border border-white/30 bg-white/10 px-4 text-sm font-semibold text-white transition hover:bg-white/20">Create Invoice</a>
                        <a href={routes?.create_ticket} data-native="true" className="inline-flex h-10 items-center rounded-full border border-white/30 bg-white/10 px-4 text-sm font-semibold text-white transition hover:bg-white/20">Open Ticket</a>
                        <form method="POST" action={routes?.impersonate} data-native="true" className="inline-flex">
                            <input type="hidden" name="_token" value={csrf} />
                            <button type="submit" className="inline-flex h-10 items-center rounded-full border border-white/30 bg-white/10 px-4 text-sm font-semibold text-white transition hover:bg-white/20">Login as client</button>
                        </form>
                    </div>
                </div>
            </div>

            <div className="card overflow-hidden p-6">
                <div className="-mx-2 mb-6 overflow-x-auto px-2">
                    <div className="flex min-w-max gap-3 text-sm font-semibold text-slate-700">
                    {tabs.map((item) => (
                        <a
                            key={item.key}
                            href={item.href}
                            data-native="true"
                            className={`rounded-full border px-4 py-2 transition ${tab === item.key ? 'border-teal-500 bg-teal-50 text-teal-700 shadow-sm shadow-teal-100' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'}`}
                        >
                            {item.label}
                        </a>
                    ))}
                    </div>
                </div>

                {tab === 'summary' ? (
                    <div className="space-y-5">
                        <div className="grid gap-3 md:grid-cols-5">
                            {summaryCards.map((item) => (
                                <a key={item.key} href={item.href} data-native="true" className={`rounded-2xl border p-4 transition hover:-translate-y-0.5 hover:shadow-sm ${item.tone}`}>
                                    <div className="text-[11px] uppercase tracking-[0.2em]">{item.label}</div>
                                    <div className="mt-2 text-3xl font-semibold leading-none">{item.value}</div>
                                </a>
                            ))}
                        </div>

                        <div className="grid gap-4 lg:grid-cols-3">
                            <div className="rounded-2xl border border-slate-300 bg-white p-5 text-sm text-slate-600">
                                <div className="text-xs uppercase tracking-[0.25em] text-slate-400">Customer Snapshot</div>
                                <div className="mt-3 space-y-2">
                                    <div className="flex items-start gap-3"><span className="text-slate-500">Company</span><span className="font-medium text-slate-900">{customer?.company_name || '--'}</span></div>
                                    <div className="flex items-start gap-3"><span className="text-slate-500">Email</span><span className="font-medium text-slate-900">{customer?.email || '--'}</span></div>
                                    <div className="flex items-start gap-3"><span className="text-slate-500">Phone</span><span className="font-medium text-slate-900">{customer?.phone || '--'}</span></div>
                                    <div className="flex items-start gap-3"><span className="text-slate-500">Address</span><span className="max-w-[220px] text-right font-medium text-slate-900">{customer?.address || '--'}</span></div>
                                </div>
                            </div>

                            <div className="rounded-2xl border border-slate-300 bg-white p-5 lg:col-span-2">
                                <div className="text-xs uppercase tracking-[0.25em] text-slate-400">Financial Snapshot</div>
                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                    {financialCards.map((item) => (
                                        <div key={item.key} className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                            <div className="text-xs uppercase tracking-[0.2em] text-slate-500">{item.label}</div>
                                            <div className="mt-1 text-lg font-semibold text-slate-900">{item.value}</div>
                                            <div className="mt-1 text-xs text-slate-500">{item.hint}</div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-slate-300 bg-white p-5">
                            <div className="text-xs uppercase tracking-[0.25em] text-slate-400">Invoice Status Breakdown</div>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                {invoiceStatusSummary.map((item) => (
                                    <div key={item.key} className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                                        <div className="text-xs uppercase tracking-[0.2em] text-slate-500">{item.label}</div>
                                        <div className="mt-1 text-lg font-semibold text-slate-900">{item.count}</div>
                                        <div className="text-xs text-slate-600">{asMoney(item.amount, currency?.code)}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                ) : null}

                {tab === 'profile' ? (
                    <form method="POST" action={routes?.update} encType="multipart/form-data" data-native="true" className="grid gap-4 rounded-2xl border border-slate-300 bg-white p-5 md:grid-cols-3">
                            <input type="hidden" name="_token" value={csrf} />
                            <input type="hidden" name="_method" value="PUT" />
                            <input type="hidden" name="redirect_tab" value="profile" />

                            <div className="md:col-span-3 text-xs uppercase tracking-[0.25em] text-slate-500">Identity</div>
                            <div>
                                <label className="text-sm text-slate-600">Full Name</label>
                                <input name="name" defaultValue={profileDefaults?.name || ''} required className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                                {errors?.name ? <p className="mt-1 text-xs text-rose-500">{errors.name}</p> : null}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Company Name</label>
                                <input name="company_name" defaultValue={profileDefaults?.company_name || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                                {errors?.company_name ? <p className="mt-1 text-xs text-rose-500">{errors.company_name}</p> : null}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Email Address</label>
                                <input name="email" type="email" defaultValue={profileDefaults?.email || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                                {errors?.email ? <p className="mt-1 text-xs text-rose-500">{errors.email}</p> : null}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Phone Number</label>
                                <input name="phone" defaultValue={profileDefaults?.phone || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                                {errors?.phone ? <p className="mt-1 text-xs text-rose-500">{errors.phone}</p> : null}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Default Sales Rep</label>
                                <select name="default_sales_rep_id" defaultValue={profileDefaults?.default_sales_rep_id || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                                    <option value="">None</option>
                                    {profile_sales_reps.map((rep) => (
                                        <option key={rep.id} value={rep.id}>
                                            {rep.name} {rep.status !== 'active' ? `(${String(rep.status).charAt(0).toUpperCase() + String(rep.status).slice(1)})` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors?.default_sales_rep_id ? <p className="mt-1 text-xs text-rose-500">{errors.default_sales_rep_id}</p> : null}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Profile Image</label>
                                <input
                                    name="avatar"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp,image/*"
                                    onChange={(event) => {
                                        setAvatarFile(event.target.files?.[0] || null);
                                        setAvatarLoadFailed(false);
                                    }}
                                    className="mt-2 block w-full text-sm text-slate-600"
                                />
                                <p className="mt-1 text-xs text-slate-500">PNG/JPG/WEBP, max 2MB.</p>
                                <div className="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-2">
                                    <div className="text-[11px] uppercase tracking-[0.2em] text-slate-500">
                                        {avatarPreviewUrl ? 'Selected image preview' : 'Current logo'}
                                    </div>
                                    <div className="mt-2">
                                        {customerAvatarUrl ? (
                                            <img
                                                src={customerAvatarUrl}
                                                alt={customer?.name || 'Customer avatar'}
                                                onError={() => setAvatarLoadFailed(true)}
                                                className="h-16 w-16 rounded-xl object-cover"
                                            />
                                        ) : (
                                            <div className="flex h-16 w-16 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white text-[11px] font-semibold text-slate-500">
                                                {customerInitials}
                                            </div>
                                        )}
                                    </div>
                                </div>
                                {errors?.avatar ? <p className="mt-1 text-xs text-rose-500">{errors.avatar}</p> : null}
                            </div>
                            <div className="md:col-span-2">
                                <label className="text-sm text-slate-600">Address</label>
                                <textarea name="address" rows={1} defaultValue={profileDefaults?.address || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                                {errors?.address ? <p className="mt-1 text-xs text-rose-500">{errors.address}</p> : null}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Notes</label>
                                <textarea name="notes" rows={1} defaultValue={profileDefaults?.notes || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                                {errors?.notes ? <p className="mt-1 text-xs text-rose-500">{errors.notes}</p> : null}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">New Password</label>
                                <input
                                    name="client_password"
                                    type="password"
                                    autoComplete="new-password"
                                    className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                />
                                <p className="mt-1 text-xs text-slate-500">Leave blank if you do not want to change password.</p>
                                {errors?.client_password ? <p className="mt-1 text-xs text-rose-500">{errors.client_password}</p> : null}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Confirm Password</label>
                                <input
                                    name="client_password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Status</label>
                                <select name="status" defaultValue={profileDefaults?.status || 'active'} required className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                {errors?.status ? <p className="mt-1 text-xs text-rose-500">{errors.status}</p> : null}
                            </div>

                            <div className="md:col-span-3 flex items-center justify-end gap-3">
                                <a href={routes?.edit} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600">
                                    Open full edit page
                                </a>
                                <button type="submit" className="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">
                                    Update customer
                                </button>
                            </div>
                    </form>
                ) : null}

                {tab === 'project-specific' ? (
                    <div className="space-y-4">
                        <div className="overflow-x-auto rounded-2xl border border-slate-300">
                            <table className="w-full min-w-[800px] text-left text-sm">
                                <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <tr><th className="px-4 py-3">Name</th><th className="px-4 py-3">Email</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Project</th><th className="px-4 py-3 text-right">Actions</th></tr>
                                </thead>
                                <tbody>
                                    {project_clients.length === 0 ? <tr><td colSpan={5} className="px-4 py-6 text-center text-slate-500">No project-specific logins yet.</td></tr> : project_clients.map((row) => (
                                        <tr key={row.id} className="border-b border-slate-100">
                                            <td className="px-4 py-3">{row.name}</td>
                                            <td className="px-4 py-3">{row.email}</td>
                                            <td className="px-4 py-3"><span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(row.status)}`}>{row.status}</span></td>
                                            <td className="px-4 py-3">{row.project_name || '--'}</td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex items-center gap-3">
                                                    <form
                                                        method="POST"
                                                        action={row.routes?.status}
                                                        data-native="true"
                                                        className="inline"
                                                        onSubmit={(event) => {
                                                            const nextStatus = row.status === 'active' ? 'inactive' : 'active';
                                                            if (!window.confirm(`Set this user as ${nextStatus}?`)) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        <input type="hidden" name="_token" value={csrf} />
                                                        <input type="hidden" name="_method" value="PATCH" />
                                                        <input type="hidden" name="status" value={row.status === 'active' ? 'inactive' : 'active'} />
                                                        <button
                                                            type="submit"
                                                            className={row.status === 'active' ? 'text-amber-600 hover:text-amber-500' : 'text-emerald-600 hover:text-emerald-500'}
                                                        >
                                                            {row.status === 'active' ? 'Make Inactive' : 'Make Active'}
                                                        </button>
                                                    </form>
                                                    <form
                                                        method="POST"
                                                        action={row.routes?.destroy}
                                                        data-native="true"
                                                        className="inline"
                                                        onSubmit={(event) => {
                                                            if (!window.confirm(`Delete project login ${row.email}?`)) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        <input type="hidden" name="_token" value={csrf} />
                                                        <input type="hidden" name="_method" value="DELETE" />
                                                        <button type="submit" className="text-rose-600 hover:text-rose-500">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <form method="POST" action={routes?.project_user_store} data-native="true" className="grid gap-4 rounded-2xl border border-slate-300 bg-white p-4 md:grid-cols-2 text-sm">
                            <input type="hidden" name="_token" value={csrf} />
                            <div>
                                <label className="text-slate-600">Project</label>
                                <select name="project_id" defaultValue={projectUserDefaults?.project_id || ''} className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2">
                                    <option value="">Select a project</option>
                                    {project_options.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}
                                </select>
                            </div>
                            <div><label className="text-slate-600">Name</label><input name="name" defaultValue={projectUserDefaults?.name || ''} className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2" /></div>
                            <div><label className="text-slate-600">Email</label><input name="email" type="email" defaultValue={projectUserDefaults?.email || ''} className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2" /></div>
                            <div><label className="text-slate-600">Password</label><input name="password" type="password" className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2" /></div>
                            <div className="md:col-span-2"><label className="text-slate-600">Confirm Password</label><input name="password_confirmation" type="password" className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2" /></div>
                            <div className="md:col-span-2 flex justify-end"><button type="submit" className="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Create project login</button></div>
                        </form>
                    </div>
                ) : null}

                {tab === 'services' ? (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-slate-500">Add a product/service for this customer with prorated checkout preview.</p>
                            <button type="button" onClick={() => setShowServiceForm((prev) => !prev)} className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">Add product/service for customer</button>
                        </div>

                        {showServiceForm ? (
                            <form method="POST" action={routes?.service_store} data-native="true" className="mt-4 grid gap-4 rounded-2xl border border-slate-300 bg-white/90 p-4 md:grid-cols-2">
                                <input type="hidden" name="_token" value={csrf} />
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">Product / Service Plan</label>
                                    <select name="plan_id" value={planId} onChange={(event) => setPlanId(event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                        <option value="">Select a plan</option>
                                        {service_plans.map((item) => <option key={item.id} value={item.id}>{item.product_name} - {item.name} ({item.interval})</option>)}
                                    </select>
                                </div>
                                <div>
                                    <DatePickerField
                                        name="start_date"
                                        value={startDate}
                                        onChange={(nextValue) => setStartDate(nextValue)}
                                        submitFormat="iso"
                                        label="Start Date"
                                        labelClassName="mb-1 block text-sm font-medium text-slate-700"
                                        inputClassName="w-full rounded-lg border border-slate-300 px-3 py-2"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">Sales Rep</label>
                                    <select name="sales_rep_id" value={salesRepId} onChange={(event) => setSalesRepId(event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                        <option value="">None</option>
                                        {service_sales_reps.map((rep) => <option key={rep.id} value={rep.id}>{rep.name} ({rep.status})</option>)}
                                    </select>
                                </div>
                                {salesRepId ? (
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-slate-700">Sales Rep Commission</label>
                                        <input name="sales_rep_commission_amount" type="number" min="0" step="0.01" defaultValue={serviceDefaults?.sales_rep_commission_amount || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                                    </div>
                                ) : null}
                                <div className="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="text-xs uppercase tracking-[0.25em] text-slate-500">Summary</div>
                                        <div className="font-semibold text-slate-900">{plan ? `${plan.product_name} - ${plan.name}` : '--'}</div>
                                    </div>
                                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                        <div>Billing period: <span className="font-semibold text-slate-900">{summary.period}</span></div>
                                        <div>Subtotal: <span className="font-semibold text-slate-900">{asMoney(summary.subtotal, currency?.code)}</span></div>
                                        <div className="text-xs text-slate-500">{summary.label}</div>
                                    </div>
                                </div>
                                <div className="md:col-span-2 flex items-center justify-end gap-3">
                                    <button type="button" onClick={() => setShowServiceForm(false)} className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600">Cancel</button>
                                    <button type="submit" className="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Add Product/Service</button>
                                </div>
                            </form>
                        ) : null}

                        <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-300">
                            <table className="w-full min-w-[800px] text-left text-sm">
                                <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                                    <tr><th className="px-4 py-3">SL</th><th className="px-4 py-3">Product &amp; Plan</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Order number &amp; Date</th><th className="px-4 py-3">Next invoice</th><th className="px-4 py-3">Period end</th><th className="px-4 py-3 text-right">Actions</th></tr>
                                </thead>
                                <tbody>
                                    {subscriptions.length === 0 ? <tr><td colSpan={7} className="px-4 py-6 text-center text-slate-500">No services yet.</td></tr> : subscriptions.map((item, idx) => (
                                        <tr key={item.id} className="border-b border-slate-100">
                                            <td className="px-4 py-3 text-slate-500">{idx + 1}</td>
                                            <td className="px-4 py-3 text-slate-900">{item.product_name} &gt; {item.plan_name}</td>
                                            <td className="px-4 py-3"><span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(item.status)}`}>{item.status_label || '--'}</span></td>
                                            <td className="px-4 py-3 text-slate-500">{item.order_number} - {item.order_date_display}</td>
                                            <td className="px-4 py-3 text-slate-500">{item.next_invoice_display}</td>
                                            <td className="px-4 py-3 text-slate-500">{item.period_end_display}</td>
                                            <td className="px-4 py-3 text-right"><a href={item.manage_url} data-native="true" className="text-teal-600 hover:text-teal-500">Manage</a></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                ) : null}

                {tab === 'projects' ? (
                    <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="rounded-2xl border border-slate-300 bg-white/70 p-4">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Project Tasks</div>
                                <div className="mt-2 text-3xl font-semibold text-slate-900">{project_task_summary?.total || 0}</div>
                                <div className="mt-3 flex flex-wrap gap-2 text-xs">
                                    <span className="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-slate-600">Projects: {project_task_summary?.projects || projects.length}</span>
                                    <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">Pending: {project_task_summary?.pending || 0}</span>
                                    <span className="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-blue-700">In progress: {project_task_summary?.in_progress || 0}</span>
                                    <span className="rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-slate-600">Blocked: {project_task_summary?.blocked || 0}</span>
                                    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700">Completed: {project_task_summary?.completed || 0}</span>
                                </div>
                            </div>
                            <div className="rounded-2xl border border-slate-300 bg-white/70 p-4">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Subtasks</div>
                                <div className="mt-2 text-3xl font-semibold text-slate-900">{project_subtask_summary?.total || 0}</div>
                                <div className="mt-3 flex flex-wrap gap-2 text-xs">
                                    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700">Completed: {project_subtask_summary?.completed || 0}</span>
                                    <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">Pending: {project_subtask_summary?.pending || 0}</span>
                                </div>
                            </div>
                            <div className="rounded-2xl border border-slate-300 bg-white/70 p-4">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Task Progress</div>
                                <div className="mt-2 text-3xl font-semibold text-slate-900">{projectProgressPercent}%</div>
                                <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200">
                                    <div className="h-full rounded-full bg-emerald-500" style={{ width: `${projectProgressPercent}%` }} />
                                </div>
                                <div className="mt-2 text-xs text-slate-500">Based on completed tasks</div>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="text-sm text-slate-600">Projects: {projects.length}</div>
                            <a
                                href={routes?.create_project}
                                data-native="true"
                                className="inline-flex items-center rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 transition hover:border-teal-300 hover:bg-teal-50"
                            >
                                Create Project
                            </a>
                        </div>
                        <div className="overflow-x-auto rounded-2xl border border-slate-300">
                            <table className="w-full min-w-[980px] text-left text-sm">
                                <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <tr><th className="px-4 py-3">Project</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Budget</th><th className="px-4 py-3">Paid</th><th className="px-4 py-3">Due</th><th className="px-4 py-3 text-right">Actions</th></tr>
                                </thead>
                                <tbody>
                                    {projects.length === 0 ? <tr><td colSpan={6} className="px-4 py-6 text-center text-slate-500">No projects yet.</td></tr> : projects.map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100">
                                            <td className="px-4 py-3"><a href={item.show_url} data-native="true" className="text-teal-700 hover:text-teal-500">{item.name}</a></td>
                                            <td className="px-4 py-3"><span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(item.status)}`}>{item.status}</span></td>
                                            <td className="px-4 py-3">{asMoney(item.total_budget, item.currency)}</td>
                                            <td className="px-4 py-3">{asMoney(item.paid_amount, item.currency)}</td>
                                            <td className="px-4 py-3">{asMoney(item.due_amount, item.currency)}</td>
                                            <td className="px-4 py-3 text-right"><a href={item.edit_url} data-native="true" className="text-slate-600 hover:text-teal-600">Edit</a></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="text-sm text-slate-600">Maintenance: {project_maintenances.length}</div>
                            <a
                                href={routes?.create_maintenance}
                                data-native="true"
                                className="inline-flex items-center rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 transition hover:border-teal-300 hover:bg-teal-50"
                            >
                                Create Maintenance
                            </a>
                        </div>
                        <div className="overflow-x-auto rounded-2xl border border-slate-300">
                            <table className="w-full min-w-[900px] text-left text-sm">
                                <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <tr><th className="px-4 py-3">Title</th><th className="px-4 py-3">Project</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Billing cycle</th><th className="px-4 py-3">Amount</th><th className="px-4 py-3">Next billing</th><th className="px-4 py-3">Invoices</th><th className="px-4 py-3 text-right">Actions</th></tr>
                                </thead>
                                <tbody>
                                    {project_maintenances.length === 0 ? <tr><td colSpan={8} className="px-4 py-6 text-center text-slate-500">No maintenance records yet.</td></tr> : project_maintenances.map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100">
                                            <td className="px-4 py-3 text-slate-900">{item.title}</td>
                                            <td className="px-4 py-3 text-slate-600">{item.project_name || '--'}</td>
                                            <td className="px-4 py-3"><span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(item.status)}`}>{item.status}</span></td>
                                            <td className="px-4 py-3 text-slate-600">{item.billing_cycle || '--'}</td>
                                            <td className="px-4 py-3 text-slate-700">{asMoney(item.amount, item.currency || currency?.code)}</td>
                                            <td className="px-4 py-3 text-slate-600">{item.next_billing_date_display || '--'}</td>
                                            <td className="px-4 py-3 text-slate-600">{item.invoices_count ?? 0}</td>
                                            <td className="px-4 py-3 text-right">
                                                <a href={item.show_url} data-native="true" className="text-teal-600 hover:text-teal-500">View</a>
                                                <span className="px-1 text-slate-300">|</span>
                                                <a href={item.edit_url} data-native="true" className="text-slate-600 hover:text-teal-600">Edit</a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                {tab === 'invoices' ? (
                    <div className="overflow-x-auto rounded-2xl border border-slate-300">
                        <table className="w-full min-w-[800px] text-left text-sm">
                            <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">ID</th>
                                    <th className="px-4 py-3">Details</th>
                                    <th className="px-4 py-3">Total</th>
                                    <th className="px-4 py-3">Paid date</th>
                                    <th className="px-4 py-3">Due date</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.length === 0 ? (
                                    <tr><td colSpan={7} className="px-4 py-6 text-center text-slate-500">No records found.</td></tr>
                                ) : invoices.map((row) => (
                                    <tr key={row.id} className="border-b border-slate-100">
                                        <td className="px-4 py-3 text-slate-700">
                                            {row.show_url ? (
                                                <a href={row.show_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                    {row.id || '--'}
                                                </a>
                                            ) : (
                                                row.id || '--'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{row.details_display || '--'}</td>
                                        <td className="px-4 py-3 text-slate-700">{asMoney(row.total, row.currency || currency?.code)}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.paid_date_display || '--'}</td>
                                        <td className="px-4 py-3 text-slate-700">{row.due_date_display || '--'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(row.status)}`}>{row.status_label || '--'}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="inline-flex items-center gap-2">
                                                <a href={row.edit_url || row.show_url} data-native="true" className="text-teal-600 hover:text-teal-500">Edit</a>
                                                <span className="text-slate-300">|</span>
                                                <form
                                                    method="POST"
                                                    action={row.destroy_url}
                                                    data-native="true"
                                                    onSubmit={(event) => {
                                                        if (!window.confirm(`Delete invoice #${row.number || row.id}?`)) {
                                                            event.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    <input type="hidden" name="_token" value={csrf} />
                                                    <input type="hidden" name="_method" value="DELETE" />
                                                    <button type="submit" className="text-rose-600 hover:text-rose-500">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : null}
                {tab === 'tickets' ? <ListTable rows={tickets} columns={['subject', 'status_label', 'last_reply_display']} actionKey="show_url" actionText="View" /> : null}

                {tab === 'emails' ? (
                    <div className="overflow-x-auto rounded-2xl border border-slate-300 bg-white">
                        {email_logs.length === 0 ? <div className="px-4 py-6 text-sm text-slate-500">No emails sent to this client yet.</div> : (
                            <table className="w-full min-w-[700px] text-left text-sm">
                                <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500"><tr><th className="px-4 py-3">Date</th><th className="px-4 py-3">Subject</th><th className="px-4 py-3 text-right">Actions</th></tr></thead>
                                <tbody>
                                    {email_logs.map((log) => (
                                        <tr key={log.id} className="border-b border-slate-100">
                                            <td className="px-4 py-3 text-slate-500">{log.created_at_display}</td>
                                            <td className="px-4 py-3 text-slate-700">{log.subject}</td>
                                            <td className="px-4 py-3 text-right">
                                                <form method="POST" action={log.resend_url} data-native="true" className="inline mr-2"><input type="hidden" name="_token" value={csrf} /><button type="submit" className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600">Resend</button></form>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                ) : null}

                {tab === 'log' ? (
                    <div className="overflow-x-auto rounded-2xl border border-slate-300 bg-white">
                        {activity_logs.length === 0 ? <div className="px-4 py-6 text-sm text-slate-500">No activity recorded yet.</div> : (
                            <table className="w-full min-w-[700px] text-left text-sm">
                                <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500"><tr><th className="px-4 py-3">Date</th><th className="px-4 py-3">Category</th><th className="px-4 py-3">Level</th><th className="px-4 py-3">Message</th></tr></thead>
                                <tbody>
                                    {activity_logs.map((log) => (
                                        <tr key={log.id} className="border-b border-slate-100">
                                            <td className="px-4 py-3 text-slate-500">{log.created_at_display}</td>
                                            <td className="px-4 py-3 text-slate-600">{log.category}</td>
                                            <td className="px-4 py-3"><span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(log.level)}`}>{log.level}</span></td>
                                            <td className="px-4 py-3 text-slate-600"><div className="font-semibold text-slate-800">{log.message}</div><div className="mt-1 text-xs text-slate-500">{formatCtx(log.context)}</div></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                ) : null}
            </div>
        </>
    );
}

function ListTable({ rows = [], columns = [], actionKey = null, actionText = 'View' }) {
    return (
        <div className="overflow-x-auto rounded-2xl border border-slate-300">
            <table className="w-full min-w-[800px] text-left text-sm">
                <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        {columns.map((column) => <th key={column} className="px-4 py-3">{column.replaceAll('_', ' ')}</th>)}
                        {actionKey ? <th className="px-4 py-3 text-right">Actions</th> : null}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr><td colSpan={columns.length + (actionKey ? 1 : 0)} className="px-4 py-6 text-center text-slate-500">No records found.</td></tr>
                    ) : rows.map((row) => (
                        <tr key={row.id || JSON.stringify(row)} className="border-b border-slate-100">
                            {columns.map((column) => <td key={column} className="px-4 py-3 text-slate-700">{row[column] || '--'}</td>)}
                            {actionKey ? <td className="px-4 py-3 text-right"><a href={row[actionKey]} data-native="true" className="text-teal-600 hover:text-teal-500">{actionText}</a></td> : null}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
