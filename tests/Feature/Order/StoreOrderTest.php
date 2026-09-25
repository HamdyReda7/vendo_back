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
    $this->user = User::factory()->create();
});

test('1. successful order with simple product', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'قميص أزرق',
        'name_en' => 'Blue Shirt',
        'price' => 120.00,
        'has_variants' => false,
        'quantity' => 15,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
        ],
        'governorate' => 'القاهرة',
        'address' => 'شارع التحرير، الدقي',
        'delivery_phone' => '01012345678',
        'note' => 'يرجى الاتصال قبل الوصول',
        'shipping' => 30.00,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'تم إنشاء الطلب بنجاح.',
            'data' => [
                'subtotal' => 240.00,
                'shipping' => 30.00,
                'total' => 270.00,
                'governorate' => 'القاهرة',
                'address' => 'شارع التحرير، الدقي',
                'delivery_phone' => '01012345678',
                'note' => 'يرجى الاتصال قبل الوصول',
                'status' => 'pending',
            ],
        ]);

    $data = $response->json('data');
    expect($data['order_number'])->toMatch('/^ORD-[A-Z0-9]+$/');
    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['product_id'])->toEqual($product->id);
    expect($data['items'][0]['product_variant_id'])->toBeNull();
    expect($data['items'][0]['product_name_ar'])->toBe('قميص أزرق');
    expect($data['items'][0]['product_name_en'])->toBe('Blue Shirt');
    expect($data['items'][0]['variant_details'])->toBeNull();
    expect($data['items'][0]['quantity'])->toEqual(2);
    expect($data['items'][0]['price'])->toEqual(120.00);
    expect($data['items'][0]['total'])->toEqual(240.00);
});

test('2. successful order with variant product and snapshot', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'حذاء رياضي',
        'name_en' => 'Running Shoes',
        'price' => 250.00,
        'has_variants' => true,
        'status' => true,
    ]);

    $color = Color::create([
        'name_ar' => 'أسود',
        'name_en' => 'Black',
        'status' => true,
    ]);

    $size = Size::create([
        'name' => '42',
        'status' => true,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'quantity' => 8,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 3,
            ],
        ],
        'governorate' => 'الجيزة',
        'address' => 'شارع الهرم',
        'shipping' => 25.00,
    ]);

    $response->assertStatus(201);
    $item = $response->json('data.items.0');

    expect($item['variant_details'])->toEqual([
        'color' => [
            'id' => $color->id,
            'name_ar' => 'أسود',
            'name_en' => 'Black',
        ],
        'size' => [
            'id' => $size->id,
            'name' => '42',
        ],
    ]);
    expect($item['price'])->toEqual(250.00);
    expect($item['quantity'])->toEqual(3);
    expect($item['total'])->toEqual(750.00);
});

test('3. multiple products in one order', function () {
    Sanctum::actingAs($this->user);

    $simpleProduct = Product::create([
        'name_ar' => 'منتج بسيط',
        'name_en' => 'Simple Product',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $variantProduct = Product::create([
        'name_ar' => 'منتج بمتغيرات',
        'name_en' => 'Variant Product',
        'price' => 100.00,
        'has_variants' => true,
        'status' => true,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $variantProduct->id,
        'quantity' => 5,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $simpleProduct->id,
                'quantity' => 2,
            ],
            [
                'product_id' => $variantProduct->id,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ],
        ],
        'governorate' => 'الإسكندرية',
        'address' => 'سموحة',
        'shipping' => 40.00,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'subtotal' => 200.00,
                'shipping' => 40.00,
                'total' => 240.00,
            ],
        ]);

    expect($response->json('data.items'))->toHaveCount(2);
});

test('4. unauthenticated user is rejected', function () {
    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => 1, 'quantity' => 1],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(401);
});

test('5. non-existing product returns 404 with Arabic message', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => 999999, 'quantity' => 1],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'المنتج غير موجود.',
        ]);
});

test('6. inactive product returns 422 with Arabic message', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج غير مفعل',
        'name_en' => 'Inactive Product',
        'price' => 100.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => false,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'هذا المنتج غير متاح حاليًا.',
        ]);
});

test('7. variant belonging to another product returns 422 with Arabic message', function () {
    Sanctum::actingAs($this->user);

    $product1 = Product::create([
        'name_ar' => 'منتج 1',
        'name_en' => 'Product 1',
        'price' => 100.00,
        'has_variants' => true,
        'status' => true,
    ]);

    $product2 = Product::create([
        'name_ar' => 'منتج 2',
        'name_en' => 'Product 2',
        'price' => 120.00,
        'has_variants' => true,
        'status' => true,
    ]);

    $variant2 = ProductVariant::create([
        'product_id' => $product2->id,
        'quantity' => 10,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product1->id,
                'product_variant_id' => $variant2->id,
                'quantity' => 1,
            ],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'الخيار المحدد غير تابع لهذا المنتج.',
        ]);
});

test('8. missing variant for a variant product returns 422 with Arabic message', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج بمتغيرات',
        'name_en' => 'Variant Product',
        'price' => 100.00,
        'has_variants' => true,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
                'quantity' => 1,
            ],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'يرجى تحديد خيار المنتج.',
        ]);
});

test('9. quantity greater than stock returns 422', function () {
    Sanctum::actingAs($this->user);

    $simpleProduct = Product::create([
        'name_ar' => 'منتج محدود',
        'name_en' => 'Limited Product',
        'price' => 100.00,
        'has_variants' => false,
        'quantity' => 3,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $simpleProduct->id,
                'quantity' => 5,
            ],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'الكمية المطلوبة غير متوفرة في المخزون.',
        ]);

    // Ensure order was not created and stock did not change
    expect(Order::count())->toBe(0);
    expect($simpleProduct->fresh()->quantity)->toEqual(3);
});

test('10. correct subtotal calculation', function () {
    Sanctum::actingAs($this->user);

    $p1 = Product::create([
        'name_ar' => 'منتج 1',
        'name_en' => 'P1',
        'price' => 45.50,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $p2 = Product::create([
        'name_ar' => 'منتج 2',
        'name_en' => 'P2',
        'price' => 20.25,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => $p1->id, 'quantity' => 2], // 91.00
            ['product_id' => $p2->id, 'quantity' => 4], // 81.00
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.subtotal'))->toEqual(172.00);
});

test('11. correct shipping handling', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج',
        'name_en' => 'Product',
        'price' => 100.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    // With explicit shipping
    $resWithShipping = $this->postJson('/api/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'governorate' => 'أسوان',
        'address' => 'عنوان',
        'shipping' => 60.50,
    ]);

    $resWithShipping->assertStatus(201);
    expect($resWithShipping->json('data.shipping'))->toEqual(60.50);

    // Without shipping sent (defaults to 0)
    $resWithoutShipping = $this->postJson('/api/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'governorate' => 'أسوان',
        'address' => 'عنوان',
    ]);

    $resWithoutShipping->assertStatus(201);
    expect($resWithoutShipping->json('data.shipping'))->toEqual(0.00);
});

test('12. correct total calculation', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج',
        'name_en' => 'Product',
        'price' => 150.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
        'shipping' => 35.50,
    ]);

    $response->assertStatus(201);
    // subtotal = 300.00, shipping = 35.50, total = 335.50
    expect($response->json('data.subtotal'))->toEqual(300.00);
    expect($response->json('data.shipping'))->toEqual(35.50);
    expect($response->json('data.total'))->toEqual(335.50);
});

test('13. backend ignores frontend price tampering', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج ثمين',
        'name_en' => 'Expensive Product',
        'price' => 1000.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 1.00, // tampered
                'total' => 1.00, // tampered
            ],
        ],
        'subtotal' => 1.00, // tampered
        'total' => 1.00,    // tampered
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.subtotal'))->toEqual(1000.00);
    expect($response->json('data.total'))->toEqual(1000.00);
    expect($response->json('data.items.0.price'))->toEqual(1000.00);
    expect($response->json('data.items.0.total'))->toEqual(1000.00);
});

test('14. stock decreases correctly for both simple and variant products', function () {
    Sanctum::actingAs($this->user);

    $simpleProduct = Product::create([
        'name_ar' => 'منتج بسيط',
        'name_en' => 'Simple',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 20,
        'status' => true,
    ]);

    $variantProduct = Product::create([
        'name_ar' => 'منتج متغير',
        'name_en' => 'Variant',
        'price' => 100.00,
        'has_variants' => true,
        'status' => true,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $variantProduct->id,
        'quantity' => 15,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => $simpleProduct->id, 'quantity' => 4],
            ['product_id' => $variantProduct->id, 'product_variant_id' => $variant->id, 'quantity' => 5],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(201);

    expect($simpleProduct->fresh()->quantity)->toEqual(16);
    expect($variant->fresh()->quantity)->toEqual(10);
});

test('15. OrderItems are created correctly in database', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'ساعة يد',
        'name_en' => 'Watch',
        'price' => 400.00,
        'has_variants' => false,
        'quantity' => 5,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
        'governorate' => 'الجيزة',
        'address' => 'شارع فيصل',
    ]);

    $response->assertStatus(201);
    $orderId = $response->json('data.id');

    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_id' => $product->id,
        'product_name_ar' => 'ساعة يد',
        'product_name_en' => 'Watch',
        'quantity' => 2,
        'price' => 400.00,
        'total' => 800.00,
    ]);
});

test('16. product snapshot is saved correctly and ignores frontend tampering', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'اسم حقيقي بالعربي',
        'name_en' => 'Real Name EN',
        'price' => 80.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'product_name_ar' => 'اسم مزيف',
                'product_name_en' => 'Fake Name',
                'variant_details' => ['fake' => 'details'],
            ],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(201);
    $item = $response->json('data.items.0');

    expect($item['product_name_ar'])->toBe('اسم حقيقي بالعربي');
    expect($item['product_name_en'])->toBe('Real Name EN');
    expect($item['variant_details'])->toBeNull();

    $this->assertDatabaseHas('order_items', [
        'product_name_ar' => 'اسم حقيقي بالعربي',
        'product_name_en' => 'Real Name EN',
        'variant_details' => null,
    ]);
});

test('17. order status starts as pending even if frontend sends different status', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج',
        'name_en' => 'Product',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        'status' => 'completed', // frontend attempts to set completed
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.status'))->toBe('pending');

    $orderId = $response->json('data.id');
    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'status' => 'pending',
    ]);
});

test('18. user ID comes strictly from authenticated user', function () {
    Sanctum::actingAs($this->user);

    $otherUser = User::factory()->create();

    $product = Product::create([
        'name_ar' => 'منتج',
        'name_en' => 'Product',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        'user_id' => $otherUser->id, // frontend attempts to spoof another user
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(201);

    $orderId = $response->json('data.id');
    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'user_id' => $this->user->id,
    ]);
    $this->assertDatabaseMissing('orders', [
        'id' => $orderId,
        'user_id' => $otherUser->id,
    ]);
});

test('variant on non-variant product returns 422', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج عادي',
        'name_en' => 'Simple Product',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => 99,
                'quantity' => 1,
            ],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'الخيار المحدد غير تابع لهذا المنتج.',
        ]);
});

test('inactive variant on active product returns 422', function () {
    Sanctum::actingAs($this->user);

    $product = Product::create([
        'name_ar' => 'منتج',
        'name_en' => 'Product',
        'price' => 50.00,
        'has_variants' => true,
        'status' => true,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'quantity' => 10,
        'status' => false, // inactive
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ],
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'هذا الخيار غير متاح حاليًا.',
        ]);
});

test('validation error returns Arabic messages for missing required fields', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/orders', [
        'items' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items', 'governorate', 'address']);
});

test('transaction rolls back all changes if any item fails', function () {
    Sanctum::actingAs($this->user);

    $p1 = Product::create([
        'name_ar' => 'منتج 1',
        'name_en' => 'P1',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    $p2 = Product::create([
        'name_ar' => 'منتج 2',
        'name_en' => 'P2',
        'price' => 100.00,
        'has_variants' => false,
        'quantity' => 2,
        'status' => true,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => $p1->id, 'quantity' => 5], // valid
            ['product_id' => $p2->id, 'quantity' => 10], // exceeds stock (2)
        ],
        'governorate' => 'القاهرة',
        'address' => 'عنوان',
    ]);

    $response->assertStatus(422);

    expect($p1->fresh()->quantity)->toEqual(10);
    expect($p2->fresh()->quantity)->toEqual(2);
    expect(Order::count())->toBe(0);
    expect(OrderItem::count())->toBe(0);
});

