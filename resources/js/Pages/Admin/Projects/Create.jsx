import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const asArray = (value) => (Array.isArray(value) ? value : []);
const rowId = () => `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
const formatCustomerLabel = (customer) => `#${customer.id} - ${customer.display_name}`;

function StepSection({ step, title, description, children, optional = false }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 md:p-5">
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                        Step {step}
                    </div>
                    <div className="mt-1 text-base font-semibold text-slate-900">{title}</div>
                    {description ? <div className="mt-1 text-xs text-slate-600">{description}</div> : null}
                </div>
                {optional ? (
                    <span className="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Optional
                    </span>
                ) : null}
            </div>
            {children}
        </section>
    );
}

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
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const customerFieldRef = React.useRef(null);

    const [selectedEmployeeIds, setSelectedEmployeeIds] = React.useState(asArray(form.selected_employee_ids).map(Number));
    const [selectedSalesRepIds, setSelectedSalesRepIds] = React.useState(asArray(form.selected_sales_rep_ids).map(Number));
    const [selectedCustomerId, setSelectedCustomerId] = React.useState(String(form.customer_id || ''));
    const [customerSearch, setCustomerSearch] = React.useState(() => {
        const currentCustomer = customers.find((customer) => String(customer.id) === String(form.customer_id || ''));

        return currentCustomer ? formatCustomerLabel(currentCustomer) : '';
    });
    const [isCustomerMenuOpen, setIsCustomerMenuOpen] = React.useState(false);

    const [taskRows, setTaskRows] = React.useState(() => {
        const seeded = asArray(tasks).map((task) => ({ id: rowId(), ...task, descriptions: asArray(task?.descriptions).length ? task.descriptions : [''] }));
        return seeded.length
            ? seeded
            : [{ id: rowId(), title: '', task_type: 'feature', priority: 'medium', descriptions: [''], start_date: '', due_date: '', assignee: '', customer_visible: false }];
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

    const filteredCustomers = React.useMemo(() => {
        const keyword = customerSearch.trim().toLowerCase();

        if (!keyword) {
            return customers.slice(0, 20);
        }

        return customers
            .filter((customer) => {
                const haystack = `${customer.id} ${customer.display_name}`.toLowerCase();

                return haystack.includes(keyword);
            })
            .slice(0, 20);
    }, [customerSearch, customers]);

    React.useEffect(() => {
        const handleOutsideClick = (event) => {
            if (!customerFieldRef.current?.contains(event.target)) {
                setIsCustomerMenuOpen(false);
            }
        };

        document.addEventListener('mousedown', handleOutsideClick);

        return () => {
            document.removeEventListener('mousedown', handleOutsideClick);
        };
    }, []);

    const handleCustomerSelect = (customer) => {
        setSelectedCustomerId(String(customer.id));
        setCustomerSearch(formatCustomerLabel(customer));
        setIsCustomerMenuOpen(false);
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
                <form method="POST" action={routes.store} data-native="true" className="mt-2 space-y-5 rounded-2xl border border-slate-200 bg-white/90 p-5" encType="multipart/form-data">
                    <input type="hidden" name="_token" value={csrf} />

                    <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div className="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.15em] text-slate-500">
                            <span className="rounded-full bg-white px-2.5 py-1">1 Basic Info</span>
                            <span className="rounded-full bg-white px-2.5 py-1">2 Team</span>
                            <span className="rounded-full bg-white px-2.5 py-1">3 Timeline & Files</span>
                            <span className="rounded-full bg-white px-2.5 py-1">4 Budget</span>
                            <span className="rounded-full bg-white px-2.5 py-1">5 Initial Tasks</span>
                        </div>
                    </div>

                    <StepSection
                        step="1"
                        title="Project Basics"
                        description="Start with core project identity and customer mapping."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Project name</label>
                                <input name="name" defaultValue={form.name || ''} required className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                {errors.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Customer</label>
                                <div ref={customerFieldRef} className="relative mt-1">
                                    <input
                                        type="hidden"
                                        name="customer_id"
                                        value={selectedCustomerId}
                                    />
                                    <input
                                        type="text"
                                        value={customerSearch}
                                        onChange={(event) => {
                                            setCustomerSearch(event.target.value);
                                            setSelectedCustomerId('');
                                            setIsCustomerMenuOpen(true);
                                        }}
                                        onFocus={() => setIsCustomerMenuOpen(true)}
                                        placeholder="Search customer by name or ID"
                                        required
                                        autoComplete="off"
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                    {isCustomerMenuOpen ? (
                                        <div className="absolute z-20 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                                            {filteredCustomers.length > 0 ? filteredCustomers.map((customer) => {
                                                const isActive = String(customer.id) === selectedCustomerId;

                                                return (
                                                    <button
                                                        key={customer.id}
                                                        type="button"
                                                        onClick={() => handleCustomerSelect(customer)}
                                                        className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-left transition ${isActive ? 'bg-teal-50 text-teal-700' : 'hover:bg-slate-50'}`}
                                                    >
                                                        <span className="truncate text-sm text-slate-800">{customer.display_name}</span>
                                                        <span className="ml-3 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-500">ID #{customer.id}</span>
                                                    </button>
                                                );
                                            }) : (
                                                <div className="px-3 py-2 text-sm text-slate-500">No customer found.</div>
                                            )}
                                        </div>
                                    ) : null}
                                </div>
                                {errors.customer_id ? <div className="mt-1 text-xs text-rose-600">{errors.customer_id}</div> : null}
                            </div>
                        </div>
                        <div className="mt-4 grid gap-4 md:grid-cols-3">
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
                                <label className="text-xs text-slate-500">Notes</label>
                                <textarea name="notes" rows={1} defaultValue={form.notes || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                            </div>
                        </div>
                    </StepSection>

                    <StepSection
                        step="2"
                        title="Assign Team"
                        description="Select delivery members and sales reps with optional amount mapping."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Assign employees</label>
                                <div className="mt-2 space-y-2 rounded-2xl border border-slate-300 bg-white/90 p-3">
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
                                <label className="text-xs text-slate-500">Sales representatives</label>
                                <div className="mt-2 space-y-2 rounded-2xl border border-slate-300 bg-white/90 p-3">
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
                    </StepSection>

                    <StepSection
                        step="3"
                        title="Timeline And Documents"
                        description="Set delivery dates and attach project source files."
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            <div><label className="text-xs text-slate-500">Start date</label><input name="start_date" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" defaultValue={form.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                            <div><label className="text-xs text-slate-500">Expected end date</label><input name="expected_end_date" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" defaultValue={form.expected_end_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                            <div><label className="text-xs text-slate-500">Due date (internal)</label><input name="due_date" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" defaultValue={form.due_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                        </div>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <div><label className="text-xs text-slate-500">Contract file</label><input type="file" name="contract_file" accept=".pdf,.doc,.docx,image/*" className="mt-1 w-full text-xs text-slate-600" /></div>
                            <div><label className="text-xs text-slate-500">Proposal file</label><input type="file" name="proposal_file" accept=".pdf,.doc,.docx,image/*" className="mt-1 w-full text-xs text-slate-600" /></div>
                        </div>
                    </StepSection>

                    <StepSection
                        step="4"
                        title="Budget And Billing"
                        description="Define project budget, initial payment, and currency."
                    >
                        <div className="grid gap-4 md:grid-cols-4">
                            <div><label className="text-xs text-slate-500">Total budget</label><input name="total_budget" type="number" step="0.01" defaultValue={form.total_budget || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                            <div><label className="text-xs text-slate-500">Initial payment</label><input name="initial_payment_amount" type="number" step="0.01" defaultValue={form.initial_payment_amount || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required /></div>
                            <div><label className="text-xs text-slate-500">Currency</label><select name="currency" defaultValue={form.currency || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>{currencyOptions.map((currency) => <option key={currency} value={currency}>{currency}</option>)}</select></div>
                            <div><label className="text-xs text-slate-500">Budget (legacy)</label><input name="budget_amount" type="number" step="0.01" defaultValue={form.budget_amount || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" /></div>
                        </div>
                    </StepSection>

                    <StepSection
                        step="5"
                        title="Initial Tasks"
                        description="Add at least one task with assignee and dates."
                    >
                        <div className="mb-3 flex items-center justify-between">
                            <div className="section-label">Initial Tasks</div>
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
                    </StepSection>

                    <div className="flex justify-end gap-3 pt-2">
                        <a href={routes.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                        <button type="submit" className="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save project</button>
                    </div>
                </form>
            </div>
        </>
    );
}
