import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';

export default function Show({
    pageTitle = 'Payroll',
    period = {},
    totals = [],
    items = [],
    pagination = {},
    paymentMethods = [],
    today = '',
    routes = {},
}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const [adjustItem, setAdjustItem] = useState(null);
    const [paymentItem, setPaymentItem] = useState(null);

    const periodStatusLabel = useMemo(() => {
        const status = period?.status || '';
        return status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
    }, [period?.status]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Payroll {period.period_key}</div>
                    <div className="text-sm text-slate-500">
                        {period.start_date} - {period.end_date} | {periodStatusLabel}
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <a href={routes?.export} data-native="true" className="text-sm text-slate-700 hover:underline">Export CSV</a>
                    {period?.status === 'draft' ? (
                        <>
                            <a href={routes?.edit} data-native="true" className="text-sm text-slate-700 hover:underline">Edit Period</a>
                            <form method="POST" action={routes?.destroy} data-native="true">
                                <input type="hidden" name="_token" value={token} />
                                <input type="hidden" name="_method" value="DELETE" />
                                <button type="submit" className="text-sm text-rose-700 hover:underline">Delete</button>
                            </form>
                        </>
                    ) : null}
                    {period?.status === 'draft' && period?.month_closed ? (
                        <form method="POST" action={routes?.finalize} data-native="true">
                            <input type="hidden" name="_token" value={token} />
                            <button className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Finalize</button>
                        </form>
                    ) : null}
                    {period?.status === 'draft' && !period?.month_closed ? <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Month not closed</span> : null}
                    <a href={routes?.index} data-native="true" className="text-sm text-slate-600 hover:text-slate-800">Back</a>
                </div>
            </div>

            {totals.length > 0 ? (
                <div className="mb-6 space-y-3">
                    {totals.map((total) => (
                        <div key={total.currency} className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                            <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Totals ({total.currency})</div>
                                <div><span className="text-slate-500">Base:</span> {total.base_total}</div>
                                <div><span className="text-slate-500">Gross:</span> {total.gross_total}</div>
                                <div><span className="text-slate-500">Net:</span> {total.net_total}</div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : null}

            <div className="card p-6">
                <div className="overflow-x-auto">
                    <table className="min-w-full whitespace-nowrap text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="py-2 px-3">Employee ID</th>
                                <th className="py-2 px-3">Employee</th>
                                <th className="py-2 px-3">Pay Type</th>
                                <th className="py-2 px-3">Currency</th>
                                <th className="py-2 px-3">Base</th>
                                <th className="py-2 px-3">Hours / Attendance</th>
                                <th className="py-2 px-3">Overtime</th>
                                <th className="py-2 px-3">Bonus</th>
                                <th className="py-2 px-3">Penalty</th>
                                <th className="py-2 px-3">Advance</th>
                                <th className="py-2 px-3">Est. Subtotal</th>
                                <th className="py-2 px-3">Gross</th>
                                <th className="py-2 px-3">Deduction</th>
                                <th className="py-2 px-3">Net</th>
                                <th className="py-2 px-3">Status</th>
                                <th className="py-2 px-3">Paid</th>
                                <th className="py-2 px-3">Paid At</th>
                                <th className="py-2 px-3">Payment methods</th>
                                <th className="py-2 px-3 text-right">Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr><td colSpan={19} className="py-3 px-3 text-center text-slate-500">No payroll items found.</td></tr>
                            ) : items.map((item) => (
                                <tr key={item.id} className="border-b border-slate-100">
                                    <td className="py-2 px-3">{item.employee_id}</td>
                                    <td className="py-2 px-3">{item.employee_name}</td>
                                    <td className="py-2 px-3">{item.pay_type}</td>
                                    <td className="py-2 px-3">{item.currency}</td>
                                    <td className="py-2 px-3">{item.base_pay}</td>
                                    <td className="py-2 px-3">{item.hours_display}</td>
                                    <td className="py-2 px-3">{item.can_adjust ? <button type="button" className="hover:text-teal-700" onClick={() => setAdjustItem(item)}>{item.overtime_hours}<div className="text-[11px] text-slate-500">@ {item.overtime_rate}</div></button> : <>{item.overtime_hours}<div className="text-[11px] text-slate-500">@ {item.overtime_rate}</div></>}</td>
                                    <td className="py-2 px-3">{item.can_adjust ? <button type="button" className="hover:text-teal-700" onClick={() => setAdjustItem(item)}>{item.bonus}</button> : item.bonus}</td>
                                    <td className="py-2 px-3">{item.can_adjust ? <button type="button" className="hover:text-teal-700" onClick={() => setAdjustItem(item)}>{item.penalty}</button> : item.penalty}</td>
                                    <td className="py-2 px-3">{item.advance}</td>
                                    <td className="py-2 px-3">{item.est_subtotal} {item.currency}</td>
                                    <td className="py-2 px-3">{item.computed_gross}</td>
                                    <td className="py-2 px-3">
                                        {item.can_adjust ? <button type="button" className="hover:text-teal-700" onClick={() => setAdjustItem(item)}>{item.deduction}</button> : item.deduction}
                                        {item.deduction_reference ? <div className="text-[11px] text-slate-500">{item.deduction_reference}</div> : null}
                                    </td>
                                    <td className="py-2 px-3">{item.computed_net}</td>
                                    <td className="py-2 px-3">
                                        <span className={`rounded-full px-2 py-1 text-xs font-semibold ${item.display_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : item.display_status === 'partial' ? 'bg-orange-100 text-orange-700' : item.display_status === 'approved' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'}`}>
                                            {item.display_status.charAt(0).toUpperCase() + item.display_status.slice(1)}
                                        </span>
                                    </td>
                                    <td className="py-2 px-3">{item.paid_amount}</td>
                                    <td className="py-2 px-3">{item.paid_at}</td>
                                    <td className="py-2 px-3">{item.payment_reference}</td>
                                    <td className="py-2 px-3 text-right">
                                        {item.can_pay ? <button type="button" className="rounded-full border border-emerald-300 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" onClick={() => setPaymentItem(item)}>Payment</button> : <span className="text-xs text-slate-400">--</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {pagination?.has_pages ? (
                    <div className="mt-4 flex items-center justify-between gap-2 text-sm">
                        <a href={pagination?.previous_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.previous_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Previous</a>
                        <a href={pagination?.next_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.next_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Next</a>
                    </div>
                ) : null}
            </div>

            {adjustItem ? (
                <div className="fixed inset-0 z-50">
                    <div className="absolute inset-0 bg-slate-900/50" onClick={() => setAdjustItem(null)} />
                    <div className="relative mx-auto mt-16 w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <div className="section-label">Payroll Adjustments</div>
                                <div className="text-lg font-semibold text-slate-900">{adjustItem.employee_name}</div>
                                <div className="text-sm text-slate-500">Overtime, bonus, and penalty update</div>
                            </div>
                            <button type="button" className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900" onClick={() => setAdjustItem(null)}>Close</button>
                        </div>

                        <form method="POST" action={adjustItem.routes.adjust} data-native="true" className="mt-5 grid gap-4">
                            <input type="hidden" name="_token" value={token} />
                            <div className="grid gap-3 md:grid-cols-2">
                                <Field label="Overtime Hours" name="overtime_hours" defaultValue={adjustItem.overtime_hours} />
                                <Field label="Overtime Rate" name="overtime_rate" defaultValue={adjustItem.overtime_rate} />
                                <Field label="Bonus" name="bonuses" defaultValue={adjustItem.bonus} />
                                <Field label="Penalty" name="penalties" defaultValue={adjustItem.penalty} />
                                <Field label="Deduction" name="deductions" defaultValue={adjustItem.deduction} />
                                <div>
                                    <label htmlFor="adjustDeductionReference" className="text-xs uppercase tracking-[0.2em] text-slate-500">Deduction Reference</label>
                                    <input id="adjustDeductionReference" name="deduction_reference" type="text" maxLength={120} defaultValue={adjustItem.deduction_reference || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                </div>
                                <div className="md:col-span-2">
                                    <label htmlFor="adjustDeductionNote" className="text-xs uppercase tracking-[0.2em] text-slate-500">Deduction Reason</label>
                                    <textarea id="adjustDeductionNote" name="deduction_note" rows={2} maxLength={500} defaultValue={adjustItem.deduction_note || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                </div>
                            </div>
                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button type="button" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" onClick={() => setAdjustItem(null)}>Cancel</button>
                                <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save Adjustments</button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}

            {paymentItem ? (
                <div className="fixed inset-0 z-50">
                    <div className="absolute inset-0 bg-slate-900/50" onClick={() => setPaymentItem(null)} />
                    <div className="relative mx-auto mt-16 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <div className="section-label">Payroll Payment</div>
                                <div className="text-lg font-semibold text-slate-900">{paymentItem.employee_name}</div>
                                <div className="text-sm text-slate-500">Net: {paymentItem.payment_data.net}</div>
                            </div>
                            <button type="button" className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900" onClick={() => setPaymentItem(null)}>Close</button>
                        </div>

                        <form method="POST" action={paymentItem.routes.pay} data-native="true" encType="multipart/form-data" className="mt-5 grid gap-4">
                            <input type="hidden" name="_token" value={token} />
                            <Field label="Amount" name="amount" defaultValue={paymentItem.payment_data.remaining_amount} min="0.01" step="0.01" />
                            <div>
                                <label htmlFor="paymentMethod" className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Method</label>
                                <select id="paymentMethod" name="payment_method" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                                    <option value="">Select</option>
                                    {paymentMethods.map((method) => (
                                        <option key={method.code} value={method.code}>{method.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label htmlFor="paymentReference" className="text-xs uppercase tracking-[0.2em] text-slate-500">Reference</label>
                                <input id="paymentReference" name="payment_reference" type="text" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label htmlFor="paymentProof" className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Proof</label>
                                <input id="paymentProof" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label htmlFor="paidAt" className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Date</label>
                                <input id="paidAt" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="paid_at" defaultValue={today} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required />
                            </div>
                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button type="button" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" onClick={() => setPaymentItem(null)}>Cancel</button>
                                <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Confirm Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </>
    );
}

function Field({ label, name, defaultValue, min = '0', step = '0.01' }) {
    return (
        <div>
            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">{label}</label>
            <input name={name} type="number" step={step} min={min} defaultValue={defaultValue} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
        </div>
    );
}
