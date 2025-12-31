<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\SystemLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        return view('admin.products.index', [
            'products' => Product::query()->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('admin.products.create');
    }

    public function store(Request $request)
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

        return redirect()->route('admin.products.index')
            ->with('status', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.edit', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product)
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

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        SystemLogger::write('activity', 'Product deleted.', [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
        ], auth()->id(), request()->ip());

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }
}
