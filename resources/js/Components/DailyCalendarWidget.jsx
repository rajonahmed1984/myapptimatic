import React, { useState, useEffect } from 'react';
import DatePickerField from '@/Components/DatePickerField';

// Premium styled dynamic Daily Calendar Task Manager Widget
export default function DailyCalendarWidget({ apiBase = '/support/portal/api/tasks', enableClientSelect = false, className = '' }) {

    const [selectedDate, setSelectedDate] = useState(() => {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    });

    const [weekOffset, setWeekOffset] = useState(0);
    const [tasks, setTasks] = useState([]);
    const [clients, setClients] = useState([]);
    const [newTaskTitle, setNewTaskTitle] = useState('');
    const [newTaskDate, setNewTaskDate] = useState(selectedDate);
    const [newTaskClientId, setNewTaskClientId] = useState('');
    const [clientSearch, setClientSearch] = useState('');
    const [clientDropdownOpen, setClientDropdownOpen] = useState(false);
    const [mentionQuery, setMentionQuery] = useState('');
    const [mentionOpen, setMentionOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const normalizeApiError = (fallbackMessage, response, payload = null) => {
        if (response?.status === 401 || response?.status === 403) {
            return null;
        }

        const message = payload?.message || payload?.error || fallbackMessage;
        if (typeof message !== 'string') {
            return fallbackMessage;
        }

        return /authentication required|unauthenticated/i.test(message) ? null : message;
    };

    const readJsonSafely = async (response) => {
        try {
            return await response.json();
        } catch (err) {
            return null;
        }
    };

    // Calculate dates of the current visible week based onselectedDate + weekOffset
    const getVisibleWeek = () => {
        const baseDate = new Date(selectedDate);
        // adjust by weekOffset * 7 days
        baseDate.setDate(baseDate.getDate() + (weekOffset * 7));

        // Find Monday of that baseDate's week
        const dayOfWeek = baseDate.getDay();
        const diffToMon = baseDate.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
        const monday = new Date(baseDate.setDate(diffToMon));

        const week = [];
        for (let i = 0; i < 7; i++) {
            const day = new Date(monday);
            day.setDate(monday.getDate() + i);
            const yyyy = day.getFullYear();
            const mm = String(day.getMonth() + 1).padStart(2, '0');
            const dd = String(day.getDate()).padStart(2, '0');
            const formatted = `${yyyy}-${mm}-${dd}`;

            week.push({
                dateString: formatted,
                dayName: day.toLocaleDateString('en-US', { weekday: 'short' }),
                dayOfMonth: day.getDate(),
                label: day.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
            });
        }
        return week;
    };

    const visibleWeek = getVisibleWeek();

    // Fetch tasks for the visible week range
    const fetchTasks = async () => {
        if (visibleWeek.length === 0) return;
        setLoading(true);
        setError(null);
        const start = visibleWeek[0].dateString;
        const end = visibleWeek[6].dateString;
        try {
            const query = new URLSearchParams({
                start_date: start,
                end_date: end,
            });
            if (enableClientSelect) {
                query.set('include_clients', '1');
            }

            const response = await fetch(`${apiBase}?${query.toString()}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (response.ok) {
                const data = await response.json();
                if (Array.isArray(data)) {
                    setTasks(data);
                    return;
                }

                setTasks(Array.isArray(data?.tasks) ? data.tasks : []);
                if (enableClientSelect) {
                    setClients(Array.isArray(data?.clients) ? data.clients : []);
                }
            } else {
                const payload = await readJsonSafely(response);
                setTasks([]);
                if (enableClientSelect) {
                    setClients([]);
                }
                setError(normalizeApiError('Failed to load tasks', response, payload));
            }
        } catch (err) {
            setError('Could not connect to task service');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchTasks();
    }, [weekOffset, selectedDate]);

    useEffect(() => {
        setNewTaskDate(selectedDate);
    }, [selectedDate]);

    const mentionCandidates = enableClientSelect
        ? clients
            .filter((client) => {
                if (!mentionQuery.trim()) {
                    return true;
                }

                return String(client?.name || '').toLowerCase().includes(mentionQuery.trim().toLowerCase());
            })
            .slice(0, 8)
        : [];

    const filteredClients = enableClientSelect
        ? clients
            .filter((client) => String(client?.name || '').toLowerCase().includes(clientSearch.trim().toLowerCase()))
            .slice(0, 12)
        : [];

    const selectedClient = clients.find((client) => String(client.id) === String(newTaskClientId)) || null;

    const handleTaskTitleChange = (value) => {
        setNewTaskTitle(value);

        if (!enableClientSelect) {
            return;
        }

        const atIndex = value.lastIndexOf('@');
        if (atIndex === -1) {
            setMentionOpen(false);
            setMentionQuery('');
            return;
        }

        const tail = value.slice(atIndex + 1);
        if (tail.includes(' ') || tail.includes('\n')) {
            setMentionOpen(false);
            setMentionQuery('');
            return;
        }

        setMentionQuery(tail);
        setMentionOpen(true);
    };

    const applyClientMention = (client) => {
        const atIndex = newTaskTitle.lastIndexOf('@');
        if (atIndex === -1) {
            return;
        }

        const prefix = newTaskTitle.slice(0, atIndex);
        const nextTitle = `${prefix}@${client.name} `;

        setNewTaskTitle(nextTitle);
        setNewTaskClientId(String(client.id));
        setClientSearch(client.name);
        setMentionOpen(false);
        setMentionQuery('');
    };

    const applyClientSelection = (client) => {
        setNewTaskClientId(String(client.id));
        setClientSearch(client.name);
        setClientDropdownOpen(false);
    };

    // Handle adding a task for the currently selected date
    const handleAddTask = async (e) => {
        e.preventDefault();
        const title = newTaskTitle.trim();
        if (!title) return;

        setLoading(true);
        try {
            const response = await fetch(apiBase, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    title,
                    due_date: newTaskDate,
                    customer_id: newTaskClientId ? Number(newTaskClientId) : null,
                })
            });

            if (response.ok) {
                setNewTaskTitle('');
                setNewTaskDate(selectedDate);
                setNewTaskClientId('');
                setClientSearch('');
                setClientDropdownOpen(false);
                setMentionOpen(false);
                setMentionQuery('');
                fetchTasks();
            } else {
                const payload = await readJsonSafely(response);
                setError(normalizeApiError('Failed to add task', response, payload));
            }
        } catch (err) {
            setError('Error saving task');
        } finally {
            setLoading(false);
        }
    };

    // Toggle task completion
    const handleToggleTask = async (task) => {
        try {
            const response = await fetch(`${apiBase}/${task.id}`, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    is_completed: !task.is_completed
                })
            });

            if (response.ok) {
                // update local state instantly for extreme responsiveness
                setTasks(prev => prev.map(t => t.id === task.id ? { ...t, is_completed: !t.is_completed } : t));
            } else {
                const payload = await readJsonSafely(response);
                setError(normalizeApiError('Failed to update task', response, payload));
            }
        } catch (err) {
            setError('Error updating task');
        }
    };

    // Delete task
    const handleDeleteTask = async (taskId) => {
        try {
            const response = await fetch(`${apiBase}/${taskId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });

            if (response.ok) {
                setTasks(prev => prev.filter(t => t.id !== taskId));
            } else {
                const payload = await readJsonSafely(response);
                setError(normalizeApiError('Failed to delete task', response, payload));
            }
        } catch (err) {
            setError('Error deleting task');
        }
    };

    // Filter tasks for the currently selected day
    const tasksForSelectedDay = tasks.filter(t => {
        // Handle ISO string or simple YYYY-MM-DD
        const taskDate = typeof t.due_date === 'string' ? t.due_date.substring(0, 10) : '';
        return taskDate === selectedDate;
    });

    const activeTasksCount = tasksForSelectedDay.filter(t => !t.is_completed).length;

    // Helper to see if a date string has any tasks
    const hasTasks = (dateStr) => {
        return tasks.some(t => {
            const taskDate = typeof t.due_date === 'string' ? t.due_date.substring(0, 10) : '';
            return taskDate === dateStr;
        });
    };

    return (
        <div className={`card flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 lg:p-6 ${className}`}>
            {/* Header section */}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-4">
                <div>
                    <div className="text-xs font-bold uppercase tracking-[0.2em] text-teal-600">Daily Calendar</div>
                    <h2 className="mt-0.5 text-lg font-bold text-slate-800 sm:text-xl">My Tasks</h2>
                    <p className="mt-1 text-xs text-slate-400">
                        {activeTasksCount > 0 ? `${activeTasksCount} tasks remaining for selected day` : 'No active tasks remaining'}
                    </p>
                </div>
                {/* Navigation week chevrons */}
                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        onClick={() => setWeekOffset(prev => prev - 1)}
                        className="p-2 rounded-full border border-slate-200 hover:bg-slate-50 hover:text-teal-600 transition"
                        title="Previous Week"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setWeekOffset(0);
                            const today = new Date();
                            const yyyy = today.getFullYear();
                            const mm = String(today.getMonth() + 1).padStart(2, '0');
                            const dd = String(today.getDate()).padStart(2, '0');
                            setSelectedDate(`${yyyy}-${mm}-${dd}`);
                        }}
                        className="px-3 py-1 rounded-full border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        onClick={() => setWeekOffset(prev => prev + 1)}
                        className="p-2 rounded-full border border-slate-200 hover:bg-slate-50 hover:text-teal-600 transition"
                        title="Next Week"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            {/* Date strip */}
            <div className="mb-6 grid grid-cols-7 gap-1 sm:gap-2">
                {visibleWeek.map((day) => {
                    const isSelected = day.dateString === selectedDate;
                    const dayHasTasks = hasTasks(day.dateString);
                    return (
                        <button
                            key={day.dateString}
                            type="button"
                            onClick={() => {
                                setSelectedDate(day.dateString);
                            }}
                            className={`flex flex-col items-center rounded-xl py-2 transition sm:py-2.5 ${
                                isSelected
                                    ? 'bg-teal-600 text-white shadow-md shadow-teal-100 font-bold scale-105'
                                    : 'bg-slate-50 hover:bg-slate-100 text-slate-700'
                            }`}
                        >
                            <span className={`text-[10px] uppercase tracking-wider ${isSelected ? 'text-teal-100' : 'text-slate-400'}`}>
                                {day.dayName}
                            </span>
                            <span className="mt-0.5 text-base font-bold leading-none sm:text-lg">
                                {day.dayOfMonth}
                            </span>
                            {dayHasTasks && (
                                <span className={`w-1.5 h-1.5 rounded-full mt-1.5 ${isSelected ? 'bg-white' : 'bg-teal-500'}`} />
                            )}
                        </button>
                    );
                })}
            </div>

            {/* Error Display */}
            {error && (
                <div className="mb-4 rounded-xl bg-rose-50 border border-rose-100 p-3 text-xs text-rose-700 flex items-center justify-between">
                    <span>{error}</span>
                    <button type="button" onClick={() => setError(null)} className="font-bold text-rose-800 hover:underline">Dismiss</button>
                </div>
            )}

            {/* Tasks list */}
            <div className="max-h-[220px] space-y-2.5 overflow-y-auto pr-1 md:max-h-[300px]">
                {tasksForSelectedDay.length === 0 ? (
                    <div className="text-center py-8 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                        <svg className="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <p className="text-xs text-slate-500 font-medium">All clear for this day!</p>
                    </div>
                ) : (
                    tasksForSelectedDay.map((task) => {
                        const taskClientLabel = task?.customer
                            ? [task.customer?.name, task.customer?.company_name].filter(Boolean).join(' - ')
                            : '';

                        return (
                            <div
                                key={task.id}
                                className="flex items-center justify-between px-3 py-2.5 bg-slate-50/60 rounded-xl border border-slate-100 hover:border-slate-200 transition group"
                            >
                            <label className="flex items-center gap-3 cursor-pointer select-none min-w-0 flex-1">
                                <input
                                    type="checkbox"
                                    checked={task.is_completed}
                                    onChange={() => handleToggleTask(task)}
                                    className="w-4 h-4 text-teal-600 border-slate-300 rounded focus:ring-teal-500 focus:ring-offset-0 cursor-pointer"
                                />
                                <span className={`text-sm truncate font-medium text-slate-700 transition ${
                                    task.is_completed ? 'line-through text-slate-400 font-normal' : ''
                                }`}>
                                    {task.title}
                                </span>
                            </label>
                            {taskClientLabel ? (
                                <span className="mr-2 max-w-[180px] truncate text-xs font-semibold text-rose-700" title={taskClientLabel}>
                                    @{taskClientLabel}
                                </span>
                            ) : null}
                            <button
                                type="button"
                                onClick={() => handleDeleteTask(task.id)}
                                className="p-1 text-slate-400 hover:text-rose-600 rounded-full hover:bg-slate-100 transition opacity-0 group-hover:opacity-100 focus:opacity-100"
                                title="Delete task"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            </div>
                        );
                    })
                )}
            </div>

            {/* Inline Add Task Form */}
            <form onSubmit={handleAddTask} className="mt-4 space-y-2.5 sm:mt-5">
                <div className="relative">
                    <input
                        type="text"
                        value={newTaskTitle}
                        onChange={(e) => handleTaskTitleChange(e.target.value)}
                        placeholder="Create a task for this day... (type @ to mention client)"
                        className="flex-1 w-full rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:bg-white focus:outline-none focus:ring-0 transition"
                        maxLength={255}
                        autoComplete="off"
                    />

                    {enableClientSelect && mentionOpen && mentionCandidates.length > 0 ? (
                        <div className="absolute left-0 right-0 top-[calc(100%+6px)] z-20 max-h-48 overflow-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                            {mentionCandidates.map((client) => (
                                <button
                                    key={client.id}
                                    type="button"
                                    onMouseDown={(event) => {
                                        event.preventDefault();
                                        applyClientMention(client);
                                    }}
                                    className="block w-full rounded-lg px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                                >
                                    @{client.name}
                                </button>
                            ))}
                        </div>
                    ) : null}
                </div>

                <div className="grid gap-2 sm:grid-cols-2">
                    <label className="block">
                        <span className="mb-1 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Task Date</span>
                        <DatePickerField
                            name="task-date"
                            value={newTaskDate}
                            onChange={(nextValue) => setNewTaskDate(nextValue || '')}
                            required
                            hideLabel
                            placeholder=""
                            submitFormat="iso"
                            inputClassName="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:border-teal-500 focus:bg-white focus:outline-none focus:ring-0 transition whitespace-nowrap"
                            containerClassName="space-y-0"
                        />
                    </label>

                    {enableClientSelect ? (
                        <label className="relative block">
                            <span className="mb-1 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client</span>
                            <input
                                type="text"
                                value={clientSearch || (selectedClient?.name || '')}
                                onChange={(e) => {
                                    setClientSearch(e.target.value);
                                    setClientDropdownOpen(true);
                                    if (!e.target.value.trim()) {
                                        setNewTaskClientId('');
                                    }
                                }}
                                onFocus={() => setClientDropdownOpen(true)}
                                placeholder="Search client..."
                                className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 pr-8 text-xs text-slate-800 focus:border-teal-500 focus:bg-white focus:outline-none focus:ring-0 transition"
                                autoComplete="off"
                            >
                            </input>
                            <button
                                type="button"
                                onClick={() => {
                                    setClientDropdownOpen((previous) => !previous);
                                    if (!clientSearch && selectedClient?.name) {
                                        setClientSearch(selectedClient.name);
                                    }
                                }}
                                className="absolute right-2 top-[30px] text-slate-400 hover:text-slate-600"
                                aria-label="Toggle client search"
                            >
                                <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 011.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clipRule="evenodd" />
                                </svg>
                            </button>

                            {clientDropdownOpen ? (
                                <div className="absolute left-0 right-0 top-[calc(100%+6px)] z-20 max-h-48 overflow-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                                    <button
                                        type="button"
                                        onMouseDown={(event) => {
                                            event.preventDefault();
                                            setNewTaskClientId('');
                                            setClientSearch('');
                                            setClientDropdownOpen(false);
                                        }}
                                        className="block w-full rounded-lg px-3 py-2 text-left text-xs text-slate-500 hover:bg-slate-50"
                                    >
                                        No client selected
                                    </button>
                                    {filteredClients.map((client) => (
                                        <button
                                            key={client.id}
                                            type="button"
                                            onMouseDown={(event) => {
                                                event.preventDefault();
                                                applyClientSelection(client);
                                            }}
                                            className="block w-full rounded-lg px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                                        >
                                            {client.name}
                                        </button>
                                    ))}
                                    {filteredClients.length === 0 ? (
                                        <div className="px-3 py-2 text-xs text-slate-400">No matching client found.</div>
                                    ) : null}
                                </div>
                            ) : null}
                            <span className="mt-1 block text-[11px] text-slate-400">Tip: টাইপ করুন @ তারপর client name লিখুন, quick mention select হবে.</span>
                        </label>
                    ) : null}
                </div>

                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={loading || !newTaskTitle.trim() || !newTaskDate}
                        className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800 focus:outline-none transition disabled:opacity-50"
                    >
                        Add
                    </button>
                </div>
            </form>
        </div>
    );
}
