import React from 'react';
import { Head } from '@inertiajs/react';

const asArray = (value) => (Array.isArray(value) ? value : []);

function CurrencyTotals({ title, rows = [] }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-500">{title}</div>
            <div className="mt-3 flex flex-wrap gap-2">
                {rows.length > 0 ? rows.map((row) => (
                    <span key={`${title}-${row.currency}`} className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                        {row.display}
                    </span>
                )) : (
                    <span className="text-sm text-slate-500">No data</span>
                )}
            </div>
        </div>
    );
}

export default function Dashboard({
    pageTitle = 'Projects Dashboard',
    overview = {},
    statusCards = [],
    typeCards = [],
    riskProjects = [],
    recentProjects = [],
    aiSummary = null,
    aiError = null,
    aiFocusProjects = [],
    routes = {},
}) {
    const overviewCards = [
        { key: 'total_projects', label: 'Total Projects', value: overview?.total_projects ?? 0 },
        { key: 'total_tasks', label: 'Total Tasks', value: overview?.total_tasks ?? 0 },
        { key: 'completion_rate', label: 'Completion Rate', value: `${overview?.completion_rate ?? 0}%` },
        { key: 'overdue_tasks', label: 'Overdue Tasks', value: overview?.overdue_tasks ?? 0, tone: 'text-rose-600' },
        { key: 'due_soon_tasks', label: 'Due Soon (7d)', value: overview?.due_soon_tasks ?? 0, tone: 'text-amber-600' },
        { key: 'active_maintenances', label: 'Active Maintenances', value: overview?.active_maintenances ?? 0, tone: 'text-emerald-600' },
    ];

    return (
        <>
            <Head title={pageTitle} />

            <div className="space-y-6">
                <div className="card overflow-hidden p-6">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {overviewCards.map((card) => (
                            <div key={card.key} className="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                <div className="text-xs uppercase tracking-[0.18em] text-slate-500">{card.label}</div>
                                <div className={`mt-2 text-3xl font-semibold text-slate-900 ${card.tone || ''}`}>{card.value}</div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                        <CurrencyTotals title="Budget Totals" rows={asArray(overview?.budget_totals)} />
                        <CurrencyTotals title="Paid Invoice Totals" rows={asArray(overview?.paid_totals)} />
                    </div>
                </div>

                <div className="card p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div className="section-label">Google AI Summary</div>
                            <div className="mt-1 text-[11px] text-slate-500">Portfolio summary with profitability, task/subtask timeline, and chat context.</div>
                        </div>
                        <a href={routes?.refresh_ai} data-native="true" className="rounded-full border border-emerald-200 bg-white px-3 py-1 text-[11px] font-semibold text-emerald-700">
                            Refresh AI
                        </a>
                    </div>
                    <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-[13px] leading-relaxed text-slate-600">
                        {aiSummary ? (
                            <pre className="whitespace-pre-wrap font-sans">{aiSummary}</pre>
                        ) : aiError ? (
                            <div className="text-xs text-slate-500">AI summary unavailable: {aiError}</div>
                        ) : (
                            <div className="text-xs text-slate-500">AI summary is not available yet.</div>
                        )}
                    </div>

                    {aiFocusProjects.length > 0 ? (
                        <div className="mt-4 grid gap-4 xl:grid-cols-2">
                            {aiFocusProjects.map((project) => (
                                <div key={project.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <a href={project.routes?.show} data-native="true" className="text-sm font-semibold text-slate-900 hover:text-teal-700">
                                                {project.name}
                                            </a>
                                            <div className="mt-1 text-xs text-slate-500">{project.customer_name}</div>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-semibold ring-1 ring-inset ${project.status_class}`}>
                                                {project.status_label}
                                            </span>
                                            <span className={`inline-flex rounded-full border px-3 py-1 text-[11px] font-semibold ${project.profitability?.tone_class || ''}`}>
                                                {project.profitability?.label}: {project.profitability?.profit_display}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="mt-4 flex flex-wrap gap-2 text-[11px]">
                                        <span className={`inline-flex rounded-full border px-3 py-1 font-semibold ${project.timeline?.tone_class || ''}`}>
                                            {project.timeline?.label}
                                        </span>
                                        <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-semibold text-slate-600">
                                            Budget: {project.financials?.budget_with_overhead_display}
                                        </span>
                                        <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-semibold text-slate-600">
                                            Payouts: {project.financials?.payouts_total_display}
                                        </span>
                                    </div>

                                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-[12px] text-slate-600">
                                            <div className="text-[10px] uppercase tracking-[0.18em] text-slate-400">Tasks</div>
                                            <div className="mt-2 font-semibold text-slate-900">
                                                {project.tasks?.completed || 0}/{project.tasks?.total || 0} done
                                            </div>
                                            <div className="mt-1">
                                                Open {project.tasks?.open || 0} | Progress {project.tasks?.completion_rate || 0}% | Overdue {project.tasks?.overdue || 0}
                                            </div>
                                            <div className="mt-2 text-slate-500">
                                                Next: {project.tasks?.next_due ? `${project.tasks.next_due.title} (${project.tasks.next_due.due})` : 'No active task deadline'}
                                            </div>
                                        </div>
                                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-[12px] text-slate-600">
                                            <div className="text-[10px] uppercase tracking-[0.18em] text-slate-400">Subtasks</div>
                                            <div className="mt-2 font-semibold text-slate-900">
                                                {project.subtasks?.completed || 0}/{project.subtasks?.total || 0} done
                                            </div>
                                            <div className="mt-1">
                                                Open {project.subtasks?.open || 0} | Progress {project.subtasks?.completion_rate || 0}% | Overdue {project.subtasks?.overdue || 0}
                                            </div>
                                            <div className="mt-2 text-slate-500">
                                                Next: {project.subtasks?.next_due ? `${project.subtasks.next_due.title} (${project.subtasks.next_due.due})` : 'No active subtask deadline'}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-[12px] text-slate-600">
                                        <div className="text-[10px] uppercase tracking-[0.18em] text-slate-400">Timeline</div>
                                        <div className="mt-2">
                                            Start {project.timeline?.start || '--'} | Expected {project.timeline?.expected_end || '--'} | Due {project.timeline?.due || '--'}
                                        </div>
                                        <div className="mt-1 text-slate-500">{project.timeline?.note}</div>
                                    </div>

                                    <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-[12px] text-slate-600">
                                        <div className="text-[10px] uppercase tracking-[0.18em] text-slate-400">Project Chat</div>
                                        <div className="mt-2 text-slate-700">
                                            {project.project_chat?.summary || project.project_chat?.latest_activity || 'No project chat summary yet.'}
                                        </div>
                                    </div>

                                    {asArray(project.task_chats).length > 0 ? (
                                        <div className="mt-3 space-y-2">
                                            {asArray(project.task_chats).map((chat, index) => (
                                                <div key={`${project.id}-task-chat-${index}`} className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-[12px] text-slate-600">
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <div className="font-semibold text-slate-900">{chat.task_title}</div>
                                                        <div className="text-[11px] text-slate-500">Due {chat.due_date || '--'} | Messages {chat.messages_count || 0}</div>
                                                    </div>
                                                    <div className="mt-2 text-slate-700">
                                                        {chat.summary || chat.latest_activity || 'No task chat summary yet.'}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    ) : null}
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="card p-6">
                        <div className="section-label">Status Breakdown</div>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            {statusCards.map((card) => (
                                <a key={card.key} href={card.href} data-native="true" className="rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                                    <div className="text-xs uppercase tracking-[0.18em] text-slate-500">{card.label}</div>
                                    <div className="mt-2 flex items-center justify-between gap-3">
                                        <div className="text-2xl font-semibold text-slate-900">{card.count}</div>
                                        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${card.badge_class}`}>{card.label}</span>
                                    </div>
                                </a>
                            ))}
                        </div>
                    </div>

                    <div className="card p-6">
                        <div className="section-label">Type Breakdown</div>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            {typeCards.map((card) => (
                                <a key={card.key} href={card.href} data-native="true" className="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-center transition hover:-translate-y-0.5 hover:shadow-sm">
                                    <div className="text-xs uppercase tracking-[0.18em] text-slate-500">{card.label}</div>
                                    <div className="mt-2 text-2xl font-semibold text-slate-900">{card.count}</div>
                                </a>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="card p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div className="section-label">Risk Projects</div>
                            <div className="mt-1 text-sm text-slate-500">Projects with hold status, overdue work, or near-term due pressure.</div>
                        </div>
                        <a href={routes?.all} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">
                            Open Full List
                        </a>
                    </div>

                    <div className="mt-5 overflow-x-auto rounded-2xl border border-slate-200">
                        <table className="w-full min-w-[880px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Project</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Due</th>
                                    <th className="px-4 py-3 text-right">Overdue</th>
                                    <th className="px-4 py-3 text-right">Due Soon</th>
                                    <th className="px-4 py-3 text-right">Open</th>
                                    <th className="px-4 py-3 text-right">Progress</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {riskProjects.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-6 text-center text-slate-500">No risky projects found.</td>
                                    </tr>
                                ) : riskProjects.map((project) => (
                                    <tr key={project.id} className="border-t border-slate-100">
                                        <td className="px-4 py-3 font-semibold text-slate-900">{project.name}</td>
                                        <td className="px-4 py-3 text-slate-600">{project.customer_name}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${project.status_class}`}>
                                                {project.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{project.due_date}</td>
                                        <td className="px-4 py-3 text-right text-rose-600">{project.overdue_tasks}</td>
                                        <td className="px-4 py-3 text-right text-amber-600">{project.due_soon_tasks}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{project.open_tasks}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{project.completion_rate}%</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="inline-flex items-center gap-2">
                                                <a href={project.routes?.show} data-native="true" className="font-semibold text-slate-700 hover:text-teal-700">View</a>
                                                <a href={project.routes?.edit} data-native="true" className="font-semibold text-slate-700 hover:text-teal-700">Edit</a>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="card p-6">
                    <div className="section-label">Recent Projects</div>
                    <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {recentProjects.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-sm text-slate-500">
                                No recent projects found.
                            </div>
                        ) : recentProjects.map((project) => (
                            <div key={project.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <a href={project.routes?.show} data-native="true" className="text-base font-semibold text-slate-900 hover:text-teal-700">
                                            {project.name}
                                        </a>
                                        <div className="mt-1 text-sm text-slate-500">{project.customer_name}</div>
                                    </div>
                                    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${project.status_class}`}>
                                        {project.status_label}
                                    </span>
                                </div>
                                <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div className="text-xs uppercase tracking-[0.18em] text-slate-500">Open</div>
                                        <div className="mt-1 font-semibold text-slate-900">{project.open_tasks}</div>
                                    </div>
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div className="text-xs uppercase tracking-[0.18em] text-slate-500">Progress</div>
                                        <div className="mt-1 font-semibold text-slate-900">{project.completion_rate}%</div>
                                    </div>
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div className="text-xs uppercase tracking-[0.18em] text-slate-500">Due</div>
                                        <div className="mt-1 font-semibold text-slate-900">{project.due_date}</div>
                                    </div>
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div className="text-xs uppercase tracking-[0.18em] text-slate-500">Maintenance</div>
                                        <div className="mt-1 font-semibold text-slate-900">{project.active_maintenances}</div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
