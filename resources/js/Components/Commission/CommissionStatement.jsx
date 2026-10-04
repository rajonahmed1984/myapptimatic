import React from 'react';

/**
 * A sales rep's commission account in one view: what clients have paid for,
 * everything the rep has taken, and the balance between them.
 *
 * audience="admin" talks about the rep by name; audience="rep" talks to them.
 */

export const taka = (value) =>
    `৳${Math.abs(Number(value || 0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const n = (value) => Number(value || 0);

export function balanceTone(balance) {
    if (n(balance) < -0.009) return 'owes';
    if (n(balance) > 0.009) return 'due';
    return 'settled';
}

export function BalanceBadge({ balance, audience = 'admin' }) {
    const tone = balanceTone(balance);
    const styles = {
        owes: 'border-rose-200 bg-rose-50 text-rose-700',
        due: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        settled: 'border-slate-200 bg-slate-50 text-slate-600',
    };
    const labels = {
        owes: audience === 'rep' ? `You owe ${taka(balance)}` : `Holds ${taka(balance)} extra`,
        due: audience === 'rep' ? `Due to you ${taka(balance)}` : `Owed ${taka(balance)}`,
        settled: 'Settled',
    };

    return (
        <span className={`inline-flex whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-semibold ${styles[tone]}`}>
            {labels[tone]}
        </span>
    );
}

function ProgressBar({ percent, tone = 'teal' }) {
    const width = Math.max(0, Math.min(100, n(percent)));
    const colors = { teal: 'bg-teal-500', sky: 'bg-sky-500' };

    return (
        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div className={`h-full rounded-full ${colors[tone] || colors.teal}`} style={{ width: `${width}%` }} />
        </div>
    );
}

function Row({ label, value, tone = 'slate', sign = '' }) {
    const colors = { slate: 'text-slate-700', rose: 'text-rose-700', emerald: 'text-emerald-700', amber: 'text-amber-700' };

    return (
        <div className="flex items-center justify-between gap-3 text-xs">
            <span className="text-slate-500">{label}</span>
            <span className={`font-semibold tabular-nums ${colors[tone]}`}>{sign}{taka(value)}</span>
        </div>
    );
}

function narrative(statement, audience, name) {
    const s = statement || {};
    const taken = s.taken || {};
    const who = audience === 'rep' ? 'You' : name || 'The rep';
    const has = audience === 'rep' ? 'have' : 'has';
    const holds = audience === 'rep' ? 'hold' : 'holds';
    const their = audience === 'rep' ? 'your' : 'their';

    const parts = [];
    if (n(taken.retained) > 0) parts.push(`${taka(taken.retained)} kept from client payments collected`);
    if (n(taken.advances) > 0) parts.push(`${taka(taken.advances)} in company advances`);
    if (n(taken.payouts) > 0) parts.push(`${taka(taken.payouts)} in payouts`);

    let text = `${who} ${has} received ${taka(taken.gross)}`;
    text += parts.length ? ` (${parts.join(', ')})` : '';
    text += n(taken.recovered) > 0 ? ` and paid back ${taka(taken.recovered)}` : '';
    text += `. Clients have paid for ${taka(s.commission_earned)} of ${their} ${taka(s.commission_total)} commission.`;

    const tone = balanceTone(s.balance);
    if (tone === 'owes') {
        text += ` So ${who.toLowerCase() === 'you' ? 'you' : who} ${holds} ${taka(s.balance)} more than ${audience === 'rep' ? 'you have' : 'they have'} earned.`;
        if (n(s.waiting_on_clients) > 0) {
            const after = n(s.balance_if_all_paid);
            text += ` When clients pay the remaining ${taka(s.waiting_on_clients)}, `;
            text += after < -0.009
                ? `the gap falls to ${taka(after)}.`
                : after > 0.009
                    ? `the gap closes and ${audience === 'rep' ? 'you would be owed' : 'the company would owe'} ${taka(after)}.`
                    : 'the account settles.';
        }
    } else if (tone === 'due') {
        text += audience === 'rep'
            ? ` The company owes you ${taka(s.balance)}.`
            : ` The company owes ${who} ${taka(s.balance)}.`;
    } else {
        text += ' The account is settled.';
    }

    return text;
}

function BalanceCard({ statement, audience, name }) {
    const tone = balanceTone(statement?.balance);
    const after = n(statement?.balance_if_all_paid);
    const styles = {
        owes: 'border-rose-200 bg-rose-50',
        due: 'border-emerald-200 bg-emerald-50',
        settled: 'border-slate-200 bg-white',
    };
    const titles = {
        owes: audience === 'rep' ? 'You owe the company' : `${name || 'Rep'} holds more than earned`,
        due: audience === 'rep' ? 'Due to you' : 'Company owes the rep',
        settled: 'Balance',
    };
    const valueColor = { owes: 'text-rose-700', due: 'text-emerald-700', settled: 'text-slate-900' };

    return (
        <div className={`rounded-2xl border p-4 ${styles[tone]}`}>
            <div className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{titles[tone]}</div>
            <div className={`mt-1 text-2xl font-bold tabular-nums ${valueColor[tone]}`}>
                {tone === 'owes' ? '−' : ''}{taka(statement?.balance)}
            </div>
            <div className="mt-1 text-xs text-slate-500">Earned (client paid) minus everything received</div>
            {n(statement?.waiting_on_clients) > 0 ? (
                <div className="mt-3 rounded-xl bg-white/70 px-3 py-2 text-xs text-slate-600">
                    Once clients pay the rest:{' '}
                    <span className={`font-semibold ${after < -0.009 ? 'text-rose-700' : after > 0.009 ? 'text-emerald-700' : 'text-slate-700'}`}>
                        {after < -0.009 ? `still ${taka(after)} over` : after > 0.009 ? `${taka(after)} due to ${audience === 'rep' ? 'you' : 'the rep'}` : 'settled'}
                    </span>
                </div>
            ) : null}
        </div>
    );
}

function LinesTable({ lines = [] }) {
    if (!lines.length) {
        return <div className="px-4 py-6 text-sm text-slate-500">No commission recorded yet.</div>;
    }

    const kindLabel = { project: 'Project', subscription: 'Service', invoice: 'Invoice', other: 'Other' };

    return (
        <>
            <div className="hidden overflow-x-auto md:block">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th className="px-4 py-2">Project / service</th>
                            <th className="px-4 py-2 text-right">Commission</th>
                            <th className="px-4 py-2">Client paid</th>
                            <th className="px-4 py-2 text-right">Earned</th>
                            <th className="px-4 py-2 text-right">Received</th>
                            <th className="px-4 py-2 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        {lines.map((line) => (
                            <tr key={line.key} className="border-t border-slate-100 align-top">
                                <td className="px-4 py-3">
                                    <div className="font-medium text-slate-800">{line.label}</div>
                                    <div className="text-xs text-slate-500">
                                        <span className="mr-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-600">{kindLabel[line.kind] || line.kind}</span>
                                        {line.customer || ''}
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums text-slate-700">{taka(line.commission_total)}</td>
                                <td className="px-4 py-3">
                                    {line.client_paid_percent !== null && line.client_paid_percent !== undefined ? (
                                        <div className="w-40">
                                            <div className="mb-1 flex justify-between text-xs text-slate-600">
                                                <span className="tabular-nums">{taka(line.client_paid)}</span>
                                                <span className="font-semibold">{line.client_paid_percent}%</span>
                                            </div>
                                            <ProgressBar percent={line.client_paid_percent} tone="sky" />
                                            <div className="mt-0.5 text-[11px] text-slate-400">of {taka(line.client_total)}</div>
                                        </div>
                                    ) : (
                                        <span className="text-xs text-slate-400">--</span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right font-semibold tabular-nums text-slate-800">{taka(line.commission_earned)}</td>
                                <td className="px-4 py-3 text-right tabular-nums text-slate-700">{taka(line.taken)}</td>
                                <td className="px-4 py-3 text-right">
                                    <span className={`font-semibold tabular-nums ${balanceTone(line.balance) === 'owes' ? 'text-rose-700' : balanceTone(line.balance) === 'due' ? 'text-emerald-700' : 'text-slate-500'}`}>
                                        {balanceTone(line.balance) === 'owes' ? '−' : ''}{taka(line.balance)}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="divide-y divide-slate-100 md:hidden">
                {lines.map((line) => (
                    <div key={line.key} className="space-y-2 px-4 py-3">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <div className="text-sm font-medium text-slate-800">{line.label}</div>
                                <div className="text-xs text-slate-500">{kindLabel[line.kind] || line.kind}{line.customer ? ` · ${line.customer}` : ''}</div>
                            </div>
                            <span className={`text-sm font-semibold tabular-nums ${balanceTone(line.balance) === 'owes' ? 'text-rose-700' : balanceTone(line.balance) === 'due' ? 'text-emerald-700' : 'text-slate-500'}`}>
                                {balanceTone(line.balance) === 'owes' ? '−' : ''}{taka(line.balance)}
                            </span>
                        </div>
                        {line.client_paid_percent !== null && line.client_paid_percent !== undefined ? (
                            <div>
                                <div className="mb-1 flex justify-between text-[11px] text-slate-500">
                                    <span>Client paid {taka(line.client_paid)} of {taka(line.client_total)}</span>
                                    <span className="font-semibold">{line.client_paid_percent}%</span>
                                </div>
                                <ProgressBar percent={line.client_paid_percent} tone="sky" />
                            </div>
                        ) : null}
                        <div className="grid grid-cols-3 gap-2 text-[11px] text-slate-500">
                            <div>Commission<div className="font-semibold text-slate-700">{taka(line.commission_total)}</div></div>
                            <div>Earned<div className="font-semibold text-slate-700">{taka(line.commission_earned)}</div></div>
                            <div>Received<div className="font-semibold text-slate-700">{taka(line.taken)}</div></div>
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}

export default function CommissionStatement({ statement, audience = 'admin', name = '', showLines = true, footer = null }) {
    if (!statement) {
        return null;
    }

    const taken = statement.taken || {};
    const who = audience === 'rep' ? 'you' : name || 'the rep';

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-4 py-3 sm:px-5">
                <div className="text-sm font-semibold text-slate-800">Commission statement</div>
                <div className="text-xs text-slate-500">Commission counts as earned only for what clients have actually paid.</div>
            </div>

            <div className="grid gap-3 p-4 sm:p-5 md:grid-cols-3">
                <div className="rounded-2xl border border-slate-200 p-4">
                    <div className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Earned (client paid)</div>
                    <div className="mt-1 text-2xl font-bold tabular-nums text-slate-900">{taka(statement.commission_earned)}</div>
                    <div className="mt-1 text-xs text-slate-500">of {taka(statement.commission_total)} total commission</div>
                    <div className="mt-3">
                        <ProgressBar percent={statement.earned_percent} />
                        <div className="mt-1 flex justify-between text-[11px] text-slate-500">
                            <span>{statement.earned_percent}% paid by clients</span>
                            {n(statement.waiting_on_clients) > 0 ? <span>{taka(statement.waiting_on_clients)} waiting</span> : null}
                        </div>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 p-4">
                    <div className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        {audience === 'rep' ? 'Received by you' : `Received by ${name || 'the rep'}`}
                    </div>
                    <div className="mt-1 text-2xl font-bold tabular-nums text-slate-900">{taka(taken.net)}</div>
                    <div className="mt-3 space-y-1.5">
                        <Row label="Kept from client collections" value={taken.retained} tone={n(taken.retained) > 0 ? 'amber' : 'slate'} />
                        <Row label="Company advances" value={taken.advances} />
                        <Row label="Commission payouts" value={taken.payouts} />
                        {n(taken.recovered) > 0 ? <Row label="Paid back" value={taken.recovered} tone="emerald" sign="−" /> : null}
                    </div>
                </div>

                <BalanceCard statement={statement} audience={audience} name={name} />
            </div>

            <div className="mx-4 mb-4 rounded-xl bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-700 sm:mx-5">
                {narrative(statement, audience, name)}
            </div>

            {footer}

            {showLines ? (
                <div className="border-t border-slate-100">
                    <div className="px-4 pt-3 text-xs font-semibold uppercase tracking-wider text-slate-500 sm:px-5">
                        By project / service · where {who} {audience === 'rep' ? 'are' : 'is'} over or under
                    </div>
                    <LinesTable lines={statement.lines || []} />
                </div>
            ) : null}
        </section>
    );
}
