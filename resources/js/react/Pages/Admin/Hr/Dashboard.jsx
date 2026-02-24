import React from 'react';
import { Head } from '@inertiajs/react';

export default function Dashboard({
    pageTitle = 'HR Dashboard',
    activeEmployees = 0,
    activeFullTimeEmployees = 0,
    onLeaveToday = 0,
    pendingLeaveRequests = 0,
    attendanceMarkedToday = 0,
    attendanceMissingToday = 0,
    workLogDaysThisMonth = 0,
    onTargetDaysThisMonth = 0,
    paidHolidaysThisMonth = 0,
    draftPeriods = 0,
    payrollToPay = 0,
    recentWorkLogs = [],
    recentLeaveRequests = [],
    recentAttendance = [],
    currentMonth = '',
    routes = {},
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR & Payroll</div>
                    <div className="text-2xl font-semibold text-slate-900">Overview</div>
                    <div className="text-sm text-slate-500">Live summary from Employees, Work Logs, Leave, Attendance, Paid Holidays, and Payroll.</div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <a href={routes?.employeesCreate} data-native="true" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add employee</a>
                    <a href={routes?.attendanceIndex} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Attendance</a>
                    <a href={routes?.paidHolidaysIndex} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Paid holidays</a>
                    <a href={routes?.payrollIndex} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Payroll</a>
                </div>
            </div>

            <div className="space-y-6">
                <div>
                    <div className="section-label">People & Leave</div>
                    <div className="mt-3 grid gap-4 md:grid-cols-4">
                        <MetricCard href={routes?.employeesIndex} title="Active employees" value={activeEmployees} note="All active profiles" />
                        <MetricCard href={routes?.leaveRequestsIndex} title="Pending leave requests" value={pendingLeaveRequests} note="Awaiting HR approval" />
                        <MetricCard href={routes?.leaveRequestsIndex} title="On leave today" value={onLeaveToday} note="Approved leave covering today" />
                        <MetricCard href={`${routes?.paidHolidaysIndex}?month=${currentMonth}`} title="Paid holidays (month)" value={paidHolidaysThisMonth} note="Current month calendar" />
                    </div>
                </div>

                <div>
                    <div className="section-label">Work, Attendance & Payroll</div>
                    <div className="mt-3 grid gap-4 md:grid-cols-4">
                        <MetricCard href={routes?.attendanceIndex} title="Attendance marked today" value={attendanceMarkedToday} note={`Full-time total: ${activeFullTimeEmployees}`} />
                        <MetricCard href={routes?.attendanceIndex} title="Attendance pending today" value={attendanceMissingToday} note="Not marked yet" />
                        <MetricCard href={`${routes?.timesheetsIndex}?month=${currentMonth}`} title="Work log days (month)" value={workLogDaysThisMonth} note="Employee-date rows" />
                        <MetricCard href={`${routes?.timesheetsIndex}?month=${currentMonth}`} title="On-target days (month)" value={onTargetDaysThisMonth} note="Required time reached" />
                        <MetricCard href={routes?.payrollIndex} title="Draft payroll periods" value={draftPeriods} note="Needs finalize" />
                        <MetricCard href={routes?.payrollIndex} title="Payroll to pay" value={payrollToPay} note="Approved unpaid items" />
                    </div>
                </div>

                <div className="card p-6">
                    <div className="grid gap-6 lg:grid-cols-3">
                        <ListCard title="Recent work logs" href={routes?.timesheetsIndex} empty="No recent work logs." rows={recentWorkLogs.map((log, idx) => ({ key: `${log.work_date}-${idx}`, leftTop: log.employee_name, leftBottom: log.work_date, right: `${log.active_hours}h` }))} />
                        <ListCard title="Recent leave requests" href={routes?.leaveRequestsIndex} empty="No recent leave requests." rows={recentLeaveRequests.map((leave, idx) => ({ key: `${leave.start_date}-${idx}`, leftTop: leave.employee_name, leftBottom: `${leave.leave_type} (${leave.status})`, right: leave.start_date }))} />
                        <ListCard title="Recent attendance" href={routes?.attendanceIndex} empty="No recent attendance records." rows={recentAttendance.map((attendance, idx) => ({ key: `${attendance.date}-${idx}`, leftTop: attendance.employee_name, leftBottom: attendance.status, right: attendance.date }))} />
                    </div>
                </div>
            </div>
        </>
    );
}

function MetricCard({ href, title, value, note }) {
    return (
        <a href={href} data-native="true" className="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
            <div className="text-xs uppercase tracking-[0.25em] text-slate-400">{title}</div>
            <div className="text-xl font-semibold text-slate-900">{value}</div>
            <div className="mt-1 text-[11px] text-slate-500">{note}</div>
        </a>
    );
}

function ListCard({ title, href, rows, empty }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div className="flex items-center justify-between gap-3">
                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
                <a href={href} data-native="true" className="text-xs font-semibold text-teal-700 hover:text-teal-600">View all</a>
            </div>
            <div className="mt-3 space-y-3 text-sm text-slate-700">
                {rows.length === 0 ? <div className="text-xs text-slate-500">{empty}</div> : rows.map((row) => (
                    <div key={row.key} className="flex items-start justify-between gap-3">
                        <div>
                            <div className="font-semibold text-slate-900">{row.leftTop}</div>
                            <div className="text-xs text-slate-500">{row.leftBottom}</div>
                        </div>
                        <div className="text-xs text-slate-500">{row.right}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}
