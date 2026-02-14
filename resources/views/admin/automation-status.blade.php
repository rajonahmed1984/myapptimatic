@extends('layouts.admin')

@section('title', 'Automation Status')
@section('page-title', 'Automation Status')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="section-label">Automation</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Automation Status</h1>
            <p class="mt-2 text-sm text-slate-600">Live status for billing cron, queue health, and daily automation actions.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $statusLabel }}</span>
            <a href="{{ route('admin.settings.edit', ['tab' => 'cron']) }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600">
                Cron settings
            </a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Last Invocation</div>
            <div class="mt-2 text-xl font-semibold text-slate-900">{{ $lastInvocationText }}</div>
            <div class="text-xs text-slate-500">{{ $lastInvocationAt }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Last Completion</div>
            <div class="mt-2 text-xl font-semibold text-slate-900">{{ $lastCompletionText }}</div>
            <div class="text-xs text-slate-500">{{ $lastCompletionAt }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Next Daily Run</div>
            <div class="mt-2 text-xl font-semibold text-slate-900">{{ $nextDailyRunText }}</div>
            <div class="text-xs text-slate-500">{{ $nextDailyRunAt }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Last Status</div>
            <div class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $cronStatusClasses }}">{{ $cronStatusLabel }}</div>
            <div class="mt-2 text-xs text-slate-500">Portal time: <span id="portal-time" data-timezone="{{ $portalTimeZone ?? config('app.timezone', 'UTC') }}">{{ $portalTimeLabel ?? '' }}</span></div>
        </div>
    </div>

    @if($lastStatus === 'failed' && $lastError)
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <div class="font-semibold">Last Error</div>
            <div class="mt-1">{{ $lastError }}</div>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <div class="section-label">Cron Health</div>
            <div class="mt-4 grid gap-3">
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Cron Setup</div>
                        <div class="text-xs text-slate-500">Token configured</div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $cronSetup ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $cronSetup ? 'Ok' : 'Error' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Invocation Window</div>
                        <div class="text-xs text-slate-500">Within {{ $cronInvocationWindowHours ?? 24 }} hours</div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $cronInvoked ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $cronInvoked ? 'Ok' : 'Warning' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Daily Run</div>
                        <div class="text-xs text-slate-500">Within {{ $dailyCronWindowHours ?? 24 }} hours</div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $dailyCronRun ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $dailyCronRun ? 'Ok' : 'Warning' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Completion State</div>
                        <div class="text-xs text-slate-500">Last cron run completion</div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $dailyCronCompleting ? 'bg-emerald-100 text-emerald-700' : (($lastStatus === 'failed') ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600') }}">{{ $dailyCronCompleting ? 'Ok' : (($lastStatus === 'failed') ? 'Error' : 'Pending') }}</span>
                </div>
            </div>

            <div class="mt-5">
                <label class="text-sm text-slate-600">Secure cron URL</label>
                <input value="{{ $cronUrl ?? '' }}" readonly class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700" />
            </div>
        </div>

        <div class="card p-6">
            <div class="section-label">AI Queue</div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">AI Enabled</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ $aiHealth['enabled'] ? 'Yes' : 'No' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Risk Module</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ $aiHealth['risk_enabled'] ? 'On' : 'Off' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Pending Jobs</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ $aiHealth['queue_pending'] }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Failed Jobs</div>
                    <div class="mt-1 font-semibold {{ $aiHealth['queue_failed'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $aiHealth['queue_failed'] }}</div>
                </div>
            </div>
            <div class="mt-4 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $aiHealth['status_classes'] }}">{{ $aiHealth['status_label'] }}</div>
        </div>
    </div>

    <div class="mt-6 card p-6">
        <div class="section-label">Daily Actions (Last Run)</div>
        <div class="mt-1 text-sm text-slate-500">Only enabled modules are shown.</div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @php($enabledActions = collect($dailyActions ?? [])->where('enabled', true)->values())
            @forelse($enabledActions as $action)
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-sm font-semibold text-slate-900">{{ $action['label'] }}</div>
                    <div class="mt-3 space-y-2">
                        @foreach($action['stats'] as $stat)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">{{ $stat['label'] }}</span>
                                <span class="font-semibold text-slate-900">{{ $stat['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500">No enabled automation actions found.</div>
            @endforelse
        </div>
    </div>

    <script>
        const portalTimeEl = document.getElementById('portal-time');
        if (portalTimeEl) {
            const tz = portalTimeEl.dataset.timezone || '{{ config('app.timezone', 'UTC') }}';
            const formatter = new Intl.DateTimeFormat(undefined, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: tz,
            });

            const updateTime = () => {
                portalTimeEl.textContent = formatter.format(new Date());
            };

            updateTime();
            setInterval(updateTime, 1000);
        }
    </script>
@endsection
