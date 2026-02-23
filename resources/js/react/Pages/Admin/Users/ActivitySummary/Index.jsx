import React from 'react';
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
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Reporting</div>
                    <div className="text-2xl font-semibold text-slate-900">User activity summary</div>
                    <div className="text-sm text-slate-500">Track user sessions and activity across all user types</div>
                </div>
            </div>

            <div className="card mb-6 p-6">
                <form method="GET" action={routes?.index} className="grid gap-4 md:grid-cols-5" data-native="true">
                    <div>
                        <label htmlFor="type" className="block text-sm font-medium text-slate-700">
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
                        <label htmlFor="user_id" className="block text-sm font-medium text-slate-700">
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
                        <label htmlFor="from" className="block text-sm font-medium text-slate-700">
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
                        <label htmlFor="to" className="block text-sm font-medium text-slate-700">
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

                    <div className="flex items-end gap-2">
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
            </div>

            <div className="card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-slate-50">
                            <tr className="border-b border-slate-200">
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">User</th>
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
                                                <div
                                                    className={`flex h-2 w-2 rounded-full ${
                                                        row.is_online ? 'bg-emerald-500' : 'bg-slate-300'
                                                    }`}
                                                    title={row.is_online ? 'Online' : 'Offline'}
                                                />
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
                                                    {initials(row?.user?.name)}
                                                </div>
                                                <div>
                                                    <div className="font-medium text-slate-900">{row?.user?.name || '--'}</div>
                                                    <div className="text-xs text-slate-500">{row?.user?.email || '--'}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-700">
                                            <div className="font-medium">{row?.today?.sessions_count || 0} sessions</div>
                                            <div className="text-xs text-slate-500">{row?.today?.active_duration || '0:00'}</div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-700">
                                            <div className="font-medium">{row?.week?.sessions_count || 0} sessions</div>
                                            <div className="text-xs text-slate-500">{row?.week?.active_duration || '0:00'}</div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-700">
                                            <div className="font-medium">{row?.month?.sessions_count || 0} sessions</div>
                                            <div className="text-xs text-slate-500">{row?.month?.active_duration || '0:00'}</div>
                                        </td>
                                        {showRange ? (
                                            <td className="px-6 py-4 text-sm text-slate-700">
                                                {row?.range ? (
                                                    <>
                                                        <div className="font-medium">{row.range.sessions_count} sessions</div>
                                                        <div className="text-xs text-slate-500">{row.range.active_duration}</div>
                                                    </>
                                                ) : (
                                                    <div className="text-xs text-slate-500">--</div>
                                                )}
                                            </td>
                                        ) : null}
                                        <td className="px-6 py-4 text-sm text-slate-700">{row?.last_seen_human || '--'}</td>
                                        <td className="px-6 py-4 text-sm text-slate-700">{row?.last_login_display || '--'}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={showRange ? 7 : 6}
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
