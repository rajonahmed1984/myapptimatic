@extends('layouts.admin')

@section('title', 'AI Business Status')
@section('page-title', 'AI Business Status')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-1">
            <div class="card p-5">
                <div class="section-label">Filters</div>
                <div class="mt-3 text-sm text-slate-500">Set the reporting window and generate an AI summary.</div>

                <form id="ai-business-status-form" class="mt-4 grid gap-4">
                    <div>
                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Start date</label>
                        <input type="date" name="start_date" value="{{ $filters['start_date'] }}"
                               class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">End date</label>
                        <input type="date" name="end_date" value="{{ $filters['end_date'] }}"
                               class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Projection days</label>
                        <input type="number" min="7" max="120" name="projection_days" value="{{ $filters['projection_days'] }}"
                               class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                        <div class="mt-1 text-xs text-slate-400">Future due window for income/expense projection.</div>
                    </div>

                    <button type="submit" class="btn btn-primary" @disabled(! $aiReady)>
                        Generate AI Summary
                    </button>

                    @if(! $aiReady)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                            GOOGLE_AI_API_KEY is missing. Add it to .env to enable AI summary.
                        </div>
                    @endif
                </form>
            </div>

            <div class="card mt-6 p-5">
                <div class="section-label">Snapshot</div>
                <div class="mt-4 grid gap-4">
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Tasks</div>
                        <div class="mt-2 text-sm text-slate-600">Total: {{ $metrics['tasks']['total'] ?? 0 }}</div>
                        <div class="mt-1 text-xs text-slate-500">Open {{ $metrics['tasks']['open'] ?? 0 }} · In progress {{ $metrics['tasks']['in_progress'] ?? 0 }} · Completed {{ $metrics['tasks']['completed'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Projects</div>
                        <div class="mt-2 text-sm text-slate-600">Total: {{ $metrics['projects']['total'] ?? 0 }}</div>
                        <div class="mt-1 text-xs text-slate-500">By status: {{ collect($metrics['projects']['by_status'] ?? [])->map(fn($count, $status) => $status.': '.$count)->implode(', ') }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Finance</div>
                        <div class="mt-2 text-sm text-slate-600">Income: {{ $metrics['currency']['code'] }} {{ number_format($metrics['finance']['income_total'] ?? 0, 2) }}</div>
                        <div class="mt-1 text-sm text-slate-600">Expense: {{ $metrics['currency']['code'] }} {{ number_format($metrics['finance']['expense_total'] ?? 0, 2) }}</div>
                        <div class="mt-1 text-xs text-slate-500">Net profit: {{ $metrics['currency']['code'] }} {{ number_format($metrics['finance']['net_profit'] ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="card p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="section-label">AI Summary</div>
                        <div class="mt-1 text-sm text-slate-500">Generated with Gemini using live business metrics.</div>
                    </div>
                    <span id="ai-status-badge" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Ready</span>
                </div>

                <div id="ai-summary" class="mt-5 rounded-2xl border border-slate-300 bg-white p-5 text-sm text-slate-700">
                    <div class="text-slate-500">Click "Generate AI Summary" to create the report.</div>
                </div>
            </div>

            <div class="card mt-6 p-6">
                <div class="section-label">Projection Highlights</div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Income due (next {{ $filters['projection_days'] }} days)</div>
                        <div class="mt-2 text-xl font-semibold text-emerald-600">
                            {{ $metrics['currency']['code'] }} {{ number_format($metrics['projections']['income_due_next_window'] ?? 0, 2) }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">Invoices: {{ $metrics['projections']['income_due_count'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Expense due (next {{ $filters['projection_days'] }} days)</div>
                        <div class="mt-2 text-xl font-semibold text-rose-600">
                            {{ $metrics['currency']['code'] }} {{ number_format($metrics['projections']['expense_due_next_window'] ?? 0, 2) }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">Expense invoices: {{ $metrics['projections']['expense_due_count'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('ai-business-status-form');
                const summaryEl = document.getElementById('ai-summary');
                const badgeEl = document.getElementById('ai-status-badge');

                if (!form || !summaryEl) {
                    return;
                }

                const setStatus = (label, colorClass) => {
                    if (!badgeEl) {
                        return;
                    }
                    badgeEl.textContent = label;
                    badgeEl.className = `rounded-full px-3 py-1 text-xs font-semibold ${colorClass}`;
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    setStatus('Generating...', 'bg-amber-100 text-amber-700');
                    summaryEl.innerHTML = '<div class="text-slate-500">Working on your report...</div>';

                    const formData = new FormData(form);

                    try {
                        const response = await fetch("{{ route('admin.ai.business-status.generate') }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                            },
                            body: formData,
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            throw new Error(payload.error || 'Failed to generate summary.');
                        }

                        summaryEl.textContent = payload.summary;
                        setStatus('Updated', 'bg-emerald-100 text-emerald-700');
                    } catch (error) {
                        summaryEl.innerHTML = `<div class="text-rose-600">${error.message}</div>`;
                        setStatus('Error', 'bg-rose-100 text-rose-700');
                    }
                });
            });
        </script>
    @endpush
@endsection
