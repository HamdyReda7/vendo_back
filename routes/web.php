<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => [
        'localize',
        'localizationRedirect',
        'localeSessionRedirect',
        'localeViewPath',
    ],
], function () {

    Route::get('/', function () {
        return Auth::check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    });

    Route::get('/dashboard', function () {
        return view('dashboard.index');
    })->middleware(['auth', 'verified', 'admin'])->name('dashboard');

    require __DIR__ . '/auth.php';
});
