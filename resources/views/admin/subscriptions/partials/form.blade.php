@php
    $isEdit = isset($subscription) && $subscription;
    $ajaxForm = $ajaxForm ?? true;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.subscriptions.update', $subscription) : route('admin.subscriptions.store') }}" @if($ajaxForm) data-ajax-form="true" @endif class="space-y-4">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm text-slate-600">Customer</label>
            <select name="customer_id" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(old('customer_id', $subscription->customer_id ?? null) == $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm text-slate-600">Plan</label>
            <select name="plan_id" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(old('plan_id', $subscription->plan_id ?? null) == $plan->id)>{{ $plan->name }} ({{ $plan->product->name }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm text-slate-600">Sales rep</label>
            <select name="sales_rep_id" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                <option value="">None</option>
                @foreach($salesReps as $rep)
                    <option value="{{ $rep->id }}" @selected(old('sales_rep_id', $subscription->sales_rep_id ?? null) == $rep->id)>
                        {{ $rep->name }} @if($rep->status !== 'active') ({{ ucfirst($rep->status) }}) @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm text-slate-600">Start date</label>
            <input name="start_date" type="date" value="{{ old('start_date', optional($subscription->start_date ?? now())->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
        </div>

        @if($isEdit)
            <div>
                <label class="text-sm text-slate-600">Current period start</label>
                <input name="current_period_start" type="date" value="{{ old('current_period_start', optional($subscription->current_period_start)->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" required />
            </div>
            <div>
                <label class="text-sm text-slate-600">Current period end</label>
                <input name="current_period_end" type="date" value="{{ old('current_period_end', optional($subscription->current_period_end)->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" required />
            </div>
            <div>
                <label class="text-sm text-slate-600">Next invoice at</label>
                <input name="next_invoice_at" type="date" value="{{ old('next_invoice_at', optional($subscription->next_invoice_at)->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" required />
            </div>
        @endif

        <div>
            <label class="text-sm text-slate-600">Status</label>
            <select name="status" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                <option value="active" @selected(old('status', $subscription->status ?? 'active') === 'active')>Active</option>
                <option value="suspended" @selected(old('status', $subscription->status ?? 'active') === 'suspended')>Suspended</option>
                <option value="cancelled" @selected(old('status', $subscription->status ?? 'active') === 'cancelled')>Cancelled</option>
            </select>
        </div>
        <div class="flex items-center gap-2 text-sm text-slate-600">
            <input type="hidden" name="auto_renew" value="0" />
            <input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew', $subscription->auto_renew ?? true)) class="rounded border-slate-300 text-teal-500" />
            Auto renew
        </div>
        <div class="flex items-center gap-2 text-sm text-slate-600">
            <input type="hidden" name="cancel_at_period_end" value="0" />
            <input type="checkbox" name="cancel_at_period_end" value="1" @checked(old('cancel_at_period_end', $subscription->cancel_at_period_end ?? false)) class="rounded border-slate-300 text-teal-500" />
            Cancel at period end
        </div>
        <div class="md:col-span-2">
            <label class="text-sm text-slate-600">Notes</label>
            <textarea name="notes" rows="2" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">{{ old('notes', $subscription->notes ?? '') }}</textarea>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        @if($ajaxForm)
            <button type="button" data-ajax-modal-close="true" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400">Cancel</button>
        @else
            <a href="{{ route('admin.subscriptions.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400">Cancel</a>
        @endif
        <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold text-white hover:bg-slate-800">
            {{ $isEdit ? 'Save subscription' : 'Create subscription' }}
        </button>
    </div>
</form>
