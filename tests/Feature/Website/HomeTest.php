<?php

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Support\Facades\DB;

test('1. GET /api/home/products returns only active products ordered by latest first', function () {
    $activeProduct1 = Product::create([
        'name_ar' => 'منتج نشط 1',
        'name_en' => 'Active Product 1',
        'price' => 100.00,
        'status' => true,
        'has_variants' => false,
        'quantity' => 10,
    ]);
    $activeProduct1->created_at = now()->subMinutes(10);
    $activeProduct1->save();

    $activeProduct2 = Product::create([
        'name_ar' => 'منتج نشط 2',
        'name_en' => 'Active Product 2',
        'price' => 150.00,
        'status' => true,
        'has_variants' => false,
        'quantity' => 5,
    ]);
    $activeProduct2->created_at = now()->subMinutes(5);
    $activeProduct2->save();

    $inactiveProduct = Product::create([
        'name_ar' => 'منتج غير نشط',
        'name_en' => 'Inactive Product',
        'price' => 200.00,
        'status' => false,
        'has_variants' => false,
        'quantity' => 2,
    ]);

    $response = $this->getJson('/api/home/products');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب المنتجات بنجاح.',
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    // Latest first: activeProduct2 was created after activeProduct1
    expect($data[0]['id'])->toBe($activeProduct2->id);
    expect($data[1]['id'])->toBe($activeProduct1->id);

    // Inactive product is not present
    $ids = collect($data)->pluck('id')->all();
    expect($ids)->not->toContain($inactiveProduct->id);
});

test('2. GET /api/home/categories returns only active categories ordered by latest first', function () {
    $cat1 = Category::create([
        'name_ar' => 'قسم نشط 1',
        'name_en' => 'Active Category 1',
        'status' => true,
    ]);
    $cat1->created_at = now()->subMinutes(10);
    $cat1->save();

    $cat2 = Category::create([
        'name_ar' => 'قسم نشط 2',
        'name_en' => 'Active Category 2',
        'status' => true,
    ]);
    $cat2->created_at = now()->subMinutes(5);
    $cat2->save();

    $inactiveCat = Category::create([
        'name_ar' => 'قسم غير نشط',
        'name_en' => 'Inactive Category',
        'status' => false,
    ]);

    $response = $this->getJson('/api/home/categories');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب الأقسام بنجاح.',
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    expect($data[0]['id'])->toBe($cat2->id);
    expect($data[1]['id'])->toBe($cat1->id);

    $ids = collect($data)->pluck('id')->all();
    expect($ids)->not->toContain($inactiveCat->id);
});

test('3. GET /api/home/offers returns only active products with old_price ordered by latest first', function () {
    $offerProduct1 = Product::create([
        'name_ar' => 'عرض 1',
        'name_en' => 'Offer 1',
        'price' => 80.00,
        'old_price' => 100.00,
        'status' => true,
        'has_variants' => false,
        'quantity' => 10,
    ]);
    $offerProduct1->created_at = now()->subMinutes(10);
    $offerProduct1->save();

    $offerProduct2 = Product::create([
        'name_ar' => 'عرض 2',
        'name_en' => 'Offer 2',
        'price' => 120.00,
        'old_price' => 150.00,
        'status' => true,
        'has_variants' => false,
        'quantity' => 5,
    ]);
    $offerProduct2->created_at = now()->subMinutes(2);
    $offerProduct2->save();

    $response = $this->getJson('/api/home/offers');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب المنتجات التي عليها عروض بنجاح.',
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    expect($data[0]['id'])->toBe($offerProduct2->id);
    expect($data[1]['id'])->toBe($offerProduct1->id);
    expect($data[0]['old_price'])->toEqual(150.0);
    expect($data[0]['discount_percentage'])->toBe('20%');
});

test('4. inactive products are excluded from /api/home/products', function () {
    Product::create([
        'name_ar' => 'منتج معطل',
        'name_en' => 'Disabled Product',
        'price' => 50.00,
        'status' => false,
        'has_variants' => false,
    ]);

    $response = $this->getJson('/api/home/products');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeEmpty();
});

test('5. inactive categories are excluded from /api/home/categories', function () {
    Category::create([
        'name_ar' => 'قسم معطل',
        'name_en' => 'Disabled Category',
        'status' => false,
    ]);

    $response = $this->getJson('/api/home/categories');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeEmpty();
});

test('6. products without old_price or inactive are excluded from offers', function () {
    // Active product but WITHOUT old_price
    Product::create([
        'name_ar' => 'منتج بدون خصم',
        'name_en' => 'Regular Product',
        'price' => 100.00,
        'old_price' => null,
        'status' => true,
        'has_variants' => false,
    ]);

    // Inactive product WITH old_price
    Product::create([
        'name_ar' => 'عرض غير نشط',
        'name_en' => 'Inactive Offer',
        'price' => 70.00,
        'old_price' => 100.00,
        'status' => false,
        'has_variants' => false,
    ]);

    $response = $this->getJson('/api/home/offers');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeEmpty();
});

test('7. the endpoints are accessible without authentication', function () {
    $this->getJson('/api/home/products')->assertStatus(200);
    $this->getJson('/api/home/categories')->assertStatus(200);
    $this->getJson('/api/home/offers')->assertStatus(200);
});

test('8. verify relationships and data returned by ProductResource are present in home products and offers', function () {
    $category = Category::create([
        'name_ar' => 'إلكترونيات',
        'name_en' => 'Electronics',
        'status' => true,
    ]);

    $product = Product::create([
        'name_ar' => 'هاتف ذكي',
        'name_en' => 'Smartphone',
        'description_ar' => 'وصف بالعربي',
        'description_en' => 'English description',
        'price' => 800.00,
        'old_price' => 1000.00,
        'status' => true,
        'has_variants' => true,
    ]);

    $product->categories()->attach($category->id);

    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'phone_front.jpg',
    ]);

    $color = Color::create(['name_ar' => 'أسود', 'name_en' => 'Black']);
    $size = Size::create(['name' => '128GB']);

    ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'quantity' => 15,
        'status' => true,
    ]);

    // Test in /api/home/products
    $prodResponse = $this->getJson('/api/home/products');
    $prodResponse->assertStatus(200);
    $prodData = $prodResponse->json('data.0');

    expect($prodData['name_ar'])->toBe('هاتف ذكي');
    expect($prodData['name_en'])->toBe('Smartphone');
    expect($prodData['price'])->toEqual(800.0);
    expect($prodData['old_price'])->toEqual(1000.0);
    expect($prodData['discount_percentage'])->toBe('20%');
    expect($prodData['has_variants'])->toBeTrue();

    // Verify categories
    expect($prodData['categories'])->toHaveCount(1);
    expect($prodData['categories'][0]['id'])->toBe($category->id);
    expect($prodData['categories'][0]['name_ar'])->toBe('إلكترونيات');

    // Verify images
    expect($prodData['images'])->toHaveCount(1);
    expect($prodData['images'][0]['image'])->toBe(asset('img/products/phone_front.jpg'));

    // Verify variants with color and size
    expect($prodData['variants'])->toHaveCount(1);
    expect($prodData['variants'][0]['quantity'])->toBe(15);
    expect($prodData['variants'][0]['color']['name_ar'])->toBe('أسود');
    expect($prodData['variants'][0]['size']['name'])->toBe('128GB');

    // Test in /api/home/offers
    $offerResponse = $this->getJson('/api/home/offers');
    $offerResponse->assertStatus(200);
    $offerData = $offerResponse->json('data.0');
    expect($offerData['id'])->toBe($product->id);
    expect($offerData['categories'])->toHaveCount(1);
    expect($offerData['images'])->toHaveCount(1);
    expect($offerData['variants'])->toHaveCount(1);
});

test('9. eager loading prevents N+1 queries on home products and offers', function () {
    for ($i = 1; $i <= 3; $i++) {
        $category = Category::create([
            'name_ar' => "قسم {$i}",
            'name_en' => "Category {$i}",
            'status' => true,
        ]);

        $prod = Product::create([
            'name_ar' => "منتج {$i}",
            'name_en' => "Product {$i}",
            'price' => 100.00,
            'old_price' => 120.00,
            'status' => true,
            'has_variants' => true,
        ]);

        $prod->categories()->attach($category->id);

        ProductImage::create([
            'product_id' => $prod->id,
            'image' => "img_{$i}.jpg",
        ]);

        $color = Color::create(['name_ar' => "لون {$i}", 'name_en' => "Color {$i}"]);
        $size = Size::create(['name' => "مقاس {$i}"]);

        ProductVariant::create([
            'product_id' => $prod->id,
            'color_id' => $color->id,
            'size_id' => $size->id,
            'quantity' => 10,
            'status' => true,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson('/api/home/products')->assertStatus(200);

    $queries = DB::getQueryLog();
    // With eager loading (categories, images, variants.color, variants.size),
    // query count should be a small constant number (<= 6 queries), not proportional to product count.
    expect(count($queries))->toBeLessThanOrEqual(6);
});
