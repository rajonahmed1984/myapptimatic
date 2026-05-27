import React from 'react';
import { Head } from '@inertiajs/react';
import DateTimeText from '../../../Components/DateTimeText';
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

            <div className="card p-6">
                <form
                    method="GET"
                    action={routes?.current}
                    className="mb-4 flex items-center gap-2"
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
                        className="w-full max-w-sm rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                    />
                </form>
                <div className="flex flex-wrap gap-2 text-sm">
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
            </div>

            <div className="mt-6 card overflow-x-auto">
                {logs.length === 0 ? (
                    <div className="px-6 py-8 text-sm text-slate-500">No log entries yet.</div>
                ) : (
                    <>
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Date</th>
                                    <th className="px-4 py-3">User</th>
                                    <th className="px-4 py-3">IP</th>
                                    <th className="px-4 py-3">Level</th>
                                    <th className="px-4 py-3">Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.map((log) => (
                                    <tr key={log.id} className="border-b border-slate-100">
                                        <td className="px-4 py-3 text-slate-500">
                                            <DateTimeText value={log.created_at_display} mode="datetime" />
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{log.user_name}</td>
                                        <td className="px-4 py-3 text-slate-500">{log.ip_address}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${levelClasses(log.level)}`}>
                                                {log.level_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            <div className="font-semibold text-slate-800">{log.message}</div>
                                            {log.context_json ? <div className="mt-1 text-xs text-slate-500">{log.context_json}</div> : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {pagination?.has_pages ? (
                            <div className="flex items-center justify-between px-4 py-3 text-sm text-slate-500">
                                <div>
                                    Showing {pagination?.count ?? 0} of {pagination?.total ?? 0}
                                </div>
                                <div className="flex items-center gap-2">
                                    {pagination?.previous_url ? (
                                        <a
                                            href={pagination.previous_url}
                                            data-native="true"
                                            className={BTN.secondary}
                                        >
                                            Previous
                                        </a>
                                    ) : (
                                        <span className="rounded-full border border-slate-100 px-3 py-1 text-slate-300">Previous</span>
                                    )}
                                    {pagination?.next_url ? (
                                        <a
                                            href={pagination.next_url}
                                            data-native="true"
                                            className={BTN.secondary}
                                        >
                                            Next
                                        </a>
                                    ) : (
                                        <span className="rounded-full border border-slate-100 px-3 py-1 text-slate-300">Next</span>
                                    )}
                                </div>
                            </div>
                        ) : null}
                    </>
                )}
            </div>
        </>
    );
}
