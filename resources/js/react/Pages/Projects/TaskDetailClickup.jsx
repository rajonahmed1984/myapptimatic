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

function isImageAttachment(subtask) {
    const url = String(subtask?.attachment_url || '');
    const name = String(subtask?.attachment_name || '');
    const mime = String(subtask?.attachment_mime || subtask?.mime_type || '');
    const imagePattern = /\.(png|jpe?g|webp|gif|bmp|svg)$/i;

    return (
        mime.startsWith('image/') ||
        imagePattern.test(url.split('?')[0]) ||
        imagePattern.test(name)
    );
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
    const [inlineSubtasksOpen, setInlineSubtasksOpen] = useState(false);
    const [subtaskFormOpen, setSubtaskFormOpen] = useState(true);
    const [taskEditOpen, setTaskEditOpen] = useState(false);

    const canEdit = Boolean(permissions?.canEdit);
    const canAddSubtask = Boolean(permissions?.canAddSubtask);
    const canPost = Boolean(permissions?.canPost);
    const canShowTaskEdit = (routePrefix === 'admin' || routePrefix === 'client') && canEdit && Boolean(routes?.update);

    const subtaskRows = Array.isArray(subtasks) ? subtasks : [];
    const employeeOptions = Array.isArray(employees) ? employees : [];
    const currentTaskStatus = String(task?.status || '').toLowerCase();
    const taskIsInProgress = currentTaskStatus === 'in_progress';
    const taskIsCompleted = ['completed', 'done'].includes(currentTaskStatus);
    const subtaskSectionTitle = /loan\s*repayment/i.test(String(task?.title || ''))
        ? 'Loan Repayment Schedule'
        : 'Subtasks';
    const completedSubtaskCount = useMemo(
        () => subtaskRows.filter((row) => String(row?.status || '') === 'completed').length,
        [subtaskRows]
    );
    const subtaskProgressPercent = subtaskRows.length > 0
        ? Math.round((completedSubtaskCount / subtaskRows.length) * 100)
        : 0;

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

    const toggleTaskEdit = () => {
        setTaskEditOpen((previous) => {
            const next = !previous;
            if (next) {
                window.setTimeout(() => {
                    document.getElementById('task-edit-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 40);
            }
            return next;
        });
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
                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="card p-6">
                        <div className="flex items-start justify-between gap-3">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Main Task</div>
                            {canShowTaskEdit ? (
                                <button
                                    type="button"
                                    onClick={toggleTaskEdit}
                                    className="rounded-full border border-teal-200 px-3 py-1 text-xs font-semibold text-teal-700 hover:bg-teal-50"
                                >
                                    {taskEditOpen ? 'Close edit' : 'Edit'}
                                </button>
                            ) : null}
                        </div>
                        <div className="mt-1 text-lg font-semibold text-slate-900">{task?.title || 'Task Details'}</div>
                        <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span className="rounded-full px-3 py-1 font-semibold" style={{ backgroundColor: task?.status_colors?.bg || '#f1f5f9', color: task?.status_colors?.text || '#64748b' }}>
                                {task?.status_label || 'Pending'}
                            </span>
                            <span className="rounded-full bg-blue-100 px-3 py-1 font-semibold text-blue-700">{task?.priority_label || 'Medium'}</span>
                            <span className="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">{task?.task_type_label || 'Task'}</span>
                        </div>

                        {routePrefix === 'employee' && (
                            (permissions?.canStartTask && routes?.start && !taskIsInProgress) ||
                            (permissions?.canCompleteTask && routes?.update && !taskIsCompleted)
                        ) ? (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {permissions?.canStartTask && routes?.start && !taskIsInProgress ? (
                                    <form method="POST" action={routes.start} data-native="true">
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <HiddenMethod method="PATCH" />
                                        <button type="submit" className="rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">Inprogress</button>
                                    </form>
                                ) : null}
                                {permissions?.canCompleteTask && routes?.update && !taskIsCompleted ? (
                                    <form method="POST" action={routes.update} data-native="true">
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <HiddenMethod method="PATCH" />
                                        <input type="hidden" name="status" value="completed" />
                                        <button type="submit" className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700">Complete</button>
                                    </form>
                                ) : null}
                            </div>
                        ) : null}

                        <div className="mt-4 grid gap-3 text-sm md:grid-cols-3">
                            <div><span className="text-xs text-slate-500">Start:</span> {task?.start_date_display || '-'}</div>
                            <div><span className="text-xs text-slate-500">Due:</span> {task?.due_date_display || '-'}</div>
                            <div><span className="text-xs text-slate-500">Estimate:</span> {task?.time_estimate_minutes ? `${task.time_estimate_minutes} min` : '-'}</div>
                        </div>
                        <div className="mt-2 text-sm text-slate-700">Tags: {Array.isArray(task?.tags) && task.tags.length > 0 ? task.tags.join(', ') : '-'}</div>
                        <div className="mt-1 text-xs text-slate-500">Created {task?.created_at_display || '-'} by {task?.creator_name || '--'}</div>

                        <div className="mt-4 border-t border-slate-200 pt-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                    Subtasks ({subtaskRows.length})
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setInlineSubtasksOpen((prev) => !prev)}
                                        className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-700"
                                    >
                                        {inlineSubtasksOpen ? 'Hide in Main Task' : 'Open in Main Task'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => document.getElementById('task-subtask-management')?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
                                        className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-700"
                                    >
                                        Manage Subtasks
                                    </button>
                                </div>
                            </div>

                            {inlineSubtasksOpen ? (
                                <div className="mt-3 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    {subtaskRows.length === 0 ? (
                                        <div className="text-sm text-slate-500">No subtasks yet.</div>
                                    ) : (
                                        <div className="space-y-2">
                                            {subtaskRows.map((subtask) => (
                                                <div key={`inline-${subtask.id}`} className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <div className="font-semibold text-slate-900">{subtask.title}</div>
                                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                                            {subtask.status_label}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 text-xs text-slate-500">
                                                        Due: {subtask.due_date_display || '-'} {subtask.due_time_display !== '-' ? subtask.due_time_display : ''}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ) : null}
                        </div>
                    </div>

                    <div className="card p-6">
                        <div className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Assignees</div>
                        {assigneeRows.length === 0 ? (
                            <div className="text-sm text-slate-500">No assignees</div>
                        ) : (
                            <div className="max-h-64 overflow-y-auto pr-1">
                                <div className="space-y-1 text-sm text-slate-700">
                                    {assigneeRows.map((row) => <div key={`${row?.type || 'x'}-${row?.id || 'x'}`}>{row?.name || 'Unknown'}</div>)}
                                </div>
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
                </div>

                <div className="grid gap-6 lg:grid-cols-[7fr_3fr]">
                    <div id="task-subtask-management" className="card p-6">
                        <div className="mb-2 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="text-lg font-semibold text-slate-900">{subtaskSectionTitle}</div>
                                <div className="mt-0.5 text-sm text-slate-500">
                                    {completedSubtaskCount} of {subtaskRows.length} completed
                                </div>
                            </div>
                            {canAddSubtask && routes?.subtasksStore ? (
                                <button
                                    type="button"
                                    onClick={() => setSubtaskFormOpen((prev) => !prev)}
                                    className="rounded-full border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700 hover:bg-teal-50"
                                >
                                    {subtaskFormOpen ? 'Hide form' : '+ Add subtask'}
                                </button>
                            ) : null}
                        </div>

                        <div className="mb-4 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div className="h-full rounded-full bg-teal-500 transition-all" style={{ width: `${subtaskProgressPercent}%` }} />
                        </div>

                        {subtaskRows.length > 0 ? (
                            <div className="max-h-[26rem] overflow-y-auto pr-1">
                                <div className="space-y-3">
                                    {subtaskRows.map((subtask) => {
                                        const subtaskStatus = String(subtask?.status || '').toLowerCase();
                                        const subtaskIsInProgress = subtaskStatus === 'in_progress';
                                        const subtaskIsCompleted = ['completed', 'done'].includes(subtaskStatus);

                                        return (
                                            <div key={subtask.id} className="rounded-xl border border-slate-200 bg-white p-4">
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <div className="font-semibold text-slate-900">{subtask.title}</div>
                                                    <div className="text-xs text-slate-500">
                                                        Created: {subtask.created_at_display || '-'} | Added by: {subtask.created_by_label || '--'}
                                                    </div>
                                                    {subtask.is_completed ? (
                                                        <div className="text-xs font-semibold text-emerald-600">
                                                            Completed: {subtask.completed_at_display || '-'} | Completed by: {subtask.completed_by_name || '--'}
                                                        </div>
                                                    ) : null}
                                                </div>
                                                <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                                    {subtask.status_label || 'Open'}
                                                </span>
                                            </div>
                                            {subtask.attachment_url ? (
                                                isImageAttachment(subtask) ? (
                                                    <a
                                                        href={subtask.attachment_url}
                                                        target="_blank"
                                                        rel="noopener"
                                                        className="mt-2 inline-flex max-w-full items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 hover:border-teal-200 hover:bg-teal-50/40"
                                                    >
                                                        <img
                                                            src={subtask.attachment_url}
                                                            alt={subtask.attachment_name || 'Subtask image'}
                                                            className="h-12 w-12 rounded-md border border-slate-200 object-cover bg-white"
                                                            loading="lazy"
                                                        />
                                                        <span className="truncate text-xs font-semibold text-slate-700">View image</span>
                                                    </a>
                                                ) : (
                                                    <a href={subtask.attachment_url} target="_blank" rel="noopener" className="mt-2 inline-flex text-xs font-semibold text-teal-600">Attachment</a>
                                                )
                                            ) : null}
                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                {subtask.can_edit ? (
                                                    <details>
                                                        <summary className="subtask-edit-btn cursor-pointer rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-200 hover:text-teal-700">Edit</summary>
                                                        <form method="POST" action={subtask?.routes?.update} data-native="true" className="mt-2 flex items-center gap-2">
                                                            <input type="hidden" name="_token" value={csrfToken} />
                                                            <HiddenMethod method="PATCH" />
                                                            <input name="title" defaultValue={subtask.title} className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs" />
                                                            <button type="submit" className="rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700">Save</button>
                                                        </form>
                                                    </details>
                                                ) : null}
                                                {subtask.can_change_status ? (
                                                    <>
                                                        {!subtaskIsInProgress ? (
                                                            <form method="POST" action={subtask?.routes?.update} data-native="true">
                                                                <input type="hidden" name="_token" value={csrfToken} />
                                                                <HiddenMethod method="PATCH" />
                                                                <input type="hidden" name="status" value="in_progress" />
                                                                <button
                                                                    type="submit"
                                                                    className="rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50"
                                                                >
                                                                    Inprogress
                                                                </button>
                                                            </form>
                                                        ) : null}
                                                        {!subtaskIsCompleted ? (
                                                            <form method="POST" action={subtask?.routes?.update} data-native="true">
                                                                <input type="hidden" name="_token" value={csrfToken} />
                                                                <HiddenMethod method="PATCH" />
                                                                <input type="hidden" name="status" value="completed" />
                                                                <button
                                                                    type="submit"
                                                                    className="rounded-full border border-emerald-200 bg-white px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                                                >
                                                                    Complete
                                                                </button>
                                                            </form>
                                                        ) : null}
                                                    </>
                                                ) : null}
                                            </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : (
                            <div className="text-sm text-slate-500">No subtasks yet.</div>
                        )}

                        {canAddSubtask && routes?.subtasksStore && subtaskFormOpen ? (
                            <form method="POST" action={routes.subtasksStore} data-native="true" encType="multipart/form-data" className="mt-4 rounded-xl border border-teal-200 bg-teal-50/40 p-4">
                                <input type="hidden" name="_token" value={csrfToken} />
                                <div className="space-y-3">
                                    <input
                                        name="title"
                                        required
                                        placeholder="What needs to be done?"
                                        className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-teal-300 focus:outline-none"
                                    />
                                    <div>
                                        <div className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Image (optional)</div>
                                        <input name="image" type="file" accept=".png,.jpg,.jpeg,.webp" className="mt-1 w-full text-xs text-slate-600" />
                                        <div className="mt-1 text-xs text-slate-500">Max {uploadMaxMb}MB.</div>
                                    </div>
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => setSubtaskFormOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">
                                            Cancel
                                        </button>
                                        <button type="submit" className="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">
                                            Add Subtask
                                        </button>
                                    </div>
                                </div>
                            </form>
                        ) : null}
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

                        {activityRows.length > 0 ? (
                            <div className="max-h-[26rem] space-y-2 overflow-y-auto pr-1">
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

                {routePrefix === 'admin' && canEdit && routes?.update && taskEditOpen ? (
                    <form method="POST" action={routes.update} data-native="true" id="task-edit-panel" className="card space-y-3 p-6">
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

                {routePrefix === 'client' && canEdit && routes?.update && taskEditOpen ? (
                    <form method="POST" action={routes.update} data-native="true" id="task-edit-panel" className="card space-y-3 p-6">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <HiddenMethod method="PATCH" />
                        <select name="status" defaultValue={task?.status || 'pending'} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                            {Object.entries(statusOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <textarea name="description" defaultValue={task?.description || ''} rows={4} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                        <div className="flex justify-end"><button type="submit" className="rounded-lg bg-teal-600 px-6 py-2 font-semibold text-white">Update Task</button></div>
                    </form>
                ) : null}

            </div>
        </>
    );
}
