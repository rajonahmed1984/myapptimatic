import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Attendance',
    selectedDate = '',
    isPaidHoliday = false,
    employees = [],
    routes = {},
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR</div>
                    <div className="text-2xl font-semibold text-slate-900">Daily Manual Attendance</div>
                    <div className="text-sm text-slate-500">Only active full-time employees are listed.</div>
                    {isPaidHoliday ? (
                        <div className="mt-2 inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            Paid holiday: employees default to Present for this date
                        </div>
                    ) : null}
                </div>
            </div>

            <div className="card p-6">
                <form method="GET" action={routes?.index} data-native="true" className="mb-5 flex flex-wrap items-end gap-2">
                    <div>
                        <label htmlFor="attendanceDate" className="text-xs uppercase tracking-[0.2em] text-slate-500">Date</label>
                        <input id="attendanceDate" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="date" defaultValue={selectedDate} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Load</button>
                    <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Today</a>
                </form>

                <form method="POST" action={routes?.store} data-native="true">
                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''} />
                    <input type="hidden" name="date" value={selectedDate} />

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th className="py-2 px-3">Employee</th>
                                    <th className="py-2 px-3">Department</th>
                                    <th className="py-2 px-3">Designation</th>
                                    <th className="py-2 px-3">Status</th>
                                    <th className="py-2 px-3">Note</th>
                                    <th className="py-2 px-3">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                {employees.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-3 px-3 text-center text-slate-500">No active full-time employees found.</td>
                                    </tr>
                                ) : employees.map((employee, index) => (
                                    <tr key={employee.id} className="border-b border-slate-100">
                                        <td className="py-2 px-3">
                                            <div className="font-semibold text-slate-900">{employee.name}</div>
                                            <div className="text-xs text-slate-500">{employee.email}</div>
                                            <input type="hidden" name={`records[${index}][employee_id]`} value={employee.id} />
                                        </td>
                                        <td className="py-2 px-3">{employee.department}</td>
                                        <td className="py-2 px-3">{employee.designation}</td>
                                        <td className="py-2 px-3">
                                            <select name={`records[${index}][status]`} defaultValue={employee.status || ''} className="w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs">
                                                <option value="">Not set</option>
                                                <option value="present">Present</option>
                                                <option value="absent">Absent</option>
                                                <option value="leave">Leave</option>
                                                <option value="half_day">Half Day</option>
                                            </select>
                                        </td>
                                        <td className="py-2 px-3">
                                            <input type="text" name={`records[${index}][note]`} defaultValue={employee.note || ''} placeholder="Optional note" className="w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs" />
                                        </td>
                                        <td className="py-2 px-3 text-xs text-slate-500">
                                            {employee.recorder_name ? employee.recorder_name : isPaidHoliday && employee.status === 'present' ? 'Paid holiday (System)' : '--'}
                                            {employee.updated_at ? <div>{employee.updated_at}</div> : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {employees.length > 0 ? (
                        <div className="mt-4 flex justify-end">
                            <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save Attendance</button>
                        </div>
                    ) : null}
                </form>
            </div>
        </>
    );
}
