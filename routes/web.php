<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Illuminate\Support\Facades\Auth;

Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => [
        'localize',
        'localizationRedirect',
        'localeSessionRedirect',
        'localeViewPath'
    ]
], function () {


    Route::get('/', function () {
        return Auth::check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    });

    Route::get('/dashboard', function () {
        return view('dashboard.index');
    })->middleware(['auth', 'verified', 'admin'])->name('dashboard');

    /*
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


        // table categories

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories/store', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/edit{id}', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::post('/categories/update', [CategoryController::class, 'update'])->name('categories.update');
        Route::get('/categories/delete{id}', [CategoryController::class, 'delete'])->name('categories.delete');
        Route::get('/categories/show{id}', [CategoryController::class, 'show'])->name('categories.show');


        // table products

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products/store', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/edit{id}', [ProductController::class, 'edit'])->name('products.edit');
        Route::post('/products/update', [ProductController::class, 'update'])->name('products.update');
        Route::get('/products/delete{id}', [ProductController::class, 'delete'])->name('products.delete');
        Route::get('/products/show{id}', [ProductController::class, 'show'])->name('products.show');

        Route::post('/products/image/delete', [ProductController::class, 'deleteImage'])->name('products.image.delete');
    });
    */

    require __DIR__ . '/auth.php';
});
