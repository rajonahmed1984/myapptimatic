<div id="customersTable">
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Name & Company</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Services</th>
                        <th class="px-4 py-3">Projects & Maintenance</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Login status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 text-slate-500"><a href="{{ route('admin.customers.show', $customer) }}" class="hover:text-teal-600">{{ $customer->id }}</a></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium text-slate-900 hover:text-teal-600">
                                    {{ $customer->name }}
                                </a>
                                <div class="text-xs text-slate-500">{{ $customer->company_name ?: '--' }}</div>
                            </td>
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
                                    $hasActiveMaintenance = ($customer->active_project_maintenances_count ?? 0) > 0;
                                    $effectiveStatus = ($hasActiveService || $hasActiveProject || $hasActiveMaintenance)
                                        ? 'active'
                                        : $customer->status;
                                @endphp
                                <x-status-badge :status="$effectiveStatus" />
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $loginMeta = $loginStatuses[$customer->id] ?? ['status' => 'logout', 'last_login_at' => null];
                                    $loginStatus = is_array($loginMeta) ? ($loginMeta['status'] ?? 'logout') : $loginMeta;
                                    $lastLoginAt = is_array($loginMeta) ? ($loginMeta['last_login_at'] ?? null) : null;
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
                                <div class="mt-1 text-[11px] text-slate-400">
                                    Last login: {{ $lastLoginAt ? $lastLoginAt->format($globalDateFormat . ' H:i') : '--' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-slate-500">No customers yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</div>
