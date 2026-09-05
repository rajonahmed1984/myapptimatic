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
    const [isProfileInfoOpen, setIsProfileInfoOpen] = useState(false);

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
                <div className="card p-4 sm:p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <div className="section-label">Welcome</div>
                            <div className="text-xl sm:text-2xl font-semibold text-slate-900">{employee?.name || 'Employee'}</div>
                            <div className="text-xs sm:text-sm text-slate-500">Access your work logs, leave requests, payroll, and projects.</div>
                            <button
                                type="button"
                                onClick={() => setIsProfileInfoOpen(!isProfileInfoOpen)}
                                className="md:hidden mt-2 inline-flex items-center gap-1 text-xs font-semibold text-teal-600 hover:text-teal-700 cursor-pointer"
                            >
                                <span>{isProfileInfoOpen ? 'Hide Profile Details' : 'View Profile Details'}</span>
                                <svg className={`w-3.5 h-3.5 transform transition-transform duration-200 ${isProfileInfoOpen ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                        <form method="POST" action={routes?.logout} data-native="true">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <button type="submit" className="rounded-full border border-slate-200 px-3.5 py-1.5 text-xs sm:text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-600 transition">
                                Logout
                            </button>
                        </form>
                    </div>

                    <div className={`mt-4 sm:mt-5 grid gap-2.5 sm:gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3.5 sm:p-4 text-xs sm:text-sm text-slate-700 md:grid-cols-2 ${isProfileInfoOpen ? 'block' : 'hidden md:grid'}`}>
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
                    <div className="card p-4 sm:p-6 border-2 border-teal-500/20 shadow-sm">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <div className="section-label">Work Session</div>
                                    <span className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider ${statusClass(session?.status)}`}>
                                        {session?.status || 'stopped'}
                                    </span>
                                </div>
                                <div className="text-xs sm:text-sm text-slate-500 mt-0.5">Idle for 15+ minutes is not counted.</div>
                            </div>
                            <div className="flex items-center">
                                {!session?.is_active ? (
                                    <button
                                        type="button"
                                        disabled={busy}
                                        onClick={() => postSession(work_session?.routes?.start)}
                                        className="w-full sm:w-auto min-h-[44px] rounded-xl bg-slate-900 px-6 py-2.5 text-xs sm:text-sm font-bold text-white shadow hover:bg-slate-800 active:scale-95 disabled:opacity-60 transition"
                                    >
                                        ▶ Start Session
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        disabled={busy}
                                        onClick={() => postSession(work_session?.routes?.stop)}
                                        className="w-full sm:w-auto min-h-[44px] rounded-xl border border-rose-200 bg-rose-50 px-6 py-2.5 text-xs sm:text-sm font-bold text-rose-700 shadow hover:bg-rose-100 hover:text-rose-800 active:scale-95 disabled:opacity-60 transition"
                                    >
                                        ⏹ Stop Session
                                    </button>
                                )}
                            </div>
                        </div>

                        <div className="mt-4 sm:mt-6 grid grid-cols-3 gap-2 sm:gap-4">
                            <div className="rounded-xl sm:rounded-2xl border border-slate-200 bg-white/90 p-2.5 sm:p-4 text-center sm:text-left">
                                <div className="text-[10px] sm:text-xs uppercase tracking-[0.16em] text-slate-400 truncate">Worked Today</div>
                                <div className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold text-slate-900 tabular-nums">{formatSeconds(session?.active_seconds || 0)}</div>
                            </div>
                            <div className="rounded-xl sm:rounded-2xl border border-slate-200 bg-white/90 p-2.5 sm:p-4 text-center sm:text-left">
                                <div className="text-[10px] sm:text-xs uppercase tracking-[0.16em] text-slate-400 truncate">Required</div>
                                <div className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold text-slate-900 tabular-nums">{Math.round(Number(session?.required_seconds || 0) / 3600)}h</div>
                            </div>
                            <div className="rounded-xl sm:rounded-2xl border border-slate-200 bg-white/90 p-2.5 sm:p-4 text-center sm:text-left">
                                <div className="text-[10px] sm:text-xs uppercase tracking-[0.16em] text-slate-400 truncate">Est. Salary</div>
                                <div className="mt-1 sm:mt-2 text-lg sm:text-2xl font-bold text-slate-900 tabular-nums">{Number(session?.salary_estimate || 0).toFixed(2)}</div>
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
                    <div className="flex items-center justify-between px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100">
                        <div>
                            <div className="section-label">Recent projects</div>
                            <div className="text-xs sm:text-sm text-slate-500">Projects assigned to you</div>
                        </div>
                        <a href={routes?.projects_index} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">View all</a>
                    </div>

                    {/* Mobile Card List (<md) */}
                    <div className="md:hidden divide-y divide-slate-100">
                        {recent_projects.length === 0 ? (
                            <div className="p-4 text-center text-sm text-slate-500">No assigned projects yet.</div>
                        ) : (
                            recent_projects.map((project) => (
                                <a
                                    key={project.id}
                                    href={project?.routes?.show}
                                    data-native="true"
                                    className="block p-3.5 hover:bg-slate-50 transition active:bg-slate-100"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="font-semibold text-sm text-slate-900 truncate">{project.name}</div>
                                        <span className="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700">
                                            {project.status_label}
                                        </span>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-xs text-slate-500">
                                        <span>Tasks: <strong className="text-slate-800">{project.tasks_count}</strong></span>
                                        <span>Due: <strong className="text-slate-800">{project.due_date_display || '--'}</strong></span>
                                    </div>
                                </a>
                            ))
                        )}
                    </div>

                    {/* Desktop Table (>=md) */}
                    <div className="hidden md:block overflow-x-auto">
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

Index.title = 'Employee Dashboard';
