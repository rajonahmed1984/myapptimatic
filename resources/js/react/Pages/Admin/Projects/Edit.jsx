import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Edit({
    pageTitle = 'Edit Project',
    project = {},
    statuses = [],
    types = [],
    customers = [],
    employees = [],
    salesReps = [],
    currencyOptions = [],
    form = {},
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const [selectedEmployeeIds, setSelectedEmployeeIds] = React.useState(
        Array.isArray(form.selected_employee_ids) ? form.selected_employee_ids.map((id) => Number(id)) : [],
    );
    const [selectedSalesRepIds, setSelectedSalesRepIds] = React.useState(
        Array.isArray(form.selected_sales_rep_ids) ? form.selected_sales_rep_ids.map((id) => Number(id)) : [],
    );

    const toggleSelected = (current, id) => (current.includes(id) ? current.filter((value) => value !== id) : [...current, id]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Delivery</div>
                    <div className="text-2xl font-semibold text-slate-900">Edit project</div>
                    <div className="text-sm text-slate-500">Update project details, links, and budget fields.</div>
                </div>
                <a
                    href={routes?.index}
                    data-native="true"
                    className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                >
                    Back
                </a>
            </div>

            <div className="card p-6">
                <form method="POST" action={routes?.update} encType="multipart/form-data" className="mt-2 space-y-6 rounded-2xl border border-slate-200 bg-white/80 p-5" data-native="true">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value="PUT" />

                    <fieldset className="space-y-4">
                        <legend className="text-xs uppercase tracking-[0.2em] text-slate-400">Project Info</legend>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Project name</label>
                                <input
                                    name="name"
                                    defaultValue={form.name || ''}
                                    required
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                />
                                {errors.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Customer</label>
                                <select
                                    name="customer_id"
                                    defaultValue={form.customer_id || ''}
                                    required
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="">Select customer</option>
                                    {customers.map((customer) => (
                                        <option key={customer.id} value={customer.id}>
                                            {customer.display_name}
                                        </option>
                                    ))}
                                </select>
                                {errors.customer_id ? <div className="mt-1 text-xs text-rose-600">{errors.customer_id}</div> : null}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-xs text-slate-500">Type</label>
                                <select
                                    name="type"
                                    defaultValue={form.type || ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                >
                                    {types.map((type) => (
                                        <option key={type} value={type}>
                                            {type.charAt(0).toUpperCase() + type.slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Status</label>
                                <select
                                    name="status"
                                    defaultValue={form.status || ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                >
                                    {statuses.map((status) => (
                                        <option key={status} value={status}>
                                            {status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Start date</label>
                                <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="start_date" defaultValue={form.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Expected end date</label>
                                <input
                                    type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                    name="expected_end_date"
                                    defaultValue={form.expected_end_date || ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Due date (internal)</label>
                                <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="due_date" defaultValue={form.due_date || ''} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Notes</label>
                            <textarea name="notes" rows={2} defaultValue={form.notes || ''} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Contract file</label>
                                <input type="file" name="contract_file" accept=".pdf,.doc,.docx,image/*" className="mt-1 w-full text-xs text-slate-600" />
                                {routes.download_contract ? (
                                    <a href={routes.download_contract} data-native="true" className="mt-1 block text-xs font-semibold text-teal-700 hover:text-teal-600">
                                        {project.contract_original_name || 'Download contract'}
                                    </a>
                                ) : null}
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Proposal file</label>
                                <input type="file" name="proposal_file" accept=".pdf,.doc,.docx,image/*" className="mt-1 w-full text-xs text-slate-600" />
                                {routes.download_proposal ? (
                                    <a href={routes.download_proposal} data-native="true" className="mt-1 block text-xs font-semibold text-teal-700 hover:text-teal-600">
                                        {project.proposal_original_name || 'Download proposal'}
                                    </a>
                                ) : null}
                            </div>
                        </div>
                    </fieldset>

                    <fieldset className="space-y-4">
                        <legend className="text-xs uppercase tracking-[0.2em] text-slate-400">People</legend>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Sales representatives</label>
                                <div className="mt-2 space-y-2 rounded-2xl border border-slate-200 bg-white/80 p-3">
                                    {salesReps.map((rep) => (
                                        <div key={rep.id} className="flex flex-wrap items-center justify-between gap-3">
                                            <label className="flex items-center gap-2 text-xs text-slate-600">
                                                <input
                                                    type="checkbox"
                                                    name="sales_rep_ids[]"
                                                    value={rep.id}
                                                    checked={selectedSalesRepIds.includes(Number(rep.id))}
                                                    onChange={() => setSelectedSalesRepIds((current) => toggleSelected(current, Number(rep.id)))}
                                                />
                                                <span>
                                                    {rep.name} ({rep.email})
                                                </span>
                                            </label>
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs text-slate-500">Amount</span>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    name={`sales_rep_amounts[${rep.id}]`}
                                                    defaultValue={rep.amount ?? 0}
                                                    className="w-28 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs"
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <p className="mt-1 text-xs text-slate-500">Amounts apply only to selected sales reps.</p>
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Assign employees</label>
                                <div className="mt-2 space-y-2 rounded-2xl border border-slate-200 bg-white/80 p-3">
                                    {employees.map((employee) => {
                                        const employeeId = Number(employee.id);
                                        const isContract = employee.employment_type === 'contract';
                                        const isSelected = selectedEmployeeIds.includes(employeeId);

                                        return (
                                            <div key={employee.id} className="flex flex-wrap items-center justify-between gap-3">
                                                <label className="flex items-center gap-2 text-xs text-slate-600">
                                                    <input
                                                        type="checkbox"
                                                        name="employee_ids[]"
                                                        value={employee.id}
                                                        checked={isSelected}
                                                        onChange={() => setSelectedEmployeeIds((current) => toggleSelected(current, employeeId))}
                                                        data-employment-type={employee.employment_type}
                                                    />
                                                    <span>
                                                        {employee.name}{' '}
                                                        {employee.designation ? <span className="text-slate-500">({employee.designation})</span> : null}
                                                    </span>
                                                </label>
                                                {isContract ? (
                                                    <div className={`flex items-center gap-2 ${isSelected ? '' : 'hidden'}`}>
                                                        <span className="text-xs text-slate-500">Amount</span>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            name={`contract_employee_amounts[${employee.id}]`}
                                                            defaultValue={employee.contract_amount ?? ''}
                                                            disabled={!isSelected}
                                                            className="w-28 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs"
                                                        />
                                                    </div>
                                                ) : null}
                                            </div>
                                        );
                                    })}
                                </div>
                                <p className="mt-1 text-xs text-slate-500">Select employees assigned to this project.</p>
                                <p className="mt-1 text-xs text-slate-500">Contract employee amounts apply only to selected contract employees.</p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset className="space-y-4">
                        <legend className="text-xs uppercase tracking-[0.2em] text-slate-400">Budget & Currency</legend>

                        <div className="grid gap-4 md:grid-cols-4">
                            <div>
                                <label className="text-xs text-slate-500">Total budget</label>
                                <input
                                    name="total_budget"
                                    type="number"
                                    step="0.01"
                                    defaultValue={form.total_budget ?? ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                    required
                                />
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Initial payment</label>
                                <input
                                    name="initial_payment_amount"
                                    type="number"
                                    step="0.01"
                                    defaultValue={form.initial_payment_amount ?? ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                    required
                                />
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Currency</label>
                                <select
                                    name="currency"
                                    defaultValue={form.currency || ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                    required
                                >
                                    {currencyOptions.map((currency) => (
                                        <option key={currency} value={currency}>
                                            {currency}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Budget (legacy)</label>
                                <input
                                    name="budget_amount"
                                    type="number"
                                    step="0.01"
                                    defaultValue={form.budget_amount ?? ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Software overhead fee</label>
                                <input
                                    name="software_overhead"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    defaultValue={form.software_overhead ?? ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Website overhead fee</label>
                                <input
                                    name="website_overhead"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    defaultValue={form.website_overhead ?? ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                />
                            </div>
                        </div>
                    </fieldset>

                    <div className="flex justify-end gap-3 pt-2">
                        <a
                            href={routes?.show}
                            data-native="true"
                            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                        >
                            Cancel
                        </a>
                        <button type="submit" className="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Update project
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
