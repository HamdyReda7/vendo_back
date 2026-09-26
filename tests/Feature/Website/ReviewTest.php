<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create([
        'role' => 'user',
        'name' => 'Ahmed',
        'image' => 'user_123.jpg',
    ]);

    $this->product = Product::create([
        'name_ar' => 'منتج تجريبي',
        'name_en' => 'Test Product',
        'price' => 100.00,
        'status' => true,
        'has_variants' => false,
        'quantity' => 10,
    ]);
});

test('1. authenticated user can create a review', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 5,
        'comment' => 'المنتج ممتاز جدًا',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'تم إضافة التقييم بنجاح.',
            'data' => [
                'rating' => 5,
                'comment' => 'المنتج ممتاز جدًا',
                'user' => [
                    'id' => $this->user->id,
                    'name' => 'Ahmed',
                ],
            ],
        ]);

    expect($response->json('data.id'))->not->toBeNull();
    expect($response->json('data.user.image'))->toBe(asset('img/users/user_123.jpg'));
    expect(Review::where('product_id', $this->product->id)->where('user_id', $this->user->id)->exists())->toBeTrue();
});

test('2. unauthenticated user cannot create a review', function () {
    $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 5,
        'comment' => 'تعليق بدون تسجيل الدخول',
    ]);

    $response->assertStatus(401);
});

test('3. rating accepts values 1 through 5', function () {
    foreach ([1, 2, 3, 4, 5] as $rating) {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
            'rating' => $rating,
            'comment' => "تقييم {$rating} نجوم",
        ]);

        $response->assertStatus(201);
        expect($response->json('data.rating'))->toBe($rating);
    }
});

test('4. rating rejects values below 1', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 0,
        'comment' => 'تقييم غير صحيح',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['rating']);
});

test('5. rating rejects values above 5', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 6,
        'comment' => 'تقييم غير صحيح',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['rating']);
});

test('6. rating must be an integer', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 4.5,
        'comment' => 'تقييم عشري',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['rating']);
});

test('7. comment is required', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 5,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['comment']);
});

test('8. comment has maximum 1000 characters', function () {
    Sanctum::actingAs($this->user);

    $longComment = str_repeat('a', 1001);

    $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 5,
        'comment' => $longComment,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['comment']);
});

test('9. user ID comes from authenticated user and cannot be supplied by the request', function () {
    Sanctum::actingAs($this->user);

    $anotherUser = User::factory()->create(['role' => 'user']);

    $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 4,
        'comment' => 'تقييم جيد',
        'user_id' => $anotherUser->id, // Attempt to spoof user_id
    ]);

    $response->assertStatus(201);
    expect($response->json('data.user.id'))->toBe($this->user->id);

    $review = Review::find($response->json('data.id'));
    expect($review->user_id)->toBe($this->user->id);
    expect($review->user_id)->not->toBe($anotherUser->id);
});

test('10. status is automatically true when creating a review and cannot be set to false by user', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 5,
        'comment' => 'تقييم ممتاز',
        'status' => false, // Attempt to set inactive
    ]);

    $response->assertStatus(201);
    $review = Review::find($response->json('data.id'));
    expect($review->status)->toBeTrue();
});

test('11. user cannot create a second review for the same product', function () {
    Sanctum::actingAs($this->user);

    // First review succeeds
    $res1 = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 5,
        'comment' => 'التقييم الأول',
    ]);
    $res1->assertStatus(201);

    // Second review fails
    $res2 = $this->postJson("/api/products/{$this->product->id}/reviews", [
        'rating' => 4,
        'comment' => 'محاولة تقييم ثانية',
    ]);

    $res2->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'أنت قمت بتقييم هذا المنتج من قبل.',
        ]);
});

test('12. non-existing product returns 404 on review creation', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/products/999999/reviews', [
        'rating' => 5,
        'comment' => 'تقييم منتج وهمي',
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'المنتج غير موجود.',
        ]);
});

test('13. GET /api/home/show/products/{id} returns reviews array', function () {
    $response = $this->getJson("/api/home/show/products/{$this->product->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب المنتج بنجاح.',
        ]);

    expect($response->json('data'))->toHaveKey('reviews');
    expect($response->json('data.reviews'))->toBeArray();
});

test('14. active reviews appear inside Single Product response', function () {
    Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'تقييم نشط للمنتج',
        'status' => true,
    ]);

    $response = $this->getJson("/api/home/show/products/{$this->product->id}");

    $response->assertStatus(200);
    $reviews = $response->json('data.reviews');
    expect($reviews)->toHaveCount(1);
    expect($reviews[0]['comment'])->toBe('تقييم نشط للمنتج');
});

test('15. inactive reviews do NOT appear inside Single Product response', function () {
    $inactiveUser = User::factory()->create(['role' => 'user']);

    // Active review
    Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'تقييم نشط',
        'status' => true,
    ]);

    // Inactive review
    Review::create([
        'user_id' => $inactiveUser->id,
        'product_id' => $this->product->id,
        'rating' => 1,
        'comment' => 'تقييم معطل لن يظهر',
        'status' => false,
    ]);

    $response = $this->getJson("/api/home/show/products/{$this->product->id}");

    $response->assertStatus(200);
    $reviews = $response->json('data.reviews');
    expect($reviews)->toHaveCount(1);
    expect($reviews[0]['comment'])->toBe('تقييم نشط');
});

test('16. reviews include user id, name, image, rating, and comment', function () {
    Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 4,
        'comment' => 'تعليق مفصل',
        'status' => true,
    ]);

    $response = $this->getJson("/api/home/show/products/{$this->product->id}");

    $response->assertStatus(200);
    $review = $response->json('data.reviews.0');

    expect($review)->toMatchArray([
        'rating' => 4,
        'comment' => 'تعليق مفصل',
        'user' => [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'image' => asset('img/users/user_123.jpg'),
        ],
    ]);
    expect($review)->not->toHaveKey('status');
    expect($review['user'])->not->toHaveKey('password');
});

test('17. product with no reviews returns empty reviews array', function () {
    $response = $this->getJson("/api/home/show/products/{$this->product->id}");

    $response->assertStatus(200);
    expect($response->json('data.reviews'))->toBeArray();
    expect($response->json('data.reviews'))->toBeEmpty();
    expect($response->json('data.reviews'))->not->toBeNull();
});

test('18. reviews are ordered latest first in Single Product response', function () {
    $user1 = User::factory()->create(['role' => 'user']);
    $user2 = User::factory()->create(['role' => 'user']);

    $review1 = Review::create([
        'user_id' => $user1->id,
        'product_id' => $this->product->id,
        'rating' => 3,
        'comment' => 'التقييم الأول',
        'status' => true,
    ]);
    $review1->created_at = now()->subMinutes(10);
    $review1->save();

    $review2 = Review::create([
        'user_id' => $user2->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'التقييم الأحدث',
        'status' => true,
    ]);
    $review2->created_at = now()->subMinutes(2);
    $review2->save();

    $response = $this->getJson("/api/home/show/products/{$this->product->id}");

    $response->assertStatus(200);
    $reviews = $response->json('data.reviews');
    expect($reviews)->toHaveCount(2);
    expect($reviews[0]['id'])->toBe($review2->id);
    expect($reviews[1]['id'])->toBe($review1->id);
});

test('19. non-existing product returns 404 for Single Product API', function () {
    $response = $this->getJson('/api/home/show/products/999999');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'المنتج غير موجود.',
        ]);
});

test('20. Single Product does not cause N+1 queries because reviews.user is eager loaded', function () {
    for ($i = 1; $i <= 5; $i++) {
        $u = User::factory()->create(['role' => 'user']);
        Review::create([
            'user_id' => $u->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => "تعليق {$i}",
            'status' => true,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->getJson("/api/home/show/products/{$this->product->id}");
    $response->assertStatus(200);

    $queries = DB::getQueryLog();
    // Eager loads images, categories, variants.color, variants.size, reviews.user.
    // Query count should be small and constant (<= 7 queries), not multiplying per review.
    expect(count($queries))->toBeLessThanOrEqual(7);
});

test('21. GET /api/products/{product_id}/reviews no longer exists', function () {
    $response = $this->getJson("/api/products/{$this->product->id}/reviews");

    // The route was removed completely, so GET should be 405 Method Not Allowed or 404
    expect(in_array($response->status(), [404, 405]))->toBeTrue();
});
