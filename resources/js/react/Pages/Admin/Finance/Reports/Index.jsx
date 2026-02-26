import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';

const includesValue = (list, value) => Array.isArray(list) && list.includes(value);
const num = (value) => {
    const parsed = Number(value ?? 0);
    return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (amount, currency) => {
    const numeric = num(amount);
    const symbol = currency?.symbol || '';
    const code = currency?.code || '';
    return `${symbol}${numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${code}`;
};

const percent = (value) => `${num(value).toFixed(1)}%`;

export default function Index({
    pageTitle = 'Finance Reports',
    routes = {},
    filters = {},
    summary = {},
    tax = {},
    income_category_totals = [],
    expense_category_totals = [],
    employee_totals = [],
    trend = {},
    month_totals = [],
    income_categories = [],
    expense_categories = [],
    currency = {},
}) {
    const trendLabels = Array.isArray(trend?.labels) ? trend.labels : [];
    const trendIncome = Array.isArray(trend?.income) ? trend.income : [];
    const trendExpense = Array.isArray(trend?.expense) ? trend.expense : [];

    const trendRows = useMemo(
        () =>
            trendLabels.map((label, index) => {
                const income = num(trendIncome[index]);
                const expense = num(trendExpense[index]);
                const net = income - expense;
                const margin = income > 0 ? (net / income) * 100 : 0;
                return { label, income, expense, net, margin };
            }),
        [trendLabels, trendIncome, trendExpense],
    );

    const totalIncome = num(summary?.total_income);
    const totalExpense = num(summary?.total_expense);
    const netProfit = num(summary?.net_profit);
    const receivedIncome = num(summary?.received_income);
    const payoutExpense = num(summary?.payout_expense);
    const netCashflow = num(summary?.net_cashflow);
    const grossMargin = totalIncome > 0 ? (netProfit / totalIncome) * 100 : 0;
    const expenseRatio = totalIncome > 0 ? (totalExpense / totalIncome) * 100 : 0;
    const cashConversion = totalIncome > 0 ? (receivedIncome / totalIncome) * 100 : 0;
    const taxRate = num(tax?.taxable_base) > 0 ? (num(tax?.tax_amount) / num(tax?.taxable_base)) * 100 : 0;

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">Master finance reports</div>
                    <div className="mt-1 text-sm text-slate-500">One clean page for performance, profitability, tax, and payout health.</div>
                </div>
                <a href={routes?.tax_index} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                    Tax Settings
                </a>
            </div>

            <div className="card bg-gradient-to-br from-[#eef8fb] via-white to-[#f4f8ff] p-5">
                <div className="section-label">Report Controls</div>
                <form method="GET" action={routes?.index} className="mt-3 grid gap-3 text-sm md:grid-cols-6" data-native="true">
                    <div>
                        <label className="text-xs text-slate-500">Start date</label>
                        <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="start_date" defaultValue={filters?.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm" />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">End date</label>
                        <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="end_date" defaultValue={filters?.end_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm" />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Income basis</label>
                        <select name="income_basis" defaultValue={filters?.income_basis || 'received'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                            <option value="received">Received</option>
                            <option value="invoiced">Invoiced</option>
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Income category</label>
                        <select name="income_category_id" defaultValue={filters?.income_category_id || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                            <option value="">All</option>
                            {income_categories.map((category) => (
                                <option key={category.id} value={String(category.id)}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Expense category</label>
                        <select name="expense_category_id" defaultValue={filters?.expense_category_id || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                            <option value="">All</option>
                            {expense_categories.map((category) => (
                                <option key={category.id} value={String(category.id)}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="mt-6 flex items-center gap-2">
                        <button type="submit" className="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-500">
                            Apply
                        </button>
                        <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                            Reset
                        </a>
                    </div>
                    <div className="md:col-span-3">
                        <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Income sources</div>
                        <div className="mt-1.5 flex flex-wrap gap-4 text-xs text-slate-600">
                            <label className="flex items-center gap-2"><input type="checkbox" name="income_sources[]" value="manual" defaultChecked={includesValue(filters?.income_sources, 'manual')} className="h-4 w-4 rounded border-slate-300 text-emerald-600" /> Manual</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="income_sources[]" value="system" defaultChecked={includesValue(filters?.income_sources, 'system')} className="h-4 w-4 rounded border-slate-300 text-emerald-600" /> System</label>
                        </div>
                    </div>
                    <div className="md:col-span-3">
                        <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Expense sources</div>
                        <div className="mt-1.5 flex flex-wrap gap-4 text-xs text-slate-600">
                            <label className="flex items-center gap-2"><input type="checkbox" name="expense_sources[]" value="manual" defaultChecked={includesValue(filters?.expense_sources, 'manual')} className="h-4 w-4 rounded border-slate-300 text-emerald-600" /> Manual</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="expense_sources[]" value="salary" defaultChecked={includesValue(filters?.expense_sources, 'salary')} className="h-4 w-4 rounded border-slate-300 text-emerald-600" /> Salaries</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="expense_sources[]" value="contract_payout" defaultChecked={includesValue(filters?.expense_sources, 'contract_payout')} className="h-4 w-4 rounded border-slate-300 text-emerald-600" /> Contract</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="expense_sources[]" value="sales_payout" defaultChecked={includesValue(filters?.expense_sources, 'sales_payout')} className="h-4 w-4 rounded border-slate-300 text-emerald-600" /> Sales payout</label>
                        </div>
                    </div>
                </form>
            </div>

            <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <MetricCard label="Total Income" value={formatCurrency(totalIncome, currency)} tone="text-emerald-600" />
                <MetricCard label="Total Expense" value={formatCurrency(totalExpense, currency)} tone="text-rose-600" />
                <MetricCard label="Net Profit" value={formatCurrency(netProfit, currency)} tone={netProfit >= 0 ? 'text-emerald-600' : 'text-rose-600'} />
                <MetricCard label="Received Income" value={formatCurrency(receivedIncome, currency)} tone="text-slate-900" />
                <MetricCard label="Payout Expense" value={formatCurrency(payoutExpense, currency)} tone="text-amber-600" />
                <MetricCard label="Net Cashflow" value={formatCurrency(netCashflow, currency)} tone={netCashflow >= 0 ? 'text-emerald-600' : 'text-rose-600'} />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
                <div className="card p-6">
                    <div className="section-label">Period Performance</div>
                    <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
                        <table className="min-w-full text-left text-sm text-slate-700">
                            <thead>
                                <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th className="whitespace-nowrap px-3 py-2">Period</th>
                                    <th className="whitespace-nowrap px-3 py-2 text-right">Income</th>
                                    <th className="whitespace-nowrap px-3 py-2 text-right">Expense</th>
                                    <th className="whitespace-nowrap px-3 py-2 text-right">Net</th>
                                    <th className="whitespace-nowrap px-3 py-2 text-right">Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                {trendRows.length ? (
                                    trendRows.map((row, index) => (
                                        <tr key={`${row.label}-${index}`} className="border-t border-slate-100">
                                            <td className="whitespace-nowrap px-3 py-2 tabular-nums">{row.label}</td>
                                            <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums">{formatCurrency(row.income, currency)}</td>
                                            <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums">{formatCurrency(row.expense, currency)}</td>
                                            <td className={`whitespace-nowrap px-3 py-2 text-right tabular-nums font-semibold ${row.net >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>{formatCurrency(row.net, currency)}</td>
                                            <td className={`whitespace-nowrap px-3 py-2 text-right tabular-nums font-semibold ${row.margin >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>{percent(row.margin)}</td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="px-3 py-4 text-center text-slate-500">
                                            No trend data.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="space-y-3">
                    <InsightCard label="Gross Margin" value={percent(grossMargin)} hint="Net profit / total income" tone={grossMargin >= 0 ? 'text-emerald-600' : 'text-rose-600'} />
                    <InsightCard label="Expense Ratio" value={percent(expenseRatio)} hint="Total expense / total income" tone={expenseRatio <= 70 ? 'text-emerald-600' : 'text-rose-600'} />
                    <InsightCard label="Cash Conversion" value={percent(cashConversion)} hint="Received income / total income" tone={cashConversion >= 80 ? 'text-emerald-600' : 'text-amber-600'} />
                    <InsightCard label="Effective Tax Rate" value={percent(taxRate)} hint="Tax amount / taxable base" tone="text-slate-900" />
                </div>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <BreakdownCard title="Income by Category" rows={income_category_totals} labelKey="name" valueKey="total" base={Math.max(totalIncome, 1)} currency={currency} emptyText="No income entries." />
                <BreakdownCard title="Expense by Category" rows={expense_category_totals} labelKey="name" valueKey="total" base={Math.max(totalExpense, 1)} currency={currency} emptyText="No expense entries." />
                <BreakdownCard title="Employee Payout Breakdown" rows={employee_totals} labelKey="label" valueKey="total" base={Math.max(payoutExpense, 1)} currency={currency} emptyText="No payout entries in this range." />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <div className="card p-6">
                    <div className="section-label">Monthly Summary</div>
                    <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
                        <table className="min-w-full text-left text-sm text-slate-700">
                            <thead>
                                <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th className="whitespace-nowrap px-3 py-2">Month</th>
                                    <th className="whitespace-nowrap px-3 py-2 text-right">Income</th>
                                    <th className="whitespace-nowrap px-3 py-2 text-right">Expense</th>
                                    <th className="whitespace-nowrap px-3 py-2 text-right">Net</th>
                                    <th className="whitespace-nowrap px-3 py-2 text-right">Run Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                {month_totals.length ? (
                                    month_totals.map((row, index) => {
                                        const income = num(row.income);
                                        const expense = num(row.expense);
                                        const net = income - expense;
                                        const runRate = expense > 0 ? (income / expense) * 100 : 0;
                                        return (
                                            <tr key={`${row.label}-${index}`} className="border-t border-slate-100">
                                                <td className="whitespace-nowrap px-3 py-2 tabular-nums">{row.label}</td>
                                                <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums">{formatCurrency(income, currency)}</td>
                                                <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums">{formatCurrency(expense, currency)}</td>
                                                <td className={`whitespace-nowrap px-3 py-2 text-right tabular-nums font-semibold ${net >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>{formatCurrency(net, currency)}</td>
                                                <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums">{runRate > 0 ? percent(runRate) : 'N/A'}</td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="px-3 py-4 text-center text-slate-500">
                                            No monthly totals.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="card p-6">
                    <div className="section-label">Tax Deep Dive</div>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <MetricMini label="Taxable Base" value={formatCurrency(tax?.taxable_base, currency)} />
                        <MetricMini label="Tax Amount" value={formatCurrency(tax?.tax_amount, currency)} />
                        <MetricMini label="Gross Total" value={formatCurrency(tax?.tax_gross, currency)} />
                        <MetricMini label="Exclusive Tax" value={formatCurrency(tax?.tax_exclusive, currency)} />
                        <MetricMini label="Inclusive Tax" value={formatCurrency(tax?.tax_inclusive, currency)} />
                    </div>
                </div>
            </div>
        </>
    );
}

function MetricCard({ label, value, tone = 'text-slate-900' }) {
    return (
        <div className="card px-4 py-3">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{label}</div>
            <div className={`mt-2 text-2xl font-semibold ${tone}`}>{value}</div>
        </div>
    );
}

function MetricMini({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white/90 px-3 py-3">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{label}</div>
            <div className="mt-2 whitespace-nowrap text-lg font-semibold text-slate-900">{value}</div>
        </div>
    );
}

function InsightCard({ label, value, hint, tone = 'text-slate-900' }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{label}</div>
            <div className={`mt-2 text-2xl font-semibold ${tone}`}>{value}</div>
            <div className="mt-1 text-xs text-slate-500">{hint}</div>
        </div>
    );
}

function BreakdownCard({ title, rows, labelKey, valueKey, base, currency, emptyText }) {
    const items = Array.isArray(rows) ? rows.slice(0, 8) : [];

    return (
        <div className="card p-6">
            <div className="section-label">{title}</div>
            <div className="mt-4 space-y-3 text-sm text-slate-600">
                {items.length ? (
                    items.map((row, index) => {
                        const label = String(row?.[labelKey] ?? '--');
                        const value = num(row?.[valueKey]);
                        const width = base > 0 ? Math.min(100, (value / base) * 100) : 0;
                        return (
                            <div key={`${label}-${index}`} className="rounded-xl border border-slate-100 bg-white/80 p-3">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="truncate">{label}</div>
                                    <div className="whitespace-nowrap font-semibold text-slate-900">{formatCurrency(value, currency)}</div>
                                </div>
                                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div className="h-full rounded-full bg-teal-500" style={{ width: `${width}%` }} />
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <div className="text-sm text-slate-500">{emptyText}</div>
                )}
            </div>
        </div>
    );
}

