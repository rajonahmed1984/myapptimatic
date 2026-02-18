@extends('layouts.admin')

@section('title', 'Employee Dashboard')
@section('page-title', 'Employee Dashboard')

@section('content')
    @php
        $employeeName = $employee?->name ?? auth()->user()?->name ?? 'Employee';
        $completedProjects = ($projectStatusCounts['complete'] ?? 0)
            + ($projectStatusCounts['completed'] ?? 0)
            + ($projectStatusCounts['done'] ?? 0);
    @endphp

    <div class="space-y-6">
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="section-label">Welcome</div>
                    <div class="text-2xl font-semibold text-slate-900">{{ $employeeName }}</div>
                    <div class="text-sm text-slate-500">Access your work logs, leave requests, payroll, and project assignments from one place.</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-600">
                        Logout
                    </button>
                </form>
            </div>

            <div class="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 md:grid-cols-2">
                <div><span class="font-semibold text-slate-900">Employee ID:</span> {{ $employee?->id ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Email:</span> {{ $employee?->email ?? $employee?->user?->email ?? auth()->user()?->email ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Phone:</span> {{ $employee?->phone ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Status:</span> {{ $employee?->status ? ucfirst($employee->status) : '--' }}</div>
                <div><span class="font-semibold text-slate-900">Department:</span> {{ $employee?->department ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Designation:</span> {{ $employee?->designation ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Manager:</span> {{ $employee?->manager?->name ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Employment Type:</span> {{ $employee?->employment_type ? ucwords(str_replace('_', ' ', $employee->employment_type)) : '--' }}</div>
                <div><span class="font-semibold text-slate-900">Work Mode:</span> {{ $employee?->work_mode ? ucwords(str_replace('_', ' ', $employee->work_mode)) : '--' }}</div>
                <div><span class="font-semibold text-slate-900">Join Date:</span> {{ $employee?->join_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</div>
                <div class="md:col-span-2"><span class="font-semibold text-slate-900">Address:</span> {{ $employee?->address ?? '--' }}</div>
            </div>
        </div>
        @if($workSessionEligible)
            @php
                $requiredSeconds = (int) ($workSessionRequiredSeconds ?? 0);
                $requiredHoursLabel = $requiredSeconds > 0 ? (int) ($requiredSeconds / 3600) . 'h' : '--';
                $formatSeconds = function (int $seconds): string {
                    $hours = (int) floor($seconds / 3600);
                    $minutes = (int) floor(($seconds % 3600) / 60);
                    return sprintf('%02d:%02d', $hours, $minutes);
                };
            @endphp
            <div class="card p-6" id="work-session-card"
                 data-start-url="{{ route('employee.work-sessions.start') }}"
                 data-ping-url="{{ route('employee.work-sessions.ping') }}"
                 data-stop-url="{{ route('employee.work-sessions.stop') }}"
                 data-summary-url="{{ route('employee.work-summaries.today') }}"
                 data-required-seconds="{{ $requiredSeconds }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="section-label">Work Session</div>
                        <div class="text-sm text-slate-500">Track your remote hours. Idle 15+ minutes are not counted.</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span data-work-session-status class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Stopped</span>
                        <button type="button" data-work-session-start class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Start</button>
                        <button type="button" data-work-session-stop class="hidden rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-rose-200 hover:text-rose-600">Stop</button>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Worked Today</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900" data-work-session-worked>{{ $formatSeconds(0) }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Required</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900" data-work-session-required>{{ $requiredHoursLabel }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Salary Estimate</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900" data-work-session-salary>0.00</div>
                    </div>
                </div>

            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="section-label">Assigned projects</div>
                        <div class="text-sm text-slate-500">What you are currently delivering</div>
                    </div>
                    <span class="text-xl font-semibold text-slate-900">{{ $totalProjects ?? 0 }}</span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Ongoing</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $projectStatusCounts['ongoing'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">On hold</div>
                        <div class="mt-2 text-2xl font-semibold text-amber-600">{{ $projectStatusCounts['hold'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Completed</div>
                        <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $completedProjects }}</div>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="section-label">Assigned tasks</div>
                        <div class="text-sm text-slate-500">Tasks people expect you to work on</div>
                    </div>
                    <span class="text-xl font-semibold text-slate-900">{{ $taskStats['total'] ?? 0 }}</span>
                </div>
                <div class="mt-6 space-y-3 text-sm text-slate-600">
                    <div class="flex items-center justify-between">
                        <div>In progress</div>
                        <div class="font-semibold text-slate-900">{{ $taskStats['in_progress'] ?? 0 }}</div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>Completed</div>
                        <div class="font-semibold text-emerald-600">{{ $taskStats['completed'] ?? 0 }}</div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>Other statuses</div>
                        <div class="font-semibold text-slate-500">{{ max(0, ($taskStats['total'] ?? 0) - ($taskStats['in_progress'] ?? 0) - ($taskStats['completed'] ?? 0)) }}</div>
                    </div>
                </div>
                <div class="mt-4 text-xs text-slate-500">
                    <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 mr-2"></span>Completed
                    <span class="ml-4 inline-flex h-2 w-2 rounded-full bg-yellow-400 mr-2"></span>In progress
                </div>
            </div>
        </div>

        @if(!empty($showTasksWidget))
            @include('tasks.partials.dashboard-widget', [
                'taskSummary' => $taskSummary,
                'openTasks' => $openTasks,
                'inProgressTasks' => $inProgressTasks,
                'routePrefix' => 'employee',
                'usesStartRoute' => true,
            ])
        @endif

        @if($contractSummary)
            <div class="card p-6">
                <div class="section-label">Contract earnings</div>
                <div class="text-sm text-slate-500">Summary of your contract project payments.</div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Payable</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($contractSummary['payable'] ?? 0, 2) }}</div>
                        <div class="text-xs text-slate-500">Awaiting payout</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Total earned</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($contractSummary['total_earned'] ?? 0, 2) }}</div>
                        <div class="text-xs text-slate-500">All time</div>
                    </div>
                </div>

                <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-slate-800">Recent contract projects</div>
                        <a href="{{ route('employee.projects.index') }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View all</a>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse($contractProjects as $project)
                            <div class="rounded-xl border border-slate-200 bg-white/70 px-3 py-2">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold text-slate-900">
                                        <a href="{{ route('employee.projects.show', $project) }}" class="hover:text-teal-600">{{ $project->name }}</a>
                                    </div>
                                    <div class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
                                </div>
                                <div class="text-xs text-slate-600">Earned: {{ number_format((float) ($project->contract_employee_total_earned ?? 0), 2) }} {{ $project->currency }}</div>
                                <div class="text-xs text-slate-500">Payable: {{ number_format((float) ($project->contract_employee_payable ?? 0), 2) }} {{ $project->currency }}</div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">No contract projects yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <div class="card overflow-hidden rounded-2xl border border-slate-200">
            <div class="flex items-center justify-between px-6 py-4">
                <div>
                    <div class="section-label">Recent projects</div>
                    <div class="text-sm text-slate-500">Projects you are assigned to</div>
                </div>
                <a href="{{ route('employee.projects.index') }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View all projects</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Project</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Tasks</th>
                            <th class="px-4 py-3">Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentProjects as $project)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('employee.projects.show', $project) }}" class="font-semibold text-slate-900 hover:text-teal-600">{{ $project->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $project->tasks_count }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $project->due_date?->format($globalDateFormat) ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500">No assigned projects yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($workSessionEligible)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const card = document.getElementById('work-session-card');
                    if (!card) {
                        return;
                    }

                    const startUrl = card.dataset.startUrl;
                    const pingUrl = card.dataset.pingUrl;
                    const stopUrl = card.dataset.stopUrl;
                    const summaryUrl = card.dataset.summaryUrl;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    const workedEl = card.querySelector('[data-work-session-worked]');
                    const requiredEl = card.querySelector('[data-work-session-required]');
                    const salaryEl = card.querySelector('[data-work-session-salary]');
                    const statusEl = card.querySelector('[data-work-session-status]');
                    const startBtn = card.querySelector('[data-work-session-start]');
                    const stopBtn = card.querySelector('[data-work-session-stop]');

                    const statusStyles = {
                        working: { text: 'Working', classes: ['bg-emerald-100', 'text-emerald-700'] },
                        idle: { text: 'Idle', classes: ['bg-amber-100', 'text-amber-700'] },
                        stopped: { text: 'Stopped', classes: ['bg-slate-100', 'text-slate-600'] },
                    };

                    const allStatusClasses = Object.values(statusStyles).flatMap((item) => item.classes);
                    const formatSeconds = (seconds) => {
                        const total = Math.max(0, Number(seconds || 0));
                        const hours = Math.floor(total / 3600);
                        const minutes = Math.floor((total % 3600) / 60);
                        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
                    };

                    const updateStatus = (statusKey) => {
                        const status = statusStyles[statusKey] || statusStyles.stopped;
                        statusEl.textContent = status.text;
                        allStatusClasses.forEach((cls) => statusEl.classList.remove(cls));
                        status.classes.forEach((cls) => statusEl.classList.add(cls));
                    };

                    const updateButtons = (isActive) => {
                        startBtn.classList.toggle('hidden', Boolean(isActive));
                        stopBtn.classList.toggle('hidden', !isActive);
                    };

                    const updateUI = (data) => {
                        if (!data) {
                            return;
                        }
                        workedEl.textContent = formatSeconds(data.active_seconds || 0);
                        if (data.required_seconds) {
                            requiredEl.textContent = `${Math.round(data.required_seconds / 3600)}h`;
                        }
                        salaryEl.textContent = Number(data.salary_estimate || 0).toFixed(2);
                        updateStatus(data.status || 'stopped');
                        updateButtons(data.is_active);
                        window.dispatchEvent(new CustomEvent('employee-work-session:update', { detail: data }));
                    };

                    const postJson = async (url) => {
                        const formData = new FormData();
                        formData.append('_token', csrfToken);
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            window.notify(payload.message || 'Request failed.', 'error');
                            return null;
                        }
                        return payload.data || null;
                    };

                    const fetchSummary = async () => {
                        const response = await fetch(summaryUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            return null;
                        }
                        return payload.data || null;
                    };

                    let pingTimer = null;
                    const startPing = () => {
                        if (pingTimer) {
                            return;
                        }
                        pingTimer = setInterval(async () => {
                            const data = await postJson(pingUrl);
                            if (data) {
                                updateUI(data);
                            } else {
                                const summary = await fetchSummary();
                                if (summary) {
                                    updateUI(summary);
                                }
                                stopPing();
                            }
                        }, 90000);
                    };

                    const stopPing = () => {
                        if (pingTimer) {
                            clearInterval(pingTimer);
                            pingTimer = null;
                        }
                    };

                    startBtn?.addEventListener('click', async () => {
                        const data = await postJson(startUrl);
                        if (data) {
                            updateUI(data);
                            startPing();
                        }
                    });

                    stopBtn?.addEventListener('click', async () => {
                        const data = await postJson(stopUrl);
                        if (data) {
                            updateUI(data);
                            stopPing();
                        }
                    });

                    fetchSummary().then(async (data) => {
                        updateUI(data);
                        if (!data?.is_active) {
                            return;
                        }

                        const pingData = await postJson(pingUrl);
                        if (pingData) {
                            updateUI(pingData);
                        }

                        startPing();
                    });
                });
            </script>
        @endpush
    @endif
@endsection
