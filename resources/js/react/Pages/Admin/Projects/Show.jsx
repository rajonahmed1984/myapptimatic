import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Show({
    pageTitle = 'Project',
    project = null,
    tasks = [],
    tasksPagination = {},
    initialInvoice = null,
    remainingBudgetInvoices = [],
    taskStats = {},
    aiReady = false,
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    const [aiStatus, setAiStatus] = useState('Ready');
    const [aiHealth, setAiHealth] = useState('--');
    const [aiSummary, setAiSummary] = useState('Click Generate AI to analyze this project.');
    const [aiHighlights, setAiHighlights] = useState(['--']);
    const [aiRisks, setAiRisks] = useState(['--']);
    const [aiNextSteps, setAiNextSteps] = useState(['--']);

    const canInvoiceRemaining = useMemo(() => {
        const line = project?.financials?.remaining_budget_invoiceable_display || '';
        const parts = String(line).split(' ');
        const amount = Number((parts[1] || '0').replace(/,/g, ''));
        return amount > 0;
    }, [project?.financials?.remaining_budget_invoiceable_display]);

    const generateAi = async () => {
        if (!routes?.ai_summary) {
            return;
        }

        setAiStatus('Generating...');
        setAiSummary('Working on the AI summary...');
        setAiHealth('--');
        setAiHighlights(['--']);
        setAiRisks(['--']);
        setAiNextSteps(['--']);

        try {
            const response = await fetch(routes.ai_summary, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            });

            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload?.error || 'Failed to generate AI summary.');
            }

            const data = payload?.data || null;
            setAiSummary(data?.summary || payload?.raw || '--');
            setAiHealth(data?.health || '--');
            setAiHighlights(Array.isArray(data?.highlights) && data.highlights.length > 0 ? data.highlights : ['--']);
            setAiRisks(Array.isArray(data?.risks) && data.risks.length > 0 ? data.risks : ['--']);
            setAiNextSteps(Array.isArray(data?.next_steps) && data.next_steps.length > 0 ? data.next_steps : ['--']);
            setAiStatus('Updated');
        } catch (error) {
            setAiSummary(error?.message || 'AI request failed.');
            setAiStatus('Error');
        }
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="text-2xl font-semibold text-slate-900">{project?.name}</div>
                    <div className="text-sm text-slate-500">Status: {project?.status_label}</div>
                </div>
                <div className="flex items-center gap-3">
                    <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        Back
                    </a>
                    <a href={routes?.invoices} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        All Invoices
                    </a>
                    <a href={routes?.tasks} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        Tasks
                    </a>
                    <a href={routes?.chat} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        Chat
                        <span className="ml-2 inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                            {project?.project_chat_unread_count ?? 0}
                        </span>
                    </a>
                    {project?.can_mark_complete ? (
                        <form method="POST" action={routes?.complete} data-native="true" onSubmit={(e) => !window.confirm('Mark this project as complete?') && e.preventDefault()}>
                            <input type="hidden" name="_token" value={csrf} />
                            <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                                Project Complete
                            </button>
                        </form>
                    ) : null}
                    <a href={routes?.edit} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        Edit
                    </a>
                    <form method="POST" action={routes?.destroy} data-native="true" onSubmit={(e) => !window.confirm(`Delete project ${project?.name}?`) && e.preventDefault()}>
                        <input type="hidden" name="_token" value={csrf} />
                        <input type="hidden" name="_method" value="DELETE" />
                        <button type="submit" className="rounded-full border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600">
                            Delete
                        </button>
                    </form>
                </div>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Total Tasks" value={taskStats?.total ?? 0} />
                <StatCard label="In Progress" value={taskStats?.in_progress ?? 0} />
                <StatCard label="Completed" value={taskStats?.completed ?? 0} />
                <StatCard label="Unread" value={taskStats?.unread ?? 0} />
            </div>

            <div className="card mb-6 space-y-4 p-6" id="project-ai-summary">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">AI Project Summary</div>
                        <div className="mt-1 text-sm text-slate-500">Quick project health, risks, and next steps.</div>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{aiStatus}</span>
                        <button type="button" disabled={!aiReady} onClick={generateAi} className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white disabled:opacity-60">
                            Generate AI
                        </button>
                    </div>
                </div>

                {!aiReady ? <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">GOOGLE_AI_API_KEY is missing. Add it to .env to enable AI summaries.</div> : null}

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-slate-100 bg-white p-4 text-sm md:col-span-2">
                        <div className="flex items-center justify-between">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Summary</div>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600">{aiHealth}</span>
                        </div>
                        <div className="mt-2 text-slate-700">{aiSummary}</div>
                    </div>
                    <ListCard title="Highlights" items={aiHighlights} />
                    <ListCard title="Risks" items={aiRisks} />
                    <ListCard title="Next steps" items={aiNextSteps} colSpan />
                </div>
            </div>

            <div className="card space-y-6 p-6">
                <InfoBlock title="Project Info">
                    <div className="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
                        <Pane title="Overview">{project?.type_label}<br />Project ID: {project?.id}<br />Status: {project?.status_label}</Pane>
                        <Pane title="Dates">Start: {project?.dates?.start}<br />Expected end: {project?.dates?.expected_end}<br />Due: {project?.dates?.due}</Pane>
                        <Pane title="Description">{project?.description}</Pane>
                    </div>
                </InfoBlock>

                <InfoBlock title="People">
                    <div className="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                        <Pane title="Customer">{project?.customer?.name}<br />Client ID: {project?.customer?.id ?? '--'}</Pane>
                        <Pane title="Team">Employees: {(project?.team?.employees || []).join(', ') || '--'}<br />Sales reps: {(project?.team?.sales_reps || []).join(', ') || '--'}</Pane>
                    </div>
                </InfoBlock>

                <InfoBlock title="Documents">
                    <div className="rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700">
                        {project?.files?.contract ? <div>Contract: <a href={project.files.contract.url} className="text-teal-700 hover:text-teal-600">{project.files.contract.name}</a></div> : <div className="text-xs text-slate-500">No contract uploaded.</div>}
                        {project?.files?.proposal ? <div className="mt-2">Proposal: <a href={project.files.proposal.url} className="text-teal-700 hover:text-teal-600">{project.files.proposal.name}</a></div> : <div className="mt-2 text-xs text-slate-500">No proposal uploaded.</div>}
                    </div>
                </InfoBlock>

                <InfoBlock title="Budget & Currency">
                    <div className="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                        <Pane title="Budget Summary">
                            Total budget: {project?.financials?.total_budget_display}<br />
                            Overhead total: {project?.financials?.overhead_total_display}<br />
                            Budget with overhead: {project?.financials?.budget_with_overhead_display}<br />
                            Initial payment: {project?.financials?.initial_payment_display}<br />
                            Paid payment: {project?.financials?.paid_payment_display}<br />
                            Remaining budget: {project?.financials?.remaining_budget_display}<br />
                            Budget (legacy): {project?.financials?.budget_amount_display}<br />
                            Currency: {project?.currency}<br />
                            Employee salary total: {project?.financials?.employee_salary_total_display}<br />
                            Sales rep total: {project?.financials?.sales_rep_total_display}<br />
                            Profit: {project?.financials?.profit_display}
                        </Pane>
                        <Pane title="Initial Invoice">
                            {initialInvoice ? (
                                <>
                                    Number: <a href={initialInvoice.show_route} className="text-teal-700 hover:text-teal-600">{initialInvoice.number_display}</a><br />
                                    Amount: {initialInvoice.total_display}<br />
                                    Status: {initialInvoice.status_label}
                                </>
                            ) : (
                                <span className="text-xs text-slate-500">No initial invoice linked.</span>
                            )}
                        </Pane>
                    </div>
                    <div className="mt-3 text-xs text-slate-500">
                        {project?.financials?.remaining_budget_line}<br />
                        {project?.financials?.profit_line}
                    </div>
                </InfoBlock>

                <InfoBlock title="Overhead fees" action={<a href={routes?.overheads_index} data-native="true" className="text-xs font-semibold text-teal-600">Manage overheads</a>}>
                    <div className="rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700">
                        {project?.overheads?.length === 0 ? (
                            <div className="text-xs text-slate-500">No overhead line items added.</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                            <th className="px-3 py-2">Invoice</th>
                                            <th className="px-3 py-2">Details</th>
                                            <th className="px-3 py-2">Amount</th>
                                            <th className="px-3 py-2">Date</th>
                                            <th className="px-3 py-2">Status</th>
                                            <th className="px-3 py-2">View</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {project.overheads.map((overhead) => (
                                            <tr key={overhead.id} className="border-t border-slate-100">
                                                <td className="px-3 py-2">{overhead.invoice_show_route ? <a href={overhead.invoice_show_route} className="text-teal-700 hover:text-teal-600">{overhead.invoice_number}</a> : '--'}</td>
                                                <td className="px-3 py-2">{overhead.details}</td>
                                                <td className="px-3 py-2">{overhead.amount_display}</td>
                                                <td className="px-3 py-2">{overhead.date}</td>
                                                <td className="px-3 py-2">{overhead.status_label}</td>
                                                <td className="px-3 py-2">{overhead.invoice_show_route ? <a href={overhead.invoice_show_route} className="text-xs font-semibold text-slate-700">View</a> : '--'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <form method="POST" action={routes?.overheads_store} data-native="true" className="mt-4 grid gap-3 md:grid-cols-3">
                            <input type="hidden" name="_token" value={csrf} />
                            <div className="md:col-span-2">
                                <label className="text-xs text-slate-500">Details</label>
                                <input name="short_details" required className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Feature fee or description" />
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Amount</label>
                                <input name="amount" required type="number" step="0.01" min="0" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div className="md:col-span-3 flex justify-end">
                                <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Add overhead fee</button>
                            </div>
                        </form>
                    </div>
                </InfoBlock>

                <InfoBlock title="Remaining budget invoices">
                    <div className="rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700 space-y-4">
                        <div className="text-xs text-slate-500">Remaining: {project?.financials?.remaining_budget_invoiceable_display}</div>
                        {remainingBudgetInvoices.length === 0 ? <div className="text-xs text-slate-500">No invoices generated from the remaining budget yet.</div> : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                            <th className="px-3 py-2">Invoice</th><th className="px-3 py-2">Amount</th><th className="px-3 py-2">Issue</th><th className="px-3 py-2">Due</th><th className="px-3 py-2">Paid at</th><th className="px-3 py-2">Status</th><th className="px-3 py-2 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {remainingBudgetInvoices.map((invoice) => (
                                            <tr key={invoice.id} className="border-t border-slate-100">
                                                <td className="px-3 py-2"><a href={invoice.show_route} className="text-teal-700 hover:text-teal-600">{invoice.number_display}</a></td>
                                                <td className="px-3 py-2">{invoice.total_display}</td>
                                                <td className="px-3 py-2">{invoice.issue_date}</td>
                                                <td className="px-3 py-2">{invoice.due_date}</td>
                                                <td className="px-3 py-2">{invoice.paid_at}</td>
                                                <td className="px-3 py-2">{invoice.status_label}</td>
                                                <td className="px-3 py-2 text-right"><a href={invoice.show_route} className="text-xs font-semibold text-slate-700">View</a></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <div className="border-t border-slate-100 pt-4">
                            {canInvoiceRemaining ? (
                                <form method="POST" action={routes?.invoice_remaining} data-native="true" className="space-y-3 text-xs text-slate-500">
                                    <input type="hidden" name="_token" value={csrf} />
                                    <div className="space-y-1">
                                        <label className="text-[10px] uppercase tracking-[0.2em] text-slate-400">Amount</label>
                                        <input name="amount" type="number" step="0.01" min="0.01" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                    </div>
                                    <div className="flex justify-end">
                                        <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Invoice remaining budget</button>
                                    </div>
                                </form>
                            ) : <p className="text-[10px] text-slate-500">Remaining budget must be positive before you can generate an additional invoice.</p>}
                        </div>
                    </div>
                </InfoBlock>

                <InfoBlock title="Maintenance" action={<a href={routes?.maintenance_create} data-native="true" className="rounded-full border border-teal-200 px-3 py-1 text-xs font-semibold text-teal-700">Add maintenance</a>}>
                    <div className="rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700">
                        {project?.maintenances?.length === 0 ? <div className="text-xs text-slate-500">No maintenance plans for this project.</div> : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                            <th className="px-3 py-2">Title</th><th className="px-3 py-2">Cycle</th><th className="px-3 py-2">Next Billing</th><th className="px-3 py-2">Status</th><th className="px-3 py-2">Auto</th><th className="px-3 py-2">Amount</th><th className="px-3 py-2">Invoices</th><th className="px-3 py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {project.maintenances.map((maintenance) => (
                                            <tr key={maintenance.id} className="border-t border-slate-100">
                                                <td className="px-3 py-2">{maintenance.title}</td>
                                                <td className="px-3 py-2">{maintenance.cycle}</td>
                                                <td className="px-3 py-2">{maintenance.next_billing_date}</td>
                                                <td className="px-3 py-2">{maintenance.status}</td>
                                                <td className="px-3 py-2">{maintenance.auto_invoice ? 'Yes' : 'No'}</td>
                                                <td className="px-3 py-2">{maintenance.amount_display}</td>
                                                <td className="px-3 py-2"><a href={maintenance.invoices_route} className="text-xs font-semibold text-slate-700">{maintenance.invoices_count}</a></td>
                                                <td className="px-3 py-2"><a href={maintenance.edit_route} className="text-xs font-semibold text-teal-700">Edit</a></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </InfoBlock>

                <InfoBlock title="Tasks">
                    <div className="rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700">
                        {tasks.length === 0 ? (
                            <div className="text-xs text-slate-500">No tasks found.</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-[0.2em] text-slate-500"><th className="px-3 py-2">Created</th><th className="px-3 py-2">Task</th><th className="px-3 py-2">Created By</th><th className="px-3 py-2">Status</th></tr>
                                    </thead>
                                    <tbody>
                                        {tasks.map((task) => (
                                            <tr key={task.id} className="border-t border-slate-100">
                                                <td className="px-3 py-2">{task.created_at}</td>
                                                <td className="px-3 py-2"><a href={task.route} className="text-teal-600 hover:text-teal-500">{task.title}</a></td>
                                                <td className="px-3 py-2">{task.creator_name}</td>
                                                <td className="px-3 py-2">{task.status_label}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {tasksPagination?.has_pages ? (
                            <div className="mt-4 flex items-center justify-end gap-2 text-sm">
                                {tasksPagination.previous_url ? <a href={tasksPagination.previous_url} data-native="true" className="rounded-full border border-slate-300 px-3 py-1 text-slate-700">Previous</a> : <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Previous</span>}
                                {tasksPagination.next_url ? <a href={tasksPagination.next_url} data-native="true" className="rounded-full border border-slate-300 px-3 py-1 text-slate-700">Next</a> : <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>}
                            </div>
                        ) : null}
                    </div>
                </InfoBlock>

                {project?.notes ? (
                    <div className="rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Notes</div>
                        <div className="mt-2 whitespace-pre-wrap">{project.notes}</div>
                    </div>
                ) : null}
            </div>
        </>
    );
}

function StatCard({ value, label }) {
    return (
        <div className="rounded-2xl border border-slate-300 bg-white/80 p-4">
            <div className="text-2xl font-semibold text-slate-900">{value}</div>
            <div className="text-xs uppercase tracking-[0.25em] text-slate-500">{label}</div>
        </div>
    );
}

function InfoBlock({ title, action = null, children }) {
    return (
        <div>
            <div className="mb-3 flex items-center justify-between gap-3">
                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
                {action}
            </div>
            {children}
        </div>
    );
}

function Pane({ title, children }) {
    return (
        <div className="rounded-2xl border border-slate-300 bg-white/80 p-4">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <div className="mt-2 text-xs text-slate-600">{children}</div>
        </div>
    );
}

function ListCard({ title, items, colSpan = false }) {
    return (
        <div className={`rounded-2xl border border-slate-100 bg-white p-4 text-sm ${colSpan ? 'md:col-span-2' : ''}`}>
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <ul className="mt-2 list-disc space-y-1 pl-4 text-slate-700">
                {items.map((item, index) => (
                    <li key={`${title}-${index}`}>{item}</li>
                ))}
            </ul>
        </div>
    );
}
