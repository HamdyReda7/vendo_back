<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\QueryException;

class ReviewController extends Controller
{
    /**
     * Store a newly created review for a product.
     */
    public function store(StoreReviewRequest $request, $product_id)
    {
        $product = Product::find($product_id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود.',
            ], 404);
        }

        $userId = auth()->id();

        $alreadyReviewed = Review::where('user_id', $userId)
            ->where('product_id', $product->id)
            ->exists();

        if ($alreadyReviewed) {
            return response()->json([
                'success' => false,
                'message' => 'أنت قمت بتقييم هذا المنتج من قبل.',
            ], 422);
        }

        try {
            $review = Review::create([
                'user_id' => $userId,
                'product_id' => $product->id,
                'rating' => (int) $request->input('rating'),
                'comment' => $request->input('comment'),
                'status' => true,
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'أنت قمت بتقييم هذا المنتج من قبل.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة التقييم بنجاح.',
            'data' => new ReviewResource($review->load('user')),
        ], 201);
    }
}
