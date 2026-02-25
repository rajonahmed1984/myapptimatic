import React from 'react';
import { Head } from '@inertiajs/react';

function toMoney(symbol, code, amount) {
    const value = Number.parseFloat(amount ?? 0);
    const safe = Number.isFinite(value) ? value : 0;

    return `${symbol}${safe.toFixed(2)}${code}`;
}

function statusClass(value) {
    return Number(value || 0) < 0 ? 'text-rose-600' : 'text-emerald-600';
}

function buildTrendPoints(values) {
    const list = Array.isArray(values) ? values.map((v) => Number(v || 0)) : [];
    if (list.length < 2) return '';
    const max = Math.max(1, ...list);
    return list
        .map((value, index) => {
            const x = (index / (list.length - 1)) * 100;
            const y = 56 - (value / max) * 52;
            return `${x},${y}`;
        })
        .join(' ');
}

export default function Dashboard({
    pageTitle = 'Expense Dashboard',
    filters = {},
    expenseTotal = 0,
    incomeReceived = 0,
    payoutExpenseTotal = 0,
    netIncome = 0,
    netCashflow = 0,
    categoryTotals = [],
    employeeTotals = [],
    salesRepTotals = [],
    monthlyTotal = 0,
    yearlyTotal = 0,
    topCategories = [],
    trendLabels = [],
    trendExpenses = [],
    trendIncome = [],
    categories = [],
    peopleOptions = [],
    currencyCode = 'BDT',
    currencySymbol = '',
    aiSummary = null,
    aiError = null,
    routes = {},
}) {
    const sourceSelections = Array.isArray(filters?.sources) ? filters.sources : [];
    const topCategory = Array.isArray(categoryTotals) && categoryTotals.length > 0 ? categoryTotals[0] : null;
    const expensePath = buildTrendPoints(trendExpenses);
    const incomePath = buildTrendPoints(trendIncome);
    const expenseDeltaWindow = Math.min(30, trendExpenses.length);
    const currentExpenseWindowTotal =
        expenseDeltaWindow > 0
            ? trendExpenses.slice(-expenseDeltaWindow).reduce((sum, value) => sum + Number(value || 0), 0)
            : 0;
    const previousExpenseWindowTotal =
        expenseDeltaWindow > 0
            ? trendExpenses.slice(-(expenseDeltaWindow * 2), -expenseDeltaWindow).reduce((sum, value) => sum + Number(value || 0), 0)
            : 0;
    const expenseDeltaPercent =
        previousExpenseWindowTotal > 0 ? ((currentExpenseWindowTotal - previousExpenseWindowTotal) / previousExpenseWindowTotal) * 100 : null;
    const incomeCoveragePercent = expenseTotal > 0 ? (incomeReceived / expenseTotal) * 100 : 0;
    const payoutSharePercent = expenseTotal > 0 ? (payoutExpenseTotal / expenseTotal) * 100 : 0;

    return (
        <>
            <Head title={pageTitle} />

            <div className="card p-6">
                <div className="section-label">Trend overview</div>
                <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        {expensePath && incomePath ? (
                            <svg viewBox="0 0 100 62" className="h-72 w-full">
                                <polyline points={expensePath} fill="none" stroke="#ef4444" strokeWidth="0.9" />
                                <polyline points={incomePath} fill="none" stroke="#10b981" strokeWidth="0.9" strokeDasharray="1.7 1.2" />
                            </svg>
                        ) : (
                            <div className="text-sm text-slate-500">Not enough data to plot trends.</div>
                        )}
                        <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-600">
                            <span className="inline-flex items-center gap-1.5">
                                <span className="h-2.5 w-2.5 rounded-full bg-rose-500" />
                                Expenses
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />
                                Income received
                            </span>
                        </div>
                        {trendLabels.length > 0 ? (
                            <div className="mt-2 text-[11px] text-slate-500">
                                {String(trendLabels[0] || '')} - {String(trendLabels[trendLabels.length - 1] || '')}
                            </div>
                        ) : null}
                    </div>

                    <div className="space-y-3">
                        <div className="rounded-xl border border-slate-200 bg-white px-3 py-3">
                            <div className="text-xs text-slate-500">Total Expenses</div>
                            <div className="text-2xl font-semibold text-slate-900">{toMoney(currencySymbol, currencyCode, expenseTotal)}</div>
                            <div className="text-xs text-rose-600">
                                {expenseDeltaPercent !== null
                                    ? `${expenseDeltaPercent >= 0 ? '+' : ''}${expenseDeltaPercent.toFixed(0)}% vs previous ${expenseDeltaWindow} points`
                                    : 'No previous period data'}
                            </div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white px-3 py-3">
                            <div className="text-xs text-slate-500">Income Received</div>
                            <div className="text-2xl font-semibold text-slate-900">{toMoney(currencySymbol, currencyCode, incomeReceived)}</div>
                            <div className="text-xs text-emerald-600">{incomeCoveragePercent.toFixed(0)}% coverage of expenses</div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white px-3 py-3">
                            <div className="text-xs text-slate-500">Payout Expenses</div>
                            <div className="text-2xl font-semibold text-slate-900">{toMoney(currencySymbol, currencyCode, payoutExpenseTotal)}</div>
                            <div className="text-xs text-amber-600">{payoutSharePercent.toFixed(0)}% of total expenses</div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="rounded-xl border border-slate-200 bg-white px-3 py-3">
                                <div className="text-xs text-slate-500">Net Income</div>
                                <div className={`mt-1 text-lg font-semibold ${statusClass(netIncome)}`}>{toMoney(currencySymbol, currencyCode, netIncome)}</div>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-white px-3 py-3">
                                <div className="text-xs text-slate-500">Net Cashflow</div>
                                <div className={`mt-1 text-lg font-semibold ${statusClass(netCashflow)}`}>{toMoney(currencySymbol, currencyCode, netCashflow)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Filters</div>
                <form method="GET" action={routes?.index} data-native="true" className="mt-4 grid gap-3 text-sm md:grid-cols-5">
                    <div>
                        <label className="text-xs text-slate-500">Start date</label>
                        <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="start_date" defaultValue={filters?.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">End date</label>
                        <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="end_date" defaultValue={filters?.end_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Category</label>
                        <select name="category_id" defaultValue={filters?.category_id || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">All</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Employee</label>
                        <select name="person" defaultValue={filters?.person || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">All</option>
                            {peopleOptions.map((option) => (
                                <option key={option.key} value={option.key}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="mt-7 flex flex-wrap items-center gap-3 md:col-span-5">
                        <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            Apply
                        </button>
                        <a href={routes?.index} data-native="true" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            Reset
                        </a>
                    </div>

                    <div className="md:col-span-5">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Sources</div>
                        <div className="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                            {['manual', 'salary', 'contract_payout', 'sales_payout'].map((source) => (
                                <label key={source} className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        name="sources[]"
                                        value={source}
                                        defaultChecked={sourceSelections.includes(source)}
                                        className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                    />
                                    {source === 'manual' ? 'Manual' : source === 'salary' ? 'Salaries' : source === 'contract_payout' ? 'Contract Payouts' : 'Sales Rep Payouts'}
                                </label>
                            ))}
                        </div>
                    </div>
                </form>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-3">
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">This month expenses</div>
                    <div className="mt-2 text-2xl font-semibold text-rose-600">{toMoney(currencySymbol, currencyCode, monthlyTotal)}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">This year expenses</div>
                    <div className="mt-2 text-2xl font-semibold text-rose-600">{toMoney(currencySymbol, currencyCode, yearlyTotal)}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Top category</div>
                    <div className="mt-2 text-base font-semibold text-slate-900">{topCategory?.name || 'No data'}</div>
                    <div className="mt-1 text-sm text-slate-500">{topCategory ? toMoney(currencySymbol, currencyCode, topCategory.total) : '-'}</div>
                </div>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_2fr]">
                <div className="card p-6">
                    <div className="section-label">Expense by category</div>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        {categoryTotals.length > 0 ? (
                            categoryTotals.map((item, index) => (
                                <div key={`${item.category_id}-${index}`} className="flex items-center justify-between">
                                    <div>{item.name}</div>
                                    <div className="font-semibold text-slate-900">{toMoney(currencySymbol, currencyCode, item.total)}</div>
                                </div>
                            ))
                        ) : (
                            <div className="text-sm text-slate-500">No expenses found.</div>
                        )}
                    </div>
                </div>

                <div className="card p-6">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <div className="section-label">Google AI Summary</div>
                            <div className="mt-1 text-[11px] text-slate-500">Quick signals for this period</div>
                        </div>
                        <a href={routes?.refresh_ai} data-native="true" className="rounded-full border border-emerald-200 bg-white px-3 py-1 text-[11px] font-semibold text-emerald-700">
                            Refresh AI
                        </a>
                    </div>
                    <div className="mt-4 rounded-2xl border border-white/60 bg-white/80 p-4 text-[13px] leading-relaxed text-slate-600">
                        {aiSummary ? (
                            <pre className="whitespace-pre-wrap font-sans">{aiSummary}</pre>
                        ) : aiError ? (
                            <div className="text-xs text-slate-500">AI summary unavailable: {aiError}</div>
                        ) : (
                            <div className="text-xs text-slate-500">AI summary is not available yet.</div>
                        )}
                    </div>
                </div>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_1fr]">
                <div className="card p-6">
                    <div className="section-label">Expense by employee</div>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        {employeeTotals.length > 0 ? (
                            employeeTotals.map((item, index) => (
                                <div key={`${item.label}-${index}`} className="flex items-center justify-between">
                                    <div>{item.label}</div>
                                    <div className="font-semibold text-slate-900">{toMoney(currencySymbol, currencyCode, item.total)}</div>
                                </div>
                            ))
                        ) : (
                            <div className="text-sm text-slate-500">No employee payouts in this range.</div>
                        )}
                    </div>
                </div>
                <div className="card p-6">
                    <div className="section-label">Expense by sales representatives</div>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        {salesRepTotals.length > 0 ? (
                            salesRepTotals.map((item, index) => (
                                <div key={`${item.label}-${index}`} className="flex items-center justify-between">
                                    <div>{item.label}</div>
                                    <div className="font-semibold text-slate-900">{toMoney(currencySymbol, currencyCode, item.total)}</div>
                                </div>
                            ))
                        ) : (
                            <div className="text-sm text-slate-500">No sales rep payouts in this range.</div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
