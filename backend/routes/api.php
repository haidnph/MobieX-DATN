<?php

use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => (string) config('app.name', 'MobieX API'),
    ]);
})->name('api.health');

Route::post(
    '/auth/register',
    RegisterController::class
)->name('api.auth.register');

Route::post(
    '/auth/login',
    LoginController::class
)->name('api.auth.login');

Route::middleware('auth:sanctum')->post(
    '/auth/logout',
    LogoutController::class
)->name('api.auth.logout');

Route::middleware('auth:sanctum')->get(
    '/me',
    CurrentUserController::class
)->name('api.me');

Route::post(
    '/auth/forgot-password',
    [PasswordResetController::class, 'sendResetLink']
)->name('api.auth.password.email');

Route::post(
    '/auth/reset-password',
    [PasswordResetController::class, 'reset']
)->name('api.auth.password.reset');
