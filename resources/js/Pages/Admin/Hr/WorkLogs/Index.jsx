import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Work Logs',
    selectedMonth = '',
    selectedEmployeeId = null,
    employees = [],
    dailyLogs = [],
    pagination = {},
    routes = {},
}) {
    const summary = useMemo(() => {
        const rows = Array.isArray(dailyLogs) ? dailyLogs : [];

        const toSeconds = (duration) => {
            const parts = String(duration || '00:00:00').split(':').map((part) => Number(part || 0));
            if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part) || part < 0)) {
                return 0;
            }

            return (parts[0] * 3600) + (parts[1] * 60) + parts[2];
        };

        const formatDuration = (seconds) => {
            const safe = Math.max(0, Number(seconds) || 0);
            const hours = Math.floor(safe / 3600);
            const minutes = Math.floor((safe % 3600) / 60);
            const secs = safe % 60;

            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        };

        const sessions = rows.reduce((carry, row) => carry + Number(row?.sessions_count || 0), 0);
        const activeSeconds = rows.reduce((carry, row) => carry + toSeconds(row?.active_duration), 0);
        const avgCoverage = rows.length
            ? rows.reduce((carry, row) => carry + Number(row?.coverage_percent || 0), 0) / rows.length
            : 0;
        const estimatedSalary = rows.reduce((carry, row) => {
            const value = Number(String(row?.estimated_amount || 0).replace(/,/g, ''));
            return carry + (Number.isFinite(value) ? value : 0);
        }, 0);
        const primaryCurrency = rows[0]?.currency || 'BDT';

        return {
            rowsCount: rows.length,
            sessions,
            activeDuration: formatDuration(activeSeconds),
            averageCoverage: `${avgCoverage.toFixed(1)}%`,
            estimatedSalary: `${primaryCurrency} ${estimatedSalary.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
        };
    }, [dailyLogs]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR Operations</div>
                    <div className="text-2xl font-semibold text-slate-900">Work Logs</div>
                    <div className="text-sm text-slate-500">Daily employee work session summary with coverage and estimated salary impact.</div>
                </div>
            </div>

            <div className="mb-6 card p-6">
                <form method="GET" action={routes?.index} data-native="true" className="grid gap-3 md:grid-cols-4">
                    <div>
                        <label htmlFor="workLogMonth" className="text-xs uppercase tracking-[0.2em] text-slate-500">Month</label>
                        <input id="workLogMonth" type="month" name="month" defaultValue={selectedMonth} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label htmlFor="workLogEmployee" className="text-xs uppercase tracking-[0.2em] text-slate-500">Employee</label>
                        <select id="workLogEmployee" name="employee_id" defaultValue={selectedEmployeeId ?? ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">All employees</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>{employee.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="md:col-span-2 flex items-end gap-2">
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
                        <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Reset</a>
                    </div>
                </form>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <KpiCard title="Daily rows" value={summary.rowsCount} />
                <KpiCard title="Total sessions" value={summary.sessions} />
                <KpiCard title="Active time" value={summary.activeDuration} />
                <KpiCard title="Avg coverage" value={summary.averageCoverage} />
                <KpiCard title="Est. salary (page)" value={summary.estimatedSalary} />
            </div>

            <div className="card p-6 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500 border-b border-slate-200">
                                <th className="py-2 px-3">Employee</th>
                                <th className="py-2 px-3 whitespace-nowrap">Date</th>
                                <th className="py-2 px-3 whitespace-nowrap text-right">Sessions</th>
                                <th className="py-2 px-3 whitespace-nowrap">First Start</th>
                                <th className="py-2 px-3 whitespace-nowrap">Last Activity</th>
                                <th className="py-2 px-3 whitespace-nowrap text-right">Active Time</th>
                                <th className="py-2 px-3 whitespace-nowrap text-right">Required</th>
                                <th className="py-2 px-3 whitespace-nowrap text-right">Coverage</th>
                                <th className="py-2 px-3 whitespace-nowrap text-right">Est. Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            {dailyLogs.length === 0 ? (
                                <tr><td colSpan={9} className="py-3 px-3 text-center text-slate-500">No work logs.</td></tr>
                            ) : dailyLogs.map((log, index) => (
                                <tr key={`${log.employee_name}-${log.work_date}-${index}`} className="border-b border-slate-100">
                                    <td className="py-2 px-3 font-medium text-slate-900">{log.employee_name}</td>
                                    <td className="py-2 px-3 whitespace-nowrap tabular-nums">{log.work_date}</td>
                                    <td className="py-2 px-3 text-right whitespace-nowrap tabular-nums">{log.sessions_count}</td>
                                    <td className="py-2 px-3 whitespace-nowrap tabular-nums">{log.first_started_at}</td>
                                    <td className="py-2 px-3 whitespace-nowrap tabular-nums">{log.last_activity_at}</td>
                                    <td className="py-2 px-3 text-right whitespace-nowrap tabular-nums">{log.active_duration}</td>
                                    <td className="py-2 px-3 text-right whitespace-nowrap tabular-nums">{log.required_duration}</td>
                                    <td className="py-2 px-3 text-right">
                                        <CoverageBadge value={log.coverage_percent} />
                                    </td>
                                    <td className="py-2 px-3 text-right whitespace-nowrap tabular-nums font-semibold text-slate-900">{log.currency} {log.estimated_amount}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {pagination?.has_pages ? (
                    <div className="mt-4 flex items-center justify-between gap-2 text-sm">
                        <a href={pagination?.previous_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.previous_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Previous</a>
                        <a href={pagination?.next_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.next_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Next</a>
                    </div>
                ) : null}
            </div>
        </>
    );
}

function KpiCard({ title, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <div className="mt-1 whitespace-nowrap text-xl font-semibold text-slate-900 tabular-nums">{value}</div>
        </div>
    );
}

function CoverageBadge({ value }) {
    const coverage = Number(value || 0);
    const tone = coverage >= 100
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : coverage >= 70
            ? 'border-amber-200 bg-amber-50 text-amber-700'
            : 'border-rose-200 bg-rose-50 text-rose-700';

    return (
        <span className={`inline-flex whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-semibold tabular-nums ${tone}`}>
            {coverage}%
        </span>
    );
}
