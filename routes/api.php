<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\ColorController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\SizeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Website\OrderController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

/*
 * |--------------------------------------------------------------------------
 * | Authenticated User APIs
 * |--------------------------------------------------------------------------
 */

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Customer Orders API
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/my/orders', [OrderController::class, 'myOrders']);
    Route::get('/show/my/orders/{id}', [OrderController::class, 'show']);
    Route::put('/update/my/orders/{id}', [OrderController::class, 'update']);

    /*
     * |--------------------------------------------------------------------------
     * | Dashboard / Admin APIs
     * |--------------------------------------------------------------------------
     */

    Route::middleware('admin')->prefix('dashboard')->group(function () {
        Route::post('/create/categories', [CategoryController::class, 'store']);
        Route::get('/all/categories', [CategoryController::class, 'index']);
        Route::get('/show/categories/{id}', [CategoryController::class, 'show']);
        Route::post('/update/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/delete/categories/{id}', [CategoryController::class, 'destroy']);

        Route::post('/create/products', [ProductController::class, 'store']);
        Route::get('/all/products', [ProductController::class, 'index']);
        Route::get('/products/offers', [ProductController::class, 'offers']);
        Route::get('/show/products/{id}', [ProductController::class, 'show']);
        Route::post('/update/products/{id}', [ProductController::class, 'update']);
        Route::delete('/delete/products/{id}', [ProductController::class, 'destroy']);

        Route::get('/all/colors', [ColorController::class, 'index']);
        Route::post('/create/colors', [ColorController::class, 'store']);
        Route::delete('/delete/colors/{id}', [ColorController::class, 'destroy']);

        Route::get('/all/sizes', [SizeController::class, 'index']);
        Route::post('/create/sizes', [SizeController::class, 'store']);
        Route::delete('/delete/sizes/{id}', [SizeController::class, 'destroy']);

        Route::get('/all/orders', [AdminOrderController::class, 'index']);
        Route::get('/show/orders/{id}', [AdminOrderController::class, 'show']);
        Route::put('/update/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    });
});
