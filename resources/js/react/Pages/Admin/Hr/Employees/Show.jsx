import React from 'react';
import { Head } from '@inertiajs/react';

const currency = (value, code = 'BDT') => {
    const amount = Number(value || 0);
    if (Number.isNaN(amount)) return `${code} 0.00`;
    return `${code} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const asArray = (value) => (Array.isArray(value) ? value : []);
const asObject = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});
const formatSeconds = (value) => {
    const total = Math.max(0, Number(value || 0));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
};

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
    projectStatusCounts = {},
    projectTaskStatusCounts = {},
    recentEarnings = [],
    recentPayouts = [],
    advanceProjects = [],
    recentAdvanceTransactions = [],
    recentWorkSessions = [],
    recentWorkSummaries = [],
    recentPayrollItems = [],
    recentSalaryAdvances = [],
    workSessionStats = {},
    payrollSourceNote = null,
    paymentMethods = [],
    documentLinks = {},
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
    const projectStatusClass = (status) => {
        const key = String(status || '').toLowerCase();
        if (key === 'completed' || key === 'complete') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
        if (key === 'ongoing' || key === 'in_progress') return 'border-blue-200 bg-blue-50 text-blue-700';
        if (key === 'cancelled' || key === 'canceled') return 'border-rose-200 bg-rose-50 text-rose-700';
        if (key === 'on_hold') return 'border-amber-200 bg-amber-50 text-amber-700';
        return 'border-slate-300 bg-slate-50 text-slate-700';
    };
    const mediaUrl = (path) => {
        if (!path) return null;
        if (String(path).startsWith('http://') || String(path).startsWith('https://')) return path;
        return `/storage/${String(path).replace(/^\/+/, '')}`;
    };
    const payoutProofUrl = (id) => {
        const template = routes?.payoutProof || '';
        return template ? template.replace('__ID__', String(id)) : '#';
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
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Project Tasks</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{taskSummary?.total || 0}</div>
                            <div className="mt-3 flex flex-wrap gap-2 text-xs">
                                <span className="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-slate-600">Projects: {taskSummary?.projects || 0}</span>
                                <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">Pending: {taskSummary?.pending || 0}</span>
                                <span className="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-blue-700">In progress: {taskSummary?.in_progress || 0}</span>
                                <span className="rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-slate-600">Blocked: {taskSummary?.blocked || 0}</span>
                                <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700">Completed: {taskSummary?.completed || 0}</span>
                            </div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Subtasks</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{subtaskSummary?.total || 0}</div>
                            <div className="mt-3 flex flex-wrap gap-2 text-xs">
                                <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700">Completed: {subtaskSummary?.completed || 0}</span>
                                <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">Pending: {subtaskSummary?.pending || 0}</span>
                            </div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Task Progress</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{taskProgress?.percent || 0}%</div>
                            <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200">
                                <div className="h-full rounded-full bg-emerald-500" style={{ width: `${taskProgress?.percent || 0}%` }} />
                            </div>
                            <div className="mt-2 text-xs text-slate-500">Based on completed tasks</div>
                        </div>
                    </div>

                    <div className="mt-4 card p-6">
                        <div className="grid gap-4 text-base text-slate-800 md:grid-cols-2">
                            <div className="space-y-3">
                                <div><span className="font-semibold">Salary Type:</span> {String(salaryType || '--').replaceAll('_', ' ')}</div>
                                <div><span className="font-semibold">Effective From:</span> {employee?.active_compensation?.effective_from || '--'}</div>
                                <div><span className="font-semibold">Designation:</span> {employee?.designation || '--'}</div>
                                <div><span className="font-semibold">Employment Type:</span> {String(employee?.employment_type || '--').replaceAll('_', ' ')}</div>
                                <div><span className="font-semibold">Join Date:</span> {employee?.join_date || '--'}</div>
                                <div><span className="font-semibold">Address:</span> {employee?.address || '--'}</div>
                                <div><span className="font-semibold">Linked User:</span> {employee?.user?.name || '--'} {employee?.user?.email ? `(${employee.user.email})` : ''}</div>
                            </div>
                            <div className="space-y-3">
                                <div><span className="font-semibold">Basic Pay:</span> {currency(summary?.basic_pay, summary?.currency || 'BDT')}</div>
                                <div><span className="font-semibold">Department:</span> {employee?.department || '--'}</div>
                                <div><span className="font-semibold">Manager:</span> {employee?.manager?.name || '--'}</div>
                                <div><span className="font-semibold">Work Mode:</span> {String(employee?.work_mode || '--').replaceAll('_', ' ')}</div>
                                <div><span className="font-semibold">Phone:</span> {employee?.phone || '--'}</div>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 card p-6">
                        <div className="mb-3 text-lg font-semibold text-slate-900">Documents</div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <div className="mb-2 text-xs uppercase tracking-[0.28em] text-slate-500">Avatar</div>
                                {mediaUrl(employee?.photo_path || employee?.user?.avatar_path) ? (
                                    <img
                                        src={mediaUrl(employee?.photo_path || employee?.user?.avatar_path)}
                                        alt="Employee avatar"
                                        className="h-16 w-16 rounded-full border border-slate-200 object-cover"
                                    />
                                ) : (
                                    <div className="text-sm text-slate-500">No avatar</div>
                                )}
                            </div>
                            <div>
                                <div className="mb-2 text-xs uppercase tracking-[0.28em] text-slate-500">NID</div>
                                {documentLinks?.nid ? (
                                    <a href={documentLinks.nid} data-native="true" className="text-sm font-medium text-teal-700 hover:text-teal-600">View/Download</a>
                                ) : (
                                    <div className="text-sm text-slate-500">Not uploaded</div>
                                )}
                            </div>
                            <div>
                                <div className="mb-2 text-xs uppercase tracking-[0.28em] text-slate-500">CV</div>
                                {documentLinks?.cv ? (
                                    <a href={documentLinks.cv} data-native="true" className="text-sm font-medium text-teal-700 hover:text-teal-600">View/Download</a>
                                ) : (
                                    <div className="text-sm text-slate-500">Not uploaded</div>
                                )}
                            </div>
                        </div>
                    </div>
                </>
            )}

            {tab === 'projects' && (
                <>
                    <div className="card p-4">
                        <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Assigned Projects</div>
                        <div className="mt-2 text-4xl font-semibold leading-none text-slate-900">{asArray(projects).length}</div>
                        <div className="mt-3 flex flex-wrap gap-2 text-xs">
                            <span className="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-blue-700">Ongoing: {Number(asObject(projectStatusCounts)?.ongoing || 0)}</span>
                            <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">On hold: {Number(asObject(projectStatusCounts)?.on_hold || 0)}</span>
                            <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700">Completed: {Number(asObject(projectStatusCounts)?.completed || 0)}</span>
                            <span className="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-rose-700">Cancelled: {Number(asObject(projectStatusCounts)?.cancelled || asObject(projectStatusCounts)?.canceled || 0)}</span>
                        </div>
                    </div>

                    <div className="mt-4 card p-6">
                        <div className="mb-4 text-xl font-semibold text-slate-900">Projects</div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                        <th className="py-2 px-3">Project</th>
                                        <th className="py-2 px-3">Status</th>
                                        <th className="py-2 px-3">Customer</th>
                                        <th className="py-2 px-3">Assigned Tasks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {asArray(projects).length === 0 ? (
                                        <tr><td colSpan={4} className="py-3 px-3 text-center text-slate-500">No projects found.</td></tr>
                                    ) : asArray(projects).map((project) => {
                                        const statusCounts = asObject(asObject(projectTaskStatusCounts)[project.id]);
                                        const completed = Number(statusCounts.completed || 0) + Number(statusCounts.done || 0);
                                        const assignedTotal = Object.values(statusCounts).reduce((sum, v) => sum + Number(v || 0), 0);
                                        return (
                                            <tr key={project.id} className="border-t border-slate-100 align-top">
                                                <td className="py-3 px-3">
                                                    <div className="font-semibold text-teal-700">{project.name}</div>
                                                    <div className="text-xs text-slate-500">Project ID: {project.id}</div>
                                                </td>
                                                <td className="py-3 px-3">
                                                    <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${projectStatusClass(project.status)}`}>
                                                        {String(project.status || '--').replaceAll('_', ' ')}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-3">{project.customer?.name || '--'}</td>
                                                <td className="py-3 px-3">
                                                    <div className="font-medium text-slate-800">Assigned tasks: {assignedTotal}</div>
                                                    <div className="mt-2 flex flex-wrap gap-2 text-xs">
                                                        <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">Pending: {Number(statusCounts.pending || 0)}</span>
                                                        <span className="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-blue-700">In progress: {Number(statusCounts.in_progress || 0)}</span>
                                                        <span className="rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-slate-700">Blocked: {Number(statusCounts.blocked || 0)}</span>
                                                        <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700">Completed: {completed}</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </>
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
                <div className="space-y-4">
                    <div className="card p-6 overflow-hidden">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <div className="text-sm font-semibold text-slate-900">Recent Payouts</div>
                            <a
                                href={routes?.payoutCreate || '#'}
                                data-native="true"
                                className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300 hover:text-emerald-800"
                            >
                                Pay payable ({Number(projectBaseEarnings?.payable || 0).toFixed(2)})
                            </a>
                        </div>
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

                    <form method="POST" action={routes?.advancePayout} encType="multipart/form-data" data-native="true" className="card p-6">
                        <input type="hidden" name="_token" value={token} />
                        <div className="text-xl font-semibold text-slate-900">Record advance payout</div>
                        <div className="mt-1 text-sm text-slate-500">Advance payments are deducted from future project payouts.</div>

                        <div className="mt-5 grid gap-4 md:grid-cols-5">
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Project</div>
                                <select name="project_id" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    <option value="">Select project</option>
                                    {asArray(advanceProjects).map((project) => (
                                        <option key={project.id} value={project.id}>
                                            {project.name}{project?.customer?.name ? ` (${project.customer.name})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Amount</div>
                                <input type="number" name="amount" step="0.01" min="0.01" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="0.00" required />
                            </div>
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Currency</div>
                                <input type="text" name="currency" defaultValue={summary?.currency || 'BDT'} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Method</div>
                                <select name="payout_method" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    <option value="">Select</option>
                                    {asArray(paymentMethods).map((method) => (
                                        <option key={method.code} value={method.code}>{method.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Payment Date</div>
                                <input type="date" name="paid_at" defaultValue={new Date().toISOString().slice(0, 10)} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Reference</div>
                                <input type="text" name="reference" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Txn / Note" />
                            </div>
                            <div className="md:col-span-2">
                                <div className="mb-1 text-sm text-slate-600">Payment Proof</div>
                                <input type="file" name="payment_proof" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5" />
                            </div>
                            <div className="md:col-span-2">
                                <div className="mb-1 text-sm text-slate-600">Note</div>
                                <input type="text" name="note" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional note" />
                            </div>
                        </div>

                        <div className="mt-4">
                            <button type="submit" className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save advance payout</button>
                        </div>
                    </form>

                    <div className="card p-6 overflow-hidden">
                        <div className="text-sm font-semibold text-slate-900">Advance Transactions</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">Date</th><th className="py-2 px-3">Amount</th><th className="py-2 px-3">Method</th><th className="py-2 px-3">Reference</th><th className="py-2 px-3">Proof</th><th className="py-2 px-3">Note</th></tr></thead>
                                <tbody>
                                    {asArray(recentAdvanceTransactions).length === 0 ? <tr><td colSpan={6} className="py-3 px-3 text-center text-slate-500">No advance transactions found.</td></tr> : asArray(recentAdvanceTransactions).map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100">
                                            <td className="py-2 px-3">{item.paid_at || '--'}</td>
                                            <td className="py-2 px-3">{currency(item.amount, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{item.payout_method || '--'}</td>
                                            <td className="py-2 px-3">{item.reference || '--'}</td>
                                            <td className="py-2 px-3">
                                                {item?.metadata?.payment_proof_path ? (
                                                    <a href={payoutProofUrl(item.id)} data-native="true" className="text-teal-700 hover:text-teal-600">View/Download</a>
                                                ) : '--'}
                                            </td>
                                            <td className="py-2 px-3">{item.note || '--'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'timesheets' && (
                <div className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Worked Today</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{formatSeconds(workSessionStats?.today_active_seconds || 0)}</div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Worked This Month</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{formatSeconds(workSessionStats?.month_active_seconds || 0)}</div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Work Coverage</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{workSessionStats?.coverage_percent || 0}%</div>
                            <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200">
                                <div className="h-full rounded-full bg-emerald-500" style={{ width: `${workSessionStats?.coverage_percent || 0}%` }} />
                            </div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Today Salary Projection</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{currency(workSessionStats?.today_salary_projection || 0, workSessionStats?.currency || summary?.currency || 'BDT')}</div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Month Salary Projection</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{currency(workSessionStats?.month_salary_projection || 0, workSessionStats?.currency || summary?.currency || 'BDT')}</div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Payroll Source</div>
                            <div className="mt-2 text-xs leading-5 text-slate-600">{payrollSourceNote || '--'}</div>
                        </div>
                    </div>

                    <div className="card p-6 overflow-hidden">
                        <div className="text-sm font-semibold text-slate-900">Recent Work Sessions</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">Date</th><th className="py-2 px-3">Started</th><th className="py-2 px-3">Ended</th><th className="py-2 px-3">Last Activity</th><th className="py-2 px-3 text-right">Active Time</th></tr></thead>
                                <tbody>
                                    {asArray(recentWorkSessions).length === 0 ? <tr><td colSpan={5} className="py-3 px-3 text-center text-slate-500">No work sessions found.</td></tr> : asArray(recentWorkSessions).map((item, idx) => (
                                        <tr key={`${item.work_date || 'd'}-${idx}`} className="border-b border-slate-100">
                                            <td className="py-2 px-3">{item.work_date || '--'}</td>
                                            <td className="py-2 px-3">{item.started_at || '--'}</td>
                                            <td className="py-2 px-3">{item.ended_at || '--'}</td>
                                            <td className="py-2 px-3">{item.last_activity_at || '--'}</td>
                                            <td className="py-2 px-3 text-right font-medium">{formatSeconds(item.active_seconds || 0)}</td>
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
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Worked Today</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{formatSeconds(workSessionStats?.today_active_seconds || 0)}</div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Worked This Month</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{formatSeconds(workSessionStats?.month_active_seconds || 0)}</div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Work Coverage</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{workSessionStats?.coverage_percent || 0}%</div>
                            <div className="mt-1 text-xs text-slate-500">Required: {formatSeconds(workSessionStats?.month_required_seconds || 0)}</div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Today Salary Projection</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{currency(workSessionStats?.today_salary_projection || 0, workSessionStats?.currency || summary?.currency || 'BDT')}</div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Month Salary Projection</div>
                            <div className="mt-2 text-2xl font-semibold text-slate-900">{currency(workSessionStats?.month_salary_projection || 0, workSessionStats?.currency || summary?.currency || 'BDT')}</div>
                        </div>
                        <div className="card p-4">
                            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Payroll Source</div>
                            <div className="mt-2 text-xs leading-5 text-slate-600">{payrollSourceNote || '--'}</div>
                        </div>
                    </div>

                    <form method="POST" action={routes?.advancePayout} encType="multipart/form-data" data-native="true" className="card p-6">
                        <input type="hidden" name="_token" value={token} />
                        <div className="text-xl font-semibold text-slate-900">Record salary advance</div>
                        <div className="mt-1 text-sm text-slate-500">This creates an advance payout entry for payroll tracking.</div>

                        <div className="mt-5 grid gap-4 md:grid-cols-5">
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Amount</div>
                                <input type="number" name="amount" step="0.01" min="0.01" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="0.00" required />
                            </div>
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Currency</div>
                                <input type="text" name="currency" defaultValue={summary?.currency || 'BDT'} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Method</div>
                                <select name="payout_method" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    <option value="">Select</option>
                                    {asArray(paymentMethods).map((method) => (
                                        <option key={method.code} value={method.code}>{method.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Reference</div>
                                <input type="text" name="reference" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Txn / Note" />
                            </div>
                            <div>
                                <div className="mb-1 text-sm text-slate-600">Payment Date</div>
                                <input type="date" name="paid_at" defaultValue={new Date().toISOString().slice(0, 10)} className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div className="md:col-span-2">
                                <div className="mb-1 text-sm text-slate-600">Payment Proof</div>
                                <input type="file" name="payment_proof" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5" />
                            </div>
                            <div className="md:col-span-2">
                                <div className="mb-1 text-sm text-slate-600">Note</div>
                                <input type="text" name="note" className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional note" />
                            </div>
                        </div>

                        <div className="mt-4">
                            <button type="submit" className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save salary advance</button>
                        </div>
                    </form>

                    <div className="card p-6 overflow-hidden">
                        <div className="text-xl font-semibold text-slate-900">Salary Advance Transactions</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">Date</th><th className="py-2 px-3">Amount</th><th className="py-2 px-3">Method</th><th className="py-2 px-3">Reference</th><th className="py-2 px-3">Proof</th><th className="py-2 px-3">Note</th></tr></thead>
                                <tbody>
                                    {asArray(recentSalaryAdvances).length === 0 ? <tr><td colSpan={6} className="py-3 px-3 text-center text-slate-500">No advance transactions found.</td></tr> : asArray(recentSalaryAdvances).map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100">
                                            <td className="py-2 px-3">{item.paid_at || '--'}</td>
                                            <td className="py-2 px-3">{currency(item.amount, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{item.payout_method || '--'}</td>
                                            <td className="py-2 px-3">{item.reference || '--'}</td>
                                            <td className="py-2 px-3">
                                                {item?.metadata?.payment_proof_path ? (
                                                    <a href={payoutProofUrl(item.id)} data-native="true" className="text-teal-700 hover:text-teal-600">View/Download</a>
                                                ) : '--'}
                                            </td>
                                            <td className="py-2 px-3">{item.note || '--'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="card p-6 overflow-hidden">
                        <div className="text-xl font-semibold text-slate-900">Recent Payroll Items</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead><tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500"><th className="py-2 px-3">Period</th><th className="py-2 px-3">Pay Type</th><th className="py-2 px-3">Base</th><th className="py-2 px-3">Hours / Attendance</th><th className="py-2 px-3">Overtime</th><th className="py-2 px-3">Bonus</th><th className="py-2 px-3">Penalty</th><th className="py-2 px-3">Advance</th><th className="py-2 px-3">Est. Subtotal</th><th className="py-2 px-3">Gross</th><th className="py-2 px-3">Deduction</th><th className="py-2 px-3">Net</th><th className="py-2 px-3">Status</th><th className="py-2 px-3">Paid At</th></tr></thead>
                                <tbody>
                                    {asArray(recentPayrollItems).length === 0 ? <tr><td colSpan={14} className="py-3 px-3 text-center text-slate-500">No payroll items found.</td></tr> : asArray(recentPayrollItems).map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100">
                                            <td className="py-2 px-3">{item?.period?.period_key || item.id}</td>
                                            <td className="py-2 px-3">{item.pay_type || '--'}</td>
                                            <td className="py-2 px-3">{currency(item.computed_base_pay ?? item.base_pay, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{item.computed_hours_attendance || '--'}</td>
                                            <td className="py-2 px-3">
                                                <div>{item.computed_overtime_label || '--'}</div>
                                                <div className="text-xs text-slate-500">{currency(item.computed_overtime_pay || 0, item.currency || summary?.currency || 'BDT')}</div>
                                            </td>
                                            <td className="py-2 px-3">{currency(item.computed_bonus || 0, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{currency(item.computed_penalty || 0, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{currency(item.computed_advance || 0, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{currency(item.computed_est_subtotal || 0, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{currency(item.computed_gross_pay ?? item.gross_pay, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{currency(item.computed_deduction || 0, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{currency(item.computed_net_pay ?? item.net_pay, item.currency || summary?.currency || 'BDT')}</td>
                                            <td className="py-2 px-3">{item.status || '--'}</td>
                                            <td className="py-2 px-3">{item.paid_at || '--'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="card p-6 overflow-hidden">
                        <div className="text-sm font-semibold text-slate-900">Daily Work Summaries</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                        <th className="py-2 px-3">Date</th>
                                        <th className="py-2 px-3">Active</th>
                                        <th className="py-2 px-3">Required</th>
                                        <th className="py-2 px-3">Generated Salary</th>
                                        <th className="py-2 px-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {asArray(recentWorkSummaries).length === 0 ? (
                                        <tr><td colSpan={5} className="py-3 px-3 text-center text-slate-500">No work summaries found.</td></tr>
                                    ) : asArray(recentWorkSummaries).map((item, idx) => (
                                        <tr key={`${item.work_date || 'summary'}-${idx}`} className="border-b border-slate-100">
                                            <td className="py-2 px-3">{item.work_date || '--'}</td>
                                            <td className="py-2 px-3">{formatSeconds(item.active_seconds || 0)}</td>
                                            <td className="py-2 px-3">{formatSeconds(item.required_seconds || 0)}</td>
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
