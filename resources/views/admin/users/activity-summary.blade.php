@extends('layouts.admin')

@section('title', 'User Activity Summary')
@section('page-title', 'User Activity Summary')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Reporting</div>
            <div class="text-2xl font-semibold text-slate-900">User activity summary</div>
            <div class="text-sm text-slate-500">Track user sessions and activity across all user types</div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-6 p-6">
        <form method="GET" class="grid gap-4 md:grid-cols-5">
            <div>
                <label for="type" class="block text-sm font-medium text-slate-700">User Type</label>
                <select name="type" id="type" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    <option value="employee" @selected($filters['type'] === 'employee')>Employees</option>
                    <option value="customer" @selected($filters['type'] === 'customer')>Customers</option>
                    <option value="salesrep" @selected($filters['type'] === 'salesrep')>Sales Representatives</option>
                    <option value="admin" @selected($filters['type'] === 'admin')>Admin/Web Users</option>
                </select>
            </div>

            <div>
                <label for="user_id" class="block text-sm font-medium text-slate-700">Specific User (Optional)</label>
                <select name="user_id" id="user_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    <option value="">All {{ ucfirst($type) }}</option>
                    @foreach($userOptions as $id => $name)
                        <option value="{{ $id }}" @selected($filters['user_id'] == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="from" class="block text-sm font-medium text-slate-700">From Date (Optional)</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2 text-sm">
            </div>

            <div>
                <label for="to" class="block text-sm font-medium text-slate-700">To Date (Optional)</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2 text-sm">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-600">Filter</button>
                <a href="{{ route('admin.users.activity-summary') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Reset</a>
            </div>
        </form>
    </div>

    <!-- Activity Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">User</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Today</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">This Week</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">This Month</th>
                        @if($showRange)
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Range</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Last Seen</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Last Login</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($users as $data)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-2 w-2 rounded-full {{ $data['is_online'] ? 'bg-emerald-500' : 'bg-slate-300' }}" title="{{ $data['is_online'] ? 'Online' : 'Offline' }}"></div>
                                    <div>
                                        <div class="font-medium text-slate-900">{{ $data['user']->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $data['user']->email ?? '--' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                <div class="font-medium">{{ $data['today']['sessions_count'] }} sessions</div>
                                <div class="text-xs text-slate-500">{{ formatDuration($data['today']['active_seconds']) }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                <div class="font-medium">{{ $data['week']['sessions_count'] }} sessions</div>
                                <div class="text-xs text-slate-500">{{ formatDuration($data['week']['active_seconds']) }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                <div class="font-medium">{{ $data['month']['sessions_count'] }} sessions</div>
                                <div class="text-xs text-slate-500">{{ formatDuration($data['month']['active_seconds']) }}</div>
                            </td>
                            @if($showRange && $data['range'])
                                <td class="px-6 py-4 text-sm text-slate-700">
                                    <div class="font-medium">{{ $data['range']['sessions_count'] }} sessions</div>
                                    <div class="text-xs text-slate-500">{{ formatDuration($data['range']['active_seconds']) }}</div>
                                </td>
                            @endif
                            <td class="px-6 py-4 text-sm text-slate-700">
                                {{ $data['last_seen_at'] ? $data['last_seen_at']->diffForHumans() : '--' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                {{ $data['last_login_at'] ? $data['last_login_at']->format('M d, Y H:i') : '--' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500">
                                No users found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            // Format duration in seconds to HH:MM
            function formatDuration(seconds) {
                if (!seconds) return '0:00';
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                return hours + ':' + String(minutes).padStart(2, '0');
            }
        </script>
    @endpush
@endsection

@php
if (!function_exists('formatDuration')) {
    function formatDuration($seconds) {
        if (!$seconds) return '0:00';
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%d:%02d', $hours, $minutes);
    }
}
@endphp
