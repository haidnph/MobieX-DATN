<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Profile\ProfileController;
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

/*
 * Profile endpoints.
 *
 * All routes require:
 * - Sanctum authentication.
 * - An active account.
 */
Route::middleware([
    'auth:sanctum',
    'active',
])->group(function (): void {
    Route::get(
        '/profile',
        [ProfileController::class, 'show'],
    )->name('api.profile.show');

    Route::patch(
        '/profile',
        [ProfileController::class, 'update'],
    )->name('api.profile.update');
});

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

/*
 * Admin user and RBAC read-only endpoints.
 *
 * All routes in this group require:
 * - Sanctum authentication.
 * - An active account.
 * - The users.view permission.
 */
Route::prefix('admin')
    ->name('api.admin.')
    ->middleware([
        'auth:sanctum',
        'active',
        'permission:users.view',
    ])
    ->group(function (): void {
        Route::get(
            '/roles',
            [RoleController::class, 'index'],
        )->name('roles.index');

        Route::get(
            '/permissions',
            [PermissionController::class, 'index'],
        )->name('permissions.index');

        Route::get(
            '/users',
            [UserController::class, 'index'],
        )->name('users.index');

        Route::get(
            '/users/{user}',
            [UserController::class, 'show'],
        )
            ->whereNumber('user')
            ->name('users.show');
    });

/*
 * Admin user management endpoints.
 *
 * All routes in this group require:
 * - Sanctum authentication.
 * - An active account.
 * - The users.manage permission.
 */
Route::prefix('admin')
    ->name('api.admin.')
    ->middleware([
        'auth:sanctum',
        'active',
        'permission:users.manage',
    ])
    ->group(function (): void {
        Route::post(
            '/users/{user}/roles',
            [UserRoleController::class, 'store'],
        )
            ->whereNumber('user')
            ->name('users.roles.store');

        Route::delete(
            '/users/{user}/roles/{roleSlug}',
            [UserRoleController::class, 'destroy'],
        )
            ->whereNumber('user')
            ->name('users.roles.destroy');

        Route::patch(
            '/users/{user}/status',
            [UserStatusController::class, 'update'],
        )
            ->whereNumber('user')
            ->name('users.status.update');
    });
