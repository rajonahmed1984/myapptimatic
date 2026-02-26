import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';

const initials = (name) => {
    const value = String(name || '').trim();
    if (!value) {
        return '?';
    }

    const parts = value.split(/\s+/).filter(Boolean);
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
};

export default function Index({
    pageTitle = 'User Activity Summary',
    type = 'all',
    users = [],
    filters = {},
    userOptions = [],
    showRange = false,
    routes = {},
}) {
    const totals = useMemo(() => {
        const rows = Array.isArray(users) ? users : [];
        return rows.reduce((acc, row) => {
            acc.totalUsers += 1;
            acc.onlineUsers += row?.is_online ? 1 : 0;
            acc.todaySessions += Number(row?.today?.sessions_count || 0);
            acc.weekSessions += Number(row?.week?.sessions_count || 0);
            acc.monthSessions += Number(row?.month?.sessions_count || 0);
            acc.todayActiveSeconds += Number(row?.today?.active_seconds || 0);
            acc.weekActiveSeconds += Number(row?.week?.active_seconds || 0);
            acc.monthActiveSeconds += Number(row?.month?.active_seconds || 0);
            return acc;
        }, {
            totalUsers: 0,
            onlineUsers: 0,
            todaySessions: 0,
            weekSessions: 0,
            monthSessions: 0,
            todayActiveSeconds: 0,
            weekActiveSeconds: 0,
            monthActiveSeconds: 0,
        });
    }, [users]);

    const offlineUsers = Math.max(0, totals.totalUsers - totals.onlineUsers);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Reporting</div>
                    <div className="text-2xl font-semibold text-slate-900">User activity summary</div>
                    <div className="text-sm text-slate-500">Track sessions, active time, and live online status across all user types.</div>
                </div>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <KpiCard title="Total users" value={totals.totalUsers} note="Selected scope" />
                <KpiCard title="Online now" value={totals.onlineUsers} note="Live in last 2 minutes" tone="text-emerald-600" />
                <KpiCard title="Offline" value={offlineUsers} note="Not active now" />
                <KpiCard title="Today activity" value={`${totals.todaySessions} sessions`} note={`${formatDuration(totals.todayActiveSeconds)} active`} />
                <KpiCard title="Month activity" value={`${totals.monthSessions} sessions`} note={`${formatDuration(totals.monthActiveSeconds)} active`} />
            </div>

            <div className="card mb-6 p-6">
                <form method="GET" action={routes?.index} className="grid gap-4 md:grid-cols-6" data-native="true">
                    <div>
                        <label htmlFor="type" className="block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            User Type
                        </label>
                        <select
                            name="type"
                            id="type"
                            defaultValue={filters?.type || 'all'}
                            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                        >
                            <option value="all">All User Types</option>
                            <option value="employee">Employees</option>
                            <option value="customer">Customers</option>
                            <option value="salesrep">Sales Representatives</option>
                            <option value="admin">Admin/Web Users</option>
                        </select>
                    </div>

                    <div>
                        <label htmlFor="user_id" className="block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            Specific User (Optional)
                        </label>
                        <select
                            name="user_id"
                            id="user_id"
                            defaultValue={filters?.user_id ?? ''}
                            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                        >
                            <option value="">All {String(type).charAt(0).toUpperCase() + String(type).slice(1)}</option>
                            {userOptions.map((option) => (
                                <option key={String(option.id)} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="from" className="block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            From Date (Optional)
                        </label>
                        <input
                            type="date"
                            name="from"
                            id="from"
                            defaultValue={filters?.from || ''}
                            className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label htmlFor="to" className="block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            To Date (Optional)
                        </label>
                        <input
                            type="date"
                            name="to"
                            id="to"
                            defaultValue={filters?.to || ''}
                            className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2 text-sm"
                        />
                    </div>

                    <div className="md:col-span-2 flex items-end gap-2">
                        <button
                            type="submit"
                            className="flex-1 rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-600"
                        >
                            Filter
                        </button>
                        <a
                            href={routes?.index}
                            data-native="true"
                            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Reset
                        </a>
                    </div>
                </form>

                <div className="mt-3 text-xs text-slate-500">
                    Tip: select both `From` and `To` to enable range metrics.
                </div>
            </div>

            <div className="card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-slate-50">
                            <tr className="border-b border-slate-200">
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">User</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Type</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Today</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">This Week</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">This Month</th>
                                {showRange ? (
                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                                        Range
                                    </th>
                                ) : null}
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Last Seen</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Last Login</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {users.length > 0 ? (
                                users.map((row) => (
                                    <tr key={String(row?.user?.id || row?.user?.name)} className="transition hover:bg-slate-50">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                {row?.user?.avatar_url ? (
                                                    <img
                                                        src={row.user.avatar_url}
                                                        alt={`${row?.user?.name || 'User'} avatar`}
                                                        className="h-9 w-9 rounded-full border border-slate-200 object-cover"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
                                                        {initials(row?.user?.name)}
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium text-slate-900">{row?.user?.name || '--'}</div>
                                                    <div className="truncate text-xs text-slate-500">{row?.user?.email || '--'}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-semibold ${typeTone(row?.type)}`}>
                                                {typeLabel(row?.type)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-semibold ${row?.is_online ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600'}`}>
                                                {row?.is_online ? 'Online' : 'Offline'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-700">
                                            <div className="whitespace-nowrap font-medium tabular-nums">{row?.today?.sessions_count || 0} sessions</div>
                                            <div className="whitespace-nowrap text-xs text-slate-500 tabular-nums">{row?.today?.active_duration || '0:00'} active</div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-700">
                                            <div className="whitespace-nowrap font-medium tabular-nums">{row?.week?.sessions_count || 0} sessions</div>
                                            <div className="whitespace-nowrap text-xs text-slate-500 tabular-nums">{row?.week?.active_duration || '0:00'} active</div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-700">
                                            <div className="whitespace-nowrap font-medium tabular-nums">{row?.month?.sessions_count || 0} sessions</div>
                                            <div className="whitespace-nowrap text-xs text-slate-500 tabular-nums">{row?.month?.active_duration || '0:00'} active</div>
                                        </td>
                                        {showRange ? (
                                            <td className="px-6 py-4 text-sm text-slate-700">
                                                {row?.range ? (
                                                    <>
                                                        <div className="whitespace-nowrap font-medium tabular-nums">{row.range.sessions_count} sessions</div>
                                                        <div className="whitespace-nowrap text-xs text-slate-500 tabular-nums">{row.range.active_duration} active</div>
                                                    </>
                                                ) : (
                                                    <div className="whitespace-nowrap text-xs text-slate-500">--</div>
                                                )}
                                            </td>
                                        ) : null}
                                        <td className="px-6 py-4 text-sm text-slate-700 whitespace-nowrap">{row?.last_seen_human || '--'}</td>
                                        <td className="px-6 py-4 text-sm text-slate-700 whitespace-nowrap tabular-nums">{row?.last_login_display || '--'}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={showRange ? 9 : 8}
                                        className="px-6 py-8 text-center text-sm text-slate-500"
                                    >
                                        No users found for the selected filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function KpiCard({ title, value, note, tone = 'text-slate-900' }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <div className={`mt-1 whitespace-nowrap text-xl font-semibold tabular-nums ${tone}`}>{value}</div>
            <div className="text-xs text-slate-500">{note}</div>
        </div>
    );
}

function typeLabel(type) {
    const key = String(type || '').toLowerCase();
    if (key === 'employee') return 'Employee';
    if (key === 'customer' || key === 'client') return 'Customer';
    if (key === 'salesrep' || key === 'sales_rep') return 'Sales Rep';
    if (key === 'admin' || key === 'web') return 'Admin/Web';
    return 'Unknown';
}

function typeTone(type) {
    const key = String(type || '').toLowerCase();
    if (key === 'employee') return 'border-blue-200 bg-blue-50 text-blue-700';
    if (key === 'customer' || key === 'client') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (key === 'salesrep' || key === 'sales_rep') return 'border-purple-200 bg-purple-50 text-purple-700';
    if (key === 'admin' || key === 'web') return 'border-amber-200 bg-amber-50 text-amber-700';
    return 'border-slate-200 bg-slate-50 text-slate-600';
}

function formatDuration(seconds) {
    const value = Number(seconds || 0);
    if (!Number.isFinite(value) || value <= 0) {
        return '0:00';
    }

    const h = Math.floor(value / 3600);
    const m = Math.floor((value % 3600) / 60);
    return `${h}:${String(m).padStart(2, '0')}`;
}
