<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index()
    {
        $orders = Order::with(['user', 'orderItems'])
            ->latest()
            ->paginate(5);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الطلبات بنجاح.',
            'data' => OrderResource::collection($orders->items()),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show($id)
    {
        $order = Order::with(['user', 'orderItems'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الطلب بنجاح.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Update the status of the specified order.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود.',
            ], 404);
        }

        $newStatus = $request->validated('status');
        $oldStatus = $order->status;

        DB::transaction(function () use ($order, $newStatus, $oldStatus) {
            // Restore stock if changing to cancelled from any non-cancelled status
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                $orderItems = $order->orderItems()->get();

                $productIds = $orderItems->pluck('product_id')->filter()->unique()->sort()->values()->all();
                $variantIds = $orderItems->pluck('product_variant_id')->filter()->unique()->sort()->values()->all();

                $products = !empty($productIds)
                    ? Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id')
                    : collect();

                $variants = !empty($variantIds)
                    ? ProductVariant::whereIn('id', $variantIds)->lockForUpdate()->get()->keyBy('id')
                    : collect();

                foreach ($orderItems as $item) {
                    if ($item->product_variant_id && $variants->has($item->product_variant_id)) {
                        $variant = $variants->get($item->product_variant_id);
                        $variant->quantity += $item->quantity;
                        $variant->save();
                    } elseif ($item->product_id && $products->has($item->product_id)) {
                        $product = $products->get($item->product_id);
                        $product->quantity += $item->quantity;
                        $product->save();
                    }
                }
            }

            $order->status = $newStatus;
            $order->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح.',
            'data' => new OrderResource($order->fresh(['user', 'orderItems'])),
        ]);
    }
}
