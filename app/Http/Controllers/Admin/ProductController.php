<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\AjaxResponse;
use App\Support\SystemLogger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $products = Product::query()->latest()->get();

        return Inertia::render(
            'Admin/Products/Index',
            $this->indexInertiaProps($products)
        );
    }

    public function create(Request $request): View|InertiaResponse|RedirectResponse
    {
        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.products.partials.form');
        }

        return Inertia::render(
            'Admin/Products/Form',
            $this->formInertiaProps(null)
        );
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

    public function edit(Request $request, Product $product): View|InertiaResponse
    {
        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.products.partials.form', compact('product'));
        }

        return Inertia::render(
            'Admin/Products/Form',
            $this->formInertiaProps($product)
        );
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

    private function indexInertiaProps(EloquentCollection $products): array
    {
        return [
            'pageTitle' => 'Products',
            'routes' => [
                'create' => route('admin.products.create'),
            ],
            'products' => $products->values()->map(function (Product $product, int $index) {
                return [
                    'id' => $product->id,
                    'serial' => $index + 1,
                    'name' => (string) $product->name,
                    'slug' => (string) $product->slug,
                    'status' => (string) $product->status,
                    'status_label' => ucfirst((string) $product->status),
                    'routes' => [
                        'edit' => route('admin.products.edit', $product),
                        'destroy' => route('admin.products.destroy', $product),
                    ],
                ];
            })->all(),
        ];
    }

    private function formInertiaProps(?Product $product): array
    {
        $isEdit = $product !== null;

        return [
            'pageTitle' => $isEdit ? 'Edit Product' : 'Add Product',
            'is_edit' => $isEdit,
            'form' => [
                'action' => $isEdit
                    ? route('admin.products.update', $product)
                    : route('admin.products.store'),
                'method' => $isEdit ? 'PUT' : 'POST',
                'fields' => [
                    'name' => (string) old('name', (string) ($product?->name ?? '')),
                    'slug' => (string) old('slug', (string) ($product?->slug ?? '')),
                    'description' => (string) old('description', (string) ($product?->description ?? '')),
                    'status' => (string) old('status', (string) ($product?->status ?? 'active')),
                ],
            ],
            'routes' => [
                'index' => route('admin.products.index'),
            ],
        ];
    }
}
