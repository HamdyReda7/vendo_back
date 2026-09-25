<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\StoreCategoryRequest;
use App\Http\Requests\Admin\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Support\Facades\File;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')
            ->latest()
            ->paginate(5);

        return response()->json([
            'success' => true,
            'message' => 'جميع الأقسام.',
            'data' => CategoryResource::collection($categories->items()),
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $imageName = null;

        if ($request->hasFile('image')) {
            if (!File::isDirectory(public_path('img/categories'))) {
                File::makeDirectory(public_path('img/categories'), 0755, true, true);
            }

            $image = $request->file('image');
            $imageName = 'category_' . random_int(100000, 999999) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('img/categories'), $imageName);
        }

        $category = Category::create([
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en,
            'image' => $imageName,
            'status' => $request->input('status', 1),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء القسم بنجاح.',
            'data' => new CategoryResource($category->loadCount('products')),
        ], 201);
    }

    public function show($id)
    {
        $category = Category::withCount('products')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'القسم غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات القسم بنجاح.',
            'data' => new CategoryResource($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'القسم غير موجود.',
            ], 404);
        }

        $imageName = $category->image;

        if ($request->hasFile('image')) {
            if ($category->image && File::exists(public_path('img/categories/' . $category->image))) {
                File::delete(public_path('img/categories/' . $category->image));
            }

            if (!File::isDirectory(public_path('img/categories'))) {
                File::makeDirectory(public_path('img/categories'), 0755, true, true);
            }

            $image = $request->file('image');
            $imageName = 'category_' . random_int(100000, 999999) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('img/categories'), $imageName);
        }

        $category->update([
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en,
            'image' => $imageName,
            'status' => $request->has('status')
                ? $request->status
                : $category->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث القسم بنجاح.',
            'data' => new CategoryResource($category->fresh()->loadCount('products')),
        ]);
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'القسم غير موجود.',
            ], 404);
        }

        $category->products()->detach();

        if ($category->image && File::exists(public_path('img/categories/' . $category->image))) {
            File::delete(public_path('img/categories/' . $category->image));
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف القسم بنجاح.',
        ]);
    }
}
