import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const asArray = (value) => (Array.isArray(value) ? value : []);
const rowId = () => `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

export default function Create({
    pageTitle = 'New Project',
    statuses = [],
    types = [],
    customers = [],
    employees = [],
    salesReps = [],
    currencyOptions = [],
    taskTypeOptions = {},
    priorityOptions = {},
    form = {},
    tasks = [],
    maintenances = [],
    overheads = [],
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';

    const [selectedEmployeeIds, setSelectedEmployeeIds] = React.useState(asArray(form.selected_employee_ids).map(Number));
    const [selectedSalesRepIds, setSelectedSalesRepIds] = React.useState(asArray(form.selected_sales_rep_ids).map(Number));

    const [taskRows, setTaskRows] = React.useState(() => {
        const seeded = asArray(tasks).map((task) => ({ id: rowId(), ...task, descriptions: asArray(task?.descriptions).length ? task.descriptions : [''] }));
        return seeded.length
            ? seeded
            : [{ id: rowId(), title: '', task_type: 'feature', priority: 'medium', descriptions: [''], start_date: '', due_date: '', assignee: '', customer_visible: false }];
    });

    const [maintenanceRows, setMaintenanceRows] = React.useState(() => {
        const seeded = asArray(maintenances).map((item) => ({ id: rowId(), ...item }));
        return seeded.length ? seeded : [{ id: rowId(), title: '', amount: '', billing_cycle: 'monthly', start_date: '', auto_invoice: true, sales_rep_visible: false }];
    });

    const [overheadRows, setOverheadRows] = React.useState(() => {
        const seeded = asArray(overheads).map((item) => ({ id: rowId(), ...item }));
        return seeded.length ? seeded : [{ id: rowId(), short_details: '', amount: '' }];
    });

    const toggleId = (setter, current, id) => setter(current.includes(id) ? current.filter((v) => v !== id) : [...current, id]);

    const addTask = () => {
        setTaskRows((current) => [
            ...current,
            { id: rowId(), title: '', task_type: 'feature', priority: 'medium', descriptions: [''], start_date: form.start_date || '', due_date: '', assignee: '', customer_visible: false },
        ]);
    };

    const removeTask = (id) => {
        setTaskRows((current) => (current.length > 1 ? current.filter((row) => row.id !== id) : current));
    };

    const addMaintenance = () => {
        setMaintenanceRows((current) => [...current, { id: rowId(), title: '', amount: '', billing_cycle: 'monthly', start_date: '', auto_invoice: true, sales_rep_visible: false }]);
    };

    const removeMaintenance = (id) => {
        setMaintenanceRows((current) => {
            const next = current.filter((row) => row.id !== id);
            return next.length ? next : [{ id: rowId(), title: '', amount: '', billing_cycle: 'monthly', start_date: '', auto_invoice: true, sales_rep_visible: false }];
        });
    };

    const addOverhead = () => {
        setOverheadRows((current) => [...current, { id: rowId(), short_details: '', amount: '' }]);
    };

    const removeOverhead = (id) => {
        setOverheadRows((current) => {
            const next = current.filter((row) => row.id !== id);
            return next.length ? next : [{ id: rowId(), short_details: '', amount: '' }];
        });
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Delivery</div>
                    <div className="text-2xl font-semibold text-slate-900">Create project</div>
                    <div className="text-sm text-slate-500">Link to orders/invoices, assign teams, and create initial tasks.</div>
                </div>
                <a href={routes.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
            </div>

            <div className="card p-6">
                <form method="POST" action={routes.store} data-native="true" className="mt-2 grid gap-4 rounded-2xl border border-slate-300 bg-white/80 p-5" encType="multipart/form-data">
                    <input type="hidden" name="_token" value={csrf} />

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="text-xs text-slate-500">Project name</label>
                            <input name="name" defaultValue={form.name || ''} required className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                            {errors.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Customer</label>
                            <select name="customer_id" defaultValue={form.customer_id || ''} required className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option value="">Select customer</option>
                                {customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.display_name}</option>)}
                            </select>
                            {errors.customer_id ? <div className="mt-1 text-xs text-rose-600">{errors.customer_id}</div> : null}
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="text-xs text-slate-500">Type</label>
                            <select name="type" defaultValue={form.type || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                {types.map((type) => <option key={type} value={type}>{type.charAt(0).toUpperCase() + type.slice(1)}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Status</label>
                            <select name="status" defaultValue={form.status || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                {statuses.map((status) => <option key={status} value={status}>{status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Sales representatives</label>
                            <div className="mt-2 space-y-2 rounded-2xl border border-slate-300 bg-white/80 p-3">
                                {salesReps.map((rep) => (
                                    <div key={rep.id} className="flex flex-wrap items-center justify-between gap-3">
                                        <label className="flex items-center gap-2 text-xs text-slate-600">
                                            <input type="checkbox" name="sales_rep_ids[]" value={rep.id} checked={selectedSalesRepIds.includes(Number(rep.id))} onChange={() => toggleId(setSelectedSalesRepIds, selectedSalesRepIds, Number(rep.id))} />
                                            <span>{rep.name} ({rep.email})</span>
                                        </label>
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs text-slate-500">Amount</span>
                                            <input type="number" min="0" step="0.01" name={`sales_rep_amounts[${rep.id}]`} defaultValue={rep.amount ?? 0} className="w-28 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs" />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div><label className="text-xs text-slate-500">Start date</label><input name="start_date" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" defaultValue={form.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                        <div><label className="text-xs text-slate-500">Expected end date</label><input name="expected_end_date" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" defaultValue={form.expected_end_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                        <div><label className="text-xs text-slate-500">Due date (internal)</label><input name="due_date" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" defaultValue={form.due_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="text-xs text-slate-500">Assign employees</label>
                            <div className="mt-2 space-y-2 rounded-2xl border border-slate-300 bg-white/80 p-3">
                                {employees.map((employee) => {
                                    const employeeId = Number(employee.id);
                                    const isSelected = selectedEmployeeIds.includes(employeeId);
                                    const isContract = employee.employment_type === 'contract';

                                    return (
                                        <div key={employee.id} className="flex flex-wrap items-center justify-between gap-3">
                                            <label className="flex items-center gap-2 text-xs text-slate-600">
                                                <input type="checkbox" name="employee_ids[]" value={employee.id} checked={isSelected} onChange={() => toggleId(setSelectedEmployeeIds, selectedEmployeeIds, employeeId)} />
                                                <span>{employee.name} {employee.designation ? <span className="text-slate-500">({employee.designation})</span> : null}</span>
                                            </label>
                                            {isContract ? (
                                                <div className={`flex items-center gap-2 ${isSelected ? '' : 'hidden'}`}>
                                                    <span className="text-xs text-slate-500">Amount</span>
                                                    <input type="number" min="0" step="0.01" name={`contract_employee_amounts[${employee.id}]`} defaultValue={employee.contract_amount ?? ''} disabled={!isSelected} className="w-28 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs" />
                                                </div>
                                            ) : null}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Notes</label>
                            <textarea name="notes" rows={2} defaultValue={form.notes || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div><label className="text-xs text-slate-500">Contract file</label><input type="file" name="contract_file" accept=".pdf,.doc,.docx,image/*" className="mt-1 w-full text-xs text-slate-600" /></div>
                        <div><label className="text-xs text-slate-500">Proposal file</label><input type="file" name="proposal_file" accept=".pdf,.doc,.docx,image/*" className="mt-1 w-full text-xs text-slate-600" /></div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-4">
                        <div><label className="text-xs text-slate-500">Total budget</label><input name="total_budget" type="number" step="0.01" defaultValue={form.total_budget || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                        <div><label className="text-xs text-slate-500">Initial payment</label><input name="initial_payment_amount" type="number" step="0.01" defaultValue={form.initial_payment_amount || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                        <div><label className="text-xs text-slate-500">Currency</label><select name="currency" defaultValue={form.currency || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>{currencyOptions.map((currency) => <option key={currency} value={currency}>{currency}</option>)}</select></div>
                        <div><label className="text-xs text-slate-500">Budget (legacy)</label><input name="budget_amount" type="number" step="0.01" defaultValue={form.budget_amount || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                    </div>
                    <div className="rounded-2xl border border-slate-300 bg-white/60 p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <div><div className="section-label">Add Maintenance Plan</div><div className="text-sm text-slate-600">Optional recurring billing tied to this project.</div></div>
                            <button type="button" onClick={addMaintenance} className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Add plan</button>
                        </div>
                        <div className="space-y-3">
                            {maintenanceRows.map((maintenance, index) => (
                                <div key={maintenance.id} className="grid gap-3 md:grid-cols-6 mb-2">
                                    <div className="md:col-span-2"><label className="text-xs text-slate-500">Title</label><input name={`maintenances[${index}][title]`} defaultValue={maintenance.title || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                                    <div><label className="text-xs text-slate-500">Amount</label><input name={`maintenances[${index}][amount]`} type="number" min="0.01" step="0.01" defaultValue={maintenance.amount || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                                    <div><label className="text-xs text-slate-500">Billing cycle</label><select name={`maintenances[${index}][billing_cycle]`} defaultValue={maintenance.billing_cycle || 'monthly'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div>
                                    <div><label className="text-xs text-slate-500">Start date</label><input name={`maintenances[${index}][start_date]`} type="text" placeholder="DD-MM-YYYY" inputMode="numeric" defaultValue={maintenance.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                                    <div className="flex items-end justify-between gap-2">
                                        <div className="space-y-2">
                                            <label className="flex items-center gap-2 text-xs text-slate-600"><input type="hidden" name={`maintenances[${index}][auto_invoice]`} value="0" /><input type="checkbox" name={`maintenances[${index}][auto_invoice]`} value="1" defaultChecked={maintenance.auto_invoice === undefined || Boolean(Number(maintenance.auto_invoice) || maintenance.auto_invoice)} /><span>Auto invoice</span></label>
                                            <label className="flex items-center gap-2 text-xs text-slate-600"><input type="hidden" name={`maintenances[${index}][sales_rep_visible]`} value="0" /><input type="checkbox" name={`maintenances[${index}][sales_rep_visible]`} value="1" defaultChecked={Boolean(Number(maintenance.sales_rep_visible) || maintenance.sales_rep_visible)} /><span>Sales rep visible</span></label>
                                        </div>
                                        <button type="button" onClick={() => removeMaintenance(maintenance.id)} className="rounded-full border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Remove</button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-300 bg-white/60 p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <div><div className="section-label">Overhead fees (optional)</div><div className="text-sm text-slate-600">Add per-project overhead line items.</div></div>
                            <button type="button" onClick={addOverhead} className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Add fee</button>
                        </div>
                        <div className="space-y-3">
                            {overheadRows.map((overhead, index) => (
                                <div key={overhead.id} className="grid gap-3 md:grid-cols-3 items-end">
                                    <div className="md:col-span-2"><label className="text-xs text-slate-500">Details</label><input name={`overheads[${index}][short_details]`} defaultValue={overhead.short_details || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                                    <div>
                                        <label className="text-xs text-slate-500">Amount</label>
                                        <div className="flex items-center gap-2">
                                            <input name={`overheads[${index}][amount]`} type="number" step="0.01" min="0" defaultValue={overhead.amount || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                            <button type="button" onClick={() => removeOverhead(overhead.id)} className="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="rounded-2xl border border-slate-300 bg-white/60 p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <div><div className="section-label">Initial tasks</div><div className="text-sm text-slate-600">Add at least one task with dates and assignee.</div></div>
                            <button type="button" onClick={addTask} className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Add task</button>
                        </div>
                        <div className="space-y-3">
                            {taskRows.map((task, index) => (
                                <div key={task.id} className="rounded-xl border border-slate-100 bg-white p-3">
                                    <div className="grid gap-3 md:grid-cols-5 mb-3">
                                        <div className="md:col-span-2"><label className="text-xs text-slate-500">Title</label><input name={`tasks[${index}][title]`} defaultValue={task.title || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                                        <div><label className="text-xs text-slate-500">Task type</label><select name={`tasks[${index}][task_type]`} defaultValue={task.task_type || 'feature'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>{Object.entries(taskTypeOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>
                                        <div><label className="text-xs text-slate-500">Priority</label><select name={`tasks[${index}][priority]`} defaultValue={task.priority || 'medium'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">{Object.entries(priorityOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>
                                        <div><label className="text-xs text-slate-500">Start date</label><input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name={`tasks[${index}][start_date]`} defaultValue={task.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                                        <div><label className="text-xs text-slate-500">Due date</label><input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name={`tasks[${index}][due_date]`} defaultValue={task.due_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                                        <div className="md:col-span-2">
                                            <label className="text-xs text-slate-500">Assign to</label>
                                            <select name={`tasks[${index}][assignee]`} defaultValue={task.assignee || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                                                <option value="">Select assignee</option>
                                                {employees.map((employee) => <option key={`employee-${employee.id}`} value={`employee:${employee.id}`}>Employee: {employee.name}</option>)}
                                                {salesReps.map((rep) => <option key={`sales-rep-${rep.id}`} value={`sales_rep:${rep.id}`}>Sales Rep: {rep.name}</option>)}
                                            </select>
                                        </div>
                                        <div className="flex items-end"><label className="mt-1 flex w-full items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600"><input type="hidden" name={`tasks[${index}][customer_visible]`} value="0" /><input type="checkbox" name={`tasks[${index}][customer_visible]`} value="1" defaultChecked={Boolean(task.customer_visible)} className="h-4 w-4 rounded border-slate-300 text-teal-600" /><span>Customer visible</span></label></div>
                                        <div><label className="text-xs text-slate-500">Attachment (required for Upload type)</label><input type="file" name={`tasks[${index}][attachment]`} accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" className="mt-1 w-full text-xs text-slate-600" /></div>
                                    </div>
                                    <div className="grid gap-3 md:grid-cols-4 mb-2">
                                        {(asArray(task.descriptions).length ? asArray(task.descriptions) : ['']).map((description, descriptionIndex) => (
                                            <div key={`${task.id}-desc-${descriptionIndex}`} className="md:col-span-4">
                                                <label className="text-xs text-slate-500">{descriptionIndex === 0 ? 'Description' : `Description ${descriptionIndex + 1}`}</label>
                                                <input type="text" name={`tasks[${index}][descriptions][]`} defaultValue={description || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                            </div>
                                        ))}
                                    </div>
                                    <div className="flex justify-end"><button type="button" onClick={() => removeTask(task.id)} className="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Remove task</button></div>
                                </div>
                            ))}
                        </div>
                        {errors.tasks ? <div className="mt-2 text-xs text-rose-600">{errors.tasks}</div> : null}
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <a href={routes.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                        <button type="submit" className="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save project</button>
                    </div>
                </form>
            </div>
        </>
    );
}
