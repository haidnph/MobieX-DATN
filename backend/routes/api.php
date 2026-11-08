<?php

use App\Http\Controllers\Addresses\AddressController;
use App\Http\Controllers\Admin\AdminBannerController;
use App\Http\Controllers\Admin\AdminCouponController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ProductSpecificationController;
use App\Http\Controllers\Api\ReviewController;
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
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
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
    '/banners',
    [BannerController::class, 'index'],
)->name('api.banners.index');

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

Route::get(
    '/products',
    [ProductController::class, 'index'],
)->name('api.products.index');
Route::get(
    '/products/{id}',
    [ProductController::class, 'show'],
)->whereNumber('id')
    ->name('api.products.show');

Route::get(
    '/products/{id}/reviews',
    [ReviewController::class, 'index'],
)->whereNumber('id')
    ->name('api.products.reviews.index');

Route::get(
    '/product-variants',
    [ProductVariantController::class, 'index'],
)->name('api.product-variants.index');

Route::get(
    '/product-variants/{id}',
    [ProductVariantController::class, 'show'],
)->whereNumber('id')
    ->name('api.product-variants.show');

Route::get(
    '/product-images',
    [ProductImageController::class, 'index'],
)->name('api.product-images.index');
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
| Product, Variant and Image Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active',
])->group(function (): void {
    // Products
    Route::post(
        '/products',
        [ProductController::class, 'store'],
    )->name('api.products.store');

    Route::patch(
        '/products/{id}',
        [ProductController::class, 'update'],
    )->whereNumber('id')
        ->name('api.products.update');

    Route::delete(
        '/products/{id}',
        [ProductController::class, 'destroy'],
    )->whereNumber('id')
        ->name('api.products.destroy');

    // Product variants
    Route::post(
        '/product-variants',
        [ProductVariantController::class, 'store'],
    )->name('api.product-variants.store');

    Route::patch(
        '/product-variants/{id}',
        [ProductVariantController::class, 'update'],
    )->whereNumber('id')
        ->name('api.product-variants.update');

    Route::delete(
        '/product-variants/{id}',
        [ProductVariantController::class, 'destroy'],
    )->whereNumber('id')
        ->name('api.product-variants.destroy');

    // Product images
    Route::post(
        '/product-images',
        [ProductImageController::class, 'store'],
    )->name('api.product-images.store');

    Route::match(
        ['put', 'patch'],
        '/product-images/{id}',
        [ProductImageController::class, 'update'],
    )->whereNumber('id')
        ->name('api.product-images.update');

    Route::delete(
        '/product-images/{id}',
        [ProductImageController::class, 'destroy'],
    )->whereNumber('id')
        ->name('api.product-images.destroy');

    // Product specifications
    Route::get(
        '/product-specifications',
        [ProductSpecificationController::class, 'index'],
    )->name('api.product-specifications.index');

    Route::get(
        '/product-specifications/{id}',
        [ProductSpecificationController::class, 'show'],
    )->whereNumber('id')
        ->name('api.product-specifications.show');

    Route::post(
        '/product-specifications',
        [ProductSpecificationController::class, 'store'],
    )->name('api.product-specifications.store');

    Route::put(
        '/product-specifications/{id}',
        [ProductSpecificationController::class, 'update'],
    )->whereNumber('id')
        ->name('api.product-specifications.update');

    Route::delete(
        '/product-specifications/{id}',
        [ProductSpecificationController::class, 'destroy'],
    )->whereNumber('id')
        ->name('api.product-specifications.destroy');

    // Quản lý tồn kho
    Route::get('/inventories', [InventoryController::class, 'index'])
        ->name('api.inventories.index');

    Route::post('/inventories/import', [InventoryController::class, 'import'])
        ->name('api.inventories.import');

    Route::post('/inventories/export', [InventoryController::class, 'export'])
        ->name('api.inventories.export');

    Route::get('/inventories/transactions', [InventoryController::class, 'transactions'])
        ->name('api.inventories.transactions');

    Route::get('/inventories/low-stock', [InventoryController::class, 'lowStock'])
        ->name('api.inventories.low-stock');
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
    // Cart
    Route::get(
        '/cart',
        [CartController::class, 'index'],
    )->name('api.cart.index');

    Route::post(
        '/cart/items',
        [CartController::class, 'store'],
    )->name('api.cart.items.store');

    Route::put(
        '/cart/items/{id}',
        [CartController::class, 'update'],
    )->whereNumber('id')
        ->name('api.cart.items.update');

    Route::delete(
        '/cart/items/{id}',
        [CartController::class, 'destroy'],
    )->whereNumber('id')
        ->name('api.cart.items.destroy');

    Route::post(
        '/cart/apply-coupon',
        [CouponController::class, 'applyCoupon'],
    )->name('api.cart.apply-coupon');

    // Orders
    Route::get(
        '/orders',
        [OrderController::class, 'index'],
    )->name('api.orders.index');

    Route::get(
        '/orders/{id}',
        [OrderController::class, 'show'],
    )->whereNumber('id')
        ->name('api.orders.show');

    Route::post(
        '/orders',
        [OrderController::class, 'store'],
    )->name('api.orders.store');

    Route::match(['patch', 'post'], '/orders/{id}/cancel', [OrderController::class, 'cancel'])
        ->whereNumber('id')
        ->name('api.orders.cancel');

    // Product review submission
    Route::post('/products/{id}/reviews', [ReviewController::class, 'store'])
        ->whereNumber('id')
        ->name('api.products.reviews.store');

    // Online Payments
    Route::post('/payments/vnpay/create', [PaymentController::class, 'createVNPayPayment'])
        ->name('api.payments.vnpay.create');
});

/*
|--------------------------------------------------------------------------
| Payment Callbacks & Webhooks
|--------------------------------------------------------------------------
*/

Route::get('/payments/vnpay/return', [PaymentController::class, 'vnpayReturn'])
    ->name('api.payments.vnpay.return');

Route::get('/payments/vnpay/ipn', [PaymentController::class, 'vnpayIpn'])
    ->name('api.payments.vnpay.ipn');

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

/*
|--------------------------------------------------------------------------
| Admin Orders Management Endpoints
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->name('api.admin.')
    ->middleware([
        'auth:sanctum',
        'active',
    ])
    ->group(function (): void {
        Route::middleware('permission:orders.view')->group(function (): void {
            Route::get(
                '/orders',
                [AdminOrderController::class, 'index'],
            )->name('orders.index');

            Route::get(
                '/orders/{id}',
                [AdminOrderController::class, 'show'],
            )
                ->whereNumber('id')
                ->name('orders.show');
        });

        Route::middleware('permission:orders.manage')->group(function (): void {
            Route::patch(
                '/orders/{id}/status',
                [AdminOrderController::class, 'updateStatus'],
            )
                ->whereNumber('id')
                ->name('orders.status.update');
        });

        // Admin Coupon Management
        Route::middleware('permission:coupons.view')->group(function (): void {
            Route::get(
                '/coupons',
                [AdminCouponController::class, 'index'],
            )->name('coupons.index');

            Route::get(
                '/coupons/{id}',
                [AdminCouponController::class, 'show'],
            )
                ->whereNumber('id')
                ->name('coupons.show');
        });

        Route::middleware('permission:coupons.manage')->group(function (): void {
            Route::post(
                '/coupons',
                [AdminCouponController::class, 'store'],
            )->name('coupons.store');

            Route::patch(
                '/coupons/{id}',
                [AdminCouponController::class, 'update'],
            )
                ->whereNumber('id')
                ->name('coupons.update');

            Route::delete(
                '/coupons/{id}',
                [AdminCouponController::class, 'destroy'],
            )
                ->whereNumber('id')
                ->name('coupons.destroy');
        });

        // Admin Banner Management
        Route::middleware('permission:banners.view')->group(function (): void {
            Route::get(
                '/banners',
                [AdminBannerController::class, 'index'],
            )->name('banners.index');

            Route::get(
                '/banners/{id}',
                [AdminBannerController::class, 'show'],
            )
                ->whereNumber('id')
                ->name('banners.show');
        });

        Route::middleware('permission:banners.manage')->group(function (): void {
            Route::post(
                '/banners',
                [AdminBannerController::class, 'store'],
            )->name('banners.store');

            Route::patch(
                '/banners/{id}',
                [AdminBannerController::class, 'update'],
            )
                ->whereNumber('id')
                ->name('banners.update');

            Route::delete(
                '/banners/{id}',
                [AdminBannerController::class, 'destroy'],
            )
                ->whereNumber('id')
                ->name('banners.destroy');
        });

        // Admin Review Management
        Route::middleware('permission:reviews.view')->group(function (): void {
            Route::get(
                '/reviews',
                [AdminReviewController::class, 'index'],
            )->name('reviews.index');

            Route::get(
                '/reviews/{id}',
                [AdminReviewController::class, 'show'],
            )
                ->whereNumber('id')
                ->name('reviews.show');
        });

        Route::middleware('permission:reviews.moderate')->group(function (): void {
            Route::patch(
                '/reviews/{id}/status',
                [AdminReviewController::class, 'updateStatus'],
            )
                ->whereNumber('id')
                ->name('reviews.status.update');
        });

        // Admin Dashboard & Reports
        Route::middleware('permission:dashboard.view')->group(function (): void {
            Route::get(
                '/dashboard/stats',
                [AdminDashboardController::class, 'stats'],
            )->name('dashboard.stats');

            Route::get(
                '/dashboard/revenue-chart',
                [AdminDashboardController::class, 'revenueChart'],
            )->name('dashboard.revenue-chart');
        });
    });
