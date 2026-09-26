<?php

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Size;
use App\Models\User;
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

test('10. GET /api/home/show/products/{id} returns product details with categories, images, variants, and reviews', function () {
    $category = Category::create([
        'name_ar' => 'ملابس',
        'name_en' => 'Clothing',
        'status' => true,
    ]);

    $product = Product::create([
        'name_ar' => 'تيشيرت قطن معدل',
        'name_en' => 'Updated Cotton T-Shirt',
        'description_ar' => 'وصف المنتج بالعربية',
        'description_en' => 'English description',
        'price' => 450.00,
        'old_price' => 500.00,
        'status' => true,
        'has_variants' => true,
    ]);

    $product->categories()->attach($category->id);

    ProductImage::create([
        'product_id' => $product->id,
        'image' => 'tshirt.jpg',
    ]);

    $color = Color::create(['name_ar' => 'أبيض', 'name_en' => 'White']);
    $size = Size::create(['name' => 'L']);

    ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'quantity' => 20,
        'status' => true,
    ]);

    $user = User::factory()->create([
        'role' => 'user',
        'name' => 'Ahmed',
        'image' => 'user_123.jpg',
    ]);

    Review::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'rating' => 5,
        'comment' => 'المنتج ممتاز جدًا',
        'status' => true,
    ]);

    $response = $this->getJson("/api/home/show/products/{$product->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب المنتج بنجاح.',
            'data' => [
                'id' => $product->id,
                'name_ar' => 'تيشيرت قطن معدل',
                'name_en' => 'Updated Cotton T-Shirt',
                'price' => 450,
                'old_price' => 500,
                'discount_percentage' => '10%',
                'has_variants' => true,
                'reviews' => [
                    [
                        'rating' => 5,
                        'comment' => 'المنتج ممتاز جدًا',
                        'user' => [
                            'id' => $user->id,
                            'name' => 'Ahmed',
                            'image' => asset('img/users/user_123.jpg'),
                        ],
                    ],
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data['categories'])->toHaveCount(1);
    expect($data['images'])->toHaveCount(1);
    expect($data['variants'])->toHaveCount(1);
    expect($data['variants'][0]['color']['name_ar'])->toBe('أبيض');
    expect($data['variants'][0]['size']['name'])->toBe('L');
});

test('11. active reviews are included and inactive reviews are excluded from showProduct', function () {
    $product = Product::create([
        'name_ar' => 'منتج تجربة',
        'name_en' => 'Test Product',
        'price' => 100.00,
        'status' => true,
        'has_variants' => false,
    ]);

    $user1 = User::factory()->create(['role' => 'user', 'name' => 'Active Reviewer']);
    $user2 = User::factory()->create(['role' => 'user', 'name' => 'Inactive Reviewer']);

    Review::create([
        'user_id' => $user1->id,
        'product_id' => $product->id,
        'rating' => 5,
        'comment' => 'تقييم نشط',
        'status' => true,
    ]);

    Review::create([
        'user_id' => $user2->id,
        'product_id' => $product->id,
        'rating' => 1,
        'comment' => 'تقييم غير نشط',
        'status' => false,
    ]);

    $response = $this->getJson("/api/home/show/products/{$product->id}");

    $response->assertStatus(200);
    $reviews = $response->json('data.reviews');
    expect($reviews)->toHaveCount(1);
    expect($reviews[0]['comment'])->toBe('تقييم نشط');
});

test('12. reviews in showProduct include only safe user fields and exclude sensitive fields', function () {
    $product = Product::create([
        'name_ar' => 'منتج',
        'name_en' => 'Product',
        'price' => 50.00,
        'status' => true,
        'has_variants' => false,
    ]);

    $user = User::factory()->create([
        'role' => 'user',
        'name' => 'Reviewer User',
        'image' => 'avatar.png',
    ]);

    Review::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'rating' => 4,
        'comment' => 'تعليق جيد',
        'status' => true,
    ]);

    $response = $this->getJson("/api/home/show/products/{$product->id}");

    $response->assertStatus(200);
    $review = $response->json('data.reviews.0');

    expect($review)->toHaveKeys(['id', 'user', 'rating', 'comment']);
    expect($review['user'])->toHaveKeys(['id', 'name', 'image']);
    expect($review)->not->toHaveKey('status');
    expect($review['user'])->not->toHaveKey('password');
    expect($review['user'])->not->toHaveKey('email');
});

test('13. showProduct returns reviews as an empty array when product has no reviews', function () {
    $product = Product::create([
        'name_ar' => 'منتج بدون تقييمات',
        'name_en' => 'Product Without Reviews',
        'price' => 80.00,
        'status' => true,
        'has_variants' => false,
    ]);

    $response = $this->getJson("/api/home/show/products/{$product->id}");

    $response->assertStatus(200);
    expect($response->json('data.reviews'))->toBeArray();
    expect($response->json('data.reviews'))->toBeEmpty();
    expect($response->json('data.reviews'))->not->toBeNull();
});

test('14. reviews in showProduct are ordered latest first', function () {
    $product = Product::create([
        'name_ar' => 'منتج ترتيب التقييمات',
        'name_en' => 'Product Ordering',
        'price' => 90.00,
        'status' => true,
        'has_variants' => false,
    ]);

    $user1 = User::factory()->create(['role' => 'user']);
    $user2 = User::factory()->create(['role' => 'user']);

    $review1 = Review::create([
        'user_id' => $user1->id,
        'product_id' => $product->id,
        'rating' => 3,
        'comment' => 'أول تقييم أقدم',
        'status' => true,
    ]);
    $review1->created_at = now()->subMinutes(10);
    $review1->save();

    $review2 = Review::create([
        'user_id' => $user2->id,
        'product_id' => $product->id,
        'rating' => 5,
        'comment' => 'ثاني تقييم أحدث',
        'status' => true,
    ]);
    $review2->created_at = now()->subMinutes(1);
    $review2->save();

    $response = $this->getJson("/api/home/show/products/{$product->id}");

    $response->assertStatus(200);
    $reviews = $response->json('data.reviews');
    expect($reviews)->toHaveCount(2);
    expect($reviews[0]['id'])->toBe($review2->id);
    expect($reviews[1]['id'])->toBe($review1->id);
});

test('15. non-existing product returns 404 with Arabic message on showProduct', function () {
    $response = $this->getJson('/api/home/show/products/999999');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'المنتج غير موجود.',
        ]);
});

test('16. the old GET /api/products/{id} route no longer exists', function () {
    $response = $this->getJson('/api/products/1');

    expect($response->status())->toBe(404);
});

test('17. showProduct does not cause N+1 queries because reviews.user and relations are eager loaded', function () {
    $category = Category::create(['name_ar' => 'قسم', 'name_en' => 'Cat', 'status' => true]);
    $color = Color::create(['name_ar' => 'لون', 'name_en' => 'Color']);
    $size = Size::create(['name' => 'مقاس']);

    $product = Product::create([
        'name_ar' => 'منتج N+1',
        'name_en' => 'Product N+1',
        'price' => 150.00,
        'status' => true,
        'has_variants' => true,
    ]);
    $product->categories()->attach($category->id);
    ProductImage::create(['product_id' => $product->id, 'image' => 'img.jpg']);
    ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'quantity' => 5,
        'status' => true,
    ]);

    for ($i = 1; $i <= 5; $i++) {
        $u = User::factory()->create(['role' => 'user']);
        Review::create([
            'user_id' => $u->id,
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => "تعليق {$i}",
            'status' => true,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->getJson("/api/home/show/products/{$product->id}");
    $response->assertStatus(200);

    $queries = DB::getQueryLog();
    // Eager loads categories, images, variants.color, variants.size, reviews.user (8 queries constant regardless of review count)
    expect(count($queries))->toBeLessThanOrEqual(8);
});

