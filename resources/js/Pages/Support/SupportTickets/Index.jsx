import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const query = (base, status) => {
    if (!status || status === 'all') return base;
    const url = new URL(base, window.location.origin);
    url.searchParams.set('status', status);
    return `${url.pathname}${url.search}`;
};

export default function Index({ tickets = [], status = '', status_counts = {}, pagination = {}, routes = {} }) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const filters = [
        { key: 'all', label: 'All', count: status_counts?.all || 0 },
        { key: 'open', label: 'Open', count: status_counts?.open || 0 },
        { key: 'answered', label: 'Answered', count: status_counts?.answered || 0 },
        { key: 'customer_reply', label: 'Customer Reply', count: status_counts?.customer_reply || 0 },
        { key: 'closed', label: 'Closed', count: status_counts?.closed || 0 },
    ];

    return (
        <>
            <Head title="Support Tickets" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Support Tickets</h1>
                    <p className="mt-1 text-sm text-slate-500">Track and reply to client support requests.</p>
                </div>
            </div>

            <div className="card p-4">
                <div className="flex flex-wrap gap-2 text-xs">
                    {filters.map((filter) => {
                        const active = (status === filter.key) || (filter.key === 'all' && !status);
                        return (
                            <a
                                key={filter.key}
                                href={query(routes?.index || '/support/support-tickets', filter.key)}
                                data-native="true"
                                className={`rounded-full border px-3 py-1 ${active ? 'border-teal-200 bg-teal-50 text-teal-600' : 'border-slate-300 text-slate-500 hover:text-teal-600'}`}
                            >
                                {filter.label} ({filter.count})
                            </a>
                        );
                    })}
                </div>
            </div>

            <div className="card mt-6">
                <DataTable
                    rows={tickets}
                    emptyMessage="No support tickets yet."
                    columns={[
                        { key: 'sl', header: 'SL', cellClassName: 'text-slate-500', render: (ticket) => ticket.serial },
                        { key: 'ticket', header: 'Ticket', cellClassName: 'font-medium text-slate-900', render: (ticket) => ticket.ticket_no },
                        { key: 'subject', header: 'Subject', cellClassName: 'text-slate-700', render: (ticket) => ticket.subject },
                        { key: 'customer', header: 'Customer', cellClassName: 'text-slate-500', render: (ticket) => ticket.customer_name },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (ticket) => <span className="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{ticket.status_label}</span>,
                        },
                        { key: 'last_reply', header: 'Last Reply', cellClassName: 'text-slate-500', render: (ticket) => ticket.last_reply_at_display },
                        {
                            key: 'actions',
                            header: 'Action',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (ticket) => (
                                <div className="flex items-center justify-end gap-3">
                                    <a href={`${ticket?.routes?.show}#replies`} data-native="true" className="text-teal-600 hover:text-teal-500">Reply</a>
                                    <a href={ticket?.routes?.show} data-native="true" className="text-slate-600 hover:text-slate-500">View</a>
                                    <form method="POST" action={ticket?.routes?.destroy} data-native="true" onSubmit={(e) => { if (!window.confirm(`Delete ticket ${ticket.ticket_no}?`)) e.preventDefault(); }}>
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="text-rose-600 hover:text-rose-500">Delete</button>
                                    </form>
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(ticket) => (
                        <MobileCard
                            title={ticket.subject}
                            subtitle={`${ticket.ticket_no} · ${ticket.customer_name}`}
                            badge={ticket.status_label}
                            actions={
                                <>
                                    <a
                                        href={`${ticket?.routes?.show}#replies`}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        Reply
                                    </a>
                                    <a
                                        href={ticket?.routes?.show}
                                        data-native="true"
                                        className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                    >
                                        View
                                    </a>
                                    <form method="POST" action={ticket?.routes?.destroy} data-native="true" onSubmit={(e) => { if (!window.confirm(`Delete ticket ${ticket.ticket_no}?`)) e.preventDefault(); }}>
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                    </form>
                                </>
                            }
                        >
                            <div className="text-xs text-slate-500">Last reply: {ticket.last_reply_at_display}</div>
                        </MobileCard>
                    )}
                />
            </div>

            {pagination?.last_page > 1 ? (
                <div className="mt-4 flex items-center justify-between text-xs">
                    <span className="text-slate-500">Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total || 0}</span>
                    <div className="flex items-center gap-2">
                        {pagination.prev_page_url ? <a href={pagination.prev_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Previous</a> : null}
                        {pagination.next_page_url ? <a href={pagination.next_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Next</a> : null}
                    </div>
                </div>
            ) : null}
        </>
    );
}
