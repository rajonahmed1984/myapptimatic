@extends('layouts.admin')

@section('title', 'Chat')
@section('page-title', 'Chat')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="section-label">Chat</div>
                <div class="text-sm text-slate-500">Select a project to open chat.</div>
            </div>
            <a href="{{ route('admin.projects.index') }}" class="text-xs font-semibold text-slate-500 hover:text-teal-600">
                Projects
            </a>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Unread</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($projects as $project)
                        @php $unread = (int) ($unreadCounts[$project->id] ?? 0); @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $project->name }}</div>
                                <div class="text-xs text-slate-500">#{{ $project->id }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $project->status ? ucfirst(str_replace('_', ' ', $project->status)) : '--' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $unread > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-300 bg-slate-50 text-slate-500' }}">
                                    {{ $unread }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.projects.chat', $project) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                    Open Chat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">No projects available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($projects->hasPages())
            <div class="mt-4">
                {{ $projects->links() }}
            </div>
        @endif
    </div>
@endsection
