import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';

const enabledActions = (actions) => (Array.isArray(actions) ? actions.filter((action) => action?.enabled) : []);

const metricTone = (okValue, ok = 'bg-emerald-100 text-emerald-700', warn = 'bg-amber-100 text-amber-700') => (
    okValue ? ok : warn
);

export default function Index({
    pageTitle = 'Automation Status',
    routes = {},
    statusLabel = 'Pending',
    statusClasses = 'bg-slate-100 text-slate-600',
    lastStatus = null,
    lastError = null,
    lastInvocationText = 'Never',
    lastInvocationAt = 'Not yet invoked',
    lastCompletionText = 'Never',
    lastCompletionAt = 'Not yet completed',
    nextDailyRunText = 'Not scheduled',
    nextDailyRunAt = 'No historical run',
    portalTimeZone = 'UTC',
    portalTimeLabel = '--',
    cronSetup = false,
    cronInvoked = false,
    dailyCronRun = false,
    dailyCronCompleting = false,
    cronInvocationWindowHours = 24,
    dailyCronWindowHours = 24,
    aiHealth = {},
    dailyActions = [],
}) {
    const visibleActions = useMemo(() => enabledActions(dailyActions), [dailyActions]);

    const actionRows = useMemo(() => {
        return visibleActions
            .map((action) => {
                const stats = Array.isArray(action?.stats) ? action.stats : [];
                const total = stats.reduce((carry, stat) => carry + Number(stat?.value || 0), 0);
                const detail = stats
                    .filter((stat) => Number(stat?.value || 0) > 0)
                    .map((stat) => `${stat.label}: ${stat.value}`)
                    .join(', ');

                return {
                    label: action?.label || 'Action',
                    total,
                    detail,
                };
            })
            .filter((row) => row.total > 0)
            .sort((a, b) => b.total - a.total);
    }, [visibleActions]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="section-label">Automation</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Automation Status</h1>
                    <p className="mt-2 text-sm text-slate-600">Clean live summary for daily automation and cron execution health.</p>
                </div>
                <div className="flex items-center gap-3">
                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClasses}`}>{statusLabel}</span>
                    <a
                        href={routes?.cron_settings}
                        data-native="true"
                        className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                    >
                        Automation settings
                    </a>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <SummaryCard title="Last Completion" primary={lastCompletionText} secondary={lastCompletionAt} />
                <SummaryCard title="Last Invocation" primary={lastInvocationText} secondary={lastInvocationAt} />
                <SummaryCard title="Next Daily Run" primary={nextDailyRunText} secondary={nextDailyRunAt} />
            </div>

            {lastStatus === 'failed' && lastError ? (
                <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <div className="font-semibold">Last Error</div>
                    <div className="mt-1">{lastError}</div>
                </div>
            ) : null}

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <div className="card p-6 xl:col-span-2">
                    <div className="section-label">Cron Health</div>
                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                        <HealthRow
                            title="Cron token setup"
                            note="Automation token configured"
                            ok={cronSetup}
                            okText="Ready"
                            warnText="Missing"
                            warnClasses="bg-rose-100 text-rose-700"
                        />
                        <HealthRow
                            title="Invocation window"
                            note={`Triggered within ${cronInvocationWindowHours} hours`}
                            ok={cronInvoked}
                            okText="On track"
                            warnText="Delayed"
                        />
                        <HealthRow
                            title="Daily run window"
                            note={`Executed within ${dailyCronWindowHours} hours`}
                            ok={dailyCronRun}
                            okText="On track"
                            warnText="Delayed"
                        />
                        <HealthRow
                            title="Completion state"
                            note="Last run completion result"
                            ok={dailyCronCompleting}
                            okText="Completed"
                            warnText={lastStatus === 'failed' ? 'Failed' : 'Pending'}
                            warnClasses={lastStatus === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'}
                        />
                    </div>
                </div>

                <div className="card p-6">
                    <div className="section-label">AI Monitor</div>
                    <div className="mt-4 space-y-3">
                        <InfoRow label="AI status" value={aiHealth?.status_label || 'Unknown'} classes={aiHealth?.status_classes || 'bg-slate-100 text-slate-600'} />
                        <InfoRow label="AI enabled" value={aiHealth?.enabled ? 'Yes' : 'No'} />
                        <InfoRow label="Risk module" value={aiHealth?.risk_enabled ? 'On' : 'Off'} />
                        <InfoRow label="Queue pending" value={aiHealth?.queue_pending ?? 0} />
                        <InfoRow label="Queue failed" value={aiHealth?.queue_failed ?? 0} valueClasses={(aiHealth?.queue_failed ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900'} />
                        <div className="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                            Portal time ({portalTimeZone}): <span className="whitespace-nowrap font-semibold text-slate-700">{portalTimeLabel}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Action Results (Last Run)</div>
                <div className="mt-1 text-sm text-slate-500">Only actions with real output are shown.</div>

                {actionRows.length > 0 ? (
                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th className="px-3 py-2">Action</th>
                                    <th className="px-3 py-2">Details</th>
                                    <th className="px-3 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {actionRows.map((row) => (
                                    <tr key={row.label} className="border-b border-slate-100">
                                        <td className="px-3 py-2 font-semibold text-slate-900">{row.label}</td>
                                        <td className="px-3 py-2 text-slate-600">{row.detail || '--'}</td>
                                        <td className="px-3 py-2 text-right font-semibold tabular-nums text-slate-900">{row.total}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        No action output found in the last run.
                    </div>
                )}
            </div>
        </>
    );
}

function SummaryCard({ title, primary, secondary }) {
    return (
        <div className="card p-4">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <div className="mt-2 whitespace-nowrap text-xl font-semibold text-slate-900">{primary}</div>
            <div className="whitespace-nowrap text-xs text-slate-500">{secondary}</div>
        </div>
    );
}

function HealthRow({ title, note, ok, okText, warnText, warnClasses = 'bg-amber-100 text-amber-700' }) {
    const classes = ok ? metricTone(true) : warnClasses;
    return (
        <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div>
                <div className="text-sm font-semibold text-slate-900">{title}</div>
                <div className="text-xs text-slate-500">{note}</div>
            </div>
            <span className={`whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold ${ok ? metricTone(true) : classes}`}>
                {ok ? okText : warnText}
            </span>
        </div>
    );
}

function InfoRow({ label, value, classes = '', valueClasses = 'text-slate-900' }) {
    if (classes) {
        return (
            <div className="flex items-center justify-between rounded-xl border border-slate-100 bg-white px-3 py-2">
                <span className="text-sm text-slate-600">{label}</span>
                <span className={`whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ${classes}`}>{value}</span>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between rounded-xl border border-slate-100 bg-white px-3 py-2">
            <span className="text-sm text-slate-600">{label}</span>
            <span className={`whitespace-nowrap text-sm font-semibold tabular-nums ${valueClasses}`}>{value}</span>
        </div>
    );
}

