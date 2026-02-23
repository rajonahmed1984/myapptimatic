import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusClass = (status) => {
    if (status === 'open') {
        return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    }

    if (status === 'answered') {
        return 'bg-blue-100 text-blue-700 border-blue-200';
    }

    if (status === 'customer_reply') {
        return 'bg-amber-100 text-amber-700 border-amber-200';
    }

    if (status === 'closed') {
        return 'bg-slate-200 text-slate-700 border-slate-300';
    }

    return 'bg-slate-100 text-slate-600 border-slate-200';
};

export default function Index({
    pageTitle = 'Support Tickets',
    filter_links = [],
    routes = {},
    tickets = [],
    pagination = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};

    const confirmDelete = (ticketNumber) => window.confirm(`Delete ticket ${ticketNumber}?`);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Support Tickets</h1>
                    <p className="mt-1 text-sm text-slate-500">Track and reply to client support requests.</p>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                >
                    Open Ticket
                </a>
            </div>

            <div className="card p-4">
                <div className="flex flex-wrap gap-2 text-xs">
                    {filter_links.map((filter) => (
                        <a
                            key={filter.key}
                            href={filter.href}
                            data-native="true"
                            className={
                                filter.active
                                    ? 'rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-teal-600'
                                    : 'rounded-full border border-slate-300 px-3 py-1 text-slate-500 hover:text-teal-600'
                            }
                        >
                            {filter.label} ({filter.count})
                        </a>
                    ))}
                </div>
            </div>

            <div className="card mt-6 overflow-x-auto">
                <table className="w-full min-w-[800px] text-left text-sm">
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">SL</th>
                            <th className="px-4 py-3">Ticket</th>
                            <th className="px-4 py-3">Subject</th>
                            <th className="px-4 py-3">Customer</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Last Reply</th>
                            <th className="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {tickets.length > 0 ? (
                            tickets.map((ticket) => (
                                <tr key={ticket.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 text-slate-500">{ticket.serial}</td>
                                    <td className="px-4 py-3 font-medium text-slate-900">{ticket.ticket_number}</td>
                                    <td className="px-4 py-3 text-slate-700">{ticket.subject}</td>
                                    <td className="px-4 py-3 text-slate-500">{ticket.customer_name}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(ticket.status)}`}>
                                            {ticket.status_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{ticket.last_reply_at_display}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-3">
                                            <a
                                                href={ticket?.routes?.reply}
                                                data-native="true"
                                                className="text-teal-600 hover:text-teal-500"
                                            >
                                                Reply
                                            </a>
                                            <a
                                                href={ticket?.routes?.show}
                                                data-native="true"
                                                className="text-slate-600 hover:text-slate-500"
                                            >
                                                View
                                            </a>
                                            <form
                                                method="POST"
                                                action={ticket?.routes?.destroy}
                                                data-native="true"
                                                onSubmit={(event) => {
                                                    if (!confirmDelete(ticket.ticket_number)) {
                                                        event.preventDefault();
                                                    }
                                                }}
                                            >
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="DELETE" />
                                                <button type="submit" className="text-rose-600 hover:text-rose-500">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={7} className="px-4 py-6 text-center text-slate-500">
                                    No support tickets yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {pagination?.has_pages ? (
                <div className="mt-4 flex items-center justify-end gap-2 text-sm">
                    {pagination?.previous_url ? (
                        <a
                            href={pagination.previous_url}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Previous
                        </a>
                    ) : (
                        <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Previous</span>
                    )}
                    {pagination?.next_url ? (
                        <a
                            href={pagination.next_url}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Next
                        </a>
                    ) : (
                        <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>
                    )}
                </div>
            ) : null}
        </>
    );
}
