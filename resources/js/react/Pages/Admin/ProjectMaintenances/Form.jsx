import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusBadgeClass = (status) => {
    if (status === 'active') {
        return 'text-emerald-700';
    }

    if (status === 'paused') {
        return 'text-amber-700';
    }

    return 'text-slate-700';
};

export default function Form({
    pageTitle = 'Maintenance',
    is_edit = false,
    maintenance = null,
    projects = [],
    sales_reps = [],
    invoices = [],
    form = {},
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};

    const [selectedProjectId, setSelectedProjectId] = React.useState(String(fields?.project_id || ''));
    const selectedProject = projects.find((project) => String(project.id) === selectedProjectId) || null;
    const selectedSalesRepIds = new Set((fields?.selected_sales_rep_ids || []).map((id) => String(id)));

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Projects</div>
                    <div className="text-2xl font-semibold text-slate-900">{is_edit ? 'Edit maintenance' : 'Create maintenance'}</div>
                    <div className="text-sm text-slate-500">
                        {is_edit ? 'Update plan details or pause/cancel billing.' : 'Set up recurring billing for a project.'}
                    </div>
                </div>
                <a
                    href={routes?.index}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                >
                    Back to maintenance
                </a>
            </div>

            {is_edit && maintenance ? (
                <div className="mb-6 grid gap-4 md:grid-cols-3">
                    <div className="card p-4">
                        <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Status</div>
                        <div className={`mt-2 text-2xl font-semibold ${statusBadgeClass(maintenance.status)}`}>{maintenance.status_label}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Next Billing</div>
                        <div className="mt-2 text-2xl font-semibold text-slate-900">{maintenance.next_billing_date}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Amount</div>
                        <div className="mt-2 text-2xl font-semibold text-slate-900">{maintenance.amount_display}</div>
                    </div>
                </div>
            ) : null}

            <div className={`grid gap-4 ${is_edit ? 'md:grid-cols-3' : ''}`}>
                <div className={`card p-6 ${is_edit ? 'md:col-span-2' : ''}`}>
                    <form method="POST" action={form?.action} data-native="true" className="space-y-4">
                        <input type="hidden" name="_token" value={csrf} />
                        {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                            <input type="hidden" name="_method" value={form?.method} />
                        ) : null}

                        {is_edit ? (
                            <div>
                                <label className="text-xs text-slate-500">Project</label>
                                <div className="mt-1 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    {maintenance?.project_name || '--'} ({maintenance?.customer_name || 'No customer'})
                                </div>
                            </div>
                        ) : (
                            <div>
                                <label className="text-xs text-slate-500">Project</label>
                                <select
                                    name="project_id"
                                    defaultValue={fields?.project_id || ''}
                                    onChange={(event) => setSelectedProjectId(event.target.value)}
                                    required
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="">Select project</option>
                                    {projects.map((project) => (
                                        <option key={project.id} value={project.id}>
                                            {project.name} ({project.customer_name})
                                        </option>
                                    ))}
                                </select>
                                {errors?.project_id ? <p className="mt-1 text-xs text-rose-600">{errors.project_id}</p> : null}
                                <div className="mt-2 text-xs text-slate-500">
                                    Currency: <span>{selectedProject?.currency || '--'}</span>
                                </div>
                            </div>
                        )}

                        <div>
                            <label className="text-xs text-slate-500">Title</label>
                            <input
                                name="title"
                                defaultValue={fields?.title || ''}
                                required
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                placeholder="Annual Hosting & Support"
                            />
                            {errors?.title ? <p className="mt-1 text-xs text-rose-600">{errors.title}</p> : null}
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Amount</label>
                                <input
                                    name="amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    defaultValue={fields?.amount || ''}
                                    required
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                                {errors?.amount ? <p className="mt-1 text-xs text-rose-600">{errors.amount}</p> : null}
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Billing cycle</label>
                                <select
                                    name="billing_cycle"
                                    defaultValue={fields?.billing_cycle || 'monthly'}
                                    required
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                                {errors?.billing_cycle ? <p className="mt-1 text-xs text-rose-600">{errors.billing_cycle}</p> : null}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Sales representatives</label>
                                <div className="mt-2 space-y-2 rounded-2xl border border-slate-300 bg-white/80 p-3">
                                    {sales_reps.length === 0 ? (
                                        <div className="text-xs text-slate-500">No active sales representatives found.</div>
                                    ) : (
                                        sales_reps.map((rep) => (
                                            <div key={rep.id} className="flex flex-wrap items-center justify-between gap-3">
                                                <label className="flex items-center gap-2 text-xs text-slate-600">
                                                    <input
                                                        type="checkbox"
                                                        name="sales_rep_ids[]"
                                                        value={rep.id}
                                                        defaultChecked={selectedSalesRepIds.has(String(rep.id))}
                                                    />
                                                    <span>
                                                        {rep.name} ({rep.email})
                                                    </span>
                                                </label>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs text-slate-500">Amount</span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        name={`sales_rep_amounts[${rep.id}]`}
                                                        defaultValue={fields?.sales_rep_amounts?.[rep.id] ?? '0'}
                                                        className="w-28 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs"
                                                    />
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                                {errors?.sales_rep_ids ? <p className="mt-1 text-xs text-rose-600">{errors.sales_rep_ids}</p> : null}
                                <p className="mt-1 text-xs text-slate-500">Amounts apply only to selected sales reps.</p>
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Status</label>
                                <select
                                    name="status"
                                    defaultValue={fields?.status || 'active'}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="active">Active</option>
                                    <option value="paused">Paused</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                {errors?.status ? <p className="mt-1 text-xs text-rose-600">{errors.status}</p> : null}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Start date</label>
                                <input
                                    name="start_date"
                                    type="date"
                                    defaultValue={fields?.start_date || ''}
                                    required
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                                {errors?.start_date ? <p className="mt-1 text-xs text-rose-600">{errors.start_date}</p> : null}
                            </div>
                            <div className="flex items-end gap-6">
                                <label className="flex cursor-pointer items-center gap-2 text-xs text-slate-600">
                                    <input type="hidden" name="auto_invoice" value="0" />
                                    <input type="checkbox" name="auto_invoice" value="1" defaultChecked={Boolean(fields?.auto_invoice)} />
                                    <span>Auto-generate invoice</span>
                                </label>
                                <label className="flex cursor-pointer items-center gap-2 text-xs text-slate-600">
                                    <input type="hidden" name="sales_rep_visible" value="0" />
                                    <input type="checkbox" name="sales_rep_visible" value="1" defaultChecked={Boolean(fields?.sales_rep_visible)} />
                                    <span>Visible to sales reps</span>
                                </label>
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-2">
                            <a
                                href={routes?.index}
                                data-native="true"
                                className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                            >
                                Cancel
                            </a>
                            <button type="submit" className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                {is_edit ? 'Save changes' : 'Create maintenance'}
                            </button>
                        </div>
                    </form>
                </div>

                {is_edit ? (
                    <div className="card p-6">
                        <div className="mb-3 text-sm font-semibold text-slate-800">Invoice History</div>
                        {invoices.length === 0 ? (
                            <div className="text-sm text-slate-600">No maintenance invoices yet.</div>
                        ) : (
                            <ul className="space-y-2 text-sm text-slate-700">
                                {invoices.map((invoice) => (
                                    <li key={invoice.id}>
                                        <a href={invoice.routes?.show} data-native="true" className="text-teal-700 hover:text-teal-600">
                                            #{invoice.number}
                                        </a>
                                        <div className="text-xs text-slate-500">{invoice.issue_date}</div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                ) : null}
            </div>
        </>
    );
}
