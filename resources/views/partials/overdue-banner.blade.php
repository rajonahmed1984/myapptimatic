<div class="mb-8 rounded-3xl border border-amber-200 bg-amber-50 px-6 py-4 text-amber-800">
    <div class="flex flex-wrap items-center gap-3">
        <div class="text-xs uppercase tracking-[0.35em] text-amber-500">Payment due warning</div>
        <div class="flex-1 text-sm text-amber-700">
            <span class="font-semibold">Your account has outstanding invoices.</span>
            <span class="ml-1">
                @if($notice['overdue_count'] > 0)
                    {{ $notice['overdue_count'] }} overdue
                @endif
                @if($notice['overdue_count'] > 0 && $notice['unpaid_count'] > 0)
                    and
                @endif
                @if($notice['unpaid_count'] > 0)
                    {{ $notice['unpaid_count'] }} unpaid
                @endif
                invoice(s) require attention.
            </span>
        </div>
        @if(!empty($notice['payment_url']))
            <a href="{{ $notice['payment_url'] }}" class="inline-flex items-center rounded-full border border-amber-300 px-4 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100">
                Go to payment
            </a>
        @endif
    </div>
</div>
