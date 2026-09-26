<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->user = User::factory()->create([
        'role' => 'user',
        'name' => 'Reviewer',
        'image' => 'reviewer.jpg',
    ]);

    $this->product = Product::create([
        'name_ar' => 'منتج للتقييم',
        'name_en' => 'Review Product',
        'price' => 150.00,
        'status' => true,
        'has_variants' => false,
        'quantity' => 10,
    ]);

    ProductImage::create([
        'product_id' => $this->product->id,
        'image' => 'prod_img_1.jpg',
    ]);
});

test('19. admin can get all reviews', function () {
    Sanctum::actingAs($this->admin);

    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'منتج رائع',
        'status' => true,
    ]);

    $response = $this->getJson('/api/dashboard/all/reviews');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب التقييمات بنجاح.',
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($review->id);
    expect($data[0]['rating'])->toBe(5);
    expect($data[0]['comment'])->toBe('منتج رائع');
    expect($data[0]['status'])->toBeTrue();
    expect($data[0]['user']['id'])->toBe($this->user->id);
    expect($data[0]['product']['id'])->toBe($this->product->id);
    expect($data[0]['product']['name_ar'])->toBe('منتج للتقييم');
});

test('20. admin review list is paginated with exactly 5 per page', function () {
    Sanctum::actingAs($this->admin);

    for ($i = 1; $i <= 7; $i++) {
        $u = User::factory()->create(['role' => 'user']);
        Review::create([
            'user_id' => $u->id,
            'product_id' => $this->product->id,
            'rating' => 4,
            'comment' => "تقييم رقم {$i}",
            'status' => true,
        ]);
    }

    $response = $this->getJson('/api/dashboard/all/reviews');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('pagination.per_page'))->toBe(5);
    expect($response->json('pagination.total'))->toBe(7);
    expect($response->json('pagination.current_page'))->toBe(1);
    expect($response->json('pagination.last_page'))->toBe(2);
});

test('21. admin can see inactive reviews', function () {
    Sanctum::actingAs($this->admin);

    $inactiveReview = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 1,
        'comment' => 'تقييم معطل',
        'status' => false,
    ]);

    $response = $this->getJson('/api/dashboard/all/reviews');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($inactiveReview->id);
    expect($data[0]['status'])->toBeFalse();
});

test('22. admin can show one review', function () {
    Sanctum::actingAs($this->admin);

    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 4,
        'comment' => 'تقييم مفصل للعرض',
        'status' => true,
    ]);

    $response = $this->getJson("/api/dashboard/show/reviews/{$review->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب التقييم بنجاح.',
            'data' => [
                'id' => $review->id,
                'rating' => 4,
                'comment' => 'تقييم مفصل للعرض',
                'status' => true,
                'user' => [
                    'id' => $this->user->id,
                    'name' => 'Reviewer',
                ],
                'product' => [
                    'id' => $this->product->id,
                    'name_ar' => 'منتج للتقييم',
                ],
            ],
        ]);
});

test('23. admin gets 404 for non-existing review', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/dashboard/show/reviews/999999');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'التقييم غير موجود.',
        ]);
});

test('24. admin can activate a review', function () {
    Sanctum::actingAs($this->admin);

    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 3,
        'comment' => 'تفعيل تقييم',
        'status' => false,
    ]);

    $response = $this->postJson("/api/dashboard/update/reviews/{$review->id}/status", [
        'status' => 1,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم تحديث حالة التقييم بنجاح.',
            'data' => [
                'id' => $review->id,
                'status' => true,
            ],
        ]);

    expect($review->fresh()->status)->toBeTrue();
});

test('25. admin can deactivate a review', function () {
    Sanctum::actingAs($this->admin);

    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'تعطيل تقييم',
        'status' => true,
    ]);

    $response = $this->postJson("/api/dashboard/update/reviews/{$review->id}/status", [
        'status' => 0,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم تحديث حالة التقييم بنجاح.',
            'data' => [
                'id' => $review->id,
                'status' => false,
            ],
        ]);

    expect($review->fresh()->status)->toBeFalse();
});

test('26. invalid status is rejected', function () {
    Sanctum::actingAs($this->admin);

    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'اختبار حالة غير صالحة',
        'status' => true,
    ]);

    // Missing status
    $resMissing = $this->postJson("/api/dashboard/update/reviews/{$review->id}/status", []);
    $resMissing->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // Non-boolean status
    $resInvalid = $this->postJson("/api/dashboard/update/reviews/{$review->id}/status", [
        'status' => 'not-boolean',
    ]);
    $resInvalid->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('27. admin status endpoint uses POST only (PUT is not allowed)', function () {
    Sanctum::actingAs($this->admin);

    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'اختبار طريقة POST فقط',
        'status' => true,
    ]);

    // PUT must fail with 405 Method Not Allowed
    $resPut = $this->putJson("/api/dashboard/update/reviews/{$review->id}/status", [
        'status' => 0,
    ]);
    $resPut->assertStatus(405);

    // POST succeeds with 200
    $resPost = $this->postJson("/api/dashboard/update/reviews/{$review->id}/status", [
        'status' => 0,
    ]);
    $resPost->assertStatus(200);
});

test('28. admin cannot modify rating or comment through status endpoint', function () {
    Sanctum::actingAs($this->admin);

    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 2,
        'comment' => 'تعليق أصلي',
        'status' => true,
    ]);

    $response = $this->postJson("/api/dashboard/update/reviews/{$review->id}/status", [
        'status' => 0,
        'rating' => 5,
        'comment' => 'تعليق معدل احتيالاً',
        'user_id' => 99,
        'product_id' => 99,
    ]);

    $response->assertStatus(200);

    $freshReview = $review->fresh();
    expect($freshReview->status)->toBeFalse();
    expect($freshReview->rating)->toBe(2);
    expect($freshReview->comment)->toBe('تعليق أصلي');
    expect($freshReview->user_id)->toBe($this->user->id);
    expect($freshReview->product_id)->toBe($this->product->id);
});

test('29. unauthenticated users cannot access Admin Review APIs', function () {
    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 4,
        'comment' => 'تقييم تجريبي',
        'status' => true,
    ]);

    $this->getJson('/api/dashboard/all/reviews')->assertStatus(401);
    $this->getJson("/api/dashboard/show/reviews/{$review->id}")->assertStatus(401);
    $this->postJson("/api/dashboard/update/reviews/{$review->id}/status", ['status' => 0])->assertStatus(401);
});

test('30. normal users cannot access Admin Review APIs', function () {
    Sanctum::actingAs($this->user);

    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 4,
        'comment' => 'تقييم تجريبي',
        'status' => true,
    ]);

    $this->getJson('/api/dashboard/all/reviews')->assertStatus(403);
    $this->getJson("/api/dashboard/show/reviews/{$review->id}")->assertStatus(403);
    $this->postJson("/api/dashboard/update/reviews/{$review->id}/status", ['status' => 0])->assertStatus(403);
});

test('31. password is never returned in admin review responses', function () {
    Sanctum::actingAs($this->admin);

    $review = Review::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'لا تحتوي على كلمة مرور',
        'status' => true,
    ]);

    // Index
    $resIndex = $this->getJson('/api/dashboard/all/reviews');
    $resIndex->assertStatus(200);
    expect($resIndex->json('data.0.user'))->not->toHaveKey('password');
    expect($resIndex->json('data.0.user'))->not->toHaveKey('remember_token');

    // Show
    $resShow = $this->getJson("/api/dashboard/show/reviews/{$review->id}");
    $resShow->assertStatus(200);
    expect($resShow->json('data.user'))->not->toHaveKey('password');
    expect($resShow->json('data.user'))->not->toHaveKey('remember_token');

    // Update status
    $resUpdate = $this->postJson("/api/dashboard/update/reviews/{$review->id}/status", ['status' => 0]);
    $resUpdate->assertStatus(200);
    expect($resUpdate->json('data.user'))->not->toHaveKey('password');
    expect($resUpdate->json('data.user'))->not->toHaveKey('remember_token');
});
