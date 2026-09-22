import React from 'react';
import { Head } from '@inertiajs/react';
import DateTimeText from '../../../Components/DateTimeText';
import Pagination from '../../../Components/Table/Pagination';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';

const BTN = {
    primary: 'bg-teal-600 rounded-full text-xs px-3 py-1.5 font-semibold text-white hover:bg-teal-500',
    secondary: 'border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600',
};

const levelClasses = (level) => {
    if (level === 'error') {
        return 'bg-rose-100 text-rose-700';
    }

    if (level === 'warning') {
        return 'bg-amber-100 text-amber-700';
    }

    if (level === 'info') {
        return 'bg-blue-100 text-blue-700';
    }

    return 'bg-slate-100 text-slate-600';
};

export default function Index({
    pageTitle = 'System Logs',
    logTypes = [],
    logs = [],
    filters = {},
    routes = {},
    pagination = {},
}) {
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: filters?.search ?? '',
        url: routes?.current,
    });

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 text-xs">
                    <div className="flex flex-wrap gap-2">
                        {logTypes.map((type) => (
                            <a
                                key={type.slug}
                                href={type.href}
                                data-native="true"
                                className={
                                    type.active
                                        ? BTN.primary
                                        : BTN.secondary
                                }
                            >
                                {type.label}
                            </a>
                        ))}
                    </div>
                    <div className="flex items-center gap-3">
                        <form
                            method="GET"
                            action={routes?.current}
                            className="flex items-center gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                submitSearch();
                            }}
                        >
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search logs..."
                                className="w-full max-w-sm rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                            />
                        </form>
                        <span className="hidden whitespace-nowrap text-xs text-slate-500 sm:inline">
                            Showing {pagination?.from ?? (logs.length > 0 ? 1 : 0)} – {pagination?.to ?? logs.length} of {pagination?.total ?? logs.length} log entries
                        </span>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    {logs.length === 0 ? (
                        <div className="px-6 py-8 text-sm text-slate-500 text-center">No log entries yet.</div>
                    ) : (
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500 bg-slate-50/50">
                                <tr>
                                    <th className="px-4 py-3">Date</th>
                                    <th className="px-4 py-3">User</th>
                                    <th className="px-4 py-3">IP</th>
                                    <th className="px-4 py-3">Level</th>
                                    <th className="px-4 py-3">Message</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {logs.map((log) => (
                                    <tr key={log.id} className="hover:bg-slate-50/60 transition">
                                        <td className="px-4 py-3 text-slate-500 whitespace-nowrap">
                                            <DateTimeText value={log.created_at_display} mode="datetime" />
                                        </td>
                                        <td className="px-4 py-3 text-slate-700 whitespace-nowrap font-medium">{log.user_name}</td>
                                        <td className="px-4 py-3 text-slate-500 whitespace-nowrap">{log.ip_address || '—'}</td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${levelClasses(log.level)}`}>
                                                {log.level_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            <div className="font-semibold text-slate-800">{log.message}</div>
                                            {log.context_json ? <div className="mt-1 text-xs text-slate-500 font-mono">{log.context_json}</div> : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                <Pagination
                    pagination={pagination}
                    label="log entries"
                    className="border-t border-slate-200 px-4 py-3"
                />
            </div>
        </>
    );
}
