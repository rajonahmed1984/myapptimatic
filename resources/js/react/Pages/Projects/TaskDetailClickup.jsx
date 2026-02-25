import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

function HiddenMethod({ method = 'PATCH' }) {
    return <input type="hidden" name="_method" value={method} />;
}

function employeeIdsFromAssignees(rows) {
    return (Array.isArray(rows) ? rows : [])
        .filter((row) => row?.type === 'employee' && row?.id)
        .map((row) => String(row.id));
}

function normalizeActivity(item) {
    if (!item || typeof item !== 'object') {
        return null;
    }

    if (item.activity && typeof item.activity === 'object') {
        return item.activity;
    }

    return item;
}

export default function TaskDetailClickup({
    pageTitle = 'Task Details',
    routePrefix = 'admin',
    project = {},
    task = {},
    assignees = [],
    employees = [],
    subtasks = [],
    activities = [],
    uploads = [],
    taskTypeOptions = {},
    priorityOptions = {},
    statusOptions = {},
    permissions = {},
    routes = {},
    uploadMaxMb = 10,
}) {
    const { csrf_token: csrfToken } = usePage().props;

    const [assigneeRows, setAssigneeRows] = useState(Array.isArray(assignees) ? assignees : []);
    const [employeeIds, setEmployeeIds] = useState(employeeIdsFromAssignees(assignees));
    const [assigneeNotice, setAssigneeNotice] = useState('');

    const [activityRows, setActivityRows] = useState(
        (Array.isArray(activities) ? activities : [])
            .map((row) => normalizeActivity(row))
            .filter(Boolean)
    );
    const [activityMessage, setActivityMessage] = useState('');
    const [activityNotice, setActivityNotice] = useState('');
    const [activityBusy, setActivityBusy] = useState(false);

    const canEdit = Boolean(permissions?.canEdit);
    const canAddSubtask = Boolean(permissions?.canAddSubtask);
    const canPost = Boolean(permissions?.canPost);

    const subtaskRows = Array.isArray(subtasks) ? subtasks : [];
    const uploadRows = Array.isArray(uploads) ? uploads : [];
    const employeeOptions = Array.isArray(employees) ? employees : [];

    const latestUploadLabel = useMemo(() => {
        if (uploadRows.length === 0) {
            return '-';
        }
        const latest = uploadRows[uploadRows.length - 1];
        return latest?.created_at_display || '-';
    }, [uploadRows]);

    const submitAssignees = async (event) => {
        event.preventDefault();
        if (!routes?.assignees || !csrfToken) {
            setAssigneeNotice('Unable to update assignees.');
            return;
        }

        const formData = new FormData();
        employeeIds.forEach((id) => formData.append('employee_ids[]', id));
        formData.append('_method', 'PATCH');

        const response = await fetch(routes.assignees, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: formData,
        });
        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload?.ok) {
            setAssigneeNotice(payload?.message || 'Unable to update assignees.');
            return;
        }

        const nextRows = Array.isArray(payload.assignees) ? payload.assignees : [];
        setAssigneeRows(nextRows);
        setEmployeeIds(employeeIdsFromAssignees(nextRows));
        setAssigneeNotice('Assignees updated.');
    };

    const refreshActivity = async () => {
        if (!routes?.activityItems || activityBusy) {
            return;
        }

        setActivityBusy(true);
        try {
            const url = `${routes.activityItems}${routes.activityItems.includes('?') ? '&' : '?'}limit=100`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) {
                setActivityNotice('Unable to refresh activity.');
                return;
            }

            const rows = (Array.isArray(payload?.data?.items) ? payload.data.items : [])
                .map((item) => normalizeActivity(item))
                .filter(Boolean);
            setActivityRows(rows);
            setActivityNotice('Activity refreshed.');
        } catch (_error) {
            setActivityNotice('Unable to refresh activity.');
        } finally {
            setActivityBusy(false);
        }
    };

    const submitActivity = async (event) => {
        event.preventDefault();
        if (activityBusy) {
            return;
        }

        const message = String(activityMessage || '').trim();
        if (message === '') {
            setActivityNotice('Message cannot be empty.');
            return;
        }

        if (!routes?.activityItemsStore || !csrfToken) {
            setActivityNotice('Unable to post message.');
            return;
        }

        setActivityBusy(true);
        try {
            const formData = new FormData();
            formData.append('message', message);

            const response = await fetch(routes.activityItemsStore, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: formData,
            });
            const payload = await response.json().catch(() => null);

            if (!response.ok || !payload?.ok) {
                setActivityNotice(payload?.message || 'Unable to post message.');
                return;
            }

            const nextRows = (Array.isArray(payload?.data?.items) ? payload.data.items : [])
                .map((item) => normalizeActivity(item))
                .filter(Boolean);
            setActivityRows((previous) => {
                const map = new Map(previous.map((row) => [row.id, row]));
                nextRows.forEach((row) => map.set(row.id, row));
                return Array.from(map.values()).sort((a, b) => Number(a.id || 0) - Number(b.id || 0));
            });
            setActivityMessage('');
            setActivityNotice(payload?.message || 'Comment added.');
        } catch (_error) {
            setActivityNotice('Unable to post message.');
        } finally {
            setActivityBusy(false);
        }
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex items-center justify-between">
                <a href={routes?.back || '#'} data-native="true" className="text-sm font-semibold text-teal-600 hover:text-teal-700">
                    Back
                </a>
                <div className="text-xs text-slate-500">Project: {project?.name || '--'}</div>
            </div>

            <div className="space-y-6">
                <div className="card p-6">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{task?.title || 'Task Details'}</div>
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <span className="rounded-full px-3 py-1 font-semibold" style={{ backgroundColor: task?.status_colors?.bg || '#f1f5f9', color: task?.status_colors?.text || '#64748b' }}>
                            {task?.status_label || 'Pending'}
                        </span>
                        <span className="rounded-full bg-blue-100 px-3 py-1 font-semibold text-blue-700">{task?.priority_label || 'Medium'}</span>
                        <span className="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">{task?.task_type_label || 'Task'}</span>
                    </div>

                    {routePrefix === 'employee' && (permissions?.canStartTask || permissions?.canCompleteTask) ? (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {permissions?.canStartTask && routes?.start ? (
                                <form method="POST" action={routes.start} data-native="true">
                                    <input type="hidden" name="_token" value={csrfToken} />
                                    <HiddenMethod method="PATCH" />
                                    <button type="submit" className="rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">Inprogress</button>
                                </form>
                            ) : null}
                            {permissions?.canCompleteTask && routes?.update ? (
                                <form method="POST" action={routes.update} data-native="true">
                                    <input type="hidden" name="_token" value={csrfToken} />
                                    <HiddenMethod method="PATCH" />
                                    <input type="hidden" name="status" value="completed" />
                                    <button type="submit" className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700">Complete</button>
                                </form>
                            ) : null}
                        </div>
                    ) : null}

                    {routePrefix === 'client' && canEdit ? (
                        <a href="#task-edit" data-native="true" className="mt-2 inline-flex text-xs font-semibold text-teal-600 hover:text-teal-700">Edit task</a>
                    ) : null}

                    <div className="mt-4 grid gap-3 text-sm md:grid-cols-3">
                        <div><span className="text-xs text-slate-500">Start:</span> {task?.start_date_display || '-'}</div>
                        <div><span className="text-xs text-slate-500">Due:</span> {task?.due_date_display || '-'}</div>
                        <div><span className="text-xs text-slate-500">Estimate:</span> {task?.time_estimate_minutes ? `${task.time_estimate_minutes} min` : '-'}</div>
                    </div>
                    <div className="mt-2 text-sm text-slate-700">Tags: {Array.isArray(task?.tags) && task.tags.length > 0 ? task.tags.join(', ') : '-'}</div>
                    <div className="mt-1 text-xs text-slate-500">Created {task?.created_at_display || '-'} by {task?.creator_name || '--'}</div>
                </div>

                <div className="card p-6">
                    <div className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Assignees</div>
                    {assigneeRows.length === 0 ? (
                        <div className="text-sm text-slate-500">No assignees</div>
                    ) : (
                        <div className="space-y-1 text-sm text-slate-700">
                            {assigneeRows.map((row) => <div key={`${row?.type || 'x'}-${row?.id || 'x'}`}>{row?.name || 'Unknown'}</div>)}
                        </div>
                    )}

                    {routePrefix === 'admin' && canEdit && routes?.assignees ? (
                        <form onSubmit={submitAssignees} className="mt-3 space-y-2">
                            <select
                                multiple
                                value={employeeIds}
                                onChange={(event) => setEmployeeIds(Array.from(event.target.selectedOptions).map((option) => option.value))}
                                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs"
                            >
                                {employeeOptions.map((employee) => (
                                    <option key={employee.id} value={String(employee.id)}>{employee.name}</option>
                                ))}
                            </select>
                            <div className="flex items-center justify-between">
                                <span className="text-[11px] text-slate-500">{assigneeNotice}</span>
                                <button type="submit" className="rounded-full border border-teal-200 px-3 py-1 text-[11px] font-semibold text-teal-700">Save assignees</button>
                            </div>
                        </form>
                    ) : null}
                </div>

                {routePrefix === 'admin' && canEdit && routes?.update ? (
                    <form method="POST" action={routes.update} data-native="true" className="card space-y-3 p-6">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <HiddenMethod method="PATCH" />
                        <div className="grid gap-3 md:grid-cols-3">
                            <select name="status" defaultValue={task?.status || 'pending'} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                {Object.entries(statusOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                            <select name="priority" defaultValue={task?.priority || 'medium'} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                {Object.entries(priorityOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                            <select name="task_type" defaultValue={task?.task_type || 'feature'} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                {Object.entries(taskTypeOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </div>
                        <input name="time_estimate_minutes" type="number" min="0" defaultValue={task?.time_estimate_minutes || ''} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                        <input name="tags" defaultValue={Array.isArray(task?.tags) ? task.tags.join(', ') : ''} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                        <textarea name="description" defaultValue={task?.description || ''} rows={4} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="customer_visible" value="0" />
                            <input type="checkbox" name="customer_visible" value="1" defaultChecked={Boolean(task?.customer_visible)} />
                            Visible to customer
                        </label>
                        <div className="flex justify-end"><button type="submit" className="rounded-lg bg-teal-600 px-6 py-2 font-semibold text-white">Update Task</button></div>
                    </form>
                ) : null}

                {routePrefix === 'client' && canEdit && routes?.update ? (
                    <form method="POST" action={routes.update} data-native="true" id="task-edit" className="card space-y-3 p-6">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <HiddenMethod method="PATCH" />
                        <select name="status" defaultValue={task?.status || 'pending'} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                            {Object.entries(statusOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <textarea name="description" defaultValue={task?.description || ''} rows={4} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                        <div className="flex justify-end"><button type="submit" className="rounded-lg bg-teal-600 px-6 py-2 font-semibold text-white">Update Task</button></div>
                    </form>
                ) : null}

                <div className="card p-6">
                    <div className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Subtasks</div>
                    {canAddSubtask && routes?.subtasksStore ? (
                        <form method="POST" action={routes.subtasksStore} data-native="true" encType="multipart/form-data" className="mb-4 grid gap-2 md:grid-cols-3">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <input name="title" required placeholder="Subtask title" className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm md:col-span-2" />
                            <input name="image" type="file" accept=".png,.jpg,.jpeg,.webp" className="text-xs text-slate-600" />
                            <div className="md:col-span-3 flex justify-end"><button type="submit" className="rounded-full border border-slate-300 px-4 py-1.5 text-xs font-semibold text-slate-700">Add subtask</button></div>
                        </form>
                    ) : null}

                    {subtaskRows.length > 0 ? (
                        <div className="space-y-3">
                            {subtaskRows.map((subtask) => (
                                <div key={subtask.id} className="rounded-xl border border-slate-200 bg-white p-3">
                                    <div className="font-semibold text-slate-900">{subtask.title}</div>
                                    <div className="text-xs text-slate-500">{subtask.created_by_label} | {subtask.status_label}</div>
                                    <div className="text-xs text-slate-500">Due: {subtask.due_date_display || '-'} {subtask.due_time_display !== '-' ? subtask.due_time_display : ''}</div>
                                    {subtask.attachment_url ? <a href={subtask.attachment_url} target="_blank" rel="noopener" className="text-xs font-semibold text-teal-600">Attachment</a> : null}
                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        {subtask.can_change_status ? (
                                            <form method="POST" action={subtask?.routes?.update} data-native="true" className="flex items-center gap-2">
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <HiddenMethod method="PATCH" />
                                                <select name="status" defaultValue={subtask.status} className="subtask-status-select rounded-md border border-slate-200 bg-white px-2 py-1 text-xs">
                                                    <option value="open">Open</option>
                                                    <option value="in_progress">Inprogress</option>
                                                    <option value="completed">Completed</option>
                                                </select>
                                                <button type="submit" className="rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700">Save</button>
                                            </form>
                                        ) : null}
                                        {subtask.can_edit ? (
                                            <details>
                                                <summary className="subtask-edit-btn cursor-pointer text-xs font-semibold text-teal-600">Open</summary>
                                                <form method="POST" action={subtask?.routes?.update} data-native="true" className="mt-2 flex items-center gap-2">
                                                    <input type="hidden" name="_token" value={csrfToken} />
                                                    <HiddenMethod method="PATCH" />
                                                    <input name="title" defaultValue={subtask.title} className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs" />
                                                    <button type="submit" className="rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700">Save</button>
                                                </form>
                                            </details>
                                        ) : null}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-sm text-slate-500">No subtasks yet.</div>
                    )}
                </div>

                <div className="card p-6">
                    <div className="mb-3 flex items-center justify-between">
                        <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Activity</div>
                        <button type="button" onClick={refreshActivity} disabled={activityBusy} className="rounded-full border border-slate-300 px-3 py-1 text-[11px] font-semibold text-slate-700 disabled:opacity-50">
                            Refresh
                        </button>
                    </div>

                    {canPost && routes?.activityItemsStore ? (
                        <form onSubmit={submitActivity} className="mb-3 space-y-2">
                            <textarea
                                name="message"
                                rows={3}
                                value={activityMessage}
                                onChange={(event) => setActivityMessage(event.target.value)}
                                placeholder="Write a message"
                                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                            />
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-slate-500">{activityNotice}</span>
                                <button type="submit" disabled={activityBusy} className="rounded-full border border-teal-200 px-4 py-1.5 text-xs font-semibold text-teal-700 disabled:opacity-50">
                                    Post
                                </button>
                            </div>
                        </form>
                    ) : null}

                    {routes?.upload ? (
                        <form method="POST" action={routes.upload} data-native="true" encType="multipart/form-data" className="mb-4 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <div className="text-xs text-slate-500">Upload (max {uploadMaxMb}MB)</div>
                            <input name="attachment" type="file" required className="w-full text-xs text-slate-600" />
                            <input name="message" placeholder="Optional note" className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs" />
                            <div className="flex justify-end"><button type="submit" className="rounded-full border border-slate-300 px-4 py-1.5 text-xs font-semibold text-slate-700">Upload</button></div>
                        </form>
                    ) : null}

                    {uploadRows.length > 0 ? (
                        <div className="mb-3 text-xs text-slate-500">Latest upload: {latestUploadLabel}</div>
                    ) : null}

                    {activityRows.length > 0 ? (
                        <div className="space-y-2">
                            {activityRows.map((activity) => (
                                <div key={activity.id} className="rounded-xl border border-slate-200 bg-white p-3">
                                    <div className="text-xs text-slate-500">{activity.actor_name} ({activity.actor_type_label}) | {activity.created_at_display}</div>
                                    {activity.message ? <div className="mt-1 whitespace-pre-line text-sm text-slate-800">{activity.message}</div> : null}
                                    {activity.link_url ? <a href={activity.link_url} target="_blank" rel="noopener" className="text-xs font-semibold text-teal-600">{activity.link_url}</a> : null}
                                    {activity.attachment_url ? <a href={activity.attachment_url} target="_blank" rel="noopener" className="ml-2 text-xs font-semibold text-teal-600">{activity.attachment_name || 'Attachment'}</a> : null}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-sm text-slate-500">No activity yet.</div>
                    )}
                </div>
            </div>
        </>
    );
}
