import React, { useEffect, useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

const formatSeconds = (seconds) => {
    const total = Math.max(0, Number(seconds || 0));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
};

const statusClass = (status) => {
    if (status === 'working') return 'bg-emerald-100 text-emerald-700';
    if (status === 'idle') return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-600';
};

export default function Index({
    employee = null,
    project_stats = {},
    recent_projects = [],
    task_stats = {},
    contract_summary = null,
    contract_projects = [],
    work_session = {},
    tasks_widget = {},
    routes = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const [session, setSession] = useState({
        status: 'stopped',
        active_seconds: 0,
        required_seconds: work_session?.required_seconds || 0,
        salary_estimate: 0,
        is_active: false,
    });
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!work_session?.eligible || !work_session?.routes?.summary) return;

        let active = true;
        const load = async () => {
            try {
                const response = await fetch(work_session.routes.summary, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await response.json();
                if (active && response.ok && payload?.data) {
                    setSession(payload.data);
                }
            } catch (_error) {
                // keep last state
            }
        };

        load();
        return () => {
            active = false;
        };
    }, [work_session?.eligible, work_session?.routes?.summary]);

    useEffect(() => {
        if (!work_session?.eligible || !session?.is_active || !work_session?.routes?.ping) return;

        const timer = window.setInterval(async () => {
            try {
                const form = new FormData();
                form.set('_token', csrfToken);
                const response = await fetch(work_session.routes.ping, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: form,
                });
                const payload = await response.json();
                if (response.ok && payload?.data) {
                    setSession(payload.data);
                }
            } catch (_error) {
                // keep last state
            }
        }, 90000);

        return () => window.clearInterval(timer);
    }, [csrfToken, session?.is_active, work_session?.eligible, work_session?.routes?.ping]);

    const postSession = async (url) => {
        if (!url || busy) return;
        setBusy(true);
        try {
            const form = new FormData();
            form.set('_token', csrfToken);
            const response = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: form,
            });
            const payload = await response.json();
            if (response.ok && payload?.data) {
                setSession(payload.data);
            }
        } finally {
            setBusy(false);
        }
    };

    const completedProjects = useMemo(() => {
        const counts = project_stats?.status_counts || {};
        return Number(counts.complete || 0) + Number(counts.completed || 0) + Number(counts.done || 0);
    }, [project_stats]);

    return (
        <>
            <Head title="Employee Dashboard" />

            <div className="space-y-6">
                <div className="card p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <div className="section-label">Welcome</div>
                            <div className="text-2xl font-semibold text-slate-900">{employee?.name || 'Employee'}</div>
                            <div className="text-sm text-slate-500">Access your work logs, leave requests, payroll, and project assignments.</div>
                        </div>
                        <form method="POST" action={routes?.logout} data-native="true">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <button type="submit" className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-600">
                                Logout
                            </button>
                        </form>
                    </div>

                    <div className="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 md:grid-cols-2">
                        <div><span className="font-semibold text-slate-900">Employee ID:</span> {employee?.id || '--'}</div>
                        <div><span className="font-semibold text-slate-900">Email:</span> {employee?.email || '--'}</div>
                        <div><span className="font-semibold text-slate-900">Phone:</span> {employee?.phone || '--'}</div>
                        <div><span className="font-semibold text-slate-900">Status:</span> {employee?.status || '--'}</div>
                        <div><span className="font-semibold text-slate-900">Department:</span> {employee?.department || '--'}</div>
                        <div><span className="font-semibold text-slate-900">Designation:</span> {employee?.designation || '--'}</div>
                        <div><span className="font-semibold text-slate-900">Manager:</span> {employee?.manager_name || '--'}</div>
                        <div><span className="font-semibold text-slate-900">Employment Type:</span> {employee?.employment_type || '--'}</div>
                        <div><span className="font-semibold text-slate-900">Work Mode:</span> {employee?.work_mode || '--'}</div>
                        <div><span className="font-semibold text-slate-900">Join Date:</span> {employee?.join_date_display || '--'}</div>
                        <div className="md:col-span-2"><span className="font-semibold text-slate-900">Address:</span> {employee?.address || '--'}</div>
                    </div>
                </div>

                {work_session?.eligible ? (
                    <div className="card p-6">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div className="section-label">Work Session</div>
                                <div className="text-sm text-slate-500">Idle for 15+ minutes is not counted.</div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(session?.status)}`}>
                                    {session?.status || 'stopped'}
                                </span>
                                {!session?.is_active ? (
                                    <button
                                        type="button"
                                        disabled={busy}
                                        onClick={() => postSession(work_session?.routes?.start)}
                                        className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                                    >
                                        Start
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        disabled={busy}
                                        onClick={() => postSession(work_session?.routes?.stop)}
                                        className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-rose-200 hover:text-rose-600 disabled:opacity-60"
                                    >
                                        Stop
                                    </button>
                                )}
                            </div>
                        </div>

                        <div className="mt-6 grid gap-4 md:grid-cols-3">
                            <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Worked Today</div>
                                <div className="mt-2 text-2xl font-semibold text-slate-900">{formatSeconds(session?.active_seconds || 0)}</div>
                            </div>
                            <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Required</div>
                                <div className="mt-2 text-2xl font-semibold text-slate-900">{Math.round(Number(session?.required_seconds || 0) / 3600)}h</div>
                            </div>
                            <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Salary Estimate</div>
                                <div className="mt-2 text-2xl font-semibold text-slate-900">{Number(session?.salary_estimate || 0).toFixed(2)}</div>
                            </div>
                        </div>
                    </div>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="card p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="section-label">Assigned projects</div>
                                <div className="text-sm text-slate-500">Current deliveries</div>
                            </div>
                            <span className="text-xl font-semibold text-slate-900">{project_stats?.total || 0}</span>
                        </div>
                        <div className="mt-6 grid gap-4 sm:grid-cols-3">
                            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div className="text-xs uppercase tracking-[0.25em] text-slate-400">Ongoing</div>
                                <div className="mt-2 text-2xl font-semibold text-slate-900">{project_stats?.status_counts?.ongoing || 0}</div>
                            </div>
                            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div className="text-xs uppercase tracking-[0.25em] text-slate-400">On hold</div>
                                <div className="mt-2 text-2xl font-semibold text-amber-600">{project_stats?.status_counts?.hold || 0}</div>
                            </div>
                            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div className="text-xs uppercase tracking-[0.25em] text-slate-400">Completed</div>
                                <div className="mt-2 text-2xl font-semibold text-emerald-600">{completedProjects}</div>
                            </div>
                        </div>
                    </div>

                    <div className="card p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="section-label">Assigned tasks</div>
                                <div className="text-sm text-slate-500">Tasks awaiting your actions</div>
                            </div>
                            <span className="text-xl font-semibold text-slate-900">{task_stats?.total || 0}</span>
                        </div>
                        <div className="mt-6 space-y-3 text-sm text-slate-600">
                            <div className="flex items-center justify-between"><div>In progress</div><div className="font-semibold text-slate-900">{task_stats?.in_progress || 0}</div></div>
                            <div className="flex items-center justify-between"><div>Completed</div><div className="font-semibold text-emerald-600">{task_stats?.completed || 0}</div></div>
                        </div>
                    </div>
                </div>

                {contract_summary ? (
                    <div className="card p-6">
                        <div className="section-label">Contract earnings</div>
                        <div className="mt-6 grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                            <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Payable</div>
                                <div className="mt-2 text-2xl font-semibold text-slate-900">{Number(contract_summary?.payable || 0).toFixed(2)}</div>
                            </div>
                            <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total earned</div>
                                <div className="mt-2 text-2xl font-semibold text-slate-900">{Number(contract_summary?.total_earned || 0).toFixed(2)}</div>
                            </div>
                        </div>
                        <div className="mt-4 space-y-2">
                            {contract_projects.map((project) => (
                                <div key={project.id} className="rounded-xl border border-slate-200 bg-white/70 px-3 py-2">
                                    <a href={project?.routes?.show} data-native="true" className="font-semibold text-slate-900 hover:text-teal-600">{project.name}</a>
                                    <div className="text-xs text-slate-600">Earned: {Number(project.total_earned || 0).toFixed(2)} {project.currency}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}

                {tasks_widget?.show ? (
                    <div className="card p-6">
                        <div className="section-label">My Open Tasks</div>
                        <div className="mt-2 text-sm text-slate-600">
                            Open: {tasks_widget?.summary?.open ?? 0} | In progress: {tasks_widget?.summary?.in_progress ?? 0}
                        </div>
                    </div>
                ) : null}

                <div className="card overflow-hidden rounded-2xl border border-slate-200">
                    <div className="flex items-center justify-between px-6 py-4">
                        <div>
                            <div className="section-label">Recent projects</div>
                            <div className="text-sm text-slate-500">Projects assigned to you</div>
                        </div>
                        <a href={routes?.projects_index} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">View all</a>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Project</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Tasks</th>
                                    <th className="px-4 py-3">Due</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recent_projects.length === 0 ? (
                                    <tr><td colSpan={4} className="px-4 py-6 text-center text-slate-500">No assigned projects yet.</td></tr>
                                ) : recent_projects.map((project) => (
                                    <tr key={project.id}>
                                        <td className="px-4 py-3">
                                            <a href={project?.routes?.show} data-native="true" className="font-semibold text-slate-900 hover:text-teal-600">{project.name}</a>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{project.status_label}</td>
                                        <td className="px-4 py-3 text-slate-600">{project.tasks_count}</td>
                                        <td className="px-4 py-3 text-slate-600">{project.due_date_display}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
