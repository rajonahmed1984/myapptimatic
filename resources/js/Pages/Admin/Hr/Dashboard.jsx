import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';

const n = (value) => {
    const parsed = Number(value ?? 0);
    return Number.isFinite(parsed) ? parsed : 0;
};

const pct = (value) => `${n(value).toFixed(1)}%`;

function sparkPoints(values, width = 360, height = 120, padding = 10) {
    const list = Array.isArray(values) ? values.map((entry) => n(entry)) : [];
    if (!list.length) return [];

    const max = Math.max(...list, 1);
    const min = Math.min(...list, 0);
    const range = Math.max(1, max - min);
    const usableW = width - padding * 2;
    const usableH = height - padding * 2;

    if (list.length === 1) {
        return [
            {
                x: width / 2,
                y: padding + ((max - list[0]) / range) * usableH,
                value: list[0],
            },
        ];
    }

    return list.map((value, index) => ({
        x: padding + (index / (list.length - 1)) * usableW,
        y: padding + ((max - value) / range) * usableH,
        value,
    }));
}

function pointsPath(points) {
    if (!points.length) return '';
    return points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ');
}

function pointsArea(points, baseY) {
    if (!points.length) return '';
    const line = pointsPath(points);
    const first = points[0];
    const last = points[points.length - 1];
    return `${line} L ${last.x.toFixed(2)} ${baseY.toFixed(2)} L ${first.x.toFixed(2)} ${baseY.toFixed(2)} Z`;
}

export default function Dashboard({
    pageTitle = 'HR Dashboard',
    activeEmployees = 0,
    activeFullTimeEmployees = 0,
    onLeaveToday = 0,
    pendingLeaveRequests = 0,
    attendanceMarkedToday = 0,
    attendanceMissingToday = 0,
    attendanceCoverageToday = 0,
    workLogDaysThisMonth = 0,
    onTargetDaysThisMonth = 0,
    onTargetRateThisMonth = 0,
    paidHolidaysThisMonth = 0,
    draftPeriods = 0,
    finalizedPeriods = 0,
    paidPeriods = 0,
    payrollToPay = 0,
    paidPayrollItems = 0,
    leavePressure = 0,
    payrollPressure = 0,
    leaveSummary = {},
    employmentMix = [],
    workModeMix = [],
    attendanceTrend = [],
    workTrend = [],
    recentWorkLogs = [],
    recentLeaveRequests = [],
    recentAttendance = [],
    currentMonth = '',
    routes = {},
}) {
    const attendanceCoverageSeries = useMemo(
        () => (Array.isArray(attendanceTrend) ? attendanceTrend.map((item) => n(item.coverage_percent)) : []),
        [attendanceTrend],
    );
    const workHoursSeries = useMemo(
        () => (Array.isArray(workTrend) ? workTrend.map((item) => n(item.active_hours)) : []),
        [workTrend],
    );

    const attendancePoints = sparkPoints(attendanceCoverageSeries, 360, 120, 10);
    const workPoints = sparkPoints(workHoursSeries, 360, 120, 10);
    const attendanceLatest = attendanceTrend[attendanceTrend.length - 1] || null;
    const workLatest = workTrend[workTrend.length - 1] || null;

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR & Payroll</div>
                    <div className="text-2xl font-semibold text-slate-900">Master HR Dashboard</div>
                    <div className="text-sm text-slate-500">Workforce health, attendance, leave, work output, and payroll readiness in one control view.</div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <a href={routes?.employeesCreate} data-native="true" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add employee</a>
                    <a href={routes?.employeesIndex} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Employees</a>
                    <a href={routes?.attendanceIndex} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Attendance</a>
                    <a href={routes?.leaveRequestsIndex} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Leave</a>
                    <a href={routes?.payrollIndex} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Payroll</a>
                </div>
            </div>

            <div className="card bg-gradient-to-br from-[#eef8fb] via-white to-[#f4f8ff] p-5">
                <div className="section-label">Control Snapshot</div>
                <div className="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <InsightCard title="Attendance Coverage Today" value={pct(attendanceCoverageToday)} note={`${attendanceMarkedToday}/${Math.max(activeFullTimeEmployees, 0)} full-time marked`} tone={n(attendanceCoverageToday) >= 90 ? 'text-emerald-600' : 'text-amber-600'} />
                    <InsightCard title="On-Target Workdays (Month)" value={pct(onTargetRateThisMonth)} note={`${onTargetDaysThisMonth} of ${workLogDaysThisMonth} logged days`} tone={n(onTargetRateThisMonth) >= 70 ? 'text-emerald-600' : 'text-rose-600'} />
                    <InsightCard title="Leave Pressure Today" value={pct(leavePressure)} note={`${onLeaveToday} of ${activeEmployees} active employees`} tone={n(leavePressure) <= 10 ? 'text-emerald-600' : 'text-amber-600'} />
                    <InsightCard title="Payroll Pressure" value={String(payrollPressure)} note={`${payrollToPay} to pay, ${paidPayrollItems} paid items`} tone={n(payrollPressure) <= 0 ? 'text-emerald-600' : 'text-rose-600'} />
                </div>
            </div>

            <div className="mt-5 grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                <MetricCard href={routes?.employeesIndex} title="Active employees" value={activeEmployees} note="Live active profiles" />
                <MetricCard href={routes?.leaveRequestsIndex} title="Pending leave" value={pendingLeaveRequests} note="Awaiting approval" />
                <MetricCard href={routes?.attendanceIndex} title="Attendance marked" value={attendanceMarkedToday} note="Today" />
                <MetricCard href={routes?.attendanceIndex} title="Attendance pending" value={attendanceMissingToday} note="Today" />
                <MetricCard href={`${routes?.timesheetsIndex}?month=${currentMonth}`} title="Work log days" value={workLogDaysThisMonth} note="Current month" />
                <MetricCard href={routes?.payrollIndex} title="Payroll to pay" value={payrollToPay} note="Approved + partial" />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <TrendCard
                    title="Attendance Coverage Trend (14d)"
                    subtitle="How consistently attendance is being marked by full-time employees."
                    points={attendancePoints}
                    latestLabel={attendanceLatest?.label || '--'}
                    latestValue={attendanceLatest ? `${pct(attendanceLatest.coverage_percent)} coverage` : '--'}
                    accent="teal"
                />
                <TrendCard
                    title="Active Work Hours Trend (14d)"
                    subtitle="Total tracked active hours from work sessions per day."
                    points={workPoints}
                    latestLabel={workLatest?.label || '--'}
                    latestValue={workLatest ? `${n(workLatest.active_hours).toFixed(2)}h active` : '--'}
                    accent="blue"
                />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <MixCard title="Employment Mix" rows={employmentMix} base={Math.max(activeEmployees, 1)} emptyText="No active employee type data." />
                <MixCard title="Work Mode Mix" rows={workModeMix} base={Math.max(activeEmployees, 1)} emptyText="No active work-mode data." />
                <div className="card p-6">
                    <div className="section-label">Leave & Payroll Status</div>
                    <div className="mt-4 grid gap-3 text-sm text-slate-700">
                        <StatusRow label="Leave pending" value={leaveSummary?.pending ?? 0} tone="text-amber-600" />
                        <StatusRow label="Leave approved" value={leaveSummary?.approved ?? 0} tone="text-emerald-600" />
                        <StatusRow label="Leave rejected" value={leaveSummary?.rejected ?? 0} tone="text-rose-600" />
                        <StatusRow label="Draft payroll periods" value={draftPeriods} />
                        <StatusRow label="Finalized payroll periods" value={finalizedPeriods} />
                        <StatusRow label="Paid payroll periods" value={paidPeriods} tone="text-emerald-600" />
                        <StatusRow label="Paid holidays (month)" value={paidHolidaysThisMonth} />
                    </div>
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Recent Operations</div>
                <div className="mt-4 grid gap-6 lg:grid-cols-3">
                    <ListCard
                        title="Recent work logs"
                        href={routes?.timesheetsIndex}
                        empty="No recent work logs."
                        rows={recentWorkLogs.map((log, idx) => ({
                            key: `${log.work_date}-${idx}`,
                            leftTop: log.employee_name,
                            leftBottom: log.work_date,
                            right: `${log.active_hours}h`,
                        }))}
                    />
                    <ListCard
                        title="Recent leave requests"
                        href={routes?.leaveRequestsIndex}
                        empty="No recent leave requests."
                        rows={recentLeaveRequests.map((leave, idx) => ({
                            key: `${leave.start_date}-${idx}`,
                            leftTop: leave.employee_name,
                            leftBottom: `${leave.leave_type} (${leave.status})`,
                            right: leave.start_date,
                        }))}
                    />
                    <ListCard
                        title="Recent attendance"
                        href={routes?.attendanceIndex}
                        empty="No recent attendance records."
                        rows={recentAttendance.map((attendance, idx) => ({
                            key: `${attendance.date}-${idx}`,
                            leftTop: attendance.employee_name,
                            leftBottom: attendance.status,
                            right: attendance.date,
                        }))}
                    />
                </div>
            </div>
        </>
    );
}

function MetricCard({ href, title, value, note }) {
    return (
        <a href={href} data-native="true" className="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
            <div className="text-xs uppercase tracking-[0.25em] text-slate-400">{title}</div>
            <div className="whitespace-nowrap text-xl font-semibold text-slate-900 tabular-nums">{value}</div>
            <div className="mt-1 text-[11px] text-slate-500">{note}</div>
        </a>
    );
}

function InsightCard({ title, value, note, tone = 'text-slate-900' }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <div className={`mt-2 whitespace-nowrap text-2xl font-semibold tabular-nums ${tone}`}>{value}</div>
            <div className="mt-1 text-xs text-slate-500">{note}</div>
        </div>
    );
}

function TrendCard({ title, subtitle, points, latestLabel, latestValue, accent = 'teal' }) {
    const hasData = Array.isArray(points) && points.length > 0;
    const stroke = accent === 'blue' ? '#2563eb' : '#0f766e';
    const fillTop = accent === 'blue' ? 'rgba(37, 99, 235, 0.18)' : 'rgba(20, 184, 166, 0.18)';
    const dotFill = accent === 'blue' ? '#93c5fd' : '#99f6e4';
    const point = hasData ? points[points.length - 1] : null;

    return (
        <div className="card p-6">
            <div className="section-label">{title}</div>
            <div className="mt-1 text-xs text-slate-500">{subtitle}</div>
            {hasData ? (
                <div className="mt-4 rounded-2xl border border-slate-200 bg-white/90 p-3">
                    <svg viewBox="0 0 360 120" className="h-28 w-full">
                        <defs>
                            <linearGradient id={`spark-${accent}`} x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stopColor={fillTop} />
                                <stop offset="100%" stopColor="rgba(255,255,255,0.02)" />
                            </linearGradient>
                        </defs>
                        <path d={pointsArea(points, 110)} fill={`url(#spark-${accent})`} stroke="none" />
                        <path d={pointsPath(points)} fill="none" stroke={stroke} strokeWidth="2.4" />
                        {points.map((p, idx) => (
                            <circle key={idx} cx={p.x} cy={p.y} r="2.4" fill={dotFill} stroke={stroke} strokeWidth="1" />
                        ))}
                    </svg>
                    {point ? (
                        <div className="mt-1 flex items-center justify-between text-xs text-slate-600">
                            <span className="whitespace-nowrap">{latestLabel}</span>
                            <span className="whitespace-nowrap font-semibold text-slate-900 tabular-nums">{latestValue}</span>
                        </div>
                    ) : null}
                </div>
            ) : (
                <div className="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-8 text-center text-sm text-slate-500">
                    No trend data found.
                </div>
            )}
        </div>
    );
}

function MixCard({ title, rows, base, emptyText }) {
    const items = Array.isArray(rows) ? rows : [];

    return (
        <div className="card p-6">
            <div className="section-label">{title}</div>
            <div className="mt-4 space-y-3 text-sm text-slate-700">
                {items.length ? (
                    items.map((row, index) => {
                        const total = n(row?.total);
                        const width = base > 0 ? Math.min(100, (total / base) * 100) : 0;
                        return (
                            <div key={`${row?.key || 'mix'}-${index}`} className="rounded-xl border border-slate-100 bg-white/80 p-3">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="truncate">{row?.label || 'Unknown'}</div>
                                    <div className="whitespace-nowrap font-semibold text-slate-900 tabular-nums">{total}</div>
                                </div>
                                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div className="h-full rounded-full bg-teal-500" style={{ width: `${width}%` }} />
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <div className="text-sm text-slate-500">{emptyText}</div>
                )}
            </div>
        </div>
    );
}

function StatusRow({ label, value, tone = 'text-slate-900' }) {
    return (
        <div className="flex items-center justify-between rounded-xl border border-slate-100 bg-white/80 px-3 py-2">
            <span className="text-slate-600">{label}</span>
            <span className={`whitespace-nowrap font-semibold tabular-nums ${tone}`}>{value}</span>
        </div>
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
                {rows.length === 0 ? (
                    <div className="text-xs text-slate-500">{empty}</div>
                ) : (
                    rows.map((row) => (
                        <div key={row.key} className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className="truncate font-semibold text-slate-900">{row.leftTop}</div>
                                <div className="whitespace-nowrap text-xs text-slate-500 tabular-nums">{row.leftBottom}</div>
                            </div>
                            <div className="whitespace-nowrap text-xs text-slate-500 tabular-nums">{row.right}</div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

