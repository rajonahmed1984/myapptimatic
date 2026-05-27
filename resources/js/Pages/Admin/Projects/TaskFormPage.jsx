import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

export default function TaskFormPage({
    pageTitle = 'Task Form',
    isEdit = false,
    task = null,
    taskTypeOptions = {},
    priorityOptions = {},
    statusOptions = [],
    employees = [],
    salesReps = [],
    form = {},
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const [selectedAssignees, setSelectedAssignees] = React.useState(Array.isArray(form.assignees) ? form.assignees : []);
    const taskTypeSelectOptions = Object.entries(taskTypeOptions).map(([value, label]) => ({ value, label }));
    const prioritySelectOptions = Object.entries(priorityOptions).map(([value, label]) => ({ value, label }));
    const statusSelectOptions = statusOptions.map((status) => ({
        value: String(status),
        label: status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
    }));
    const assigneeSelectOptions = [
        ...employees.map((employee) => ({ value: `employee:${employee.id}`, label: `Employee: ${employee.name}` })),
        ...salesReps.map((rep) => ({ value: `sales_rep:${rep.id}`, label: `Sales rep: ${rep.name}` })),
    ];

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Project Tasks</div>
                    <h1 className="text-2xl font-semibold text-slate-900">{isEdit ? 'Edit task' : 'Add task'}</h1>
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
                {Object.keys(errors).length > 0 ? (
                    <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">
                        <ul className="space-y-1">
                            {Object.values(errors).map((error, index) => (
                                <li key={index}>{String(error)}</li>
                            ))}
                        </ul>
                    </div>
                ) : null}

                <form method="POST" action={routes?.submit} encType="multipart/form-data" className="space-y-4" data-native="true">
                    <input type="hidden" name="_token" value={csrf} />
                    {isEdit ? <input type="hidden" name="_method" value="PATCH" /> : null}
                    <input type="hidden" name="task_status_filter" value={form.task_status_filter || ''} />
                    <input type="hidden" name="return_to" value={form.return_to || routes?.index || ''} />

                    <div className="grid gap-4 md:grid-cols-2">
                        {!isEdit ? (
                            <>
                                <div className="md:col-span-2">
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Title</label>
                                    <input
                                        name="title"
                                        defaultValue={form.title || ''}
                                        required
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Start date</label>
                                    <input
                                        type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                        name="start_date"
                                        defaultValue={form.start_date || ''}
                                        required
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Due date</label>
                                    <input
                                        type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                        name="due_date"
                                        defaultValue={form.due_date || ''}
                                        required
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                </div>
                            </>
                        ) : null}

                        {isEdit ? (
                            <div>
                                <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Status</label>
                                <SearchableSelect
                                    name="status"
                                    defaultValue={String(form.status || task?.status || 'pending')}
                                    options={statusSelectOptions}
                                    className="mt-1"
                                    placeholder="Select status"
                                    required
                                />
                            </div>
                        ) : null}

                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Task type</label>
                            <SearchableSelect
                                name="task_type"
                                defaultValue={String(form.task_type || task?.task_type || '')}
                                options={taskTypeSelectOptions}
                                className="mt-1"
                                placeholder="Select task type"
                                required
                            />
                        </div>

                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Priority</label>
                            <SearchableSelect
                                name="priority"
                                defaultValue={String(form.priority || task?.priority || 'medium')}
                                options={prioritySelectOptions}
                                className="mt-1"
                                placeholder="Select priority"
                            />
                        </div>

                        {isEdit ? (
                            <>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Progress (%)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        name="progress"
                                        defaultValue={form.progress ?? task?.progress ?? 0}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Estimate (minutes)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        name="time_estimate_minutes"
                                        defaultValue={form.time_estimate_minutes ?? task?.time_estimate_minutes ?? ''}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                </div>
                            </>
                        ) : null}

                        <div className="md:col-span-2">
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Assignees</label>
                            <SearchableSelect
                                name="assignees[]"
                                value={selectedAssignees}
                                onChange={(nextValues) => setSelectedAssignees(Array.isArray(nextValues) ? nextValues : [])}
                                options={assigneeSelectOptions}
                                className="mt-1"
                                placeholder="Select assignees"
                                searchPlaceholder="Search assignees..."
                                closeOnSelect={false}
                                multiple
                            />
                            {!isEdit ? <p className="mt-1 text-xs text-slate-500">Select at least one assignee.</p> : null}
                        </div>

                        {!isEdit ? (
                            <div className="md:col-span-2">
                                <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Attachment (required for Upload type)</label>
                                <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" className="mt-1 w-full text-xs text-slate-600" />
                            </div>
                        ) : null}

                        <div className="md:col-span-2">
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Description</label>
                            <textarea
                                name="description"
                                rows={1}
                                defaultValue={form.description ?? task?.description ?? ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                        </div>

                        {isEdit ? (
                            <>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Tags</label>
                                    <input
                                        name="tags"
                                        defaultValue={form.tags ?? task?.tags ?? ''}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Relationships (IDs)</label>
                                    <input
                                        name="relationship_ids"
                                        defaultValue={form.relationship_ids ?? task?.relationship_ids ?? ''}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="md:col-span-2">
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Notes</label>
                                    <textarea
                                        name="notes"
                                        rows={1}
                                        defaultValue={form.notes ?? task?.notes ?? ''}
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                </div>
                            </>
                        ) : null}
                    </div>

                    <div className="flex items-center justify-between border-t border-slate-200 pt-4">
                        <label className="inline-flex items-center gap-2 text-xs text-slate-600">
                            <input type="hidden" name="customer_visible" value="0" />
                            <input
                                type="checkbox"
                                name="customer_visible"
                                value="1"
                                defaultChecked={Boolean(form.customer_visible)}
                                className="rounded border-slate-300 text-teal-600"
                            />
                            <span>Customer visible</span>
                        </label>

                        <div className="flex items-center gap-2">
                            <a
                                href={form.return_to || routes?.index}
                                data-native="true"
                                className="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400"
                            >
                                Cancel
                            </a>
                            <button type="submit" className="rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                {isEdit ? 'Update task' : 'Add task'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}
