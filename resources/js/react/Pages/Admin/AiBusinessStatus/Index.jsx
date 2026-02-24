import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

const formatNumber = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Index({ metrics = {}, filters = {}, aiReady = false, routes = {} }) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    const [status, setStatus] = useState('Ready');
    const [statusClass, setStatusClass] = useState('bg-slate-100 text-slate-600');
    const [summary, setSummary] = useState('Click "Generate AI Summary" to create the report.');
    const [isLoading, setIsLoading] = useState(false);

    const currencyCode = metrics?.currency?.code || '';
    const tasks = metrics?.tasks || {};
    const projects = metrics?.projects || {};
    const finance = metrics?.finance || {};
    const projections = metrics?.projections || {};

    const generate = async (event) => {
        event.preventDefault();
        if (!aiReady || isLoading) {
            return;
        }

        const form = new FormData(event.currentTarget);
        setIsLoading(true);
        setStatus('Generating...');
        setStatusClass('bg-amber-100 text-amber-700');
        setSummary('Working on your report...');

        try {
            const response = await fetch(routes?.generate, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: form,
            });

            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload?.error || 'Failed to generate summary.');
            }

            setSummary(String(payload?.summary || 'No summary returned.'));
            setStatus('Updated');
            setStatusClass('bg-emerald-100 text-emerald-700');
        } catch (error) {
            setSummary(String(error?.message || 'Failed to generate summary.'));
            setStatus('Error');
            setStatusClass('bg-rose-100 text-rose-700');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <>
            <Head title="AI Business Status" />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-1">
                    <div className="card p-5">
                        <div className="section-label">Filters</div>
                        <div className="mt-3 text-sm text-slate-500">Set the reporting window and generate an AI summary.</div>

                        <form className="mt-4 grid gap-4" onSubmit={generate}>
                            <div>
                                <label className="text-xs uppercase tracking-[0.2em] text-slate-400">Start date</label>
                                <input type="date" name="start_date" defaultValue={filters?.start_date || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label className="text-xs uppercase tracking-[0.2em] text-slate-400">End date</label>
                                <input type="date" name="end_date" defaultValue={filters?.end_date || ''} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label className="text-xs uppercase tracking-[0.2em] text-slate-400">Projection days</label>
                                <input
                                    type="number"
                                    min="7"
                                    max="120"
                                    name="projection_days"
                                    defaultValue={filters?.projection_days || 30}
                                    className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                                <div className="mt-1 text-xs text-slate-400">Future due window for income/expense projection.</div>
                            </div>

                            <button
                                type="submit"
                                disabled={!aiReady || isLoading}
                                className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Generate AI Summary
                            </button>

                            {!aiReady ? (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                    GOOGLE_AI_API_KEY is missing. Add it to .env to enable AI summary.
                                </div>
                            ) : null}
                        </form>
                    </div>

                    <div className="card mt-6 p-5">
                        <div className="section-label">Snapshot</div>
                        <div className="mt-4 grid gap-4">
                            <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Tasks</div>
                                <div className="mt-2 text-sm text-slate-600">Total: {tasks?.total || 0}</div>
                                <div className="mt-1 text-xs text-slate-500">
                                    Open {tasks?.open || 0} | In progress {tasks?.in_progress || 0} | Completed {tasks?.completed || 0}
                                </div>
                            </div>
                            <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Projects</div>
                                <div className="mt-2 text-sm text-slate-600">Total: {projects?.total || 0}</div>
                                <div className="mt-1 text-xs text-slate-500">
                                    By status: {Object.entries(projects?.by_status || {}).map(([key, val]) => `${key}: ${val}`).join(', ') || '--'}
                                </div>
                            </div>
                            <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Finance</div>
                                <div className="mt-2 text-sm text-slate-600">Income: {currencyCode} {formatNumber(finance?.income_total)}</div>
                                <div className="mt-1 text-sm text-slate-600">Expense: {currencyCode} {formatNumber(finance?.expense_total)}</div>
                                <div className="mt-1 text-xs text-slate-500">Net profit: {currencyCode} {formatNumber(finance?.net_profit)}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="lg:col-span-2">
                    <div className="card p-6">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <div className="section-label">AI Summary</div>
                                <div className="mt-1 text-sm text-slate-500">Generated with Gemini using live business metrics.</div>
                            </div>
                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass}`}>{status}</span>
                        </div>

                        <div className="mt-5 rounded-2xl border border-slate-300 bg-white p-5 text-sm text-slate-700 whitespace-pre-wrap">
                            {summary}
                        </div>
                    </div>

                    <div className="card mt-6 p-6">
                        <div className="section-label">Projection Highlights</div>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">
                                    Income due (next {filters?.projection_days || 30} days)
                                </div>
                                <div className="mt-2 text-xl font-semibold text-emerald-600">
                                    {currencyCode} {formatNumber(projections?.income_due_next_window)}
                                </div>
                                <div className="mt-1 text-xs text-slate-500">Invoices: {projections?.income_due_count || 0}</div>
                            </div>
                            <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">
                                    Expense due (next {filters?.projection_days || 30} days)
                                </div>
                                <div className="mt-2 text-xl font-semibold text-rose-600">
                                    {currencyCode} {formatNumber(projections?.expense_due_next_window)}
                                </div>
                                <div className="mt-1 text-xs text-slate-500">Expense invoices: {projections?.expense_due_count || 0}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
