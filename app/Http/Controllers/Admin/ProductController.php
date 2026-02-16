<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\AjaxResponse;
use App\Support\SystemLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()->latest()->get();

        return view('admin.products.index', compact('products'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.products.partials.form');
        }

        return view('admin.products.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $product = Product::create($data);

        SystemLogger::write('activity', 'Product created.', [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Product created.', $this->patches());
        }

        return redirect()->route('admin.products.index')
            ->with('status', 'Product created.');
    }

    public function edit(Request $request, Product $product): View
    {
        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.products.partials.form', compact('product'));
        }

        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $product->update($data);

        SystemLogger::write('activity', 'Product updated.', [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Product updated.', $this->patches());
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        SystemLogger::write('activity', 'Product deleted.', [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
        ], auth()->id(), request()->ip());

        $product->delete();

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Product deleted.', $this->patches(), closeModal: false);
        }

        return redirect()->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }

    private function patches(): array
    {
        return [
            [
                'action' => 'replace',
                'selector' => '#productsTableWrap',
                'html' => view('admin.products.partials.table', [
                    'products' => Product::query()->latest()->get(),
                ])->render(),
            ],
        ];
    }
}
