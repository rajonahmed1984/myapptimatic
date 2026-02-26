import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Show({
    pageTitle = 'Sales Representative',
    rep,
    tab = 'profile',
    tabs = [],
    summary = {},
    recentEarnings = [],
    recentPayouts = [],
    subscriptions = [],
    invoiceEarnings = [],
    projects = [],
    emailLogs = [],
    activityLogs = [],
    advanceProjects = [],
    paymentMethods = [],
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    const tabHref = (key) => `${routes?.show_tab}?tab=${encodeURIComponent(key)}`;

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Sales Representative</div>
                    <div className="text-sm text-slate-500">{rep?.email || 'No email on file'}</div>
                </div>
                <div className="flex flex-wrap gap-3">
                    <form method="POST" action={routes?.impersonate} data-native="true">
                        <input type="hidden" name="_token" value={csrf} />
                        <button type="submit" className="rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700">
                            Login as Sales Representative
                        </button>
                    </form>
                    <a href={routes?.edit} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        Edit
                    </a>
                    <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        Back to list
                    </a>
                </div>
            </div>

            <div className="mb-6 flex flex-wrap gap-3 text-sm font-semibold text-slate-700">
                {tabs.map((item) => (
                    <a
                        key={item.key}
                        href={tabHref(item.key)}
                        data-native="true"
                        className={`rounded-full border px-3 py-1 ${tab === item.key ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-300 text-slate-700'}`}
                    >
                        {item.label}
                    </a>
                ))}
            </div>

            {tab === 'profile' ? (
                <>
                    <div className="grid gap-4 md:grid-cols-4">
                        <Metric title="Total Earned" value={money(summary?.total_earned)} note={`Projects: ${money(summary?.project_earned)} | Maintenances: ${money(summary?.maintenance_earned)}`} />
                        <Metric title="Payable (Net)" value={money(summary?.payable)} note={summary?.payable_label} />
                        <Metric title="Paid (Incl. Advance)" value={money(summary?.paid)} note="" />
                        <Metric title="Advance Paid" value={money(summary?.advance_paid)} note={Number(summary?.overpaid || 0) > 0 ? `Overpaid: ${money(summary?.overpaid)}` : ''} />
                    </div>

                    <div className="mt-6 grid gap-4 md:grid-cols-2">
                        <div className="card p-4">
                            <div className="mb-3 text-sm font-semibold text-slate-800">Profile</div>
                            <dl className="grid grid-cols-2 gap-3 text-sm text-slate-700">
                                <div>
                                    <dt className="text-xs uppercase tracking-[0.2em] text-slate-500">Status</dt>
                                    <dd className="mt-1">{rep?.status_label}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-[0.2em] text-slate-500">User</dt>
                                    <dd className="mt-1">{rep?.user_name || '--'} <span className="text-slate-500">{rep?.user_email || ''}</span></dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-[0.2em] text-slate-500">Employee</dt>
                                    <dd className="mt-1">{rep?.employee_name || 'Not linked'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-[0.2em] text-slate-500">Phone</dt>
                                    <dd className="mt-1">{rep?.phone || '--'}</dd>
                                </div>
                            </dl>
                        </div>

                        <div className="card p-4">
                            <div className="mb-3 text-sm font-semibold text-slate-800">Documents</div>
                            <div className="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
                                <div>
                                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Avatar</div>
                                    <div className="mt-2">
                                        {rep?.avatar_url ? <img src={rep.avatar_url} alt={rep?.name || 'Avatar'} className="h-16 w-16 rounded-full object-cover" /> : <div className="h-16 w-16 rounded-full bg-slate-100" />}
                                    </div>
                                </div>
                                {rep?.nid_url ? <div><div className="text-xs uppercase tracking-[0.2em] text-slate-500">NID</div><a href={rep.nid_url} className="mt-2 inline-flex text-sm text-teal-600">View/Download</a></div> : null}
                                {rep?.cv_url ? <div><div className="text-xs uppercase tracking-[0.2em] text-slate-500">CV</div><a href={rep.cv_url} className="mt-2 inline-flex text-sm text-teal-600">View/Download</a></div> : null}
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 card p-4">
                        <div className="text-sm font-semibold text-slate-800">Record advance payment</div>
                        <div className="text-xs text-slate-500">Advance payments are deducted from future commissions.</div>
                        <form method="POST" action={routes?.advance_payment} data-native="true" className="mt-3 grid gap-3 md:grid-cols-8">
                            <input type="hidden" name="_token" value={csrf} />
                            <div className="md:col-span-2">
                                <label className="text-xs text-slate-500">Project</label>
                                <select name="project_id" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                    <option value="">Select project</option>
                                    {advanceProjects.map((project) => (
                                        <option key={project.id} value={project.id}>{project.name}{project.customer_name ? ` (${project.customer_name})` : ''}</option>
                                    ))}
                                </select>
                            </div>
                            <div><label className="text-xs text-slate-500">Amount</label><input name="amount" type="number" step="0.01" min="0" required className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                            <div><label className="text-xs text-slate-500">Currency</label><input name="currency" defaultValue="BDT" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                            <div>
                                <label className="text-xs text-slate-500">Method</label>
                                <select name="payout_method" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                    <option value="">Select</option>
                                    {paymentMethods.map((method) => <option key={method.code} value={method.code}>{method.name}</option>)}
                                </select>
                            </div>
                            <div><label className="text-xs text-slate-500">Reference</label><input name="reference" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                            <div className="md:col-span-8"><label className="text-xs text-slate-500">Note</label><input name="note" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                            <div className="md:col-span-8"><button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Save advance payment</button></div>
                        </form>
                    </div>
                </>
            ) : null}

            {tab === 'services' ? <SimpleTable title="Products / Services" rows={subscriptions.map((s) => [String(s.id), s.customer_name, s.plan_name, s.status, s.next_invoice_at])} headers={['Subscription', 'Customer', 'Plan', 'Status', 'Next Invoice']} empty="No linked products or services for this rep." /> : null}

            {tab === 'invoices' ? <InvoiceTable rows={invoiceEarnings} /> : null}

            {tab === 'earnings' ? <EarningsTable rows={recentEarnings} summary={summary} route={routes?.commission_payout_create} /> : null}

            {tab === 'payouts' ? <PayoutsTable rows={recentPayouts} /> : null}

            {tab === 'projects' ? <ProjectsTable rows={projects} /> : null}

            {tab === 'emails' ? <EmailsTable rows={emailLogs} /> : null}
            {tab === 'log' ? <ActivityLogTable rows={activityLogs} /> : null}
        </>
    );
}

function Metric({ title, value, note }) {
    return <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">{title}</div><div className="mt-2 text-2xl font-semibold text-slate-900">{value}</div>{note ? <div className="text-xs text-slate-500">{note}</div> : null}</div>;
}

function SimpleTable({ title, headers, rows, empty }) {
    return (
        <div className="card p-6">
            <div className="mb-3 text-sm font-semibold text-slate-800">{title}</div>
            {rows.length === 0 ? <div className="text-sm text-slate-600">{empty}</div> : <div className="overflow-x-auto"><table className="min-w-full text-left text-sm"><thead className="text-xs uppercase tracking-[0.2em] text-slate-500"><tr>{headers.map((h) => <th key={h} className="px-3 py-2">{h}</th>)}</tr></thead><tbody>{rows.map((r, i) => <tr key={i} className="border-t border-slate-100">{r.map((c, idx) => <td key={idx} className="px-3 py-2">{c}</td>)}</tr>)}</tbody></table></div>}
        </div>
    );
}

function InvoiceTable({ rows }) {
    return (
        <div className="card p-6">
            <div className="mb-3 text-sm font-semibold text-slate-800">Invoices</div>
            {rows.length === 0 ? <div className="text-sm text-slate-600">No invoices linked to this rep.</div> : <div className="overflow-x-auto"><table className="min-w-full text-left text-sm"><thead className="text-xs uppercase tracking-[0.2em] text-slate-500"><tr><th className="px-3 py-2">Invoice</th><th className="px-3 py-2">Customer</th><th className="px-3 py-2">Project</th><th className="px-3 py-2">Status</th><th className="px-3 py-2">Total</th><th className="px-3 py-2">Issued</th><th className="px-3 py-2">Due</th></tr></thead><tbody>{rows.map((row) => <tr key={row.id} className="border-t border-slate-100"><td className="px-3 py-2">{row.invoice_show_route ? <a href={row.invoice_show_route} data-native="true" className="text-teal-700 hover:text-teal-600">{row.invoice_number}</a> : row.invoice_number}</td><td className="px-3 py-2">{row.customer_name}</td><td className="px-3 py-2">{row.project_name}</td><td className="px-3 py-2">{row.status}</td><td className="px-3 py-2">{row.total_display}</td><td className="px-3 py-2">{row.issue_date}</td><td className="px-3 py-2">{row.due_date}</td></tr>)}</tbody></table></div>}
        </div>
    );
}

function EarningsTable({ rows, summary, route }) {
    return (
        <>
            <div className="grid gap-4 md:grid-cols-3">
                <Metric title="Earned Amount" value={money(summary?.total_earned)} note="Includes pending, payable, and paid commission." />
                <Metric title="Outstanding" value={money(summary?.outstanding)} note="Amount yet to be paid (total minus paid)." />
                <Metric title="Payable (Net)" value={money(summary?.payable)} note="Ready for payout after advances." />
            </div>
            <div className="mt-4 card p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="text-2xl font-semibold text-slate-800">Recent Earnings</div>
                    <a href={route} data-native="true" className="text-sm font-semibold text-teal-700 hover:text-teal-600">
                        Pay payable ({money(summary?.payable)})
                    </a>
                </div>
                {rows.length === 0 ? (
                    <div className="text-sm text-slate-600">No earnings yet.</div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Date</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Source</th>
                                    <th className="px-3 py-2">Details</th>
                                    <th className="px-3 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id} className="border-t border-slate-100">
                                        <td className="px-3 py-2">{row.earned_date || '--'}</td>
                                        <td className="px-3 py-2">{row.status_label || row.status || '--'}</td>
                                        <td className="px-3 py-2">{row.source_label || row.source_type || '--'}</td>
                                        <td className="px-3 py-2">{row.details || '--'}</td>
                                        <td className="px-3 py-2 text-right">{`${row.currency || ''} ${money(row.amount)}`.trim()}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

function PayoutsTable({ rows }) {
    return <SimpleTable title="Recent Payouts" headers={['ID', 'Type', 'Status', 'Method', 'Amount', 'Paid At', 'Reference']} rows={rows.map((r) => [String(r.id), String(r.type || '--'), String(r.status || '--'), String(r.payout_method || '--'), `${r.currency || ''} ${money(r.total_amount)}`, r.paid_at, String(r.reference || '--')])} empty="No payouts yet." />;
}

function EmailsTable({ rows }) {
    const list = Array.isArray(rows) ? rows : [];

    return (
        <div className="card p-6">
            <div className="mb-3 text-xl font-semibold text-slate-800">Email Log</div>
            {list.length === 0 ? (
                <div className="text-sm text-slate-600">No email history available for this sales representative.</div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">Subject</th>
                                <th className="px-3 py-2">To</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Mailer</th>
                                <th className="px-3 py-2">Event</th>
                            </tr>
                        </thead>
                        <tbody>
                            {list.map((row) => (
                                <tr key={row.id} className="border-t border-slate-100 align-top">
                                    <td className="px-3 py-2 whitespace-nowrap">{row.created_at || '--'}</td>
                                    <td className="px-3 py-2">{row.subject || '--'}</td>
                                    <td className="px-3 py-2">
                                        <div>{Array.isArray(row.to) ? row.to.join(', ') : '--'}</div>
                                        <div className="text-xs text-slate-500">Recipients: {row.to_count ?? 0}</div>
                                    </td>
                                    <td className="px-3 py-2">{row.status || '--'}</td>
                                    <td className="px-3 py-2">{row.mailer || '--'}</td>
                                    <td className="px-3 py-2">
                                        <div>{row.event || '--'}</div>
                                        <div className="text-xs text-slate-500">Message ID: {row.message_id || '--'}</div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function ActivityLogTable({ rows }) {
    const list = Array.isArray(rows) ? rows : [];

    return (
        <div className="card p-6">
            <div className="mb-3 text-xl font-semibold text-slate-800">Activity Log</div>
            {list.length === 0 ? (
                <div className="text-sm text-slate-600">No activity log entries.</div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">Action</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Details</th>
                                <th className="px-3 py-2">By</th>
                            </tr>
                        </thead>
                        <tbody>
                            {list.map((row) => (
                                <tr key={row.id} className="border-t border-slate-100 align-top">
                                    <td className="px-3 py-2 whitespace-nowrap">{row.created_at || '--'}</td>
                                    <td className="px-3 py-2">{row.action || '--'}</td>
                                    <td className="px-3 py-2">{`${row.status_from || '--'} -> ${row.status_to || '--'}`}</td>
                                    <td className="px-3 py-2">
                                        <div>{row.description || '--'}</div>
                                        {row.amount !== null ? (
                                            <div className="text-xs text-slate-500">
                                                Amount: {row.currency || ''} {money(row.amount)}
                                            </div>
                                        ) : null}
                                        {row.project_name ? <div className="text-xs text-slate-500">Project: {row.project_name}</div> : null}
                                    </td>
                                    <td className="px-3 py-2">{row.created_by || 'System'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function ProjectsTable({ rows }) {
    const list = Array.isArray(rows) ? rows : [];
    const normalizeStatus = (status) => String(status || '').toLowerCase().replace(/\s+/g, '_');
    const statusBadgeClass = (status) => {
        const key = normalizeStatus(status);
        if (key === 'completed' || key === 'complete') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
        if (key === 'ongoing' || key === 'in_progress') return 'border-amber-200 bg-amber-50 text-amber-700';
        if (key === 'on_hold') return 'border-slate-300 bg-slate-50 text-slate-700';
        if (key === 'cancelled' || key === 'canceled') return 'border-rose-200 bg-rose-50 text-rose-700';
        return 'border-slate-300 bg-slate-50 text-slate-700';
    };

    const projectStatusCounts = list.reduce(
        (acc, row) => {
            const key = normalizeStatus(row?.status);
            if (key === 'ongoing' || key === 'in_progress') acc.ongoing += 1;
            else if (key === 'on_hold') acc.on_hold += 1;
            else if (key === 'completed' || key === 'complete') acc.completed += 1;
            else if (key === 'cancelled' || key === 'canceled') acc.cancelled += 1;
            return acc;
        },
        { ongoing: 0, on_hold: 0, completed: 0, cancelled: 0 }
    );

    return (
        <div className="space-y-4">
            <div className="card p-4">
                <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Assigned Projects</div>
                <div className="mt-2 text-4xl font-semibold leading-none text-slate-900">{list.length}</div>
                <div className="mt-3 flex flex-wrap gap-2 text-xs">
                    <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">Ongoing: {projectStatusCounts.ongoing}</span>
                    <span className="rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-slate-700">On hold: {projectStatusCounts.on_hold}</span>
                    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700">Completed: {projectStatusCounts.completed}</span>
                    <span className="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-rose-700">Cancelled: {projectStatusCounts.cancelled}</span>
                </div>
            </div>

            <div className="card p-6">
                <div className="mb-3 text-xl font-semibold text-slate-800">Projects</div>
                {list.length === 0 ? (
                    <div className="text-sm text-slate-600">No projects linked to this rep.</div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Project</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Customer</th>
                                    <th className="px-3 py-2">Assigned Tasks</th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((row) => {
                                    const tasks = row?.tasks || {};
                                    const pending = Number(tasks.pending || 0);
                                    const inProgress = Number(tasks.in_progress || 0);
                                    const blocked = Number(tasks.blocked || 0);
                                    const completed = Number(tasks.completed || 0);
                                    const assignedTotal = pending + inProgress + blocked + completed;

                                    return (
                                        <tr key={row.id} className="border-t border-slate-100 align-top">
                                            <td className="px-3 py-3">
                                                <a href={row.route} data-native="true" className="font-semibold text-teal-700 hover:text-teal-600">{row.name}</a>
                                                <div className="text-xs text-slate-500">Project ID: {row.id}</div>
                                            </td>
                                            <td className="px-3 py-3">
                                                <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusBadgeClass(row.status)}`}>{row.status}</span>
                                            </td>
                                            <td className="px-3 py-3">{row.customer_name}</td>
                                            <td className="px-3 py-3">
                                                <div className="font-medium text-slate-800">Assigned tasks: {assignedTotal}</div>
                                                <div className="mt-2 flex flex-wrap gap-2 text-xs">
                                                    <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">Pending: {pending}</span>
                                                    <span className="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-blue-700">In progress: {inProgress}</span>
                                                    <span className="rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-slate-700">Blocked: {blocked}</span>
                                                    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700">Completed: {completed}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
