@extends('layouts.admin')

@section('title', $employee->name)
@section('page-title', $employee->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Employee</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $employee->name }}</div>
            <div class="text-sm text-slate-500">{{ $employee->email }}</div>
        </div>
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('admin.hr.employees.impersonate', $employee) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-800">
                    Login as Employee
                </button>
            </form>
            <a href="{{ route('admin.hr.employees.edit', $employee) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <a href="{{ route('admin.hr.employees.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-3 text-sm font-semibold text-slate-700">
        @php $tabs = ['summary' => 'Summary', 'profile' => 'Profile', 'compensation' => 'Compensation', 'projects' => 'Projects', 'timesheets' => 'Timesheets', 'leave' => 'Leave', 'payroll' => 'Payroll']; @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => $key]) }}"
               class="rounded-full border px-3 py-1 {{ $tab === $key ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-700 hover:border-teal-300 hover:text-teal-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($tab === 'summary')
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Status</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ ucfirst($employee->status) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Salary Type</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ ucwords(str_replace('_', ' ', $summary['salary_type'] ?? '--')) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Basic Pay</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $summary['currency'] ?? '' }} {{ number_format($summary['basic_pay'] ?? 0, 2) }}</div>
            </div>
        </div>
    @elseif($tab === 'profile')
        <div class="card p-6">
            <div class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                <div><span class="font-semibold text-slate-900">Department:</span> {{ $employee->department ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Designation:</span> {{ $employee->designation ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Manager:</span> {{ $employee->manager?->name ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Employment Type:</span> {{ ucfirst($employee->employment_type) }}</div>
                <div><span class="font-semibold text-slate-900">Work Mode:</span> {{ ucfirst(str_replace('_',' ',$employee->work_mode)) }}</div>
                <div><span class="font-semibold text-slate-900">Join Date:</span> {{ $employee->join_date?->format('Y-m-d') ?? '--' }}</div>
                <div class="md:col-span-2"><span class="font-semibold text-slate-900">Address:</span> {{ $employee->address ?? '--' }}</div>
                <div class="md:col-span-2"><span class="font-semibold text-slate-900">Linked User:</span> {{ $employee->user?->name ? $employee->user->name.' ('.$employee->user->email.')' : '--' }}</div>
            </div>
        </div>

        <div class="mt-4 card p-6">
            <div class="text-sm font-semibold text-slate-800 mb-3">Documents</div>
            <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Avatar</div>
                    <div class="mt-2">
                        @php
                            $avatarPath = $employee->photo_path ?: $employee->user?->avatar_path;
                        @endphp
                        <x-avatar :path="$avatarPath" :name="$employee->name" size="h-16 w-16" textSize="text-sm" />
                    </div>
                </div>
                @if($employee->nid_path)
                    @php
                        $nidIsImage = \Illuminate\Support\Str::endsWith(strtolower($employee->nid_path), ['.jpg', '.jpeg', '.png', '.webp']);
                        $nidUrl = route('admin.user-documents.show', ['type' => 'employee', 'id' => $employee->id, 'doc' => 'nid']);
                    @endphp
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">NID</div>
                        <div class="mt-2 flex items-center gap-3">
                            @if($nidIsImage)
                                <img src="{{ $nidUrl }}" alt="NID" class="h-16 w-20 rounded-lg object-cover border border-slate-200">
                            @else
                                <div class="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">PDF</div>
                            @endif
                            <a href="{{ $nidUrl }}" class="text-sm text-teal-600 hover:text-teal-500">View/Download</a>
                        </div>
                    </div>
                @endif
                @if($employee->cv_path)
                    @php
                        $cvUrl = route('admin.user-documents.show', ['type' => 'employee', 'id' => $employee->id, 'doc' => 'cv']);
                    @endphp
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">CV</div>
                        <div class="mt-2 flex items-center gap-3">
                            <div class="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">PDF</div>
                            <a href="{{ $cvUrl }}" class="text-sm text-teal-600 hover:text-teal-500">View/Download</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @elseif($tab === 'compensation')
        <div class="card p-6">
            <div class="text-sm text-slate-700">
                <div class="font-semibold text-slate-900 mb-2">Current Compensation</div>
                <div>Salary Type: {{ ucwords(str_replace('_', ' ', $summary['salary_type'] ?? '--')) }}</div>
                <div>Basic Pay: {{ $summary['currency'] ?? '' }} {{ number_format($summary['basic_pay'] ?? 0, 2) }}</div>
                <div>Effective From: {{ $employee->activeCompensation?->effective_from?->format('Y-m-d') ?? '--' }}</div>
            </div>
        </div>
    @elseif($tab === 'projects')
        @php
            $projectStatusLabels = [
                'ongoing' => 'Ongoing',
                'hold' => 'On hold',
                'complete' => 'Completed',
                'cancel' => 'Cancelled',
            ];
            $taskStatusOrder = ['pending', 'in_progress', 'blocked', 'completed'];
        @endphp
        <div class="grid gap-4 md:grid-cols-4">
            <div class="card p-4 md:col-span-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Assigned Projects</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $projects->count() }}</div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                    @foreach($projectStatusLabels as $status => $label)
                        <span class="rounded-full border border-slate-200 bg-white px-2 py-1">
                            {{ $label }}: {{ $projectStatusCounts[$status] ?? 0 }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="mt-4 card p-6">
            <div class="text-sm font-semibold text-slate-800 mb-3">Projects</div>
            @if($projects->isEmpty())
                <div class="text-sm text-slate-500">No projects assigned to this employee.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Project</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Customer</th>
                            <th class="px-3 py-2">Assigned Tasks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($projects as $project)
                            @php
                                $taskCounts = $projectTaskStatusCounts->get($project->id, collect());
                                $taskTotal = $taskCounts->sum();
                                $extraTaskCounts = $taskCounts->except($taskStatusOrder);
                            @endphp
                            <tr class="border-t border-slate-100 align-top">
                                <td class="px-3 py-2">
                                    <div class="font-semibold text-slate-900">
                                        <a class="text-teal-700 hover:text-teal-600" href="{{ route('admin.projects.show', $project) }}">
                                            {{ $project->name }}
                                        </a>
                                    </div>
                                    <div class="text-xs text-slate-500">Project ID: {{ $project->id }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700 bg-slate-50">
                                        {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-sm text-slate-700">
                                    {{ $project->customer?->name ?? '--' }}
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600">
                                    <div class="font-semibold text-slate-700">Assigned tasks: {{ $taskTotal }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($taskStatusOrder as $status)
                                            <span class="rounded-full border border-slate-200 bg-white px-2 py-1">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $taskCounts[$status] ?? 0 }}
                                            </span>
                                        @endforeach
                                        @foreach($extraTaskCounts as $status => $count)
                                            <span class="rounded-full border border-slate-200 bg-white px-2 py-1">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        <div class="card p-6 text-sm text-slate-600">
            No data available for this tab yet.
        </div>
    @endif
@endsection
