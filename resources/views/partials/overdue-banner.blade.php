<div class="mb-8 rounded-3xl border border-amber-200 bg-amber-50 p-6 text-amber-800">
    <div class="text-xs uppercase tracking-[0.35em] text-amber-500">Payment due</div>
    <div class="mt-2 text-lg font-semibold text-amber-700">Your account has outstanding invoices.</div>
    <p class="mt-2 text-sm text-amber-700/80">
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
    </p>
    @if(!empty($notice['payment_url']))
        <a href="{{ $notice['payment_url'] }}" class="mt-4 inline-flex items-center rounded-full border border-amber-300 px-5 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100">
            Go to payment
        </a>
    @endif
</div>
