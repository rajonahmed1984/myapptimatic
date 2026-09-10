import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

const TH = 'px-4 py-2.5 text-left text-[11px] font-medium uppercase tracking-wide text-slate-400';
const TD = 'px-4 py-3 text-sm text-slate-700';
const LINK = 'font-medium text-teal-700 hover:text-teal-600';
const BTN = 'inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50';

export default function Show({
    pageTitle = 'Project',
    project = null,
    tasks = [],
    tasksPagination = {},
    initialInvoice = null,
    remainingBudgetInvoices = [],
    taskStats = {},
    routes = {},
    transferCustomers = [],
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const financials = project?.financials || {};
    const invoiceable = Number(financials.remaining_budget_invoiceable || 0);
    const overheads = project?.overheads || [];
    const maintenances = project?.maintenances || [];
    const employees = project?.team?.employees || [];
    const salesReps = project?.team?.sales_reps || [];

    return (
        <>
            <Head title={pageTitle} />

            {/* Header */}
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <a href={routes?.index} data-native="true" className="text-xs font-medium text-slate-400 hover:text-slate-600">
                        ← All projects
                    </a>
                    <h1 className="mt-1 text-2xl font-semibold text-slate-900">{project?.name}</h1>
                    <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                        <StatusBadge status={project?.status} label={project?.status_label} />
                        <span>#{project?.id}</span>
                        <Dot />
                        <span>{project?.type_label}</span>
                        <Dot />
                        <span>{project?.customer?.name}</span>
                        {project?.dates?.due && project.dates.due !== '--' ? (
                            <>
                                <Dot />
                                <span>Due {project.dates.due}</span>
                            </>
                        ) : null}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <a href={routes?.tasks} data-native="true" className={BTN}>Tasks</a>
                    <a href={routes?.chat} data-native="true" className={BTN}>
                        Chat
                        {(project?.project_chat_unread_count ?? 0) > 0 ? (
                            <span className="rounded-full bg-teal-600 px-1.5 text-[10px] font-semibold leading-4 text-white">
                                {project.project_chat_unread_count}
                            </span>
                        ) : null}
                    </a>
                    <a href={routes?.invoices} data-native="true" className={BTN}>Invoices</a>
                    <a href={routes?.edit} data-native="true" className={BTN}>Edit</a>
                    {project?.can_mark_complete ? (
                        <form method="POST" action={routes?.complete} data-native="true" onSubmit={(e) => !window.confirm('Mark this project as complete?') && e.preventDefault()}>
                            <input type="hidden" name="_token" value={csrf} />
                            <button type="submit" className="rounded-full bg-teal-600 px-3.5 py-1.5 text-xs font-semibold text-white">
                                Mark complete
                            </button>
                        </form>
                    ) : null}
                </div>
            </div>

            {/* Task stats */}
            <div className="card mb-6 grid grid-cols-2 divide-slate-100 sm:grid-cols-4 sm:divide-x">
                <Stat label="Total tasks" value={taskStats?.total ?? 0} />
                <Stat label="In progress" value={taskStats?.in_progress ?? 0} />
                <Stat label="Completed" value={taskStats?.completed ?? 0} />
                <Stat label="Unread messages" value={taskStats?.unread ?? 0} />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main column */}
                <div className="space-y-6 lg:col-span-2">
                    <Card title="Budget">
                        <div className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-100 bg-slate-100 md:grid-cols-4">
                            <Metric label="Total budget" value={financials.budget_with_overhead_display} hint="incl. overheads" />
                            <Metric label="Paid" value={financials.paid_payment_display} tone="emerald" />
                            <Metric label="Remaining" value={financials.remaining_budget_display} hint="not yet paid" />
                            <Metric label="Available to invoice" value={financials.remaining_budget_invoiceable_display} tone={invoiceable > 0 ? 'teal' : undefined} />
                        </div>

                        <dl className="mt-5 grid gap-x-8 sm:grid-cols-2">
                            <Row label="Base budget" value={financials.total_budget_display} />
                            <Row label="Overheads" value={financials.overhead_total_display} />
                            <Row label="Initial payment" value={financials.initial_payment_display} />
                            <Row label="Invoiced, awaiting payment" value={financials.outstanding_invoiced_display} />
                            {financials.has_uninvoiced_overheads ? (
                                <Row label="Overheads not yet invoiced" value={financials.uninvoiced_overheads_display} />
                            ) : null}
                            <Row label="Employee cost" value={financials.employee_salary_total_display} />
                            <Row label="Sales rep payout" value={financials.sales_rep_total_display} />
                            {financials.budget_amount_display && financials.budget_amount_display !== '--' ? (
                                <Row label="Budget (legacy)" value={financials.budget_amount_display} />
                            ) : null}
                            <Row
                                label="Profit"
                                value={<span className={financials.profitable === false ? 'text-rose-600' : 'text-emerald-600'}>{financials.profit_display}</span>}
                            />
                        </dl>
                    </Card>

                    <Card
                        title="Remaining budget invoices"
                        subtitle={`Available to invoice: ${financials.remaining_budget_invoiceable_display ?? '--'}`}
                        flush
                    >
                        {remainingBudgetInvoices.length === 0 ? (
                            <Empty>No invoices generated from the remaining budget yet.</Empty>
                        ) : (
                            <Table head={['Invoice', 'Amount', 'Issued', 'Due', 'Paid', 'Status']}>
                                {remainingBudgetInvoices.map((invoice) => (
                                    <tr key={invoice.id} className="border-t border-slate-100">
                                        <td className={TD}><a href={invoice.show_route} className={LINK}>{invoice.number_display}</a></td>
                                        <td className={TD}>
                                            <div className="font-medium text-slate-900">{invoice.invoiced_amount_display || invoice.total_display}</div>
                                            {invoice.remaining_amount_display ? <div className="text-[11px] text-slate-400">Left after: {invoice.remaining_amount_display}</div> : null}
                                        </td>
                                        <td className={TD}>{invoice.issue_date}</td>
                                        <td className={TD}>{invoice.due_date}</td>
                                        <td className={TD}>{invoice.paid_at}</td>
                                        <td className={TD}><StatusBadge status={invoice.status} label={invoice.status_label} /></td>
                                    </tr>
                                ))}
                            </Table>
                        )}

                        <div className="border-t border-slate-100 px-5 py-4">
                            {invoiceable > 0 ? (
                                <form method="POST" action={routes?.invoice_remaining} data-native="true" className="flex flex-wrap items-end gap-3">
                                    <input type="hidden" name="_token" value={csrf} />
                                    <label className="min-w-[12rem] flex-1">
                                        <span className="text-xs text-slate-500">New invoice amount ({project?.currency})</span>
                                        <input name="amount" type="number" step="0.01" min="0.01" max={invoiceable} required placeholder={String(invoiceable)} className="ui-input mt-1" />
                                    </label>
                                    <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Create invoice</button>
                                </form>
                            ) : (
                                <p className="text-xs text-slate-500">Nothing left to invoice — the full budget is already paid or invoiced.</p>
                            )}
                        </div>
                    </Card>

                    <Card
                        title="Overhead fees"
                        action={<a href={routes?.overheads_index} data-native="true" className="text-xs font-semibold text-teal-700 hover:text-teal-600">Manage</a>}
                        flush
                    >
                        {overheads.length === 0 ? (
                            <Empty>No overhead line items added.</Empty>
                        ) : (
                            <Table head={['Details', 'Amount', 'Date', 'Invoice', 'Status']}>
                                {overheads.map((overhead) => (
                                    <tr key={overhead.id} className="border-t border-slate-100">
                                        <td className={TD}>{overhead.details}</td>
                                        <td className={`${TD} font-medium text-slate-900`}>{overhead.amount_display}</td>
                                        <td className={TD}>{overhead.date}</td>
                                        <td className={TD}>{overhead.invoice_show_route ? <a href={overhead.invoice_show_route} className={LINK}>{overhead.invoice_number}</a> : <span className="text-slate-400">—</span>}</td>
                                        <td className={TD}><StatusBadge status={overhead.status_label} label={overhead.status_label} /></td>
                                    </tr>
                                ))}
                            </Table>
                        )}

                        <form method="POST" action={routes?.overheads_store} data-native="true" className="flex flex-wrap items-end gap-3 border-t border-slate-100 px-5 py-4">
                            <input type="hidden" name="_token" value={csrf} />
                            <label className="min-w-[12rem] flex-[2]">
                                <span className="text-xs text-slate-500">Details</span>
                                <input name="short_details" required className="ui-input mt-1" placeholder="Feature fee or description" />
                            </label>
                            <label className="min-w-[8rem] flex-1">
                                <span className="text-xs text-slate-500">Amount</span>
                                <input name="amount" required type="number" step="0.01" min="0" className="ui-input mt-1" />
                            </label>
                            <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Add fee</button>
                        </form>
                    </Card>

                    <Card
                        title="Recent tasks"
                        action={<a href={routes?.tasks} data-native="true" className="text-xs font-semibold text-teal-700 hover:text-teal-600">View all</a>}
                        flush
                    >
                        {tasks.length === 0 ? (
                            <Empty>No tasks yet.</Empty>
                        ) : (
                            <Table head={['Task', 'Created by', 'Created', 'Status']}>
                                {tasks.map((task) => (
                                    <tr key={task.id} className="border-t border-slate-100">
                                        <td className={TD}>
                                            <a href={task.route} className={LINK}>{task.title}</a>
                                            <div className="text-[11px] text-slate-400">#{task.id}</div>
                                        </td>
                                        <td className={TD}>{task.creator_name}</td>
                                        <td className={`${TD} whitespace-nowrap`}>{task.created_at}</td>
                                        <td className={TD}><StatusBadge status={task.status} label={task.status_label} /></td>
                                    </tr>
                                ))}
                            </Table>
                        )}

                        {tasksPagination?.has_pages ? (
                            <div className="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3 text-xs">
                                <PageLink href={tasksPagination.previous_url}>Previous</PageLink>
                                <PageLink href={tasksPagination.next_url}>Next</PageLink>
                            </div>
                        ) : null}
                    </Card>

                    <Card
                        title="Maintenance"
                        action={<a href={routes?.maintenance_create} data-native="true" className="text-xs font-semibold text-teal-700 hover:text-teal-600">Add plan</a>}
                        flush
                    >
                        {maintenances.length === 0 ? (
                            <Empty>No maintenance plans for this project.</Empty>
                        ) : (
                            <Table head={['Plan', 'Amount', 'Next billing', 'Status', 'Invoices', '']}>
                                {maintenances.map((maintenance) => (
                                    <tr key={maintenance.id} className="border-t border-slate-100">
                                        <td className={TD}>
                                            <div className="font-medium text-slate-900">{maintenance.title}</div>
                                            <div className="text-[11px] text-slate-400">{maintenance.cycle} · Auto invoice {maintenance.auto_invoice ? 'on' : 'off'}</div>
                                        </td>
                                        <td className={TD}>{maintenance.amount_display}</td>
                                        <td className={TD}>{maintenance.next_billing_date}</td>
                                        <td className={TD}><StatusBadge status={maintenance.status} label={maintenance.status} /></td>
                                        <td className={TD}><a href={maintenance.invoices_route} className={LINK}>{maintenance.invoices_count}</a></td>
                                        <td className={`${TD} text-right`}><a href={maintenance.edit_route} className="text-xs font-semibold text-slate-500 hover:text-slate-800">Edit</a></td>
                                    </tr>
                                ))}
                            </Table>
                        )}
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    <Card title="Details">
                        <dl>
                            <Row label="Customer" value={project?.customer?.name} />
                            <Row label="Client ID" value={project?.customer?.id ?? '--'} />
                            <Row label="Currency" value={project?.currency} />
                            <Row label="Start" value={project?.dates?.start} />
                            <Row label="Expected end" value={project?.dates?.expected_end} />
                            <Row label="Due" value={project?.dates?.due} />
                        </dl>
                    </Card>

                    <Card title="Team">
                        <TeamList label="Employees" people={employees} />
                        <div className="mt-4"><TeamList label="Sales reps" people={salesReps} /></div>
                    </Card>

                    <Card title="Initial invoice">
                        {initialInvoice ? (
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <a href={initialInvoice.show_route} className={LINK}>{initialInvoice.number_display}</a>
                                    <div className="text-sm text-slate-900">{initialInvoice.total_display}</div>
                                </div>
                                <StatusBadge status={initialInvoice.status} label={initialInvoice.status_label} />
                            </div>
                        ) : (
                            <p className="text-sm text-slate-400">No initial invoice linked.</p>
                        )}
                    </Card>

                    <Card title="Documents">
                        <div className="space-y-2 text-sm">
                            <DocLink label="Contract" file={project?.files?.contract} />
                            <DocLink label="Proposal" file={project?.files?.proposal} />
                        </div>
                    </Card>

                    <Card title="Description">
                        <p className="whitespace-pre-wrap text-sm text-slate-600">{project?.description}</p>
                        {project?.notes ? (
                            <div className="mt-4 border-t border-slate-100 pt-4">
                                <div className="mb-1 text-xs font-medium text-slate-400">Notes</div>
                                <p className="whitespace-pre-wrap text-sm text-slate-600">{project.notes}</p>
                            </div>
                        ) : null}
                    </Card>

                    <details className="card group p-5">
                        <summary className="flex cursor-pointer list-none items-center justify-between text-sm font-semibold text-slate-900">
                            Transfer ownership
                            <span className="text-slate-400 transition group-open:rotate-180">⌄</span>
                        </summary>
                        {project?.transfer_eligible ? (
                            <form action={routes?.transfer_store} method="POST" data-native="true" className="mt-4 space-y-3">
                                <input type="hidden" name="_token" value={csrf} />
                                <div>
                                    <label className="mb-1 block text-xs text-slate-500">Receiving customer</label>
                                    <SearchableSelect
                                        name="to_customer_id"
                                        placeholder="Choose a client..."
                                        options={transferCustomers.map((c) => ({ value: String(c.id), label: c.name }))}
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs text-slate-500">Schedule for (optional)</label>
                                    <input type="datetime-local" name="scheduled_for" className="ui-input" />
                                    <p className="mt-1 text-[11px] text-slate-400">Leave blank to execute as soon as the receiver accepts.</p>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs text-slate-500">Reason (optional)</label>
                                    <textarea name="reason" rows={2} className="w-full rounded-[10px] border border-slate-300 px-3 py-2 text-xs" />
                                </div>
                                <button
                                    type="submit"
                                    className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white"
                                    onClick={(e) => {
                                        if (!confirm('Send this project ownership transfer invite? The receiving customer will need to accept it.')) {
                                            e.preventDefault();
                                        }
                                    }}
                                >
                                    Send transfer invite
                                </button>
                            </form>
                        ) : (
                            <p className="mt-3 text-sm text-slate-500">{project?.transfer_ineligible_reason || 'This project is not eligible for transfer.'}</p>
                        )}
                    </details>

                    <div className="rounded-2xl border border-rose-100 p-5">
                        <div className="text-sm font-semibold text-slate-900">Delete project</div>
                        <p className="mt-1 text-xs text-slate-500">Permanently removes this project and its data.</p>
                        <form method="POST" action={routes?.destroy} data-native="true" className="mt-3" onSubmit={(e) => !window.confirm(`Delete project ${project?.name}?`) && e.preventDefault()}>
                            <input type="hidden" name="_token" value={csrf} />
                            <input type="hidden" name="_method" value="DELETE" />
                            <button type="submit" className="rounded-full border border-rose-200 px-4 py-1.5 text-xs font-semibold text-rose-600">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}

function Card({ title, subtitle = null, action = null, flush = false, children }) {
    return (
        <section className="card overflow-hidden">
            <div className="flex items-center justify-between gap-3 px-5 pt-5">
                <div>
                    <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
                    {subtitle ? <div className="mt-0.5 text-xs text-slate-500">{subtitle}</div> : null}
                </div>
                {action}
            </div>
            <div className={flush ? 'mt-4' : 'p-5 pt-4'}>{children}</div>
        </section>
    );
}

function Stat({ label, value }) {
    return (
        <div className="px-5 py-4">
            <div className="text-xl font-semibold text-slate-900">{value}</div>
            <div className="text-xs text-slate-500">{label}</div>
        </div>
    );
}

function Metric({ label, value, hint = null, tone }) {
    const color = tone === 'emerald' ? 'text-emerald-600' : tone === 'teal' ? 'text-teal-700' : 'text-slate-900';

    return (
        <div className="bg-white px-4 py-3">
            <div className="text-xs text-slate-500">{label}</div>
            <div className={`mt-1 text-base font-semibold ${color}`}>{value ?? '--'}</div>
            {hint ? <div className="text-[11px] text-slate-400">{hint}</div> : null}
        </div>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-slate-100 py-2 last:border-0">
            <dt className="text-xs text-slate-500">{label}</dt>
            <dd className="text-right text-sm font-medium text-slate-800">{value ?? '--'}</dd>
        </div>
    );
}

function Table({ head, children }) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full">
                <thead className="bg-slate-50/70">
                    <tr>{head.map((h, i) => <th key={i} className={TH}>{h}</th>)}</tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}

function Empty({ children }) {
    return <div className="px-5 pb-5 text-sm text-slate-400">{children}</div>;
}

function Dot() {
    return <span className="text-slate-300">•</span>;
}

function StatusBadge({ status, label }) {
    const key = String(status || '').toLowerCase().replace(/\s+/g, '_');
    const tones = {
        emerald: ['paid', 'complete', 'completed', 'done', 'active'],
        amber: ['unpaid', 'pending', 'open', 'todo', 'on_hold', 'not_invoiced', 'paused'],
        sky: ['ongoing', 'in_progress', 'inprogress'],
        rose: ['overdue', 'cancelled', 'canceled', 'blocked', 'inactive'],
    };
    const tone = Object.keys(tones).find((t) => tones[t].includes(key)) || 'slate';
    const classes = {
        emerald: 'bg-emerald-50 text-emerald-700',
        amber: 'bg-amber-50 text-amber-700',
        sky: 'bg-sky-50 text-sky-700',
        rose: 'bg-rose-50 text-rose-700',
        slate: 'bg-slate-100 text-slate-600',
    }[tone];

    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ${classes}`}>
            {label || status || '--'}
        </span>
    );
}

function TeamList({ label, people }) {
    return (
        <div>
            <div className="mb-2 text-xs text-slate-500">{label}</div>
            {people.length === 0 ? (
                <div className="text-sm text-slate-400">None assigned</div>
            ) : (
                <div className="flex flex-wrap gap-1.5">
                    {people.map((name, i) => (
                        <span key={i} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">{name}</span>
                    ))}
                </div>
            )}
        </div>
    );
}

function DocLink({ label, file }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-xs text-slate-500">{label}</span>
            {file ? (
                <a href={file.url} className={`${LINK} truncate text-sm`}>{file.name}</a>
            ) : (
                <span className="text-sm text-slate-400">Not uploaded</span>
            )}
        </div>
    );
}

function PageLink({ href, children }) {
    return href ? (
        <a href={href} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-700 hover:bg-slate-50">{children}</a>
    ) : (
        <span className="rounded-full border border-slate-100 px-3 py-1 text-slate-300">{children}</span>
    );
}
