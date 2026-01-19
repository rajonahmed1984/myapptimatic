@extends('layouts.admin')

@section('title', 'Employee Dashboard')
@section('page-title', 'Employee Dashboard')

@section('content')
    @php
        $employee = auth()->user()?->employee;
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
                    <div class="text-sm text-slate-500">Access your timesheets, leave requests, payroll, and project assignments from one place.</div>
                </div>
                <form method="POST" action="{{ route('employee.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-600">
                        Logout
                    </button>
                </form>
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Timesheets</div>
                <div class="mt-2 text-slate-900 font-semibold">Submit your weekly hours.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Leave</div>
                <div class="mt-2 text-slate-900 font-semibold">Request time off and track approvals.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Payroll</div>
                <div class="mt-2 text-slate-900 font-semibold">View payroll history and payslip data.</div>
            </div>
        </div>

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
                            <th class="px-4 py-3">Customer</th>
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
                                <td class="px-4 py-3 text-slate-600">{{ $project->customer?->name ?? '--' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $project->tasks_count }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $project->due_date?->format($globalDateFormat) ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">No assigned projects yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
