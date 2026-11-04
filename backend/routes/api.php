<?php

use App\Http\Controllers\Addresses\AddressController;
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
use App\Http\Controllers\Catalog\AttributeController;
use App\Http\Controllers\Catalog\AttributeValueController;
use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\CategoryAttributeController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => (string) config('app.name', 'MobieX API'),
    ]);
})->name('api.health');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

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

Route::middleware([
    'auth:sanctum',
    'active',
])->get(
    '/me',
    CurrentUserController::class
)->name('api.me');

/*
|--------------------------------------------------------------------------
| Public Catalog Read Endpoints
|--------------------------------------------------------------------------
*/

Route::get(
    '/categories',
    [CategoryController::class, 'index'],
)->name('api.categories.index');

Route::get(
    '/categories/{id}',
    [CategoryController::class, 'show'],
)
    ->whereNumber('id')
    ->name('api.categories.show');

/*
 * Public Category Attribute assignments.
 *
 * Returns active attributes directly assigned to the category.
 * No parent-to-child inheritance is performed.
 */
Route::get(
    '/categories/{categoryId}/attributes',
    [CategoryAttributeController::class, 'index'],
)
    ->whereNumber('categoryId')
    ->name('api.categories.attributes.index');

Route::get(
    '/brands',
    [BrandController::class, 'index'],
)->name('api.brands.index');

Route::get(
    '/brands/{id}',
    [BrandController::class, 'show'],
)
    ->whereNumber('id')
    ->name('api.brands.show');

Route::get(
    '/attributes',
    [AttributeController::class, 'index'],
)->name('api.attributes.index');

Route::get(
    '/attributes/{id}',
    [AttributeController::class, 'show'],
)
    ->whereNumber('id')
    ->name('api.attributes.show');

/*
|--------------------------------------------------------------------------
| Public Attribute Value Read Endpoint
|--------------------------------------------------------------------------
*/

Route::get(
    '/attributes/{attribute}/values',
    [AttributeValueController::class, 'index'],
)
    ->whereNumber('attribute')
    ->name('api.attributes.values.index');

/*
|--------------------------------------------------------------------------
| Attribute and Category Attribute Management
|--------------------------------------------------------------------------
|
| All write endpoints in this group require:
| - Sanctum authentication.
| - An active user account.
| - attributes.manage permission.
|
*/

Route::middleware([
    'auth:sanctum',
    'active',
    'permission:attributes.manage',
])->group(function (): void {
    // Attribute management

    Route::post(
        '/attributes',
        [AttributeController::class, 'store'],
    )->name('api.attributes.store');

    Route::patch(
        '/attributes/{id}',
        [AttributeController::class, 'update'],
    )
        ->whereNumber('id')
        ->name('api.attributes.update');

    Route::delete(
        '/attributes/{id}',
        [AttributeController::class, 'destroy'],
    )
        ->whereNumber('id')
        ->name('api.attributes.destroy');

    // AttributeValue management

    Route::post(
        '/attributes/{attribute}/values',
        [AttributeValueController::class, 'store'],
    )
        ->whereNumber('attribute')
        ->name('api.attributes.values.store');

    Route::patch(
        '/attributes/{attribute}/values/{value}',
        [AttributeValueController::class, 'update'],
    )
        ->whereNumber('attribute')
        ->whereNumber('value')
        ->scopeBindings()
        ->name('api.attributes.values.update');

    Route::delete(
        '/attributes/{attribute}/values/{value}',
        [AttributeValueController::class, 'destroy'],
    )
        ->whereNumber('attribute')
        ->whereNumber('value')
        ->scopeBindings()
        ->name('api.attributes.values.destroy');

    // Replace all direct category attribute assignments.

    Route::put(
        '/categories/{categoryId}/attributes',
        [CategoryAttributeController::class, 'sync'],
    )
        ->whereNumber('categoryId')
        ->name('api.categories.attributes.sync');
});

/*
|--------------------------------------------------------------------------
| Category Write Endpoints
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active',
    'permission:categories.manage',
])->group(function (): void {
    Route::post(
        '/categories',
        [CategoryController::class, 'store'],
    )->name('api.categories.store');

    Route::patch(
        '/categories/{id}',
        [CategoryController::class, 'update'],
    )
        ->whereNumber('id')
        ->name('api.categories.update');

    Route::delete(
        '/categories/{id}',
        [CategoryController::class, 'destroy'],
    )
        ->whereNumber('id')
        ->name('api.categories.destroy');
});

/*
|--------------------------------------------------------------------------
| Brand Management Endpoints
|--------------------------------------------------------------------------
|
| All brand write endpoints require:
| - Sanctum authentication.
| - An active user account.
| - brands.manage permission.
|
*/

Route::middleware([
    'auth:sanctum',
    'active',
    'permission:brands.manage',
])->group(function (): void {
    Route::post(
        '/brands',
        [BrandController::class, 'store'],
    )->name('api.brands.store');

    Route::patch(
        '/brands/{id}',
        [BrandController::class, 'update'],
    )
        ->whereNumber('id')
        ->name('api.brands.update');

    Route::delete(
        '/brands/{id}',
        [BrandController::class, 'destroy'],
    )
        ->whereNumber('id')
        ->name('api.brands.destroy');
});

/*
|--------------------------------------------------------------------------
| Profile and Address Book
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active',
])->group(function (): void {
    // Profile

    Route::get(
        '/profile',
        [ProfileController::class, 'show'],
    )->name('api.profile.show');

    Route::patch(
        '/profile',
        [ProfileController::class, 'update'],
    )->name('api.profile.update');

    Route::patch(
        '/profile/password',
        [ProfileController::class, 'changePassword'],
    )->name('api.profile.password.update');

    // Address book

    Route::get(
        '/addresses',
        [AddressController::class, 'index'],
    )->name('api.addresses.index');

    Route::post(
        '/addresses',
        [AddressController::class, 'store'],
    )->name('api.addresses.store');

    Route::patch(
        '/addresses/{id}/default',
        [AddressController::class, 'setDefault'],
    )
        ->whereNumber('id')
        ->name('api.addresses.default');

    Route::patch(
        '/addresses/{id}',
        [AddressController::class, 'update'],
    )
        ->whereNumber('id')
        ->name('api.addresses.update');

    Route::delete(
        '/addresses/{id}',
        [AddressController::class, 'destroy'],
    )
        ->whereNumber('id')
        ->name('api.addresses.destroy');
});

/*
|--------------------------------------------------------------------------
| Password Reset
|--------------------------------------------------------------------------
*/

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
|--------------------------------------------------------------------------
| Admin User and RBAC Read-Only Endpoints
|--------------------------------------------------------------------------
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
|--------------------------------------------------------------------------
| Admin User Management Endpoints
|--------------------------------------------------------------------------
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
