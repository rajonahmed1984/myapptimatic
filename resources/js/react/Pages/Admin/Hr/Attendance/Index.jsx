import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Attendance',
    selectedDate = '',
    isPaidHoliday = false,
    employees = [],
    routes = {},
}) {
    const formatDateForInput = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const shiftDate = (baseDate, days) => {
        const next = new Date(baseDate);
        next.setDate(next.getDate() + days);
        return next;
    };

    const datePickerValue = useMemo(() => {
        if (typeof selectedDate === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(selectedDate)) {
            return selectedDate;
        }

        return formatDateForInput(new Date());
    }, [selectedDate]);

    const selectedDateObject = useMemo(() => {
        const parsed = new Date(`${datePickerValue}T00:00:00`);
        return Number.isNaN(parsed.getTime()) ? new Date() : parsed;
    }, [datePickerValue]);

    const quickDates = useMemo(() => {
        const today = new Date();
        return [
            { label: 'Yesterday', value: formatDateForInput(shiftDate(today, -1)) },
            { label: 'Today', value: formatDateForInput(today) },
            { label: 'Tomorrow', value: formatDateForInput(shiftDate(today, 1)) },
            { label: 'Month Start', value: formatDateForInput(new Date(today.getFullYear(), today.getMonth(), 1)) },
        ];
    }, []);

    const previousDay = formatDateForInput(shiftDate(selectedDateObject, -1));
    const nextDay = formatDateForInput(shiftDate(selectedDateObject, 1));

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
                <form method="GET" action={routes?.index} data-native="true" className="mb-5 rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
                    <label htmlFor="attendanceDate" className="text-xs uppercase tracking-[0.2em] text-slate-500">Attendance Date</label>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <a
                            href={`${routes?.index}?date=${encodeURIComponent(previousDay)}`}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Previous day
                        </a>
                        <input id="attendanceDate" type="date" name="date" defaultValue={datePickerValue} className="w-52 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                        <a
                            href={`${routes?.index}?date=${encodeURIComponent(nextDay)}`}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Next day
                        </a>
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Load</button>
                        <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Today</a>
                    </div>

                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        {quickDates.map((preset) => {
                            const active = preset.value === datePickerValue;
                            return (
                                <a
                                    key={preset.label}
                                    href={`${routes?.index}?date=${encodeURIComponent(preset.value)}`}
                                    data-native="true"
                                    className={`rounded-full px-3 py-1.5 text-xs font-semibold ${
                                        active
                                            ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : 'border border-slate-200 bg-white text-slate-600 hover:border-teal-300 hover:text-teal-600'
                                    }`}
                                >
                                    {preset.label}
                                </a>
                            );
                        })}
                    </div>
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
