import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import DateTimeText from '../../../Components/DateTimeText';
import Pagination from '../../../Components/Table/Pagination';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const BTN = {
    primary: 'bg-teal-600 rounded-full text-xs px-3 py-1.5 font-semibold text-white hover:bg-teal-500',
    secondary: 'border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600',
    danger: 'bg-red-600 rounded-full text-xs px-3 py-1.5 font-semibold text-white hover:bg-red-500',
};

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
    search = '',
    filter_links = [],
    routes = {},
    tickets = [],
    pagination = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.current || routes?.index,
    });

    const confirmDelete = (ticketNumber) => window.confirm(`Delete ticket ${ticketNumber}?`);

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 text-xs">
                    <div className="flex flex-wrap gap-2">
                        {filter_links.map((filter) => (
                            <a
                                key={filter.key}
                                href={filter.href}
                                data-native="true"
                                className={
                                    filter.active
                                        ? BTN.primary
                                        : BTN.secondary
                                }
                            >
                                {filter.label} ({filter.count})
                            </a>
                        ))}
                    </div>
                    <div className="flex items-center gap-3">
                        <form
                            method="GET"
                            action={routes?.current || routes?.index}
                            className="flex-1"
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
                                placeholder="Search tickets..."
                                className="w-full max-w-sm rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                            />
                        </form>
                        <span className="hidden whitespace-nowrap text-xs text-slate-500 sm:inline">
                            Showing {pagination?.from ?? (tickets.length > 0 ? 1 : 0)} – {pagination?.to ?? tickets.length} of {pagination?.total ?? tickets.length} tickets
                        </span>
                        <a
                            href={routes?.create}
                            data-native="true"
                            className={BTN.primary}
                        >
                            Open Ticket
                        </a>
                    </div>
                </div>

                <DataTable
                    framed={false}
                    rows={tickets}
                    emptyMessage="No support tickets yet."
                    columns={[
                        { key: 'sl', header: 'SL', cellClassName: 'text-slate-500', render: (ticket) => ticket.serial },
                        { key: 'ticket', header: 'Ticket', cellClassName: 'font-medium text-slate-900', render: (ticket) => ticket.ticket_number },
                        { key: 'subject', header: 'Subject', cellClassName: 'text-slate-700', render: (ticket) => ticket.subject },
                        { key: 'customer', header: 'Customer', cellClassName: 'text-slate-500', render: (ticket) => ticket.customer_name },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (ticket) => (
                                <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(ticket.status)}`}>
                                    {ticket.status_label}
                                </span>
                            ),
                        },
                        {
                            key: 'last_reply',
                            header: 'Last Reply',
                            cellClassName: 'text-slate-500',
                            render: (ticket) => <DateTimeText value={ticket.last_reply_at_display} mode="datetime" />,
                        },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (ticket) => (
                                <div className="flex items-center justify-end gap-3">
                                    <a href={ticket?.routes?.reply} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                        Reply
                                    </a>
                                    <a href={ticket?.routes?.show} data-native="true" className="text-xs font-semibold text-slate-600 hover:text-teal-600">
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
                                        <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-500">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(ticket) => (
                        <MobileCard
                            title={ticket.subject}
                            subtitle={`${ticket.ticket_number} · ${ticket.customer_name}`}
                            badge={ticket.status_label}
                            badgeColor={statusClass(ticket.status)}
                            actions={
                                <>
                                    <a
                                        href={ticket?.routes?.reply}
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
                                        <button
                                            type="submit"
                                            className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                </>
                            }
                        >
                            <div className="text-xs text-slate-500">
                                Last reply: <DateTimeText value={ticket.last_reply_at_display} mode="datetime" />
                            </div>
                        </MobileCard>
                    )}
                />

                <Pagination
                    pagination={pagination}
                    label="tickets"
                    className="border-t border-slate-200 px-4 py-3"
                />
            </div>
        </>
    );
}
