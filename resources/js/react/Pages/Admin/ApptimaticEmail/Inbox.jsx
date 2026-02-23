import React from 'react';
import { Head } from '@inertiajs/react';

const rowClassName = (message) => {
    const base = 'group flex items-start gap-3 border-b border-slate-100 px-4 py-3 transition';

    if (message?.is_selected) {
        return `${base} bg-teal-50/70`;
    }

    return `${base} bg-white hover:bg-slate-50`;
};

export default function Inbox({
    pageTitle = 'Apptimatic Email',
    unread_count = 0,
    portal_label = 'Admin portal',
    profile_name = 'Administrator',
    messages = [],
    selected_message = null,
    thread_messages = [],
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden p-0">
                <div className="border-b border-slate-200 bg-white px-4 py-4 md:px-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold text-slate-900">Apptimatic Email</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                {portal_label} | Unread: {unread_count}
                            </p>
                        </div>
                        <div className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-700">
                                {String(profile_name || 'A').charAt(0).toUpperCase()}
                            </span>
                            <span>{profile_name}</span>
                        </div>
                    </div>
                </div>

                <div className="grid min-h-[65vh] grid-cols-1 2xl:grid-cols-[minmax(20rem,38%)_1fr]">
                    <section className="border-b border-slate-200 bg-white 2xl:border-b-0 2xl:border-r">
                        {messages.length > 0 ? (
                            <div className="h-full">
                                <div className="border-b border-slate-200 px-4 py-3 text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Inbox
                                </div>
                                <div>
                                    {messages.map((message) => (
                                        <a key={message.id} href={message?.routes?.show} data-native="true" className={rowClassName(message)}>
                                            <span
                                                className={
                                                    message?.unread
                                                        ? 'mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-teal-500'
                                                        : 'mt-1 h-2.5 w-2.5 shrink-0 rounded-full border border-slate-300 bg-transparent'
                                                }
                                            />

                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-start gap-3">
                                                    <div className="min-w-0 flex-1">
                                                        <div
                                                            className={
                                                                message?.unread
                                                                    ? 'truncate text-sm font-semibold text-slate-900'
                                                                    : 'truncate text-sm font-medium text-slate-700'
                                                            }
                                                        >
                                                            {message.sender_name}
                                                        </div>
                                                        <div className="mt-0.5 truncate text-sm text-slate-700">
                                                            <span
                                                                className={
                                                                    message?.unread
                                                                        ? 'font-semibold text-slate-900'
                                                                        : 'font-medium text-slate-700'
                                                                }
                                                            >
                                                                {message.subject}
                                                            </span>
                                                            <span className="text-slate-500"> - {message.snippet}</span>
                                                        </div>
                                                    </div>
                                                    <div className="shrink-0 text-xs text-slate-500">{message.received_at_display}</div>
                                                </div>
                                            </div>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="grid h-full min-h-[18rem] place-items-center px-6 py-10 text-center text-slate-500">
                                No emails yet.
                            </div>
                        )}
                    </section>

                    <section className="bg-slate-50/60">
                        {!selected_message ? (
                            <div className="grid h-full min-h-[18rem] place-items-center px-6 py-10 text-center">
                                <div>
                                    <div className="text-sm font-semibold text-slate-700">Select a message</div>
                                    <div className="mt-1 text-sm text-slate-500">Open any inbox item to view the thread.</div>
                                </div>
                            </div>
                        ) : (
                            <div className="h-full space-y-4 px-4 py-4 md:px-6">
                                <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Thread</div>
                                    <h3 className="mt-1 text-base font-semibold text-slate-900">{selected_message.subject}</h3>
                                    <div className="mt-2 text-xs text-slate-500">{selected_message.thread_count} message(s)</div>
                                </div>

                                {thread_messages.map((thread) => (
                                    <article key={thread.id || `${thread.subject}-${thread.received_at_display}`} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <div className="grid gap-2 text-xs text-slate-500 md:grid-cols-2">
                                            <div>
                                                <span className="font-semibold text-slate-700">From:</span> {thread.sender_name} &lt;{thread.sender_email}&gt;
                                            </div>
                                            <div>
                                                <span className="font-semibold text-slate-700">To:</span> {thread.to}
                                            </div>
                                            <div>
                                                <span className="font-semibold text-slate-700">Date:</span> {thread.received_at_display}
                                            </div>
                                            <div>
                                                <span className="font-semibold text-slate-700">Subject:</span> {thread.subject}
                                            </div>
                                        </div>

                                        <div className="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">{thread.body}</div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
