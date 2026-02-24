import React from 'react';
import { Head, usePage } from '@inertiajs/react';

function TaskWidget({ showTasksWidget, taskSummary = {}, openTasks = [], inProgressTasks = [] }) {
    const { csrf_token: csrfToken } = usePage().props;
    if (!showTasksWidget) return null;

    return (
        <div className="card p-6">
            <div className="section-label">Tasks</div>
            <div className="mt-4 grid gap-3 text-sm text-slate-600 sm:grid-cols-3">
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Open</div>
                    <div className="mt-1 text-lg font-semibold text-slate-900">{taskSummary.open || 0}</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Inprogress</div>
                    <div className="mt-1 text-lg font-semibold text-amber-600">{taskSummary.in_progress || 0}</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Completed</div>
                    <div className="mt-1 text-lg font-semibold text-emerald-600">{taskSummary.completed || 0}</div>
                </div>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div>
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">My Open Tasks</div>
                    <div className="mt-3 space-y-3 text-sm">
                        {openTasks.map((task) => (
                            <div key={task.id} className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                                <div className="font-semibold text-slate-900">{task.title}</div>
                                <div className="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                                    {task.project ? (
                                        <a href={task.project.route_task_show} data-native="true" className="rounded-full border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-300">
                                            Open
                                        </a>
                                    ) : null}
                                    {task.can_start && task.project ? (
                                        <form method="POST" action={task.project.route_task_update} data-native="true">
                                            <input type="hidden" name="_token" value={csrfToken} />
                                            <input type="hidden" name="_method" value="PATCH" />
                                            <input type="hidden" name="status" value="in_progress" />
                                            <button type="submit" className="rounded-full border border-amber-200 px-2 py-0.5 text-[11px] font-semibold text-amber-700 hover:border-amber-300">
                                                Inprogress
                                            </button>
                                        </form>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
                <div>
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">My Inprogress Tasks</div>
                    <div className="mt-3 space-y-3 text-sm">
                        {inProgressTasks.map((task) => (
                            <div key={task.id} className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                                <div className="font-semibold text-slate-900">{task.title}</div>
                                <div className="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                                    {task.project ? (
                                        <a href={task.project.route_task_show} data-native="true" className="rounded-full border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-300">
                                            Open
                                        </a>
                                    ) : null}
                                    {task.can_complete && task.project ? (
                                        <form method="POST" action={task.project.route_task_update} data-native="true">
                                            <input type="hidden" name="_token" value={csrfToken} />
                                            <input type="hidden" name="_method" value="PATCH" />
                                            <input type="hidden" name="status" value="completed" />
                                            <button type="submit" className="rounded-full border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-300">
                                                Complete
                                            </button>
                                        </form>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function ProjectMinimal({
    project = {},
    user = {},
    totalTasks = 0,
    inProgressTaskCount = 0,
    completedTasks = 0,
    unreadMessagesCount = 0,
    recentTasks = [],
    recentMessages = [],
    showTasksWidget = false,
    taskSummary = null,
    openTasks = [],
    inProgressTasks = [],
    routes = {},
}) {
    return (
        <>
            <Head title="Project Dashboard" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div className="mt-1 text-2xl font-semibold text-slate-900">{project.name}</div>
                        <div className="mt-1 text-sm text-slate-500">Welcome, {user.name}</div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={routes.project_show} data-native="true" className="rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">
                            Project Details
                        </a>
                        <a href={routes.chat} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">
                            Open Chat
                        </a>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="card p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total Tasks</div>
                        <div className="mt-2 text-2xl font-semibold text-slate-900">{totalTasks}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">In Progress</div>
                        <div className="mt-2 text-2xl font-semibold text-blue-700">{inProgressTaskCount}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Completed</div>
                        <div className="mt-2 text-2xl font-semibold text-emerald-700">{completedTasks}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Unread Chat</div>
                        <div className="mt-2 text-2xl font-semibold text-amber-700">{unreadMessagesCount}</div>
                    </div>
                </div>

                <TaskWidget showTasksWidget={showTasksWidget} taskSummary={taskSummary || {}} openTasks={openTasks} inProgressTasks={inProgressTasks} />

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="card p-5">
                        <div className="flex items-center justify-between">
                            <div className="section-label">Recent Tasks</div>
                            <a href={routes.project_show} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                View all
                            </a>
                        </div>
                        <div className="mt-4 space-y-2 text-sm">
                            {recentTasks.length === 0 ? (
                                <div className="text-sm text-slate-500">No tasks yet.</div>
                            ) : (
                                recentTasks.map((task) => (
                                    <a key={task.id} href={task.route} data-native="true" className="block rounded-xl border border-slate-200 bg-white px-3 py-2 hover:border-teal-300">
                                        <div className="font-semibold text-slate-900">{task.title}</div>
                                        <div className="text-xs text-slate-500">{task.status_label}</div>
                                    </a>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="card p-5">
                        <div className="flex items-center justify-between">
                            <div className="section-label">Recent Chat Messages</div>
                            <a href={routes.chat} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                Open chat
                            </a>
                        </div>
                        <div className="mt-4 space-y-2 text-sm">
                            {recentMessages.length === 0 ? (
                                <div className="text-sm text-slate-500">No chat messages yet.</div>
                            ) : (
                                recentMessages.map((message) => (
                                    <div key={message.id} className="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                        <div className="text-xs font-semibold text-slate-700">{message.author_name}</div>
                                        <div className="text-sm text-slate-900">{message.message}</div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
