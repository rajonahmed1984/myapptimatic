@php
    $isEdit = isset($product) && $product;
    $ajaxForm = $ajaxForm ?? true;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.products.update', $product) : route('admin.products.store') }}" @if($ajaxForm) data-ajax-form="true" @endif class="space-y-4">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm text-slate-600">Name</label>
            <input name="name" value="{{ old('name', $product->name ?? '') }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
        </div>
        <div>
            <label class="text-sm text-slate-600">Slug</label>
            <input name="slug" value="{{ old('slug', $product->slug ?? '') }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
        </div>
        <div>
            <label class="text-sm text-slate-600">Status</label>
            <select name="status" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                <option value="active" @selected(old('status', $product->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $product->status ?? 'active') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="text-sm text-slate-600">Description</label>
            <textarea name="description" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">{{ old('description', $product->description ?? '') }}</textarea>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        @if($ajaxForm)
            <button type="button" data-ajax-modal-close="true" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400">Cancel</button>
        @else
            <a href="{{ route('admin.products.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400">Cancel</a>
        @endif
        <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold text-white hover:bg-slate-800">
            {{ $isEdit ? 'Update product' : 'Save product' }}
        </button>
    </div>
</form>
