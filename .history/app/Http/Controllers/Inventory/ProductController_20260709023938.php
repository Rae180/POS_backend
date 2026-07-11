<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductStoreRequest;
use App\Http\Requests\Product\ProductUpdateRequest;
use App\Models\Product;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     */
    public function index(Request $request)
    {
        $products = Product::query()
            ->search($request->search)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */


    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(ProductStoreRequest $request)
    {
        $productData = $request->validated();

        if ($request->hasFile('image')) {
            $productData['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($productData);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Factory|View|\Illuminate\View\View
     */


    /**
     * Update the specified resource in storage.
     *
     */
    public function update(ProductUpdateRequest $request, Product $product)
    {
        $productData = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $productData['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($productData);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }
}
