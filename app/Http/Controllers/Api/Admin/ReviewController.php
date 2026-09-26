<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Review\UpdateReviewStatusRequest;
use App\Http\Resources\AdminReviewResource;
use App\Models\Review;

class ReviewController extends Controller
{
    /**
     * Display a listing of all reviews.
     */
    public function index()
    {
        $reviews = Review::with(['user', 'product.images'])
            ->latest()
            ->paginate(5);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب التقييمات بنجاح.',
            'data' => AdminReviewResource::collection($reviews->items()),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Display the specified review.
     */
    public function show($id)
    {
        $review = Review::with(['user', 'product.images'])->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'التقييم غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب التقييم بنجاح.',
            'data' => new AdminReviewResource($review),
        ]);
    }

    /**
     * Update the status of the specified review.
     */
    public function updateStatus(UpdateReviewStatusRequest $request, $id)
    {
        $review = Review::with(['user', 'product.images'])->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'التقييم غير موجود.',
            ], 404);
        }

        $review->status = $request->boolean('status');
        $review->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة التقييم بنجاح.',
            'data' => new AdminReviewResource($review),
        ]);
    }
}
