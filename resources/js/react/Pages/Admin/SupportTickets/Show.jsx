import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Show({ pageTitle = 'Support Ticket', ticket = {}, replies = [], ai_ready = false, routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-6xl space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-semibold text-slate-900">
                                {ticket?.ticket_number || 'Ticket'}: {ticket?.subject || '--'}
                            </h1>
                            <p className="text-sm text-slate-500">
                                {ticket?.customer_name || '--'} | {ticket?.status_label || '--'} | {ticket?.priority || '--'}
                            </p>
                        </div>
                        <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                            Back to tickets
                        </a>
                    </div>
                    <div className="grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                        <p>Created: {ticket?.created_at_display || '--'}</p>
                        <p>Last Reply: {ticket?.last_reply_at_display || '--'}</p>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Replies</h2>
                    <div className="space-y-3">
                        {replies.map((reply) => (
                            <div key={reply.id} className={`rounded-xl border px-4 py-3 ${reply.is_admin ? 'border-teal-200 bg-teal-50' : 'border-slate-200 bg-slate-50'}`}>
                                <p className="mb-2 text-sm text-slate-800 whitespace-pre-wrap">{reply.message}</p>
                                <div className="flex items-center justify-between text-xs text-slate-500">
                                    <span>{reply.author_name}</span>
                                    <span>{reply.created_at_display}</span>
                                </div>
                                {reply.attachment_url ? (
                                    <a href={reply.attachment_url} data-native="true" className="mt-2 inline-block text-xs font-semibold text-teal-600 hover:text-teal-500">
                                        View attachment
                                    </a>
                                ) : null}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Reply</h2>
                    <form action={routes?.reply} method="POST" encType="multipart/form-data" data-native="true" className="space-y-4">
                        <input type="hidden" name="_token" value={csrf} />
                        <textarea name="message" rows={5} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        {errors?.message ? <p className="text-xs text-rose-600">{errors.message}</p> : null}
                        <input type="file" name="attachment" className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        {errors?.attachment ? <p className="text-xs text-rose-600">{errors.attachment}</p> : null}
                        <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Send Reply
                        </button>
                    </form>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Update Ticket</h2>
                    <form action={routes?.update} method="POST" data-native="true" className="grid gap-4 md:grid-cols-3">
                        <input type="hidden" name="_token" value={csrf} />
                        <input type="hidden" name="_method" value="PATCH" />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Subject</label>
                            <input name="subject" defaultValue={ticket?.subject || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Priority</label>
                            <select name="priority" defaultValue={ticket?.priority || 'medium'} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Status</label>
                            <select name="status" defaultValue={ticket?.status || 'open'} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="open">Open</option>
                                <option value="answered">Answered</option>
                                <option value="customer_reply">Customer Reply</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div className="md:col-span-3 flex items-center gap-3">
                            <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                Save
                            </button>
                        </div>
                    </form>
                    {ai_ready ? (
                        <form action={routes?.ai_summary} method="POST" data-native="true" className="mt-3">
                            <input type="hidden" name="_token" value={csrf} />
                            <button type="submit" className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                                Generate AI Summary
                            </button>
                        </form>
                    ) : null}
                </div>

                <div className="rounded-2xl border border-rose-200 bg-rose-50 p-6">
                    <h2 className="mb-3 text-lg font-semibold text-rose-800">Delete Ticket</h2>
                    <form action={routes?.destroy} method="POST" data-native="true">
                        <input type="hidden" name="_token" value={csrf} />
                        <input type="hidden" name="_method" value="DELETE" />
                        <button type="submit" className="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
