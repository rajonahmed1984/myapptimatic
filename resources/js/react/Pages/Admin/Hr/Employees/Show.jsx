import React from 'react';
import { Head } from '@inertiajs/react';

const currency = (value, code = 'BDT') => {
    const amount = Number(value || 0);
    if (Number.isNaN(amount)) return `${code} 0.00`;
    return `${code} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const asArray = (value) => (Array.isArray(value) ? value : []);

export default function Show({
    pageTitle = 'Employee',
    employee = {},
    tab = 'profile',
    summary = {},
    taskSummary = {},
    subtaskSummary = {},
    taskProgress = {},
    projectBaseEarnings = null,
    projects = [],
    recentEarnings = [],
    recentPayouts = [],
    recentWorkSessions = [],
    recentWorkSummaries = [],
    recentPayrollItems = [],
    workSessionStats = {},
    payrollSourceNote = null,
    routes = {},
}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const salaryType = summary?.salary_type || null;
    const isProjectBase = salaryType === 'project_base';
    const isMonthly = salaryType === 'monthly';

    const tabs = [
        { key: 'profile', label: 'Profile' },
        ...(isProjectBase ? [{ key: 'earnings', label: 'Recent Earnings' }, { key: 'payouts', label: 'Recent Payouts' }] : []),
        { key: 'projects', label: 'Projects' },
        ...(isMonthly ? [{ key: 'timesheets', label: 'Work Logs' }, { key: 'leave', label: 'Leave' }, { key: 'payroll', label: 'Payroll' }] : []),
    ];

    const tabHref = (nextTab) => {
        const base = routes?.show || '#';
        return `${base}?tab=${encodeURIComponent(nextTab)}`;
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Employee</div>
                    <div className="text-2xl font-semibold text-slate-900">{employee?.name}</div>
                    <div className="text-sm text-slate-500">{employee?.email}</div>
                </div>
                <div className="flex flex-wrap gap-3">
                    <form action={routes?.impersonate} method="POST" data-native="true">
                        <input type="hidden" name="_token" value={token} />
                        <button type="submit" className="rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-800">
                            Login as Employee
                        </button>
                    </form>
                    <a href={routes?.edit} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
                    <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
                </div>
            </div>

            <div className="mb-6 flex flex-wrap gap-3 text-sm font-semibold text-slate-700">
                {tabs.map((item) => (
                    <a
                        key={item.key}
                        href={tabHref(item.key)}
                        data-native="true"
                        className={`rounded-full border px-3 py-1 ${tab === item.key ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-700'}`}
                    >
                        {item.label}
                    </a>
                ))}
            </div>

            {tab === 'profile' && (
                <>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Status</div><div className="mt-2 text-2xl font-semibold text-slate-900">{String(employee?.status || '--').replace('_', ' ')}</div></div>
                        <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Salary Type</div><div className="mt-2 text-2xl font-semibold text-slate-900">{String(salaryType || '--').replaceAll('_', ' ')}</div></div>
                        <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Basic Pay</div><div className="mt-2 text-2xl font-semibold text-slate-900">{currency(summary?.basic_pay, summary?.currency || 'BDT')}</div></div>
                    </div>

                    {projectBaseEarnings ? (
                        <div className="mt-4 grid gap-4 md:grid-cols-4">
                            <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Total Earned</div><div className="mt-2 text-2xl font-semibold text-slate-900">{currency(projectBaseEarnings?.total_earned, summary?.currency || 'BDT')}</div></div>
                            <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Payable</div><div className="mt-2 text-2xl font-semibold text-amber-700">{currency(projectBaseEarnings?.payable, summary?.currency || 'BDT')}</div></div>
                            <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Paid</div><div className="mt-2 text-2xl font-semibold text-emerald-700">{currency(projectBaseEarnings?.paid, summary?.currency || 'BDT')}</div></div>
                            <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Advance Paid</div><div className="mt-2 text-2xl font-semibold text-slate-900">{currency(projectBaseEarnings?.advance_paid, summary?.currency || 'BDT')}</div></div>
                        </div>
                    ) : null}

                    <div className="mt-4 grid gap-4 md:grid-cols-3">
                        <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Project Tasks</div><div className="mt-2 text-2xl font-semibold text-slate-900">{taskSummary?.total || 0}</div></div>
                        <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Subtasks</div><div className="mt-2 text-2xl font-semibold text-slate-900">{subtaskSummary?.total || 0}</div></div>
                        <div className="card p-4"><div className="text-xs uppercase tracking-[0.28em] text-slate-500">Task Progress</div><div className="mt-2 text-2xl font-semibold text-slate-900">{taskProgress?.percent || 0}%</div></div>
                    </div>
                </>
            )}

            {tab === 'projects' && (
                <div className="card p-6 overflow-hidden">
                    <div className="text-sm font-semibold text-slate-900">Projects</div>
                    <div className="mt-3 overflow-x-auto">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">ID</th><th className="py-2 px-3">Name</th><th className="py-2 px-3">Status</th><th className="py-2 px-3">Customer</th></tr></thead>
                            <tbody>
                                {asArray(projects).length === 0 ? <tr><td colSpan={4} className="py-3 px-3 text-center text-slate-500">No projects found.</td></tr> : asArray(projects).map((project) => (
                                    <tr key={project.id} className="border-b border-slate-100">
                                        <td className="py-2 px-3">{project.id}</td>
                                        <td className="py-2 px-3 font-semibold text-slate-900">{project.name}</td>
                                        <td className="py-2 px-3">{project.status}</td>
                                        <td className="py-2 px-3">{project.customer?.name || '--'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {tab === 'earnings' && (
                <div className="card p-6 overflow-hidden">
                    <div className="text-sm font-semibold text-slate-900">Recent Earnings</div>
                    <div className="mt-3 overflow-x-auto">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">Project</th><th className="py-2 px-3">Earned</th><th className="py-2 px-3">Payable</th><th className="py-2 px-3">Updated</th></tr></thead>
                            <tbody>
                                {asArray(recentEarnings).length === 0 ? <tr><td colSpan={4} className="py-3 px-3 text-center text-slate-500">No earnings found.</td></tr> : asArray(recentEarnings).map((item) => (
                                    <tr key={item.id} className="border-b border-slate-100">
                                        <td className="py-2 px-3 font-semibold text-slate-900">{item.name}</td>
                                        <td className="py-2 px-3">{currency(item.contract_employee_total_earned, item.currency || summary?.currency || 'BDT')}</td>
                                        <td className="py-2 px-3">{currency(item.contract_employee_payable, item.currency || summary?.currency || 'BDT')}</td>
                                        <td className="py-2 px-3">{item.updated_at || '--'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {tab === 'payouts' && (
                <div className="card p-6 overflow-hidden">
                    <div className="text-sm font-semibold text-slate-900">Recent Payouts</div>
                    <div className="mt-3 overflow-x-auto">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">Date</th><th className="py-2 px-3">Amount</th><th className="py-2 px-3">Method</th><th className="py-2 px-3">Reference</th></tr></thead>
                            <tbody>
                                {asArray(recentPayouts).length === 0 ? <tr><td colSpan={4} className="py-3 px-3 text-center text-slate-500">No payouts found.</td></tr> : asArray(recentPayouts).map((item) => (
                                    <tr key={item.id} className="border-b border-slate-100">
                                        <td className="py-2 px-3">{item.paid_at || '--'}</td>
                                        <td className="py-2 px-3">{currency(item.amount, item.currency || summary?.currency || 'BDT')}</td>
                                        <td className="py-2 px-3">{item.payout_method || '--'}</td>
                                        <td className="py-2 px-3">{item.reference || '--'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {tab === 'timesheets' && (
                <div className="space-y-4">
                    <div className="card p-6">
                        <div className="text-sm font-semibold text-slate-900">Work Session Stats</div>
                        <div className="mt-2 text-sm text-slate-700">Today: {(workSessionStats?.today_active_seconds || 0)} sec | Month: {(workSessionStats?.month_active_seconds || 0)} sec | Coverage: {(workSessionStats?.coverage_percent || 0)}%</div>
                    </div>
                    <div className="card p-6 overflow-hidden">
                        <div className="text-sm font-semibold text-slate-900">Recent Work Sessions</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">Date</th><th className="py-2 px-3">Start</th><th className="py-2 px-3">End</th><th className="py-2 px-3">Active Seconds</th></tr></thead>
                                <tbody>
                                    {asArray(recentWorkSessions).length === 0 ? <tr><td colSpan={4} className="py-3 px-3 text-center text-slate-500">No work sessions found.</td></tr> : asArray(recentWorkSessions).map((item, idx) => (
                                        <tr key={`${item.work_date || 'd'}-${idx}`} className="border-b border-slate-100">
                                            <td className="py-2 px-3">{item.work_date || '--'}</td>
                                            <td className="py-2 px-3">{item.started_at || '--'}</td>
                                            <td className="py-2 px-3">{item.ended_at || '--'}</td>
                                            <td className="py-2 px-3">{item.active_seconds || 0}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'payroll' && (
                <div className="space-y-4">
                    {payrollSourceNote ? <div className="card p-4 text-sm text-slate-600">{payrollSourceNote}</div> : null}
                    <div className="card p-6 overflow-hidden">
                        <div className="text-sm font-semibold text-slate-900">Recent Payroll Items</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">ID</th><th className="py-2 px-3">Status</th><th className="py-2 px-3">Gross</th><th className="py-2 px-3">Net</th><th className="py-2 px-3">Paid At</th></tr></thead>
                                <tbody>
                                    {asArray(recentPayrollItems).length === 0 ? <tr><td colSpan={5} className="py-3 px-3 text-center text-slate-500">No payroll items found.</td></tr> : asArray(recentPayrollItems).map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100">
                                            <td className="py-2 px-3">{item.id}</td>
                                            <td className="py-2 px-3">{item.status || '--'}</td>
                                            <td className="py-2 px-3">{currency(item.computed_gross_pay ?? item.gross_pay, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{currency(item.computed_net_pay ?? item.net_pay, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{item.paid_at || '--'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div className="card p-6 overflow-hidden">
                        <div className="text-sm font-semibold text-slate-900">Recent Work Summaries</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">Date</th><th className="py-2 px-3">Active Seconds</th><th className="py-2 px-3">Required</th><th className="py-2 px-3">Generated Salary</th><th className="py-2 px-3">Status</th></tr></thead>
                                <tbody>
                                    {asArray(recentWorkSummaries).length === 0 ? <tr><td colSpan={5} className="py-3 px-3 text-center text-slate-500">No work summaries found.</td></tr> : asArray(recentWorkSummaries).map((item, idx) => (
                                        <tr key={`${item.work_date || 'summary'}-${idx}`} className="border-b border-slate-100">
                                            <td className="py-2 px-3">{item.work_date || '--'}</td>
                                            <td className="py-2 px-3">{item.active_seconds || 0}</td>
                                            <td className="py-2 px-3">{item.required_seconds || 0}</td>
                                            <td className="py-2 px-3">{currency(item.generated_salary_amount, summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{item.status || '--'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'leave' && (
                <div className="card p-6 text-sm text-slate-600">Leave details remain available in HR leave modules. This page shell is now React/Inertia.</div>
            )}
        </>
    );
}
