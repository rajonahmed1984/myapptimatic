import React from 'react';
import { Head } from '@inertiajs/react';

export default function Create({
    pageTitle = 'Employee Payout',
    employees = [],
    selectedEmployee = null,
    earnings = [],
    summary = {},
    paymentMethods = [],
    routes = {},
}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const selectedTotal = earnings.reduce((sum, earning) => sum + Number(earning?.contract_employee_payable || 0), 0);
    const outstanding = Number(summary?.payable || 0);
    const cappedByOutstanding = selectedTotal > outstanding && outstanding > 0;

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR</div>
                    <h1 className="text-2xl font-semibold text-slate-900">Create payout</h1>
                    <div className="text-sm text-slate-500">Select an employee and payable projects to include.</div>
                </div>
                <a href={routes?.employeesIndex} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Back to employees</a>
            </div>

            <div className="card p-6 space-y-6">
                <form method="GET" action={routes?.create} data-native="true" className="grid gap-3 md:grid-cols-3">
                    <div>
                        <label className="text-xs text-slate-500">Employee</label>
                        <select name="employee_id" defaultValue={selectedEmployee || ''} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" onChange={(e) => e.currentTarget.form?.submit()}>
                            <option value="">Select employee</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>{employee.name}</option>
                            ))}
                        </select>
                    </div>
                </form>

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric title="Total Earned" value={Number(summary?.total || 0).toFixed(2)} />
                    <Metric title="Payable" value={Number(summary?.payable || 0).toFixed(2)} extraClass="text-amber-700" />
                    <Metric title="Paid" value={Number(summary?.paid || 0).toFixed(2)} extraClass="text-emerald-700" />
                </div>

                {cappedByOutstanding ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Selected project totals are higher than current outstanding payable. Payout will be capped to {outstanding.toFixed(2)}.
                    </div>
                ) : null}

                <form method="POST" action={routes?.store} data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={token} />
                    <input type="hidden" name="employee_id" value={selectedEmployee || ''} />

                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div className="flex items-center justify-between">
                            <div className="text-sm font-semibold text-slate-800">Payable earnings</div>
                            <div className="text-xs text-slate-500">Select at least one project.</div>
                        </div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-left">
                                <thead>
                                    <tr className="text-xs uppercase text-slate-500">
                                        <th className="px-2 py-2"><input type="checkbox" className="rounded border-slate-300" onChange={(e) => {
                                            document.querySelectorAll('.earning-checkbox').forEach((cb) => {
                                                cb.checked = e.currentTarget.checked;
                                            });
                                        }} /></th>
                                        <th className="px-2 py-2">Project</th>
                                        <th className="px-2 py-2">Status</th>
                                        <th className="px-2 py-2">Payable</th>
                                        <th className="px-2 py-2">Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {earnings.length === 0 ? (
                                        <tr><td colSpan={5} className="px-2 py-3 text-slate-500">No payable earnings found for the selected employee.</td></tr>
                                    ) : earnings.map((earning) => (
                                        <tr key={earning.id} className="border-t border-slate-200">
                                            <td className="px-2 py-2"><input type="checkbox" className="earning-checkbox rounded border-slate-300" name="project_ids[]" value={earning.id} defaultChecked /></td>
                                            <td className="px-2 py-2"><a className="text-teal-700 hover:text-teal-600" href={`/admin/projects/${earning.id}`} data-native="true">{earning.name}</a></td>
                                            <td className="px-2 py-2">{String(earning.contract_employee_payout_status || 'earned').charAt(0).toUpperCase() + String(earning.contract_employee_payout_status || 'earned').slice(1)}</td>
                                            <td className="px-2 py-2">{earning.currency || summary?.currency} {Number(earning.contract_employee_payable || 0).toFixed(2)}</td>
                                            <td className="px-2 py-2 text-xs text-slate-600">{earning.updated_at || '--'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="grid gap-3 md:grid-cols-3">
                        <div>
                            <label className="text-xs text-slate-500">Payout method</label>
                            <select name="payout_method" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">Not set</option>
                                {paymentMethods.map((method) => <option key={method.code} value={method.code}>{method.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Reference</label>
                            <input name="reference" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional reference" />
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Note</label>
                            <input name="note" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional note" />
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button type="submit" className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create payout</button>
                        <a href={routes?.employeesIndex} data-native="true" className="text-sm text-slate-600 hover:text-slate-800">Cancel</a>
                    </div>
                </form>
            </div>
        </>
    );
}

function Metric({ title, value, extraClass = '' }) {
    return (
        <div className="card p-4">
            <div className="text-xs uppercase tracking-[0.28em] text-slate-500">{title}</div>
            <div className={`mt-2 text-2xl font-semibold text-slate-900 ${extraClass}`}>{value}</div>
        </div>
    );
}
