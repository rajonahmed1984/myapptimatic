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

            {tab === 'emails' ? <div className="card p-6 text-sm text-slate-600">No email history available.</div> : null}
            {tab === 'log' ? <div className="card p-6 text-sm text-slate-600">No activity log entries.</div> : null}
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
                <div className="mb-3 flex items-center justify-between"><div className="text-sm font-semibold text-slate-800">Recent Earnings</div><a href={route} data-native="true" className="text-xs font-semibold text-teal-700">Pay payable ({money(summary?.payable)})</a></div>
                <SimpleTable title="" headers={['Source', 'Amount', 'Status', 'Earned At']} rows={rows.map((r) => [String(r.source_type || '--'), `${r.currency || ''} ${money(r.amount)}`, String(r.status || '--'), r.earned_at])} empty="No earnings yet." />
            </div>
        </>
    );
}

function PayoutsTable({ rows }) {
    return <SimpleTable title="Recent Payouts" headers={['ID', 'Type', 'Status', 'Amount', 'Paid At', 'Reference']} rows={rows.map((r) => [String(r.id), String(r.type || '--'), String(r.status || '--'), `${r.currency || ''} ${money(r.total_amount)}`, r.paid_at, String(r.reference || '--')])} empty="No payouts yet." />;
}

function ProjectsTable({ rows }) {
    return (
        <div className="card p-6">
            <div className="mb-3 text-sm font-semibold text-slate-800">Projects</div>
            {rows.length === 0 ? <div className="text-sm text-slate-600">No projects linked to this rep.</div> : <div className="overflow-x-auto"><table className="min-w-full text-left text-sm"><thead className="text-xs uppercase tracking-[0.2em] text-slate-500"><tr><th className="px-3 py-2">Project</th><th className="px-3 py-2">Customer</th><th className="px-3 py-2">Status</th><th className="px-3 py-2">Tasks</th></tr></thead><tbody>{rows.map((row) => <tr key={row.id} className="border-t border-slate-100"><td className="px-3 py-2"><a href={row.route} data-native="true" className="text-teal-700 hover:text-teal-600">{row.name}</a></td><td className="px-3 py-2">{row.customer_name}</td><td className="px-3 py-2">{row.status}</td><td className="px-3 py-2">Open {row.tasks?.pending || 0}, Progress {row.tasks?.in_progress || 0}, Blocked {row.tasks?.blocked || 0}, Done {row.tasks?.completed || 0}</td></tr>)}</tbody></table></div>}
        </div>
    );
}
