<div id="productsTableWrap" class="card overflow-x-auto">
    <table class="w-full min-w-[800px] text-left text-sm">
        <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
            <tr>
                <th class="px-4 py-3">SL</th>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Slug</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr id="product-row-{{ $product->id }}" class="border-b border-slate-100">
                    <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $product->name }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $product->slug }}</td>
                    <td class="px-4 py-3">
                        <x-status-badge :status="$product->status" />
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a
                                href="{{ route('admin.products.edit', $product) }}"
                                data-ajax-modal="true"
                                data-modal-title="Edit Product"
                                data-url="{{ route('admin.products.edit', $product) }}"
                                class="text-teal-600 hover:text-teal-500"
                            >
                                Edit
                            </a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" data-ajax-form="true" onsubmit="return confirm('Delete this product?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">No products yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
