import React from 'react';
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
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <form method="GET" action={routes?.index} data-native="true" className="mb-5 grid gap-3 md:grid-cols-4">
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
            </div>

            <div className="card p-6">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="py-2 px-3">Employee</th>
                                <th className="py-2 px-3">Date</th>
                                <th className="py-2 px-3">Sessions</th>
                                <th className="py-2 px-3">First Start</th>
                                <th className="py-2 px-3">Last Activity</th>
                                <th className="py-2 px-3 text-right">Active Time</th>
                                <th className="py-2 px-3 text-right">Required</th>
                                <th className="py-2 px-3 text-right">Coverage</th>
                                <th className="py-2 px-3 text-right">Est. Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            {dailyLogs.length === 0 ? (
                                <tr><td colSpan={9} className="py-3 px-3 text-center text-slate-500">No work logs.</td></tr>
                            ) : dailyLogs.map((log, index) => (
                                <tr key={`${log.employee_name}-${log.work_date}-${index}`} className="border-b border-slate-100">
                                    <td className="py-2 px-3">{log.employee_name}</td>
                                    <td className="py-2 px-3">{log.work_date}</td>
                                    <td className="py-2 px-3">{log.sessions_count}</td>
                                    <td className="py-2 px-3">{log.first_started_at}</td>
                                    <td className="py-2 px-3">{log.last_activity_at}</td>
                                    <td className="py-2 px-3 text-right">{log.active_duration}</td>
                                    <td className="py-2 px-3 text-right">{log.required_duration}</td>
                                    <td className="py-2 px-3 text-right">{log.coverage_percent}%</td>
                                    <td className="py-2 px-3 text-right">{log.currency} {log.estimated_amount}</td>
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
