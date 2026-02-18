@extends('layouts.client')

@section('title', $title ?? 'Invoices')
@section('page-title', $title ?? 'Invoices')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $title ?? 'Invoices' }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $subtitle ?? 'Review invoices and complete payment for unpaid items.' }}</p>
        </div>
        <a href="{{ route('client.dashboard') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to dashboard</a>
    </div>

    @if($invoices->isEmpty())
        <div class="card p-6 text-sm text-slate-500">
            {{ $statusFilter ? 'No '.$title.' found.' : 'No invoices found.' }}
        </div>
    @else
        <div class="card overflow-visible">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Service/Project</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Issue</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Paid</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        @php
                            $project = $invoice->project;
                            $plan = $invoice->subscription?->plan;
                            $product = $plan?->product;
                            $serviceName = $product?->name;
                            $planName = $plan?->name;
                            $serviceLabel = $serviceName && $planName
                                ? "{$serviceName} - {$planName}"
                                : ($serviceName ?: ($planName ?: null));
                            $relatedLabel = $project?->name ?: ($serviceLabel ?: '--');
                            $relatedUrl = $project
                                ? route('client.projects.show', $project)
                                : ($invoice->subscription ? route('client.services.show', $invoice->subscription) : null);
                            $statusLabel = ucfirst($invoice->status);
                            $statusClasses = match ($invoice->status) {
                                'paid' => 'bg-emerald-100 text-emerald-700',
                                'unpaid' => 'bg-amber-100 text-amber-700',
                                'overdue' => 'bg-rose-100 text-rose-700',
                                'refunded' => 'bg-sky-100 text-sky-700',
                                'cancelled' => 'bg-slate-100 text-slate-600',
                                default => 'bg-slate-100 text-slate-600',
                            };
                            $creditTotal = $invoice->accountingEntries->where('type', 'credit')->sum('amount');
                            $paidTotal = $invoice->accountingEntries->where('type', 'payment')->sum('amount');
                            $paidAmount = (float) $paidTotal + (float) $creditTotal;
                            $isPartial = $paidAmount > 0 && $paidAmount < (float) $invoice->total;
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-medium text-slate-900">
                                <a href="{{ $relatedUrl }}" class="font-medium text-teal-600 hover:text-teal-500">
                                    #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                @if($relatedUrl && $relatedLabel !== '--')
                                    <a href="{{ $relatedUrl }}" class="font-medium text-teal-600 hover:text-teal-500">
                                        {{ $relatedLabel }}
                                    </a>
                                @else
                                    {{ $relatedLabel }}
                                @endif
                            </td>                            
                            <td class="px-4 py-3 text-slate-700">{{ $invoice->currency }} {{ $invoice->total }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $invoice->issue_date->format($globalDateFormat) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $invoice->due_date->format($globalDateFormat) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $invoice->paid_at?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <div>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                                @if($isPartial)
                                    <div class="mt-2">
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Partial</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $invoice->currency }} {{ number_format($paidAmount, 2) }} paid</div>
                                @endif
                                @php
                                    $pendingProof = $invoice->paymentProofs->firstWhere('status', 'pending');
                                    $rejectedProof = $invoice->paymentProofs->firstWhere('status', 'rejected');
                                @endphp
                                @if($pendingProof)
                                    <div class="mt-1 text-xs text-amber-600">Manual payment pending review</div>
                                @elseif($rejectedProof)
                                    <div class="mt-1 text-xs text-rose-600">Manual payment rejected</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right overflow-visible">
                                <div class="flex flex-wrap items-center justify-end gap-3 text-xs">
                                    <a href="{{ route('client.invoices.show', $invoice) }}" class="text-slate-500 hover:text-teal-600">View</a>
                                    @if(in_array($invoice->status, ['unpaid', 'overdue'], true))
                                        <a href="{{ route('client.invoices.pay', $invoice) }}" class="text-teal-600 hover:text-teal-500">Pay now</a>
                                    @endif
                                    @if($pendingProof && $pendingProof->paymentAttempt)
                                        <a href="{{ route('client.invoices.manual', [$invoice, $pendingProof->paymentAttempt]) }}" class="text-slate-500 hover:text-teal-600">View submission</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection


