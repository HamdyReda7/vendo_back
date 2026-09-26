<?php

use App\Models\Color;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->otherUser = User::factory()->create([
        'role' => 'user',
    ]);

    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);
});

// ========================================
// MY ORDERS TESTS (1 - 6)
// ========================================

test('1. user can get their own orders', function () {
    Sanctum::actingAs($this->user);

    Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-MY01',
        'subtotal' => 100.00,
        'shipping' => 15.00,
        'total' => 115.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/my/orders');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب طلباتك بنجاح.',
        ]);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.order_number'))->toBe('ORD-MY01');
});

test('2. user only receives their own orders', function () {
    Sanctum::actingAs($this->user);

    Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-MINE',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'مدينة نصر',
        'status' => 'pending',
    ]);

    Order::create([
        'user_id' => $this->otherUser->id,
        'order_number' => 'ORD-OTHER',
        'subtotal' => 200.00,
        'shipping' => 10.00,
        'total' => 210.00,
        'governorate' => 'الجيزة',
        'address' => 'الدقي',
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/my/orders');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['order_number'])->toBe('ORD-MINE');
});

test('3. pagination is 5 orders per page and newest orders first', function () {
    Sanctum::actingAs($this->user);

    for ($i = 1; $i <= 7; $i++) {
        $order = Order::create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-PAGE-0' . $i,
            'subtotal' => 50.00 * $i,
            'shipping' => 10.00,
            'total' => (50.00 * $i) + 10.00,
            'governorate' => 'القاهرة',
            'address' => 'شارع ' . $i,
            'status' => 'pending',
        ]);
        $order->created_at = now()->addMinutes($i);
        $order->save();
    }

    $response = $this->getJson('/api/my/orders');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('pagination.per_page'))->toBe(5);
    expect($response->json('pagination.total'))->toBe(7);
    expect($response->json('pagination.current_page'))->toBe(1);
    expect($response->json('pagination.last_page'))->toBe(2);

    // Newest orders first
    expect($response->json('data.0.order_number'))->toBe('ORD-PAGE-07');
});

test('4. includes user data', function () {
    Sanctum::actingAs($this->user);

    Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-USER-DATA',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/my/orders');

    $response->assertStatus(200);
    $userResponse = $response->json('data.0.user');
    expect($userResponse)->toMatchArray([
        'id' => $this->user->id,
        'name' => $this->user->name,
        'email' => $this->user->email,
        'phone' => $this->user->phone,
    ]);
    expect($userResponse)->not->toHaveKey('password');
    expect($userResponse)->not->toHaveKey('remember_token');
});

test('5. includes items in order response', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج تجربة',
        'name_en' => 'Test Product',
        'price' => 100.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-ITEMS',
        'subtotal' => 200.00,
        'shipping' => 15.00,
        'total' => 215.00,
        'governorate' => 'القاهرة',
        'address' => 'التجمع الخامس',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج تجربة',
        'product_name_en' => 'Test Product',
        'quantity' => 2,
        'price' => 100.00,
        'total' => 200.00,
    ]);

    $response = $this->getJson('/api/my/orders');

    $response->assertStatus(200);
    $items = $response->json('data.0.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['product_id'])->toBe($product->id);
    expect($items[0]['quantity'])->toBe(2);
    expect($items[0]['price'])->toEqual(100.0);
    expect($items[0]['total'])->toEqual(200.0);
});

test('6. includes product_image in my orders response', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج بصورة',
        'name_en' => 'Product with Image',
        'price' => 80.00,
        'has_variants' => false,
        'quantity' => 15,
        'status' => true,
    ]);

    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'thumb_01.png',
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-IMG-MY',
        'subtotal' => 80.00,
        'shipping' => 10.00,
        'total' => 90.00,
        'governorate' => 'الجيزة',
        'address' => 'الدقي',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج بصورة',
        'product_name_en' => 'Product with Image',
        'quantity' => 1,
        'price' => 80.00,
        'total' => 80.00,
    ]);

    $response = $this->getJson('/api/my/orders');

    $response->assertStatus(200);
    $item = $response->json('data.0.items.0');
    expect($item['product_image'])->toBe(asset('img/products/thumb_01.png'));
});

// ========================================
// SHOW ORDER TESTS (7 - 12)
// ========================================

test('7. user can show their own order', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-SHOW-01',
        'subtotal' => 120.00,
        'shipping' => 15.00,
        'total' => 135.00,
        'governorate' => 'الإسكندرية',
        'address' => 'سموحة',
        'delivery_phone' => '01011112222',
        'note' => 'يرجى الاتصال قبل الوصول',
        'status' => 'pending',
    ]);

    $response = $this->getJson("/api/show/my/orders/{$order->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب الطلب بنجاح.',
            'data' => [
                'id' => $order->id,
                'order_number' => 'ORD-SHOW-01',
            ],
        ]);
});

test("8. user cannot show another user's order", function () {
    Sanctum::actingAs($this->user);

    $otherOrder = Order::create([
        'user_id' => $this->otherUser->id,
        'order_number' => 'ORD-OTHER-SHOW',
        'subtotal' => 200.00,
        'shipping' => 10.00,
        'total' => 210.00,
        'governorate' => 'القاهرة',
        'address' => 'مدينة نصر',
        'status' => 'pending',
    ]);

    $response = $this->getJson("/api/show/my/orders/{$otherOrder->id}");

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'الطلب غير موجود.',
        ]);
});

test('9. non-existing order returns 404', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/show/my/orders/999999');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'الطلب غير موجود.',
        ]);
});

test('10. includes shipping and address details in show', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-DETAILS',
        'subtotal' => 250.00,
        'shipping' => 30.00,
        'total' => 280.00,
        'governorate' => 'أسوان',
        'address' => 'شارع كورنيش النيل',
        'delivery_phone' => '01234567890',
        'note' => 'ملاحظة خاصة للتوصيل',
        'status' => 'pending',
    ]);

    $response = $this->getJson("/api/show/my/orders/{$order->id}");

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data['governorate'])->toBe('أسوان');
    expect($data['address'])->toBe('شارع كورنيش النيل');
    expect($data['delivery_phone'])->toBe('01234567890');
    expect($data['note'])->toBe('ملاحظة خاصة للتوصيل');
    expect($data['shipping'])->toEqual(30.0);
    expect($data['subtotal'])->toEqual(250.0);
    expect($data['total'])->toEqual(280.0);
});

test('11. includes current status in show', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-STATUS-CHECK',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'confirmed',
    ]);

    $response = $this->getJson("/api/show/my/orders/{$order->id}");

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('confirmed');
});

test('12. includes product_image in show response', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج بصورة للعرض',
        'name_en' => 'Product Show Image',
        'price' => 150.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'show_img.jpg',
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-SHOW-IMG',
        'subtotal' => 150.00,
        'shipping' => 10.00,
        'total' => 160.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج بصورة للعرض',
        'product_name_en' => 'Product Show Image',
        'quantity' => 1,
        'price' => 150.00,
        'total' => 150.00,
    ]);

    $response = $this->getJson("/api/show/my/orders/{$order->id}");

    $response->assertStatus(200);
    $item = $response->json('data.items.0');
    expect($item['product_image'])->toBe(asset('img/products/show_img.jpg'));
});

// ========================================
// UPDATE / CANCEL ORDER TESTS (13 - 23)
// ========================================

test('13. user can cancel a pending order', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-CANCEL-01',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    $response = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم إلغاء الطلب بنجاح.',
        ]);
});

test('14. order status becomes cancelled after cancellation', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-STATUS-CANCELLED',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    $response = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('cancelled');
    expect($order->fresh()->status)->toBe('cancelled');
});

test('15. simple product stock is restored when customer cancels order', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج بسيط',
        'name_en' => 'Simple Product',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-STOCK-RESTORE',
        'subtotal' => 150.00,
        'shipping' => 10.00,
        'total' => 160.00,
        'governorate' => 'القاهرة',
        'address' => 'مدينة نصر',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج بسيط',
        'product_name_en' => 'Simple Product',
        'quantity' => 3,
        'price' => 50.00,
        'total' => 150.00,
    ]);

    expect($product->fresh()->quantity)->toBe(10);

    $response = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(200);
    // Quantity restored from 10 to 13
    expect($product->fresh()->quantity)->toBe(13);
});

test('16. variant product stock is restored when customer cancels order', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'تيشيرت بمتغيرات',
        'name_en' => 'T-shirt with variants',
        'price' => 80.00,
        'has_variants' => true,
        'quantity' => 0,
        'status' => true,
    ]);

    $color = Color::create(['name_ar' => 'أبيض', 'name_en' => 'White']);
    $size = Size::create(['name' => 'L']);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'quantity' => 4,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-VARIANT-RESTORE',
        'subtotal' => 160.00,
        'shipping' => 10.00,
        'total' => 170.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name_ar' => 'تيشيرت بمتغيرات',
        'product_name_en' => 'T-shirt with variants',
        'variant_details' => [
            'color' => ['id' => $color->id, 'name_ar' => 'أبيض', 'name_en' => 'White'],
            'size' => ['id' => $size->id, 'name' => 'L'],
        ],
        'quantity' => 2,
        'price' => 80.00,
        'total' => 160.00,
    ]);

    expect($variant->fresh()->quantity)->toBe(4);

    $response = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(200);
    // Variant quantity restored from 4 to 6
    expect($variant->fresh()->quantity)->toBe(6);
});

test('17. cancelling twice does not restore stock twice', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج مضاعفة المخزون',
        'name_en' => 'Double Restore Test',
        'price' => 100.00,
        'has_variants' => false,
        'quantity' => 5,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-DOUBLE-CANCEL',
        'subtotal' => 200.00,
        'shipping' => 10.00,
        'total' => 210.00,
        'governorate' => 'القاهرة',
        'address' => 'مدينة نصر',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج مضاعفة المخزون',
        'product_name_en' => 'Double Restore Test',
        'quantity' => 2,
        'price' => 100.00,
        'total' => 200.00,
    ]);

    // First cancel succeeds
    $res1 = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);
    $res1->assertStatus(200);
    expect($product->fresh()->quantity)->toBe(7);

    // Second cancel must fail with 422
    $res2 = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);
    $res2->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'لا يمكن إلغاء الطلب بعد تغيير حالته.',
        ]);

    // Stock must remain 7
    expect($product->fresh()->quantity)->toBe(7);
});

test("18. cannot update or cancel another user's order", function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج مستخدم آخر',
        'name_en' => 'Other User Product',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $otherOrder = Order::create([
        'user_id' => $this->otherUser->id,
        'order_number' => 'ORD-OTHER-CANCEL',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'مدينة نصر',
        'status' => 'pending',
    ]);

    $otherOrder->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج مستخدم آخر',
        'product_name_en' => 'Other User Product',
        'quantity' => 2,
        'price' => 50.00,
        'total' => 100.00,
    ]);

    $response = $this->putJson("/api/update/my/orders/{$otherOrder->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'الطلب غير موجود.',
        ]);

    // Stock must remain unchanged
    expect($product->fresh()->quantity)->toBe(10);
    expect($otherOrder->fresh()->status)->toBe('pending');
});

test('19. cannot cancel confirmed order', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-CONFIRMED',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'confirmed',
    ]);

    $response = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'لا يمكن إلغاء الطلب بعد تغيير حالته.',
        ]);
});

test('20. cannot cancel processing order', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-PROCESSING',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'processing',
    ]);

    $response = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'لا يمكن إلغاء الطلب بعد تغيير حالته.',
        ]);
});

test('21. cannot cancel shipped order', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-SHIPPED',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'shipped',
    ]);

    $response = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'لا يمكن إلغاء الطلب بعد تغيير حالته.',
        ]);
});

test('22. cannot cancel delivered order', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-DELIVERED',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'delivered',
    ]);

    $response = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'لا يمكن إلغاء الطلب بعد تغيير حالته.',
        ]);
});

test('23. cannot cancel already cancelled order', function () {
    Sanctum::actingAs($this->user);

    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-ALREADY-CANCELLED',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'cancelled',
    ]);

    $response = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'لا يمكن إلغاء الطلب بعد تغيير حالته.',
        ]);
});

// ========================================
// AUTHENTICATION & ADMIN SYNC (24 - 25)
// ========================================

test('24. guest cannot access the three endpoints', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-GUEST',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    $this->getJson('/api/my/orders')->assertStatus(401);
    $this->getJson("/api/show/my/orders/{$order->id}")->assertStatus(401);
    $this->putJson("/api/update/my/orders/{$order->id}", ['status' => 'cancelled'])->assertStatus(401);
});

test('25. admin status changes are reflected in customer APIs and lock customer cancellation', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'order_number' => 'ORD-SYNC-TEST',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    // Admin updates status to confirmed
    Sanctum::actingAs($this->admin);
    $adminRes = $this->putJson("/api/dashboard/update/orders/{$order->id}/status", [
        'status' => 'confirmed',
    ]);
    $adminRes->assertStatus(200);

    // Customer logs in and checks my orders
    Sanctum::actingAs($this->user);

    $myOrdersRes = $this->getJson('/api/my/orders');
    $myOrdersRes->assertStatus(200);
    expect($myOrdersRes->json('data.0.status'))->toBe('confirmed');

    // Customer checks show order
    $showOrderRes = $this->getJson("/api/show/my/orders/{$order->id}");
    $showOrderRes->assertStatus(200);
    expect($showOrderRes->json('data.status'))->toBe('confirmed');

    // Customer tries to cancel confirmed order -> 422
    $cancelRes = $this->putJson("/api/update/my/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);
    $cancelRes->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'لا يمكن إلغاء الطلب بعد تغيير حالته.',
        ]);
});

test('26. eager loads user and orderItems.product.images to avoid N+1 queries', function () {
    Sanctum::actingAs($this->user);

    for ($i = 1; $i <= 3; $i++) {
        $order = Order::create([
            'user_id' => $this->user->id,
            'order_number' => "ORD-EAGER-{$i}",
            'subtotal' => 100.00,
            'shipping' => 10.00,
            'total' => 110.00,
            'governorate' => 'القاهرة',
            'address' => 'المعادي',
            'status' => 'pending',
        ]);

        for ($j = 1; $j <= 2; $j++) {
            $product = Product::create([
                'name_ar' => "منتج {$i}-{$j}",
                'name_en' => "Product {$i}-{$j}",
                'price' => 50.00,
                'has_variants' => false,
                'quantity' => 10,
                'status' => true,
            ]);

            ProductImage::create([
                'product_id' => $product->id,
                'image' => "img_{$i}_{$j}.jpg",
            ]);

            $order->orderItems()->create([
                'product_id' => $product->id,
                'product_name_ar' => "منتج {$i}-{$j}",
                'product_name_en' => "Product {$i}-{$j}",
                'quantity' => 1,
                'price' => 50.00,
                'total' => 50.00,
            ]);
        }
    }

    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();

    $response = $this->getJson('/api/my/orders');
    $response->assertStatus(200);

    $queries = \Illuminate\Support\Facades\DB::getQueryLog();
    expect(count($queries))->toBeLessThanOrEqual(8);
});
