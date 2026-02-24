import React from 'react';
import { Head } from '@inertiajs/react';

export default function Show({ ticket = {}, replies = [], form = {}, routes = {} }) {
    return (
        <>
            <Head title="Support Ticket" />

            <div id="ticketMainWrap">
                <div className="card p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="section-label">Ticket</div>
                            <h1 className="mt-2 text-2xl font-semibold text-slate-900">{ticket.subject}</h1>
                            <div className="mt-2 text-sm text-slate-500">
                                {ticket.number} - Priority {ticket.priority_label}
                            </div>
                        </div>
                        <div className="flex flex-col items-end gap-3 text-sm">
                            <span className={`rounded-full px-4 py-1 text-xs font-semibold ${ticket.status_classes}`}>{ticket.status_label}</span>
                            <div className="text-slate-500">Opened {ticket.created_at_display}</div>
                            <form method="POST" action={routes.status} data-native="true">
                                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                                <input type="hidden" name="_method" value="PATCH" />
                                <input type="hidden" name="status" value={ticket.is_closed ? 'open' : 'closed'} />
                                <button
                                    type="submit"
                                    className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                                >
                                    {ticket.is_closed ? 'Reopen ticket' : 'Close ticket'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div className="mt-8 space-y-4">
                    {replies.length === 0 ? (
                        <div className="card-muted p-4 text-sm text-slate-500">No replies yet.</div>
                    ) : (
                        replies.map((reply) => (
                            <div key={reply.id} className={`flex ${reply.is_admin ? 'justify-start' : 'justify-end'}`}>
                                <div className="max-w-2xl rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm shadow-sm">
                                    <div className="flex items-center justify-between text-xs text-slate-500">
                                        <span>{reply.user_name}</span>
                                        <span>{reply.created_at_display}</span>
                                    </div>
                                    <div className="mt-3 whitespace-pre-line text-slate-700">{reply.message}</div>
                                    {reply.attachment_url ? (
                                        <div className="mt-3 text-xs text-slate-500">
                                            Attachment:{' '}
                                            <a href={reply.attachment_url} target="_blank" rel="noreferrer" className="font-semibold text-teal-600 hover:text-teal-500">
                                                {reply.attachment_name}
                                            </a>
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        ))
                    )}
                </div>

                <div className="card mt-8 p-6">
                    <div className="section-label">Post reply</div>
                    <form method="POST" action={routes.reply} className="mt-4 space-y-4" encType="multipart/form-data" data-native="true">
                        <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                        <textarea
                            id="ticket-reply-message"
                            name="message"
                            rows="5"
                            defaultValue={form.message || ''}
                            required
                            className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                        />
                        <div>
                            <label className="text-sm text-slate-600">Attachment (image/PDF)</label>
                            <input name="attachment" type="file" accept="image/*,.pdf" className="mt-2 block w-full text-sm text-slate-600" />
                        </div>
                        <div className="flex items-center justify-between">
                            <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                                Back to tickets
                            </a>
                            <button type="submit" className="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">
                                Send reply
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
