import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ tickets = [], routes = {} }) {
    return (
        <>
            <Head title="Support Tickets" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Support Tickets</h1>
                    <p className="mt-1 text-sm text-slate-500">Open a ticket or reply to existing requests.</p>
                </div>
                <a href={routes.create} data-native="true" className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">
                    New Ticket
                </a>
            </div>

            <div className="card overflow-hidden">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Ticket</th>
                            <th className="px-4 py-3">Subject</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Last Reply</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {tickets.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
                                    No tickets yet.
                                </td>
                            </tr>
                        ) : (
                            tickets.map((ticket) => (
                                <tr key={ticket.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 font-medium text-slate-900">
                                        <a href={ticket.routes.show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                            {ticket.number}
                                        </a>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{ticket.subject}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${ticket.status_classes}`}>{ticket.status_label}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{ticket.last_reply_at_display}</td>
                                    <td className="px-4 py-3 text-right">
                                        <a href={ticket.routes.show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
