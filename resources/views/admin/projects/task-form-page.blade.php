@php
    $isEdit = isset($task) && $task;
@endphp

@extends('layouts.admin')

@section('title', $isEdit ? 'Edit Task' : 'Add Task')
@section('page-title', $isEdit ? 'Edit Task' : 'Add Task')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Project Tasks</div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $isEdit ? 'Edit task' : 'Add task' }}</h1>
        </div>
        <a href="{{ route('admin.projects.tasks.index', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">
            Back
        </a>
    </div>

    <div class="card p-6">
        @include('admin.projects.partials.task-form', [
            'project' => $project,
            'task' => $task ?? null,
            'taskTypeOptions' => $taskTypeOptions,
            'priorityOptions' => $priorityOptions,
            'employees' => $employees,
            'salesReps' => $salesReps,
            'statusOptions' => $statusOptions,
            'ajaxForm' => false,
            'returnToUrl' => route('admin.projects.tasks.index', $project),
        ])
    </div>
@endsection
