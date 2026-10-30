<?php

use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;


use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => (string) config('app.name', 'MobieX API'),
    ]);
})->name('api.health');

Route::post(
    '/auth/register',
    RegisterController::class
)->middleware('throttle:auth-register')
    ->name('api.auth.register');

Route::post(
    '/auth/login',
    LoginController::class
)->middleware('throttle:auth-login')
    ->name('api.auth.login');

/*
 * Logout intentionally does not use the "active" middleware.
 * Inactive users must still be able to revoke their own token.
 */
Route::middleware('auth:sanctum')->post(
    '/auth/logout',
    LogoutController::class
)->name('api.auth.logout');

/*
 * GET /api/me requires both authentication and an active account.
 */
Route::middleware([
    'auth:sanctum',
    'active',
])->get(
    '/me',
    CurrentUserController::class
)->name('api.me');

Route::post(
    '/auth/forgot-password',
    [PasswordResetController::class, 'sendResetLink']
)->middleware('throttle:auth-forgot-password')
    ->name('api.auth.password.email');

Route::post(
    '/auth/reset-password',
    [PasswordResetController::class, 'reset']
)->middleware('throttle:auth-reset-password')
    ->name('api.auth.password.reset');


// Quản lý điện thoại
Route::get('/products', [ProductController::class, 'index'])
    ->name('api.products.index');
// API xem chi tiết sản phẩm
Route::get('/products/{id}', [ProductController::class, 'show'])
    ->name('api.products.show');
// API thêm sản phẩm
Route::post('/products', [ProductController::class, 'store'])
    ->name('api.products.store');
// API cập nhật sản phẩm
Route::put('/products/{id}', [ProductController::class, 'update'])
    ->name('api.products.update');
// API xóa sản phẩm
Route::delete('/products/{id}', [ProductController::class, 'destroy'])
    ->name('api.products.destroy');
// API quản lý biến thể sản phẩm
Route::get('/product-variants', [ProductVariantController::class, 'index']);
Route::post('/product-variants', [ProductVariantController::class, 'store']);
Route::get('/product-variants/{id}', [ProductVariantController::class, 'show']);
Route::put('/product-variants/{id}', [ProductVariantController::class, 'update']);
// API xóa biến thể sản phẩm
Route::delete('/product-variants/{id}', [ProductVariantController::class, 'destroy']);