@extends('layouts.client')

@section('title', 'Chat')
@section('page-title', 'Chat')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="section-label">Chat</div>
                <div class="text-sm text-slate-500">Select a project to open chat.</div>
            </div>
            <a href="{{ route('client.projects.index') }}" class="text-xs font-semibold text-slate-500 hover:text-teal-600">
                Projects
            </a>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Unread</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($projects as $project)
                        @php $unread = (int) $unreadCounts->get((int) $project->id, 0); @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $project->name }}</div>
                                <div class="text-xs text-slate-500">#{{ $project->id }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $project->status ? ucfirst(str_replace('_', ' ', $project->status)) : '--' }}
                            </td>
                            <td class="px-4 py-3 text-slate-700 font-semibold">
                                <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold border-slate-300 bg-slate-50 text-slate-500">
                                   {{ $unread }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('client.projects.chat', $project) }}" class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300 whitespace-nowrap">
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
