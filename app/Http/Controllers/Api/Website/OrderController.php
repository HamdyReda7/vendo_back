<?php

namespace App\Http\Controllers\Api\Website;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Store a newly created order.
     */
    public function store(StoreOrderRequest $request)
    {
        $userId = auth()->id();
        $items = $request->input('items', []);
        $shipping = $request->filled('shipping') ? (float) $request->input('shipping') : 0.0;
        $shipping = max(0.0, round($shipping, 2));

        try {
            $order = DB::transaction(function () use ($request, $userId, $items, $shipping) {
                // 1. Lock all unique products in ascending ID order to prevent deadlocks
                $productIds = collect($items)->pluck('product_id')->unique()->sort()->values()->all();
                $products = Product::whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // 2. Lock all unique variants in ascending ID order
                $variantIds = collect($items)->pluck('product_variant_id')->filter()->unique()->sort()->values()->all();
                $variants = empty($variantIds)
                    ? collect()
                    : ProductVariant::with(['color', 'size'])
                        ->whereIn('id', $variantIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                $orderItemsData = [];
                $subtotal = 0.0;

                // 3. Validate products, variants, and stock
                foreach ($items as $item) {
                    $productId = $item['product_id'];
                    $product = $products->get($productId);

                    if (!$product) {
                        throw new OrderException('المنتج غير موجود.', 404);
                    }

                    if (!$product->status) {
                        throw new OrderException('هذا المنتج غير متاح حاليًا.', 422);
                    }

                    $quantity = (int) $item['quantity'];

                    if (!$product->has_variants) {
                        if (!empty($item['product_variant_id'])) {
                            throw new OrderException('الخيار المحدد غير تابع لهذا المنتج.', 422);
                        }

                        if ($quantity > $product->quantity) {
                            throw new OrderException('الكمية المطلوبة غير متوفرة في المخزون.', 422);
                        }

                        // Decrement stock in memory
                        $product->quantity -= $quantity;

                        $variantId = null;
                        $variantDetails = null;
                    } else {
                        if (empty($item['product_variant_id'])) {
                            throw new OrderException('يرجى تحديد خيار المنتج.', 422);
                        }

                        $variant = $variants->get($item['product_variant_id']);

                        if (!$variant || (int) $variant->product_id !== (int) $product->id) {
                            throw new OrderException('الخيار المحدد غير تابع لهذا المنتج.', 422);
                        }

                        if (!$variant->status) {
                            throw new OrderException('هذا الخيار غير متاح حاليًا.', 422);
                        }

                        if ($quantity > $variant->quantity) {
                            throw new OrderException('الكمية المطلوبة غير متوفرة في المخزون.', 422);
                        }

                        // Decrement stock in memory
                        $variant->quantity -= $quantity;

                        $variantId = $variant->id;
                        $variantDetails = [
                            'color' => $variant->color ? [
                                'id' => $variant->color->id,
                                'name_ar' => $variant->color->name_ar,
                                'name_en' => $variant->color->name_en,
                            ] : null,
                            'size' => $variant->size ? [
                                'id' => $variant->size->id,
                                'name' => $variant->size->name,
                            ] : null,
                        ];
                    }

                    $price = (float) $product->price;
                    $itemTotal = round($price * $quantity, 2);
                    $subtotal += $itemTotal;

                    $orderItemsData[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $variantId,
                        'product_name_ar' => $product->name_ar,
                        'product_name_en' => $product->name_en,
                        'variant_details' => $variantDetails,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total' => $itemTotal,
                    ];
                }

                // 4. Persist updated stock
                foreach ($products as $prod) {
                    $prod->save();
                }

                foreach ($variants as $var) {
                    $var->save();
                }

                // 5. Generate unique order number
                do {
                    $orderNumber = 'ORD-' . strtoupper(Str::random(12));
                } while (Order::where('order_number', $orderNumber)->exists());

                $subtotal = round($subtotal, 2);
                $total = round($subtotal + $shipping, 2);

                // 6. Create Order
                $order = Order::create([
                    'user_id' => $userId,
                    'order_number' => $orderNumber,
                    'subtotal' => $subtotal,
                    'shipping' => $shipping,
                    'total' => $total,
                    'governorate' => $request->input('governorate'),
                    'address' => $request->input('address'),
                    'delivery_phone' => $request->input('delivery_phone'),
                    'note' => $request->input('note'),
                    'status' => 'pending',
                ]);

                // 7. Create OrderItems
                foreach ($orderItemsData as $itemData) {
                    $order->orderItems()->create($itemData);
                }

                return $order;
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح.',
                'data' => new OrderResource($order->load('orderItems.product.images')),
            ], 201);
        } catch (OrderException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
