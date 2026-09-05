import React from 'react';
import { Head } from '@inertiajs/react';
import DateTimeText from '../../../Components/DateTimeText';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

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

            <DataTable
                rows={tickets}
                emptyMessage="No tickets yet."
                columns={[
                    {
                        key: 'ticket',
                        header: 'Ticket',
                        cellClassName: 'font-medium text-slate-900',
                        render: (ticket) => <a href={ticket.routes.show} data-native="true" className="text-teal-600 hover:text-teal-500">{ticket.number}</a>,
                    },
                    { key: 'subject', header: 'Subject', cellClassName: 'text-slate-700', render: (ticket) => ticket.subject },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (ticket) => <span className={`rounded-full px-3 py-1 text-xs font-semibold ${ticket.status_classes}`}>{ticket.status_label}</span>,
                    },
                    {
                        key: 'last_reply',
                        header: 'Last Reply',
                        cellClassName: 'text-slate-500',
                        render: (ticket) => <DateTimeText value={ticket.last_reply_at_display} mode="datetime" />,
                    },
                    {
                        key: 'actions',
                        header: '',
                        headerClassName: 'text-right',
                        cellClassName: 'text-right',
                        render: (ticket) => <a href={ticket.routes.show} data-native="true" className="text-teal-600 hover:text-teal-500">View</a>,
                    },
                ]}
                renderMobileCard={(ticket) => (
                    <MobileCard
                        title={ticket.subject}
                        subtitle={ticket.number}
                        badge={ticket.status_label}
                        badgeColor={ticket.status_classes}
                        actions={
                            <a
                                href={ticket.routes.show}
                                data-native="true"
                                className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                            >
                                View
                            </a>
                        }
                    >
                        <div className="text-xs text-slate-500">
                            Last reply: <DateTimeText value={ticket.last_reply_at_display} mode="datetime" />
                        </div>
                    </MobileCard>
                )}
            />
        </>
    );
}
