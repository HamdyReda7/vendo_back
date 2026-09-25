<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->customer = User::factory()->create([
        'role' => 'user',
    ]);
});

test('1. order item contains product_image when product exists and has images', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'منتج بصور',
        'name_en' => 'Product with images',
        'price' => 150.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'product_1001.jpg',
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-IMG01',
        'subtotal' => 150.00,
        'shipping' => 10.00,
        'total' => 160.00,
        'governorate' => 'القاهرة',
        'address' => 'مدينة نصر',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج بصور',
        'product_name_en' => 'Product with images',
        'quantity' => 1,
        'price' => 150.00,
        'total' => 150.00,
    ]);

    $response = $this->getJson("/api/dashboard/orders/{$order->id}");

    $response->assertStatus(200);
    $item = $response->json('data.items.0');

    expect($item['product_image'])->toBe(asset('img/products/product_1001.jpg'));
});

test('2. the returned image is the FIRST product image', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'منتج متعدد الصور',
        'name_en' => 'Multi Image Product',
        'price' => 200.00,
        'has_variants' => false,
        'quantity' => 5,
        'status' => true,
    ]);

    // Create 3 images in order
    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'first_primary_image.jpg',
    ]);
    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'second_image.jpg',
    ]);
    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'third_image.jpg',
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-IMG02',
        'subtotal' => 200.00,
        'shipping' => 10.00,
        'total' => 210.00,
        'governorate' => 'الجيزة',
        'address' => 'الدقي',
        'status' => 'confirmed',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج متعدد الصور',
        'product_name_en' => 'Multi Image Product',
        'quantity' => 1,
        'price' => 200.00,
        'total' => 200.00,
    ]);

    $response = $this->getJson("/api/dashboard/orders/{$order->id}");

    $response->assertStatus(200);
    $item = $response->json('data.items.0');

    expect($item['product_image'])->toBe(asset('img/products/first_primary_image.jpg'));
    expect($item['product_image'])->not->toBe(asset('img/products/second_image.jpg'));
});

test('3. product_image is null if the product has no images or no longer exists', function () {
    Sanctum::actingAs($this->admin);

    // Case A: Product exists without images
    $productWithoutImages = Product::create([
        'name_ar' => 'منتج بدون صور',
        'name_en' => 'Product without images',
        'price' => 50.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    // Case B: Product will be deleted
    $productToBeDeleted = Product::create([
        'name_ar' => 'منتج سيحذف',
        'name_en' => 'Product to be deleted',
        'price' => 60.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);
    ProductImage::create([
        'product_id' => $productToBeDeleted->id,
        'image' => 'deleted_prod.jpg',
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-IMG03',
        'subtotal' => 110.00,
        'shipping' => 0.00,
        'total' => 110.00,
        'governorate' => 'القاهرة',
        'address' => 'المعادي',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $productWithoutImages->id,
        'product_name_ar' => 'منتج بدون صور',
        'product_name_en' => 'Product without images',
        'quantity' => 1,
        'price' => 50.00,
        'total' => 50.00,
    ]);

    $itemDeleted = $order->orderItems()->create([
        'product_id' => $productToBeDeleted->id,
        'product_name_ar' => 'منتج سيحذف',
        'product_name_en' => 'Product to be deleted',
        'quantity' => 1,
        'price' => 60.00,
        'total' => 60.00,
    ]);

    // Delete product B
    $productToBeDeleted->delete();

    $response = $this->getJson("/api/dashboard/orders/{$order->id}");

    $response->assertStatus(200);
    $items = $response->json('data.items');

    // Both should safely have product_image as null without throwing an error
    expect($items[0]['product_image'])->toBeNull();
    expect($items[1]['product_image'])->toBeNull();
});

test('4. Admin Orders index includes product_image', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'منتج للإندكس',
        'name_en' => 'Index Product',
        'price' => 80.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'index_image.png',
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-INDEXIMG',
        'subtotal' => 80.00,
        'shipping' => 10.00,
        'total' => 90.00,
        'governorate' => 'الإسكندرية',
        'address' => 'سموحة',
        'status' => 'pending',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج للإندكس',
        'product_name_en' => 'Index Product',
        'quantity' => 1,
        'price' => 80.00,
        'total' => 80.00,
    ]);

    $response = $this->getJson('/api/dashboard/orders');

    $response->assertStatus(200);
    $item = $response->json('data.0.items.0');

    expect($item)->toHaveKey('product_image');
    expect($item['product_image'])->toBe(asset('img/products/index_image.png'));
});

test('5. Admin Orders show includes product_image', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::create([
        'name_ar' => 'منتج للعرض',
        'name_en' => 'Show Product',
        'price' => 120.00,
        'has_variants' => false,
        'quantity' => 10,
        'status' => true,
    ]);

    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'show_image.webp',
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-SHOWIMG',
        'subtotal' => 120.00,
        'shipping' => 15.00,
        'total' => 135.00,
        'governorate' => 'القاهرة',
        'address' => 'شبرا',
        'status' => 'confirmed',
    ]);

    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج للعرض',
        'product_name_en' => 'Show Product',
        'quantity' => 1,
        'price' => 120.00,
        'total' => 120.00,
    ]);

    $response = $this->getJson("/api/dashboard/orders/{$order->id}");

    $response->assertStatus(200);
    $item = $response->json('data.items.0');

    expect($item)->toHaveKey('product_image');
    expect($item['product_image'])->toBe(asset('img/products/show_image.webp'));
});

test('6. no N+1 query issue is introduced by relationship loading', function () {
    Sanctum::actingAs($this->admin);

    // Create 5 orders with 2 items each, each item pointing to a product with 2 images
    for ($i = 1; $i <= 5; $i++) {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'order_number' => 'ORD-NPLUS1-' . $i,
            'subtotal' => 100.00,
            'shipping' => 10.00,
            'total' => 110.00,
            'governorate' => 'القاهرة',
            'address' => 'عنوان',
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
                'image' => "img_{$i}_{$j}_1.jpg",
            ]);
            ProductImage::create([
                'product_id' => $product->id,
                'image' => "img_{$i}_{$j}_2.jpg",
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

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->getJson('/api/dashboard/orders');

    $response->assertStatus(200);

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Without eager loading, 5 orders * 2 items * 2 relationships = 20+ queries.
    // With eager loading (Order::with(['user', 'orderItems.product.images'])),
    // it executes a constant small number of queries (order count query, orders, users, order_items, products, product_images <= 8).
    expect($queryCount)->toBeLessThanOrEqual(8);
});
