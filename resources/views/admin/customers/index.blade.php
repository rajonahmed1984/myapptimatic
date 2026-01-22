@extends('layouts.admin')

@section('title', 'Customers')
@section('page-title', 'Customers')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Customers</h1>
        <a href="{{ route('admin.customers.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Customer</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Company</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Services</th>
                        <th class="px-4 py-3">Projects & Maintenance</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Login status</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 text-slate-500"><a href="{{ route('admin.customers.show', $customer) }}" class="hover:text-teal-600">{{ $customer->id }}</a></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="flex items-center gap-3 hover:text-teal-600">
                                    <x-avatar :path="$customer->avatar_path" :name="$customer->name" size="h-8 w-8" textSize="text-xs" />
                                    <div class="font-medium text-slate-900">{{ $customer->name }}</div>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $customer->company_name ?: '--' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $customer->email }}</td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ $customer->active_subscriptions_count }} ({{ $customer->subscriptions_count }})
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                <a href="{{ route('admin.projects.index') }}?customer_id={{ $customer->id }}" class="hover:text-teal-600">
                                    {{ $customer->projects_count ?? 0 }}
                                </a>
                                <span class="text-slate-400">/</span>
                                <span>{{ $customer->project_maintenances_count ?? 0 }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $customer->created_at?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $hasActiveService = ($customer->active_subscriptions_count ?? 0) > 0;
                                    $hasActiveProject = ($customer->active_projects_count ?? 0) > 0;
                                    $effectiveStatus = ($hasActiveService || $hasActiveProject) ? 'active' : $customer->status;
                                @endphp
                                <x-status-badge :status="$effectiveStatus" />
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $loginStatus = $loginStatuses[$customer->id] ?? 'logout';
                                    $loginLabel = match ($loginStatus) {
                                        'login' => 'Login',
                                        'idle' => 'Idle',
                                        default => 'Logout',
                                    };
                                    $loginClasses = match ($loginStatus) {
                                        'login' => 'border-emerald-200 text-emerald-700 bg-emerald-50',
                                        'idle' => 'border-amber-200 text-amber-700 bg-amber-50',
                                        default => 'border-rose-200 text-rose-700 bg-rose-50',
                                    };
                                @endphp
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $loginClasses }}">
                                    {{ $loginLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">                                
                                <div class="flex items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer? This will remove related subscriptions and invoices.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                                <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-6 text-center text-slate-500">No customers yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
@endsection
