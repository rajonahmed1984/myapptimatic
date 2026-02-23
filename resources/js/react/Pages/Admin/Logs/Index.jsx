import React from 'react';
import { Head } from '@inertiajs/react';

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
    pagination = {},
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="card p-6">
                <div className="flex flex-wrap gap-2 text-sm">
                    {logTypes.map((type) => (
                        <a
                            key={type.slug}
                            href={type.href}
                            data-native="true"
                            className={
                                type.active
                                    ? 'rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-white'
                                    : 'rounded-full border border-slate-200 px-4 py-2 text-slate-600 hover:border-teal-300 hover:text-teal-600'
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
                                        <td className="px-4 py-3 text-slate-500">{log.created_at_display}</td>
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
                                            className="rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600"
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
                                            className="rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600"
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
