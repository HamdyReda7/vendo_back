<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Size\StoreSizeRequest;
use App\Http\Requests\Admin\Size\UpdateSizeRequest;
use App\Http\Resources\SizeResource;
use App\Models\Size;

class SizeController extends Controller
{
    public function index()
    {
        $sizes = Size::latest()->paginate(5);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المقاسات بنجاح.',
            'data' => SizeResource::collection($sizes->items()),
            'pagination' => [
                'current_page' => $sizes->currentPage(),
                'last_page' => $sizes->lastPage(),
                'per_page' => $sizes->perPage(),
                'total' => $sizes->total(),
            ],
        ]);
    }

    public function store(StoreSizeRequest $request)
    {
        $data = $request->validated();

        if ($request->has('status') && $request->status !== null) {
            $data['status'] = (int) $request->boolean('status');
        } else {
            $data['status'] = 1;
        }

        $size = Size::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المقاس بنجاح.',
            'data' => new SizeResource($size),
        ], 201);
    }

    public function show($id)
    {
        $size = Size::find($id);

        if (!$size) {
            return response()->json([
                'success' => false,
                'message' => 'المقاس غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات المقاس بنجاح.',
            'data' => new SizeResource($size),
        ]);
    }

    public function update(UpdateSizeRequest $request, $id)
    {
        $size = Size::find($id);

        if (!$size) {
            return response()->json([
                'success' => false,
                'message' => 'المقاس غير موجود.',
            ], 404);
        }

        $data = $request->validated();

        if ($request->has('status')) {
            $data['status'] = (int) $request->boolean('status');
        } else {
            unset($data['status']);
        }

        $size->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المقاس بنجاح.',
            'data' => new SizeResource($size->fresh()),
        ]);
    }

    public function destroy($id)
    {
        $size = Size::find($id);

        if (!$size) {
            return response()->json([
                'success' => false,
                'message' => 'المقاس غير موجود.',
            ], 404);
        }

        try {
            $size->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المقاس بنجاح.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف المقاس لارتباطه ببيانات أخرى.',
            ], 400);
        }
    }
}
