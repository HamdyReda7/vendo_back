<?php

use App\Models\Color;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->customer = User::factory()->create([
        'role' => 'user',
    ]);
});

test('1. admin can list orders', function () {
    Sanctum::actingAs($this->admin);

    Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-111111111111',
        'subtotal' => 100.00,
        'shipping' => 20.00,
        'total' => 120.00,
        'governorate' => 'القاهرة',
        'address' => 'مدينة نصر',
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/dashboard/orders');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب الطلبات بنجاح.',
        ]);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.order_number'))->toBe('ORD-111111111111');
});

test('2. index pagination is 5 per page', function () {
    Sanctum::actingAs($this->admin);

    for ($i = 1; $i <= 7; $i++) {
        Order::create([
            'user_id' => $this->customer->id,
            'order_number' => 'ORD-TEST0000000' . $i,
            'subtotal' => 50.00 * $i,
            'shipping' => 10.00,
            'total' => (50.00 * $i) + 10.00,
            'governorate' => 'القاهرة',
            'address' => 'عنوان ' . $i,
            'status' => 'pending',
        ]);
    }

    $response = $this->getJson('/api/dashboard/orders');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('pagination.per_page'))->toBe(5);
    expect($response->json('pagination.total'))->toBe(7);
    expect($response->json('pagination.last_page'))->toBe(2);
    expect($response->json('pagination.current_page'))->toBe(1);
});

test('3. index includes customer information', function () {
    Sanctum::actingAs($this->admin);

    Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-CUSTOMERINFO',
        'subtotal' => 150.00,
        'shipping' => 15.00,
        'total' => 165.00,
        'governorate' => 'الجيزة',
        'address' => 'الدقي',
        'delivery_phone' => '01099999999',
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/dashboard/orders');

    $response->assertStatus(200);
    $order = $response->json('data.0');

    expect($order['user'])->toMatchArray([
        'id' => $this->customer->id,
        'name' => $this->customer->name,
        'email' => $this->customer->email,
        'phone' => $this->customer->phone,
    ]);

    // Ensure password / sensitive tokens are not exposed
    expect($order['user'])->not->toHaveKey('password');
    expect($order['user'])->not->toHaveKey('remember_token');
});

test('4. admin can show an order', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'منتج تجريبي',
        'name_en' => 'Test Product',
        'price' => 75.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-SHOWTEST01',
        'subtotal' => 150.00,
        'shipping' => 20.00,
        'total' => 170.00,
        'governorate' => 'الإسكندرية',
        'address' => 'ميامي',
        'delivery_phone' => '01234567890',
        'note' => 'ملاحظة خاصة',
        'status' => 'confirmed',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج تجريبي',
        'product_name_en' => 'Test Product',
        'quantity' => 2,
        'price' => 75.00,
        'total' => 150.00,
    ]);

    $response = $this->getJson("/api/dashboard/orders/{$order->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب الطلب بنجاح.',
            'data' => [
                'id' => $order->id,
                'order_number' => 'ORD-SHOWTEST01',
                'subtotal' => 150.00,
                'shipping' => 20.00,
                'total' => 170.00,
                'governorate' => 'الإسكندرية',
                'address' => 'ميامي',
                'delivery_phone' => '01234567890',
                'note' => 'ملاحظة خاصة',
                'status' => 'confirmed',
            ],
        ]);

    expect($response->json('data.items'))->toHaveCount(1);
    expect($response->json('data.items.0.product_id'))->toBe($product->id);
});

test('5. show includes customer information and non-existing returns 404', function () {
    Sanctum::actingAs($this->admin);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-SHOWCUST01',
        'subtotal' => 80.00,
        'shipping' => 10.00,
        'total' => 90.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    $response = $this->getJson("/api/dashboard/orders/{$order->id}");

    $response->assertStatus(200);
    expect($response->json('data.user'))->toMatchArray([
        'id' => $this->customer->id,
        'name' => $this->customer->name,
        'email' => $this->customer->email,
        'phone' => $this->customer->phone,
    ]);

    // Test non-existing order returns 404 with Arabic message
    $resNotFound = $this->getJson('/api/dashboard/orders/999999');
    $resNotFound->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'الطلب غير موجود.',
        ]);
});

test('6. non-admin authenticated user cannot access admin orders', function () {
    Sanctum::actingAs($this->customer);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-FORBIDDEN01',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'status' => 'pending',
    ]);

    $this->getJson('/api/dashboard/orders')->assertStatus(403);
    $this->getJson("/api/dashboard/orders/{$order->id}")->assertStatus(403);
    $this->putJson("/api/dashboard/orders/{$order->id}/status", ['status' => 'confirmed'])->assertStatus(403);
});

test('7. guest cannot access admin orders', function () {
    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-GUEST01',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'status' => 'pending',
    ]);

    $this->getJson('/api/dashboard/orders')->assertStatus(401);
    $this->getJson("/api/dashboard/orders/{$order->id}")->assertStatus(401);
    $this->putJson("/api/dashboard/orders/{$order->id}/status", ['status' => 'confirmed'])->assertStatus(401);
});

test('8. admin can update order status', function () {
    Sanctum::actingAs($this->admin);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-STATUS01',
        'subtotal' => 100.00,
        'shipping' => 15.00,
        'total' => 115.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان أصلي',
        'status' => 'pending',
    ]);

    $response = $this->putJson("/api/dashboard/orders/{$order->id}/status", [
        'status' => 'confirmed',
        'subtotal' => 1.00, // Should be ignored
        'total' => 1.00,    // Should be ignored
        'address' => 'عنوان تم التلاعب به', // Should be ignored
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح.',
            'data' => [
                'id' => $order->id,
                'status' => 'confirmed',
                'subtotal' => 100.00,
                'total' => 115.00,
                'address' => 'عنوان أصلي',
            ],
        ]);

    $order->refresh();
    expect($order->status)->toBe('confirmed');
    expect($order->subtotal)->toEqual(100.00);
    expect($order->total)->toEqual(115.00);
    expect($order->address)->toBe('عنوان أصلي');
});

test('9. invalid status is rejected', function () {
    Sanctum::actingAs($this->admin);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-INVALIDSTATUS',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'status' => 'pending',
    ]);

    $response = $this->putJson("/api/dashboard/orders/{$order->id}/status", [
        'status' => 'unknown_status',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($order->fresh()->status)->toBe('pending');
});

test('10. admin cannot delete orders because no delete endpoint exists', function () {
    Sanctum::actingAs($this->admin);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-NODELETE',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'status' => 'pending',
    ]);

    $response = $this->deleteJson("/api/dashboard/orders/{$order->id}");

    // Should be 405 Method Not Allowed
    expect(in_array($response->status(), [404, 405]))->toBeTrue();
    expect(Order::where('id', $order->id)->exists())->toBeTrue();
});

test('11. cancelling an order restores stock', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'منتج',
        'name_en' => 'Product',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-CANCELSTOCK01',
        'subtotal' => 150.00,
        'shipping' => 10.00,
        'total' => 160.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج',
        'product_name_en' => 'Product',
        'quantity' => 3,
        'price' => 50.00,
        'total' => 150.00,
    ]);

    $response = $this->putJson("/api/dashboard/orders/{$order->id}/status", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(200);
    expect($order->fresh()->status)->toBe('cancelled');
    // Stock restored from 10 to 13
    expect($product->fresh()->quantity)->toBe(13);
});

test('12. cancelling an already-cancelled order does NOT restore stock twice', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'منتج',
        'name_en' => 'Product',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-DOUBLECANCEL',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج',
        'product_name_en' => 'Product',
        'quantity' => 2,
        'price' => 50.00,
        'total' => 100.00,
    ]);

    // First cancel: stock goes from 10 to 12
    $this->putJson("/api/dashboard/orders/{$order->id}/status", [
        'status' => 'cancelled',
    ])->assertStatus(200);

    expect($product->fresh()->quantity)->toBe(12);

    // Second cancel: stock MUST remain 12
    $this->putJson("/api/dashboard/orders/{$order->id}/status", [
        'status' => 'cancelled',
    ])->assertStatus(200);

    expect($product->fresh()->quantity)->toBe(12);
});

test('13. simple product stock restoration works', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'بسيط',
        'name_en' => 'Simple',
        'price' => 20.00,
        'has_variants' => false,
        'quantity' => 5,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-SIMPLERESTORE',
        'subtotal' => 80.00,
        'shipping' => 10.00,
        'total' => 90.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'status' => 'processing',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_variant_id' => null,
        'product_name_ar' => 'بسيط',
        'product_name_en' => 'Simple',
        'quantity' => 4,
        'price' => 20.00,
        'total' => 80.00,
    ]);

    $this->putJson("/api/dashboard/orders/{$order->id}/status", [
        'status' => 'cancelled',
    ])->assertStatus(200);

    expect($product->fresh()->quantity)->toBe(9);
});

test('14. variant stock restoration works', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'منتج بمتغيرات',
        'name_en' => 'Variant Product',
        'price' => 100.00,
        'has_variants' => true,
        'status' => true,
    ]);

    $color = Color::create(['name_ar' => 'أحمر', 'name_en' => 'Red', 'status' => true]);
    $size = Size::create(['name' => 'M', 'status' => true]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'quantity' => 8,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-VARIANTRESTORE',
        'subtotal' => 200.00,
        'shipping' => 10.00,
        'total' => 210.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'status' => 'shipped',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name_ar' => 'منتج بمتغيرات',
        'product_name_en' => 'Variant Product',
        'quantity' => 3,
        'price' => 100.00,
        'total' => 200.00,
    ]);

    $this->putJson("/api/dashboard/orders/{$order->id}/status", [
        'status' => 'cancelled',
    ])->assertStatus(200);

    expect($variant->fresh()->quantity)->toBe(11);
});

test('15. updating non-cancelled statuses does not modify stock', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'منتج ثابت',
        'name_en' => 'Fixed Product',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 20,
        'status' => true,
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-STOCKINTACT',
        'subtotal' => 100.00,
        'shipping' => 10.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج ثابت',
        'product_name_en' => 'Fixed Product',
        'quantity' => 2,
        'price' => 50.00,
        'total' => 100.00,
    ]);

    $transitions = ['confirmed', 'processing', 'shipped', 'delivered'];

    foreach ($transitions as $status) {
        $this->putJson("/api/dashboard/orders/{$order->id}/status", [
            'status' => $status,
        ])->assertStatus(200);

        expect($product->fresh()->quantity)->toBe(20);
        expect($order->fresh()->status)->toBe($status);
    }
});
