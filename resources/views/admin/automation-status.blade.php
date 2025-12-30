@extends('layouts.admin')

@section('title', 'Automation Status')
@section('page-title', 'Automation Status')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="section-label">Automation Status</div>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Automation Status</h1>
                <p class="mt-2 text-sm text-slate-600">Monitor daily billing and support automation tasks.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $statusLabel }}</span>
                <a href="{{ route('admin.settings.edit', ['tab' => 'cron']) }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600">
                    Cron settings
                </a>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Last Cron Invocation</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $lastInvocationText }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $lastInvocationAt }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Last Run Completed</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $lastCompletionText }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $lastCompletionAt }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Next Daily Task Run</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $nextDailyRunText }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $nextDailyRunAt }}</div>
            </div>
        </div>

        @if($lastStatus === 'failed' && $lastError)
            <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <div class="font-semibold">Last Error</div>
                <div class="mt-1">{{ $lastError }}</div>
            </div>
        @endif
    </div>

    <div class="mt-8 card p-6">
        <div>
            <div class="section-label">Cron Status</div>
            <div class="mt-1 text-sm text-slate-500">Monitor scheduler health and configuration.</div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
            <h3 class="text-lg font-semibold text-slate-900">System Health Checks</h3>
            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Cron Setup</div>
                        <div class="text-xs text-slate-500">Cron token is configured</div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($cronSetup)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Ok
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                Error
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Cron Invocation Frequency</div>
                        <div class="text-xs text-slate-500">Cron executed within last 48 hours</div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($cronInvoked)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Ok
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                                <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Warning
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Daily Cron Run</div>
                        <div class="text-xs text-slate-500">Billing cron executed within last 36 hours</div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($dailyCronRun)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Ok
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                                <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Warning
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Daily Cron Completing</div>
                        <div class="text-xs text-slate-500">Last cron run completed successfully</div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($dailyCronCompleting)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Ok
                            </span>
                        @elseif($lastStatus === 'failed')
                            <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                Error
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                Pending
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <div>
                <label class="text-sm text-slate-600">Last billing run</label>
                <div class="mt-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700">
                    {{ $lastCompletionText }}
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ $lastCompletionAt }}</p>
            </div>
            <div>
                <label class="text-sm text-slate-600">Last status</label>
                <div class="mt-2 items-center rounded-full px-4 py-2 text-sm {{ $cronStatusClasses }}">
                    {{ $cronStatusLabel }}
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Secure cron URL (use in cPanel)</label>
                <input value="{{ $cronUrl ?? '' }}" readonly class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700" />
                <p class="mt-2 text-xs text-slate-500">Example cPanel command: <code>curl -fsS "{{ $cronUrl ?? '' }}" &gt;/dev/null 2&gt;&amp;1</code></p>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">CLI command (server cron)</label>
                <input value="php /path/to/artisan billing:run" readonly class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700" />
                <p class="mt-2 text-xs text-slate-500">Run from your project root if you have shell access.</p>
            </div>
        </div>
    </div>

    <div class="mt-8 card p-6">
        <div>
            <div class="section-label">Daily Actions</div>
            <div class="mt-1 text-sm text-slate-500">Results captured from the most recent automation run.</div>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($dailyActions as $action)
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="text-sm font-semibold text-slate-800">{{ $action['label'] }}</div>
                        @if(! $action['enabled'])
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                                {{ $action['disabled_label'] }}
                            </span>
                        @endif
                    </div>
                    <div class="mt-4 grid gap-3">
                        @if($action['enabled'])
                            @foreach($action['stats'] as $stat)
                                <div class="flex items-center justify-between">
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ $stat['label'] }}</div>
                                    <div class="text-lg font-semibold text-slate-900">{{ $stat['value'] }}</div>
                                </div>
                            @endforeach
                        @else
                            <div class="flex items-center justify-between">
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ $action['disabled_label'] }}</div>
                                <div class="text-lg font-semibold text-slate-400">-</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
