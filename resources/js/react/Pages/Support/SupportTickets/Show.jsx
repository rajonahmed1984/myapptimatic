import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Show({ ticket = {}, replies = [], ai_ready = false, routes = {} }) {
    const { csrf_token: csrfToken = '', errors = {} } = usePage().props || {};
    const [aiLoading, setAiLoading] = useState(false);
    const [aiError, setAiError] = useState('');
    const [summary, setSummary] = useState('Click Generate AI to analyze this ticket.');
    const [category, setCategory] = useState('--');
    const [urgency, setUrgency] = useState('--');
    const [sentiment, setSentiment] = useState('--');
    const [nextSteps, setNextSteps] = useState(['--']);
    const [suggestedReply, setSuggestedReply] = useState('');

    const runAi = async () => {
        if (!routes?.ai || aiLoading) return;
        setAiLoading(true);
        setAiError('');
        try {
            const response = await fetch(routes.ai, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload?.error || 'Failed to generate AI summary.');

            if (payload?.data) {
                setSummary(payload.data.summary || payload.raw || '--');
                setCategory(payload.data.category || '--');
                setUrgency(payload.data.urgency || '--');
                setSentiment(payload.data.sentiment || '--');
                setNextSteps(Array.isArray(payload.data.next_steps) && payload.data.next_steps.length > 0 ? payload.data.next_steps : ['--']);
                setSuggestedReply(payload.data.suggested_reply || '');
            } else {
                setSummary(payload?.raw || '--');
                setNextSteps(['--']);
            }
        } catch (error) {
            setAiError(error?.message || 'Failed to generate AI summary.');
        } finally {
            setAiLoading(false);
        }
    };

    return (
        <>
            <Head title="Support Ticket" />

            <div id="ticketMainWrap">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="section-label">Ticket</div>
                        <h1 className="mt-2 text-2xl font-semibold text-slate-900">{ticket.subject}</h1>
                        <div className="mt-2 text-sm text-slate-500">{ticket.ticket_no} - {ticket.customer_name} - Priority {ticket.priority_label}</div>
                    </div>
                    <div className="flex flex-col items-end gap-3 text-sm">
                        <span className="rounded-full bg-slate-100 px-4 py-1 text-xs font-semibold text-slate-700">{ticket.status_label}</span>
                        <div className="text-slate-500">Opened {ticket.created_at_display}</div>
                        <form method="POST" action={routes?.status} data-native="true">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <input type="hidden" name="_method" value="PATCH" />
                            <input type="hidden" name="status" value={ticket.status === 'closed' ? 'open' : 'closed'} />
                            <button type="submit" className="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                                {ticket.status === 'closed' ? 'Reopen ticket' : 'Close ticket'}
                            </button>
                        </form>
                    </div>
                </div>

                <div className="card mt-6 p-6">
                    <div className="section-label">Ticket details</div>
                    <form method="POST" action={routes?.update} className="mt-4 grid gap-4 md:grid-cols-2" data-native="true">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="_method" value="PATCH" />
                        <div className="md:col-span-2">
                            <label className="text-sm text-slate-600">Subject</label>
                            <input name="subject" defaultValue={ticket.subject} required className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
                            {errors?.subject ? <div className="mt-1 text-xs text-rose-600">{errors.subject}</div> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Priority</label>
                            <select name="priority" defaultValue={ticket.priority} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Status</label>
                            <select name="status" defaultValue={ticket.status} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                                <option value="open">Open</option>
                                <option value="answered">Answered</option>
                                <option value="customer_reply">Customer Reply</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div className="md:col-span-2 flex justify-end"><button type="submit" className="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Save changes</button></div>
                    </form>
                    <div className="mt-4 flex justify-end">
                        <form method="POST" action={routes?.destroy} data-native="true" onSubmit={(e) => { if (!window.confirm(`Delete ticket ${ticket.ticket_no}?`)) e.preventDefault(); }}>
                            <input type="hidden" name="_token" value={csrfToken} />
                            <input type="hidden" name="_method" value="DELETE" />
                            <button type="submit" className="rounded-full border border-rose-200 px-5 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300 hover:text-rose-500">Delete ticket</button>
                        </form>
                    </div>
                </div>
            </div>

            <div className="card mt-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">AI Assistant</div>
                        <div className="mt-1 text-sm text-slate-500">Get a quick summary and suggested reply.</div>
                    </div>
                    <button type="button" onClick={runAi} disabled={!ai_ready || aiLoading} className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50">
                        {aiLoading ? 'Generating...' : 'Generate AI'}
                    </button>
                </div>
                {!ai_ready ? <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">GOOGLE_AI_API_KEY missing.</div> : null}
                {aiError ? <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{aiError}</div> : null}

                <div className="mt-5 grid gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-slate-100 bg-white p-4 text-sm"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Summary</div><div className="mt-2 text-slate-700">{summary}</div></div>
                    <div className="rounded-2xl border border-slate-100 bg-white p-4 text-sm"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Signals</div><div className="mt-2 text-slate-600">Category: {category}</div><div className="mt-1 text-slate-600">Urgency: {urgency}</div><div className="mt-1 text-slate-600">Sentiment: {sentiment}</div></div>
                    <div className="rounded-2xl border border-slate-100 bg-white p-4 text-sm"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Next steps</div><ul className="mt-2 list-disc space-y-1 pl-4 text-slate-700">{nextSteps.map((step, index) => <li key={`${step}-${index}`}>{step}</li>)}</ul></div>
                    <div className="rounded-2xl border border-slate-100 bg-white p-4 text-sm"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Suggested reply</div><textarea value={suggestedReply} onChange={(e) => setSuggestedReply(e.target.value)} rows={6} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700" /></div>
                </div>
            </div>

            <div className="mt-8 space-y-4">
                {replies.length === 0 ? (
                    <div className="card-muted p-4 text-sm text-slate-500">No replies yet.</div>
                ) : replies.map((reply) => (
                    <div key={reply.id} className={`flex ${reply.is_admin ? 'justify-end' : 'justify-start'}`}>
                        <div className="max-w-2xl rounded-2xl border border-slate-300 bg-white px-5 py-4 text-sm shadow-sm">
                            <div className="flex items-center justify-between text-xs text-slate-500"><span>{reply.author_name}</span><span>{reply.created_at_display}</span></div>
                            <div className="mt-3 whitespace-pre-line text-slate-700">{reply.message}</div>
                            {reply.attachment_url ? <div className="mt-3 text-xs text-slate-500">Attachment: <a href={reply.attachment_url} target="_blank" rel="noreferrer" className="font-semibold text-teal-600 hover:text-teal-500">{reply.attachment_name}</a></div> : null}
                        </div>
                    </div>
                ))}
            </div>

            <div id="replies" className="card mt-8 p-6">
                <div className="section-label">Post reply</div>
                <form method="POST" action={routes?.reply} className="mt-4 space-y-4" encType="multipart/form-data" data-native="true">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <textarea id="ticket-reply-message" name="message" rows={5} required className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700" defaultValue={suggestedReply} />
                    {errors?.message ? <div className="text-xs text-rose-600">{errors.message}</div> : null}
                    <div>
                        <label className="text-sm text-slate-600">Attachment (image/PDF)</label>
                        <input name="attachment" type="file" accept="image/*,.pdf" className="mt-2 block w-full text-sm text-slate-600" />
                        {errors?.attachment ? <div className="mt-1 text-xs text-rose-600">{errors.attachment}</div> : null}
                    </div>
                    <div className="flex justify-end"><button type="submit" className="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Send reply</button></div>
                </form>
            </div>
        </>
    );
}
