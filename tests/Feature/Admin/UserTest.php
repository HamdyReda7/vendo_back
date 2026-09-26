<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->normalUser = User::factory()->create([
        'role' => 'user',
        'status' => true,
    ]);
});

test('1. admin can get users list', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/dashboard/all/users');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب المستخدمين بنجاح.',
        ]);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($this->normalUser->id);
});

test('2. users are paginated with exactly 5 per page', function () {
    Sanctum::actingAs($this->admin);

    // We already have 1 normal user from beforeEach, let's create 6 more (total 7)
    User::factory()->count(6)->create(['role' => 'user']);

    $response = $this->getJson('/api/dashboard/all/users');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('pagination.per_page'))->toBe(5);
    expect($response->json('pagination.total'))->toBe(7);
    expect($response->json('pagination.current_page'))->toBe(1);
    expect($response->json('pagination.last_page'))->toBe(2);
});

test('3. admin users are excluded from index', function () {
    Sanctum::actingAs($this->admin);

    $anotherAdmin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this->getJson('/api/dashboard/all/users');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->normalUser->id);
    expect($ids)->not->toContain($this->admin->id);
    expect($ids)->not->toContain($anotherAdmin->id);
});

test('4. index returns latest users first', function () {
    Sanctum::actingAs($this->admin);

    $firstUser = User::factory()->create([
        'role' => 'user',
        'name' => 'First User',
    ]);
    $firstUser->created_at = now()->subMinutes(10);
    $firstUser->save();

    $secondUser = User::factory()->create([
        'role' => 'user',
        'name' => 'Second User',
    ]);
    $secondUser->created_at = now()->subMinutes(2);
    $secondUser->save();

    $response = $this->getJson('/api/dashboard/all/users');

    $response->assertStatus(200);
    $data = $response->json('data');

    // Second user was created more recently than first user
    $firstIndex = collect($data)->search(fn ($u) => $u['id'] === $firstUser->id);
    $secondIndex = collect($data)->search(fn ($u) => $u['id'] === $secondUser->id);

    expect($secondIndex)->toBeLessThan($firstIndex);
});

test('5. admin can show a normal user', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson("/api/dashboard/show/users/{$this->normalUser->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم جلب المستخدم بنجاح.',
            'data' => [
                'id' => $this->normalUser->id,
                'name' => $this->normalUser->name,
                'email' => $this->normalUser->email,
                'role' => 'user',
                'status' => true,
            ],
        ]);
});

test('6. showing an admin returns 404', function () {
    Sanctum::actingAs($this->admin);

    $anotherAdmin = User::factory()->create([
        'role' => 'admin',
    ]);

    // Trying to view self or another admin must return 404
    $resSelf = $this->getJson("/api/dashboard/show/users/{$this->admin->id}");
    $resSelf->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'المستخدم غير موجود.',
        ]);

    $resOther = $this->getJson("/api/dashboard/show/users/{$anotherAdmin->id}");
    $resOther->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'المستخدم غير موجود.',
        ]);
});

test('7. showing a non-existing user returns 404', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/dashboard/show/users/999999');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'المستخدم غير موجود.',
        ]);
});

test('8. admin can activate a user', function () {
    Sanctum::actingAs($this->admin);

    $user = User::factory()->create([
        'role' => 'user',
        'status' => false,
    ]);

    $response = $this->postJson("/api/dashboard/update/users/{$user->id}/status", [
        'status' => true,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم تحديث حالة المستخدم بنجاح.',
            'data' => [
                'id' => $user->id,
                'status' => true,
            ],
        ]);

    expect($user->fresh()->status)->toBeTrue();
});

test('9. admin can deactivate a user', function () {
    Sanctum::actingAs($this->admin);

    $user = User::factory()->create([
        'role' => 'user',
        'status' => true,
    ]);

    $response = $this->postJson("/api/dashboard/update/users/{$user->id}/status", [
        'status' => false,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'تم تحديث حالة المستخدم بنجاح.',
            'data' => [
                'id' => $user->id,
                'status' => false,
            ],
        ]);

    expect($user->fresh()->status)->toBeFalse();
});

test('10. invalid status is rejected', function () {
    Sanctum::actingAs($this->admin);

    // Missing status
    $resMissing = $this->postJson("/api/dashboard/update/users/{$this->normalUser->id}/status", []);
    $resMissing->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // Non-boolean status
    $resInvalid = $this->postJson("/api/dashboard/update/users/{$this->normalUser->id}/status", [
        'status' => 'invalid-status',
    ]);
    $resInvalid->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('11. admin user cannot have their status changed', function () {
    Sanctum::actingAs($this->admin);

    $anotherAdmin = User::factory()->create([
        'role' => 'admin',
        'status' => true,
    ]);

    $response = $this->postJson("/api/dashboard/update/users/{$anotherAdmin->id}/status", [
        'status' => false,
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'المستخدم غير موجود.',
        ]);

    expect($anotherAdmin->fresh()->status)->toBeTrue();
});

test('12. unauthenticated users cannot access the APIs', function () {
    $this->getJson('/api/dashboard/all/users')->assertStatus(401);
    $this->getJson("/api/dashboard/show/users/{$this->normalUser->id}")->assertStatus(401);
    $this->postJson("/api/dashboard/update/users/{$this->normalUser->id}/status", ['status' => false])->assertStatus(401);
});

test('13. normal non-admin users cannot access the APIs', function () {
    Sanctum::actingAs($this->normalUser);

    $this->getJson('/api/dashboard/all/users')->assertStatus(403);
    $this->getJson("/api/dashboard/show/users/{$this->normalUser->id}")->assertStatus(403);
    $this->postJson("/api/dashboard/update/users/{$this->normalUser->id}/status", ['status' => false])->assertStatus(403);
});

test('14. password is never returned in the response', function () {
    Sanctum::actingAs($this->admin);

    // Index
    $resIndex = $this->getJson('/api/dashboard/all/users');
    $resIndex->assertStatus(200);
    expect($resIndex->json('data.0'))->not->toHaveKey('password');
    expect($resIndex->json('data.0'))->not->toHaveKey('remember_token');

    // Show
    $resShow = $this->getJson("/api/dashboard/show/users/{$this->normalUser->id}");
    $resShow->assertStatus(200);
    expect($resShow->json('data'))->not->toHaveKey('password');
    expect($resShow->json('data'))->not->toHaveKey('remember_token');

    // Update status
    $resUpdate = $this->postJson("/api/dashboard/update/users/{$this->normalUser->id}/status", ['status' => false]);
    $resUpdate->assertStatus(200);
    expect($resUpdate->json('data'))->not->toHaveKey('password');
    expect($resUpdate->json('data'))->not->toHaveKey('remember_token');
});

test('15. only status is changed by the update endpoint', function () {
    Sanctum::actingAs($this->admin);

    $originalName = $this->normalUser->name;
    $originalEmail = $this->normalUser->email;
    $originalRole = $this->normalUser->role;
    $originalPassword = $this->normalUser->password;
    $originalPhone = $this->normalUser->phone;

    $response = $this->postJson("/api/dashboard/update/users/{$this->normalUser->id}/status", [
        'status' => false,
        'name' => 'Hacked Name',
        'email' => 'hacked@example.com',
        'role' => 'admin',
        'password' => 'newpassword123',
        'phone' => '01999999999',
    ]);

    $response->assertStatus(200);

    $freshUser = $this->normalUser->fresh();
    expect($freshUser->status)->toBeFalse();
    expect($freshUser->name)->toBe($originalName);
    expect($freshUser->email)->toBe($originalEmail);
    expect($freshUser->role)->toBe($originalRole);
    expect($freshUser->password)->toBe($originalPassword);
    expect($freshUser->phone)->toBe($originalPhone);
});
