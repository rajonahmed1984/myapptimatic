@extends('layouts.admin')

@section('title', 'CarrotHost Income')
@section('page-title', 'CarrotHost Income')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Income Sync</div>
            <div class="text-2xl font-semibold text-slate-900">CarrotHost</div>
            <div class="mt-1 text-sm text-slate-500">Transactions for {{ $monthLabel }} ({{ $startDate }} to {{ $endDate }}).</div>
        </div>
        <div class="text-right text-xs text-slate-500">
            <div>Last refreshed: {{ now()->format($globalDateFormat.' H:i') }}</div>
            <div class="mt-2 flex flex-wrap items-center justify-end gap-2 text-xs">
                <form id="carrothost-sync-form" method="POST" action="{{ route('admin.income.carrothost.sync') }}" class="inline">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button
                        type="submit"
                        id="carrothost-sync-btn"
                        class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 font-semibold text-emerald-700 transition hover:border-emerald-400 hover:bg-emerald-100"
                    >
                        Sync Data
                    </button>
                </form>
                @if($prevMonth)
                    <a href="{{ route('admin.income.carrothost', ['month' => $prevMonth]) }}" class="rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-emerald-200 hover:text-emerald-700">
                        &larr; {{ $prevMonthLabel }}
                    </a>
                @else
                    <span class="rounded-full border border-slate-100 px-3 py-1 text-slate-300">&larr; {{ $monthLabel }}</span>
                @endif
                @if($nextMonth)
                    <a href="{{ route('admin.income.carrothost', ['month' => $nextMonth]) }}" class="rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-emerald-200 hover:text-emerald-700">
                        {{ $nextMonthLabel }} &rarr;
                    </a>
                @else
                    <span class="rounded-full border border-slate-100 px-3 py-1 text-slate-300">{{ $monthLabel }} &rarr;</span>
                @endif
            </div>
        </div>
    </div>

    @if(!empty($whmcsErrors))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <div class="font-semibold text-amber-900">WHMCS warnings</div>
            <ul class="mt-2 list-disc pl-5">
                @foreach($whmcsErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-6">
        <div class="card p-6">
            <div class="text-sm font-semibold text-slate-700">Transactions</div>
            <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-emerald-700">
                    <div class="text-xs uppercase tracking-[0.2em] text-emerald-600">Amount In subtotal</div>
                    <div class="mt-1 text-lg font-semibold text-emerald-800">{{ $amountInSubtotalDisplay }}</div>
                </div>
                <div class="rounded-xl border border-rose-100 bg-rose-50 px-4 py-3 text-rose-700">
                    <div class="text-xs uppercase tracking-[0.2em] text-rose-600">Fees subtotal</div>
                    <div class="mt-1 text-lg font-semibold text-rose-800">{{ $feesSubtotalDisplay }}</div>
                </div>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-700">
                    <thead class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2">ID</th>
                            <th class="px-3 py-2">Client</th>
                            <th class="px-3 py-2 whitespace-nowrap">Date & Time</th>
                            <th class="px-3 py-2">Invoice</th>
                            <th class="px-3 py-2">Transaction ID</th>
                            <th class="px-3 py-2">Amount In</th>
                            <th class="px-3 py-2">Fees</th>
                            <th class="px-3 py-2">Gateway</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">{{ $row['userid'] ?? ($row['clientid'] ?? '--') }}</td>
                                <td class="px-3 py-2">{{ $row['clientname'] ?? '--' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['date'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['invoiceid'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['transid'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['amountin'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['fees'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['gateway'] ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-4 text-center text-slate-500">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const syncForm = document.getElementById('carrothost-sync-form');
            const syncButton = document.getElementById('carrothost-sync-btn');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            if (!syncForm || !syncButton || !window.fetch || !csrfToken) {
                return;
            }

            let isSyncing = false;
            let autoSyncTimer = null;

            const setSyncState = (syncing) => {
                isSyncing = syncing;
                syncButton.disabled = syncing;
                syncButton.classList.toggle('opacity-70', syncing);
                syncButton.classList.toggle('cursor-wait', syncing);
                syncButton.textContent = syncing ? 'Syncing...' : 'Sync Data';
            };

            const syncNow = async () => {
                if (isSyncing) {
                    return;
                }

                setSyncState(true);
                const formData = new FormData(syncForm);
                formData.set('_token', csrfToken);

                try {
                    const response = await fetch(syncForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    if (response.ok) {
                        window.location.reload();
                        return;
                    }
                } catch (error) {
                    // Silent fail; user can retry manually.
                }

                setSyncState(false);
            };

            syncForm.addEventListener('submit', (event) => {
                event.preventDefault();
                syncNow();
            });

            autoSyncTimer = setInterval(() => {
                if (document.visibilityState !== 'visible') {
                    return;
                }
                syncNow();
            }, 5 * 60 * 1000);

            window.addEventListener('beforeunload', () => {
                if (autoSyncTimer) {
                    clearInterval(autoSyncTimer);
                }
            });
        });
    </script>
@endpush
