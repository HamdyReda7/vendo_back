<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Color\StoreColorRequest;
use App\Http\Requests\Admin\Color\UpdateColorRequest;
use App\Http\Resources\ColorResource;
use App\Models\Color;

class ColorController extends Controller
{
    public function index()
    {
        $colors = Color::latest()->paginate(5);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الألوان بنجاح.',
            'data' => ColorResource::collection($colors->items()),
            'pagination' => [
                'current_page' => $colors->currentPage(),
                'last_page' => $colors->lastPage(),
                'per_page' => $colors->perPage(),
                'total' => $colors->total(),
            ],
        ]);
    }

    public function store(StoreColorRequest $request)
    {
        $data = $request->validated();

        if ($request->has('status') && $request->status !== null) {
            $data['status'] = (int) $request->boolean('status');
        } else {
            $data['status'] = 1;
        }

        $color = Color::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة اللون بنجاح.',
            'data' => new ColorResource($color),
        ], 201);
    }

    public function show($id)
    {
        $color = Color::find($id);

        if (!$color) {
            return response()->json([
                'success' => false,
                'message' => 'اللون غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات اللون بنجاح.',
            'data' => new ColorResource($color),
        ]);
    }

    public function update(UpdateColorRequest $request, $id)
    {
        $color = Color::find($id);

        if (!$color) {
            return response()->json([
                'success' => false,
                'message' => 'اللون غير موجود.',
            ], 404);
        }

        $data = $request->validated();

        if ($request->has('status')) {
            $data['status'] = (int) $request->boolean('status');
        } else {
            unset($data['status']);
        }

        $color->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث اللون بنجاح.',
            'data' => new ColorResource($color->fresh()),
        ]);
    }

    public function destroy($id)
    {
        $color = Color::find($id);

        if (!$color) {
            return response()->json([
                'success' => false,
                'message' => 'اللون غير موجود.',
            ], 404);
        }

        $color->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف اللون بنجاح.',
        ]);
    }
}
