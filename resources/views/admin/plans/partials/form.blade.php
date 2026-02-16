@php
    $isEdit = isset($plan) && $plan;
    $ajaxForm = $ajaxForm ?? true;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" @if($ajaxForm) data-ajax-form="true" @endif class="space-y-4">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm text-slate-600">Product</label>
            <select name="product_id" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected(old('product_id', $plan->product_id ?? null) == $product->id)>{{ $product->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm text-slate-600">Name</label>
            <input name="name" value="{{ old('name', $plan->name ?? '') }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
        </div>
        <div>
            <label class="text-sm text-slate-600">Slug</label>
            <input name="slug" value="{{ old('slug', $plan->slug ?? '') }}" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
            <p class="mt-2 text-xs text-slate-500">Leave blank to auto-generate.</p>
        </div>
        <div>
            <label class="text-sm text-slate-600">Interval</label>
            <select name="interval" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                <option value="monthly" @selected(old('interval', $plan->interval ?? 'monthly') === 'monthly')>Monthly</option>
                <option value="yearly" @selected(old('interval', $plan->interval ?? 'monthly') === 'yearly')>Yearly</option>
            </select>
        </div>
        <div>
            <label class="text-sm text-slate-600">Price</label>
            <input name="price" type="number" step="0.01" value="{{ old('price', $plan->price ?? '') }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
        </div>
        <div>
            <label class="text-sm text-slate-600">Currency</label>
            <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600">
                {{ $defaultCurrency }}
            </div>
            <p class="mt-2 text-xs text-slate-500">Currency is set globally in Settings.</p>
        </div>
        <div class="flex items-center gap-2 text-sm text-slate-600">
            <input type="hidden" name="is_active" value="0" />
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true)) class="rounded border-slate-300 text-teal-500" />
            Active plan
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        @if($ajaxForm)
            <button type="button" data-ajax-modal-close="true" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400">Cancel</button>
        @else
            <a href="{{ route('admin.plans.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400">Cancel</a>
        @endif
        <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold text-white hover:bg-slate-800">
            {{ $isEdit ? 'Update plan' : 'Save plan' }}
        </button>
    </div>
</form>
