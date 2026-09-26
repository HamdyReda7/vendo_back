<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    /**
     * Display a listing of active products.
     */
    public function products()
    {
        $products = Product::with(['categories', 'images', 'variants.color', 'variants.size'])
            ->where('status', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المنتجات بنجاح.',
            'data' => ProductResource::collection($products),
        ]);
    }

    /**
     * Display a listing of active categories.
     */
    public function categories()
    {
        $categories = Category::withCount('products')
            ->where('status', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الأقسام بنجاح.',
            'data' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * Display a listing of active products that have an old_price (offers).
     */
    public function offers()
    {
        $offers = Product::with(['categories', 'images', 'variants.color', 'variants.size'])
            ->where('status', true)
            ->whereNotNull('old_price')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المنتجات التي عليها عروض بنجاح.',
            'data' => ProductResource::collection($offers),
        ]);
    }

    /**
     * Display the specified product with its details, variants, images, categories, and active reviews.
     */
    public function showProduct($id)
    {
        $product = Product::with([
            'categories',
            'images',
            'variants.color',
            'variants.size',
            'reviews' => function ($query) {
                $query->where('status', true)->with('user')->latest();
            },
        ])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المنتج بنجاح.',
            'data' => new ProductResource($product),
        ]);
    }
}
