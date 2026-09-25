<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\StoreProductRequest;
use App\Http\Requests\Admin\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['images', 'categories', 'variants.color', 'variants.size'])
            ->latest()
            ->paginate(5);

        return response()->json([
            'success' => true,
            'message' => 'جميع المنتجات.',
            'data' => ProductResource::collection($products->items()),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function offers()
    {
        $products = Product::with(['images', 'categories', 'variants.color', 'variants.size'])
            ->whereNotNull('old_price')
            ->where('status', true)
            ->latest()
            ->paginate(5);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المنتجات التي عليها عروض بنجاح.',
            'data' => ProductResource::collection($products->items()),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        unset($data['images'], $data['variants'], $data['category_ids']);

        if ($request->has('status') && $request->status !== null) {
            $data['status'] = (int) $request->boolean('status');
        } else {
            $data['status'] = 1;
        }

        $uploadedFiles = [];

        DB::beginTransaction();
        try {
            $product = Product::create($data);

            if ($request->has('category_ids') && is_array($request->category_ids)) {
                $product->categories()->sync($request->category_ids);
            }

            if ($request->hasFile('images')) {
                if (!File::isDirectory(public_path('img/products'))) {
                    File::makeDirectory(public_path('img/products'), 0755, true, true);
                }

                foreach ($request->file('images') as $image) {
                    $imageName = 'product_' . random_int(100000, 999999) . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('img/products'), $imageName);
                    $uploadedFiles[] = $imageName;

                    $product->images()->create([
                        'image' => $imageName,
                    ]);
                }
            }

            if ($product->has_variants && $request->has('variants') && is_array($request->variants)) {
                foreach ($request->variants as $variantData) {
                    $varStatus = isset($variantData['status']) && $variantData['status'] !== null
                        ? (int) filter_var($variantData['status'], FILTER_VALIDATE_BOOLEAN)
                        : 1;

                    $product->variants()->create([
                        'color_id' => !empty($variantData['color_id']) ? $variantData['color_id'] : null,
                        'size_id' => !empty($variantData['size_id']) ? $variantData['size_id'] : null,
                        'quantity' => (int) $variantData['quantity'],
                        'status' => $varStatus,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المنتج بنجاح.',
                'data' => new ProductResource($product->load(['images', 'categories', 'variants.color', 'variants.size'])),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($uploadedFiles as $fileName) {
                if (File::exists(public_path('img/products/' . $fileName))) {
                    File::delete(public_path('img/products/' . $fileName));
                }
            }

            throw $e;
        }
    }

    public function show($id)
    {
        $product = Product::with(['images', 'categories', 'variants.color', 'variants.size'])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات المنتج بنجاح.',
            'data' => new ProductResource($product),
        ]);
    }

    public function update(UpdateProductRequest $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود.',
            ], 404);
        }

        $data = $request->validated();
        unset($data['images'], $data['delete_image_ids'], $data['variants'], $data['delete_variant_ids'], $data['category_ids']);

        if ($request->has('status')) {
            $data['status'] = (int) $request->boolean('status');
        } else {
            unset($data['status']);
        }

        $newUploadedFiles = [];

        DB::beginTransaction();
        try {
            // Check transition of has_variants
            $targetHasVariants = $request->has('has_variants')
                ? $request->boolean('has_variants')
                : (bool) $product->has_variants;

            if ($product->has_variants && !$targetHasVariants) {
                // Switched from true to false: delete all variants
                $product->variants()->delete();
            }

            // Handle Variant Deletions
            if ($targetHasVariants && $request->has('delete_variant_ids') && is_array($request->delete_variant_ids)) {
                $product->variants()->whereIn('id', $request->delete_variant_ids)->delete();
            }

            // Handle Variant Updates and Additions
            if ($targetHasVariants && $request->has('variants') && is_array($request->variants)) {
                $existingVariants = $product->variants()->get()->keyBy('id');

                foreach ($request->variants as $variantItem) {
                    $variantId = $variantItem['id'] ?? null;

                    if ($variantId) {
                        $variant = $existingVariants->get($variantId);

                        if (!$variant) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'الـ Variant لا ينتمي إلى هذا المنتج.',
                            ], 422);
                        }

                        $targetColor = array_key_exists('color_id', $variantItem)
                            ? (!empty($variantItem['color_id']) ? $variantItem['color_id'] : null)
                            : $variant->color_id;

                        $targetSize = array_key_exists('size_id', $variantItem)
                            ? (!empty($variantItem['size_id']) ? $variantItem['size_id'] : null)
                            : $variant->size_id;

                        $duplicateExists = $product->variants()
                            ->where('id', '!=', $variant->id)
                            ->where('color_id', $targetColor)
                            ->where('size_id', $targetSize)
                            ->exists();

                        if ($duplicateExists) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'لا يمكن تكرار نفس الـ Variant لهذا المنتج.',
                            ], 422);
                        }

                        $updateFields = [];
                        if (array_key_exists('color_id', $variantItem)) $updateFields['color_id'] = $targetColor;
                        if (array_key_exists('size_id', $variantItem)) $updateFields['size_id'] = $targetSize;
                        if (isset($variantItem['quantity'])) $updateFields['quantity'] = (int) $variantItem['quantity'];
                        if (isset($variantItem['status']) && $variantItem['status'] !== null) {
                            $updateFields['status'] = (int) filter_var($variantItem['status'], FILTER_VALIDATE_BOOLEAN);
                        }

                        $variant->update($updateFields);
                    } else {
                        // New variant to create
                        $newColor = !empty($variantItem['color_id']) ? $variantItem['color_id'] : null;
                        $newSize = !empty($variantItem['size_id']) ? $variantItem['size_id'] : null;

                        $duplicateExists = $product->variants()
                            ->where('color_id', $newColor)
                            ->where('size_id', $newSize)
                            ->exists();

                        if ($duplicateExists) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'لا يمكن تكرار نفس الـ Variant لهذا المنتج.',
                            ], 422);
                        }

                        $varStatus = isset($variantItem['status']) && $variantItem['status'] !== null
                            ? (int) filter_var($variantItem['status'], FILTER_VALIDATE_BOOLEAN)
                            : 1;

                        $product->variants()->create([
                            'color_id' => $newColor,
                            'size_id' => $newSize,
                            'quantity' => (int) $variantItem['quantity'],
                            'status' => $varStatus,
                        ]);
                    }
                }
            }

            // Delete specified existing images that strictly belong to this product
            if ($request->has('delete_image_ids') && is_array($request->delete_image_ids)) {
                $imagesToDelete = $product->images()
                    ->whereIn('id', $request->delete_image_ids)
                    ->get();

                foreach ($imagesToDelete as $img) {
                    if ($img->image && File::exists(public_path('img/products/' . $img->image))) {
                        File::delete(public_path('img/products/' . $img->image));
                    }
                    $img->delete();
                }
            }

            // Upload new images if sent
            if ($request->hasFile('images')) {
                if (!File::isDirectory(public_path('img/products'))) {
                    File::makeDirectory(public_path('img/products'), 0755, true, true);
                }

                foreach ($request->file('images') as $image) {
                    $imageName = 'product_' . random_int(100000, 999999) . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('img/products'), $imageName);
                    $newUploadedFiles[] = $imageName;

                    $product->images()->create([
                        'image' => $imageName,
                    ]);
                }
            }

            $product->update($data);

            if ($request->has('category_ids')) {
                $product->categories()->sync($request->category_ids ?? []);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المنتج بنجاح.',
                'data' => new ProductResource($product->fresh()->load(['images', 'categories', 'variants.color', 'variants.size'])),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($newUploadedFiles as $fileName) {
                if (File::exists(public_path('img/products/' . $fileName))) {
                    File::delete(public_path('img/products/' . $fileName));
                }
            }

            throw $e;
        }
    }

    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود.',
            ], 404);
        }

        $product->categories()->detach();

        foreach ($product->images as $productImage) {
            if ($productImage->image && File::exists(public_path('img/products/' . $productImage->image))) {
                File::delete(public_path('img/products/' . $productImage->image));
            }
        }

        $product->images()->delete();
        $product->variants()->delete();
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المنتج بنجاح.',
        ]);
    }
}
