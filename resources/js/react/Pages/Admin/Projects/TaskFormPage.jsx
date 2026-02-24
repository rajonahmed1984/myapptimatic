import React from 'react';
import { Head, usePage } from '@inertiajs/react';

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
                                        type="date"
                                        name="start_date"
                                        defaultValue={form.start_date || ''}
                                        required
                                        className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Due date</label>
                                    <input
                                        type="date"
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
                                <select
                                    name="status"
                                    defaultValue={form.status || task?.status || 'pending'}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    required
                                >
                                    {statusOptions.map((status) => (
                                        <option key={status} value={status}>
                                            {status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : null}

                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Task type</label>
                            <select
                                name="task_type"
                                defaultValue={form.task_type || task?.task_type || ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                required
                            >
                                {Object.entries(taskTypeOptions).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Priority</label>
                            <select
                                name="priority"
                                defaultValue={form.priority || task?.priority || 'medium'}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                {Object.entries(priorityOptions).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
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
                            <select
                                name="assignees[]"
                                multiple
                                size={6}
                                value={selectedAssignees}
                                onChange={(event) => setSelectedAssignees(Array.from(event.target.selectedOptions).map((option) => option.value))}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                {employees.map((employee) => (
                                    <option key={`employee:${employee.id}`} value={`employee:${employee.id}`}>
                                        Employee: {employee.name}
                                    </option>
                                ))}
                                {salesReps.map((rep) => (
                                    <option key={`sales_rep:${rep.id}`} value={`sales_rep:${rep.id}`}>
                                        Sales rep: {rep.name}
                                    </option>
                                ))}
                            </select>
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
                                rows={3}
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
                                        rows={2}
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
