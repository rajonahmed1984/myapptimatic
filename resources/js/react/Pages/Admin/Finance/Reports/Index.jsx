import React from 'react';
import { Head } from '@inertiajs/react';

const formatCurrency = (amount, currency) => {
    const numeric = Number(amount || 0);
    const symbol = currency?.symbol || '';
    const code = currency?.code || '';

    return `${symbol}${numeric.toFixed(2)}${code}`;
};

const includesValue = (list, value) => Array.isArray(list) && list.includes(value);

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

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">Finance reports</div>
                    <div className="mt-1 text-sm text-slate-500">Review income, expenses, payouts, and tax totals in one view.</div>
                </div>
                <a
                    href={routes?.tax_index}
                    data-native="true"
                    className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                >
                    Tax Settings
                </a>
            </div>

            <div className="card p-6">
                <div className="section-label">Filters</div>
                <form method="GET" action={routes?.index} className="mt-4 grid gap-3 text-sm md:grid-cols-6" data-native="true">
                    <div>
                        <label className="text-xs text-slate-500">Start date</label>
                        <input
                            type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                            name="start_date"
                            defaultValue={filters?.start_date || ''}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">End date</label>
                        <input
                            type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                            name="end_date"
                            defaultValue={filters?.end_date || ''}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Income basis</label>
                        <select
                            name="income_basis"
                            defaultValue={filters?.income_basis || 'received'}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        >
                            <option value="received">Received</option>
                            <option value="invoiced">Invoiced</option>
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Income category</label>
                        <select
                            name="income_category_id"
                            defaultValue={filters?.income_category_id || ''}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        >
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
                        <select
                            name="expense_category_id"
                            defaultValue={filters?.expense_category_id || ''}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        >
                            <option value="">All</option>
                            {expense_categories.map((category) => (
                                <option key={category.id} value={String(category.id)}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="md:col-span-6">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Income sources</div>
                        <div className="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="income_sources[]"
                                    value="manual"
                                    defaultChecked={includesValue(filters?.income_sources, 'manual')}
                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                />
                                Manual
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="income_sources[]"
                                    value="system"
                                    defaultChecked={includesValue(filters?.income_sources, 'system')}
                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                />
                                System
                            </label>
                        </div>
                    </div>

                    <div className="md:col-span-6">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Expense sources</div>
                        <div className="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="expense_sources[]"
                                    value="manual"
                                    defaultChecked={includesValue(filters?.expense_sources, 'manual')}
                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                />
                                Manual
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="expense_sources[]"
                                    value="salary"
                                    defaultChecked={includesValue(filters?.expense_sources, 'salary')}
                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                />
                                Salaries
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="expense_sources[]"
                                    value="contract_payout"
                                    defaultChecked={includesValue(filters?.expense_sources, 'contract_payout')}
                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                />
                                Contract Payouts
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="expense_sources[]"
                                    value="sales_payout"
                                    defaultChecked={includesValue(filters?.expense_sources, 'sales_payout')}
                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                />
                                Sales Rep Payouts
                            </label>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 md:col-span-6">
                        <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            Apply
                        </button>
                        <a href={routes?.index} data-native="true" className="text-xs font-semibold text-slate-500 hover:text-slate-700">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total income</div>
                    <div className="mt-2 text-2xl font-semibold text-emerald-600">{formatCurrency(summary?.total_income, currency)}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total expense</div>
                    <div className="mt-2 text-2xl font-semibold text-rose-600">{formatCurrency(summary?.total_expense, currency)}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Net profit</div>
                    <div className="mt-2 text-2xl font-semibold text-slate-900">{formatCurrency(summary?.net_profit, currency)}</div>
                </div>
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-3">
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Received income</div>
                    <div className="mt-2 text-2xl font-semibold text-slate-900">{formatCurrency(summary?.received_income, currency)}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Payout expense</div>
                    <div className="mt-2 text-2xl font-semibold text-amber-600">{formatCurrency(summary?.payout_expense, currency)}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Net cashflow</div>
                    <div className="mt-2 text-2xl font-semibold text-slate-900">{formatCurrency(summary?.net_cashflow, currency)}</div>
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Tax summary</div>
                <div className="mt-4 grid gap-4 md:grid-cols-5">
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Taxable base</div>
                        <div className="mt-2 text-lg font-semibold text-slate-900">{formatCurrency(tax?.taxable_base, currency)}</div>
                    </div>
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Tax amount</div>
                        <div className="mt-2 text-lg font-semibold text-slate-900">{formatCurrency(tax?.tax_amount, currency)}</div>
                    </div>
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Gross total</div>
                        <div className="mt-2 text-lg font-semibold text-slate-900">{formatCurrency(tax?.tax_gross, currency)}</div>
                    </div>
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Exclusive tax</div>
                        <div className="mt-2 text-lg font-semibold text-slate-900">{formatCurrency(tax?.tax_exclusive, currency)}</div>
                    </div>
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Inclusive tax</div>
                        <div className="mt-2 text-lg font-semibold text-slate-900">{formatCurrency(tax?.tax_inclusive, currency)}</div>
                    </div>
                </div>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div className="card p-6">
                    <div className="section-label">Income by category</div>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        {income_category_totals.length > 0 ? (
                            income_category_totals.map((row, index) => (
                                <div key={`${row.name}-${index}`} className="flex items-center justify-between">
                                    <div>{row.name}</div>
                                    <div className="font-semibold text-slate-900">{formatCurrency(row.total, currency)}</div>
                                </div>
                            ))
                        ) : (
                            <div className="text-sm text-slate-500">No income entries.</div>
                        )}
                    </div>
                </div>
                <div className="card p-6">
                    <div className="section-label">Expense by category</div>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        {expense_category_totals.length > 0 ? (
                            expense_category_totals.map((row, index) => (
                                <div key={`${row.name}-${index}`} className="flex items-center justify-between">
                                    <div>{row.name}</div>
                                    <div className="font-semibold text-slate-900">{formatCurrency(row.total, currency)}</div>
                                </div>
                            ))
                        ) : (
                            <div className="text-sm text-slate-500">No expense entries.</div>
                        )}
                    </div>
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Employee payout breakdown</div>
                <div className="mt-4 space-y-3 text-sm text-slate-600">
                    {employee_totals.length > 0 ? (
                        employee_totals.map((row, index) => (
                            <div key={`${row.label}-${index}`} className="flex items-center justify-between">
                                <div>{row.label}</div>
                                <div className="font-semibold text-slate-900">{formatCurrency(row.total, currency)}</div>
                            </div>
                        ))
                    ) : (
                        <div className="text-sm text-slate-500">No payout entries in this range.</div>
                    )}
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Income vs expense trend</div>
                <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
                    <table className="min-w-full text-left text-sm text-slate-700">
                        <thead>
                            <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="px-3 py-2">Period</th>
                                <th className="px-3 py-2">Income</th>
                                <th className="px-3 py-2">Expense</th>
                                <th className="px-3 py-2">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            {trendLabels.length > 0 ? (
                                trendLabels.map((label, index) => {
                                    const income = Number(trendIncome[index] || 0);
                                    const expense = Number(trendExpense[index] || 0);

                                    return (
                                        <tr key={`${label}-${index}`} className="border-t border-slate-100">
                                            <td className="px-3 py-2">{label}</td>
                                            <td className="px-3 py-2">{formatCurrency(income, currency)}</td>
                                            <td className="px-3 py-2">{formatCurrency(expense, currency)}</td>
                                            <td className="px-3 py-2">{formatCurrency(income - expense, currency)}</td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan={4} className="px-3 py-4 text-center text-slate-500">
                                        No trend data.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Monthly summary</div>
                <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
                    <table className="min-w-full text-left text-sm text-slate-700">
                        <thead>
                            <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="px-3 py-2">Month</th>
                                <th className="px-3 py-2">Income</th>
                                <th className="px-3 py-2">Expense</th>
                                <th className="px-3 py-2">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            {month_totals.length > 0 ? (
                                month_totals.map((row, index) => (
                                    <tr key={`${row.label}-${index}`} className="border-t border-slate-100">
                                        <td className="px-3 py-2">{row.label}</td>
                                        <td className="px-3 py-2">{formatCurrency(row.income, currency)}</td>
                                        <td className="px-3 py-2">{formatCurrency(row.expense, currency)}</td>
                                        <td className="px-3 py-2">{formatCurrency(Number(row.income || 0) - Number(row.expense || 0), currency)}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={4} className="px-3 py-4 text-center text-slate-500">
                                        No monthly totals.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
