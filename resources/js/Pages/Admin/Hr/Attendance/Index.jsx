import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';
import DatePickerField from '../../../../Components/DatePickerField';
import SearchableSelect from '../../../../Components/SearchableSelect';

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
    const attendanceStatusOptions = [
        { value: '', label: 'Not set' },
        { value: 'present', label: 'Present' },
        { value: 'absent', label: 'Absent' },
        { value: 'leave', label: 'Leave' },
        { value: 'half_day', label: 'Half Day' },
    ];

    return (
        <>
            <Head title={pageTitle} />


            <div className="card p-6">
                {isPaidHoliday ? (
                    <div className="mb-4 inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Paid holiday: employees default to Present for this date
                    </div>
                ) : null}

                <form method="GET" action={routes?.index} data-native="true" className="mb-5 flex flex-wrap items-center gap-4 border-b border-slate-200 pb-5">
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={`${routes?.index}?date=${encodeURIComponent(previousDay)}`}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-1 h-8 flex items-center text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Previous day
                        </a>
                        <DatePickerField
                            id="attendanceDate"
                            name="date"
                            defaultValue={datePickerValue}
                            submitFormat="iso"
                            hideLabel
                            containerClassName="w-48"
                            inputClassName="w-48 rounded-full border border-slate-300 bg-white px-4 py-1 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600"
                        />
                        <a
                            href={`${routes?.index}?date=${encodeURIComponent(nextDay)}`}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-1 h-8 flex items-center text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Next day
                        </a>
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-1 h-8 text-xs font-semibold text-white hover:bg-emerald-500">Load</button>
                        <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-1 h-8 flex items-center text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Today</a>
                    </div>

                    <div className="h-4 w-px bg-slate-300 hidden md:block"></div>

                    <div className="flex flex-wrap items-center gap-2">
                        {quickDates.map((preset) => {
                            const active = preset.value === datePickerValue;
                            return (
                                <a
                                    key={preset.label}
                                    href={`${routes?.index}?date=${encodeURIComponent(preset.value)}`}
                                    data-native="true"
                                    className={`rounded-full px-3 py-1 h-8 flex items-center text-xs font-semibold ${
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

                    {/* One row per employee, responsive via CSS only (stacked card on mobile,
                        table-like grid on desktop) — never duplicate the named inputs, or
                        browsers submit both hidden and visible copies together. */}
                    {employees.length === 0 ? (
                        <div className="rounded-xl border border-slate-200 bg-slate-50 py-6 text-center text-sm text-slate-500">No active full-time employees found.</div>
                    ) : (
                        <div className="space-y-3 md:space-y-0 md:rounded-xl md:border md:border-slate-200 md:divide-y md:divide-slate-100">
                            <div className="hidden md:grid md:grid-cols-[2fr_1fr_1fr_1.4fr_1.6fr_1.4fr] md:gap-3 md:bg-slate-50 md:px-3 md:py-2 md:text-xs md:font-semibold md:uppercase md:tracking-[0.2em] md:text-slate-500">
                                <div>Employee</div>
                                <div>Department</div>
                                <div>Designation</div>
                                <div>Status</div>
                                <div>Note</div>
                                <div>Recorded By</div>
                            </div>

                            {employees.map((employee, index) => (
                                <div
                                    key={employee.id}
                                    className="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm md:rounded-none md:border-0 md:border-b md:border-slate-100 md:bg-transparent md:p-3 md:shadow-none md:grid md:grid-cols-[2fr_1fr_1fr_1.4fr_1.6fr_1.4fr] md:items-center md:gap-3 last:md:border-b-0"
                                >
                                    <input type="hidden" name={`records[${index}][employee_id]`} value={employee.id} />

                                    <div>
                                        <div className="font-semibold text-slate-900">{employee.name}</div>
                                        <div className="text-xs text-slate-500">{employee.email}</div>
                                        <div className="mt-1 text-xs text-slate-500 md:hidden">{employee.designation}{employee.department ? ` · ${employee.department}` : ''}</div>
                                    </div>

                                    <div className="hidden md:block text-sm text-slate-700">{employee.department}</div>
                                    <div className="hidden md:block text-sm text-slate-700">{employee.designation}</div>

                                    <div className="mt-3 md:mt-0">
                                        <label className="text-[10px] uppercase tracking-[0.18em] text-slate-400 md:hidden">Status</label>
                                        <div className="mt-1 md:mt-0">
                                            <SearchableSelect
                                                name={`records[${index}][status]`}
                                                defaultValue={String(employee.status || '')}
                                                options={attendanceStatusOptions}
                                                placeholder="Not set"
                                            />
                                        </div>
                                    </div>

                                    <div className="mt-2.5 md:mt-0">
                                        <label className="text-[10px] uppercase tracking-[0.18em] text-slate-400 md:hidden">Note</label>
                                        <input
                                            type="text"
                                            name={`records[${index}][note]`}
                                            defaultValue={employee.note || ''}
                                            placeholder="Optional note"
                                            className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-teal-600 md:mt-0 md:h-8 md:rounded-full md:px-3 md:py-1.5 md:text-xs"
                                        />
                                    </div>

                                    <div className="mt-2.5 text-[11px] text-slate-400 md:mt-0 md:text-xs md:text-slate-500">
                                        {employee.recorder_name ? employee.recorder_name : isPaidHoliday && employee.status === 'present' ? 'Paid holiday (System)' : '--'}
                                        {employee.updated_at ? <div>{employee.updated_at}</div> : null}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

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
