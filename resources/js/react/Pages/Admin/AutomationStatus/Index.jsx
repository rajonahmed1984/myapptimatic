import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';

const metricBadgeClass = (okClass, warningClass, value) => (value ? okClass : warningClass);

const enabledActions = (actions) => (Array.isArray(actions) ? actions.filter((action) => action?.enabled) : []);

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
    cronStatusLabel = 'Never',
    cronStatusClasses = 'bg-slate-100 text-slate-600',
    portalTimeZone = 'UTC',
    portalTimeLabel = '',
    cronSetup = false,
    cronInvoked = false,
    dailyCronRun = false,
    dailyCronCompleting = false,
    cronInvocationWindowHours = 24,
    dailyCronWindowHours = 24,
    cronUrl = '',
    aiHealth = {},
    dailyActions = [],
}) {
    const [portalTime, setPortalTime] = useState(portalTimeLabel);
    const visibleActions = useMemo(() => enabledActions(dailyActions), [dailyActions]);

    useEffect(() => {
        const formatter = new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone: portalTimeZone || 'UTC',
        });

        const updateTime = () => {
            setPortalTime(formatter.format(new Date()));
        };

        updateTime();
        const intervalId = window.setInterval(updateTime, 1000);

        return () => {
            window.clearInterval(intervalId);
        };
    }, [portalTimeZone]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="section-label">Automation</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Automation Status</h1>
                    <p className="mt-2 text-sm text-slate-600">
                        Live status for billing cron, queue health, and daily automation actions.
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClasses}`}>{statusLabel}</span>
                    <a
                        href={routes?.cron_settings}
                        data-native="true"
                        className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                    >
                        Cron settings
                    </a>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-4">
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Last Invocation</div>
                    <div className="mt-2 text-xl font-semibold text-slate-900">{lastInvocationText}</div>
                    <div className="text-xs text-slate-500">{lastInvocationAt}</div>
                </div>
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Last Completion</div>
                    <div className="mt-2 text-xl font-semibold text-slate-900">{lastCompletionText}</div>
                    <div className="text-xs text-slate-500">{lastCompletionAt}</div>
                </div>
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Next Daily Run</div>
                    <div className="mt-2 text-xl font-semibold text-slate-900">{nextDailyRunText}</div>
                    <div className="text-xs text-slate-500">{nextDailyRunAt}</div>
                </div>
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Last Status</div>
                    <div className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold ${cronStatusClasses}`}>
                        {cronStatusLabel}
                    </div>
                    <div className="mt-2 text-xs text-slate-500">
                        Portal time: <span>{portalTime}</span>
                    </div>
                </div>
            </div>

            {lastStatus === 'failed' && lastError ? (
                <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <div className="font-semibold">Last Error</div>
                    <div className="mt-1">{lastError}</div>
                </div>
            ) : null}

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div className="card p-6">
                    <div className="section-label">Cron Health</div>
                    <div className="mt-4 grid gap-3">
                        <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div>
                                <div className="text-sm font-semibold text-slate-900">Cron Setup</div>
                                <div className="text-xs text-slate-500">Token configured</div>
                            </div>
                            <span
                                className={`rounded-full px-3 py-1 text-xs font-semibold ${metricBadgeClass(
                                    'bg-emerald-100 text-emerald-700',
                                    'bg-rose-100 text-rose-700',
                                    cronSetup,
                                )}`}
                            >
                                {cronSetup ? 'Ok' : 'Error'}
                            </span>
                        </div>
                        <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div>
                                <div className="text-sm font-semibold text-slate-900">Invocation Window</div>
                                <div className="text-xs text-slate-500">Within {cronInvocationWindowHours} hours</div>
                            </div>
                            <span
                                className={`rounded-full px-3 py-1 text-xs font-semibold ${metricBadgeClass(
                                    'bg-emerald-100 text-emerald-700',
                                    'bg-amber-100 text-amber-700',
                                    cronInvoked,
                                )}`}
                            >
                                {cronInvoked ? 'Ok' : 'Warning'}
                            </span>
                        </div>
                        <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div>
                                <div className="text-sm font-semibold text-slate-900">Daily Run</div>
                                <div className="text-xs text-slate-500">Within {dailyCronWindowHours} hours</div>
                            </div>
                            <span
                                className={`rounded-full px-3 py-1 text-xs font-semibold ${metricBadgeClass(
                                    'bg-emerald-100 text-emerald-700',
                                    'bg-amber-100 text-amber-700',
                                    dailyCronRun,
                                )}`}
                            >
                                {dailyCronRun ? 'Ok' : 'Warning'}
                            </span>
                        </div>
                        <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div>
                                <div className="text-sm font-semibold text-slate-900">Completion State</div>
                                <div className="text-xs text-slate-500">Last cron run completion</div>
                            </div>
                            <span
                                className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                    dailyCronCompleting
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : lastStatus === 'failed'
                                          ? 'bg-rose-100 text-rose-700'
                                          : 'bg-slate-100 text-slate-600'
                                }`}
                            >
                                {dailyCronCompleting ? 'Ok' : lastStatus === 'failed' ? 'Error' : 'Pending'}
                            </span>
                        </div>
                    </div>

                    <div className="mt-5">
                        <label className="text-sm text-slate-600">Secure cron URL</label>
                        <input
                            value={cronUrl || ''}
                            readOnly
                            className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700"
                        />
                    </div>
                </div>

                <div className="card p-6">
                    <div className="section-label">AI Queue</div>
                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">AI Enabled</div>
                            <div className="mt-1 font-semibold text-slate-900">{aiHealth?.enabled ? 'Yes' : 'No'}</div>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Risk Module</div>
                            <div className="mt-1 font-semibold text-slate-900">{aiHealth?.risk_enabled ? 'On' : 'Off'}</div>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Pending Jobs</div>
                            <div className="mt-1 font-semibold text-slate-900">{aiHealth?.queue_pending ?? 0}</div>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Failed Jobs</div>
                            <div className={`mt-1 font-semibold ${(aiHealth?.queue_failed ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900'}`}>
                                {aiHealth?.queue_failed ?? 0}
                            </div>
                        </div>
                    </div>
                    <div className={`mt-4 inline-flex rounded-full px-3 py-1 text-xs font-semibold ${aiHealth?.status_classes || ''}`}>
                        {aiHealth?.status_label || 'Unknown'}
                    </div>
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Daily Actions (Last Run)</div>
                <div className="mt-1 text-sm text-slate-500">Only enabled modules are shown.</div>
                <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {visibleActions.length > 0 ? (
                        visibleActions.map((action) => (
                            <div key={action.label} className="rounded-2xl border border-slate-200 bg-white p-4">
                                <div className="text-sm font-semibold text-slate-900">{action.label}</div>
                                <div className="mt-3 space-y-2">
                                    {(action.stats || []).map((stat) => (
                                        <div key={`${action.label}-${stat.label}`} className="flex items-center justify-between text-sm">
                                            <span className="text-slate-500">{stat.label}</span>
                                            <span className="font-semibold text-slate-900">{stat.value}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="text-sm text-slate-500">No enabled automation actions found.</div>
                    )}
                </div>
            </div>
        </>
    );
}
