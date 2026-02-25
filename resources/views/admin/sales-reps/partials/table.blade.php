<div id="salesRepsTable">
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.2em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Services</th>
                        <th class="px-4 py-3 text-right">Projects & Maintenance</th>
                        <th class="px-4 py-3 text-left">Login</th>
                        <th class="px-4 py-3 text-right">Total earned</th>
                        <th class="px-4 py-3 text-right">Payable (Net)</th>
                        <th class="px-4 py-3 text-right">Paid (Incl. Advance)</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($reps as $rep)
                        @php
                            $repTotals = $totals[$rep->id] ?? null;
                            $loginMeta = $loginStatuses[$rep->id] ?? ['status' => 'logout', 'last_login_at' => null];
                            $loginStatus = is_array($loginMeta) ? ($loginMeta['status'] ?? 'logout') : $loginMeta;
                            $lastLoginAt = is_array($loginMeta) ? ($loginMeta['last_login_at'] ?? null) : null;
                            $loginLabel = match ($loginStatus) {
                                'login' => 'Login',
                                'idle' => 'Idle',
                                default => 'Logout',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ $rep->id }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div>
                                        <div class="font-semibold text-slate-900">
                                            <a href="{{ route('admin.sales-reps.show', $rep) }}" class="hover:text-teal-600">
                                                {{ $rep->name }}
                                            </a>
                                        </div>
                                        <div class="text-xs text-slate-500">{{ $rep->email ?? '--' }}</div>
                                        @if($rep->employee)
                                            <div class="text-xs text-emerald-600">Employee: {{ $rep->employee->name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-slate-700 text-sm">
                                    {{ $rep->active_subscriptions_count ?? 0 }} ({{ $rep->subscriptions_count ?? 0 }})
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="text-sm text-slate-700">Projects: {{ $rep->projects_count ?? 0 }}</div>
                                <div class="text-xs text-slate-500">Maintenance: {{ $rep->maintenances_count ?? 0 }}</div>
                            </td>
                            <td class="px-4 py-3">
                                {{-- <span @class([
                                    'rounded-full border px-2 py-0.5 text-xs font-semibold',
                                    'border-emerald-200 text-emerald-700 bg-emerald-50' => $loginStatus === 'login',
                                    'border-amber-200 text-amber-700 bg-amber-50' => $loginStatus === 'idle',
                                    'border-rose-200 text-rose-700 bg-rose-50' => $loginStatus === 'logout',
                                ])>
                                    {{ $loginLabel }}
                                </span> --}}
                                <div class="mt-1 text-[11px] text-slate-400">
                                    Last login: {{ $lastLoginAt ? $lastLoginAt->format($globalDateTimeFormat) : '--' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($repTotals->total_earned ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($repTotals->total_payable ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($repTotals->total_paid ?? 0, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $rep->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-300 text-slate-600 bg-slate-50' }}">
                                    {{ ucfirst($rep->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-slate-500">No sales representatives yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
