// src/api/endpoints.js
// Single source of truth for all API URL constants.
// Keep in sync with backend/api.json (OpenAPI contract).

const EP = {
  // ── Auth ──────────────────────────────────────────────
  AUTH_LOGIN: '/auth/login',
  AUTH_REGISTER: '/auth/register',
  AUTH_LOGOUT: '/auth/logout',
  AUTH_ME: '/me',
  AUTH_FORGOT_PASSWORD: '/auth/forgot-password',
  AUTH_RESET_PASSWORD: '/auth/reset-password',

  // ── Catalog ───────────────────────────────────────────
  CATEGORIES: '/categories',
  BRANDS: '/brands',
  PRODUCTS: '/products',
  PRODUCT_COMPARE: '/products/compare',
  PRODUCT_DETAIL: (id) => `/products/${id}`,
  PRODUCT_REVIEWS: (id) => `/products/${id}/reviews`,
  CATEGORY_ATTRIBUTES: (categoryId) =>
    `/categories/${categoryId}/attributes`,
  ATTRIBUTE_VALUES: (attributeId) =>
    `/attributes/${attributeId}/values`,
  // ── Banners & Homepage ───────────────────────────────────────────
  BANNERS: '/banners',
  HOMEPAGE: '/homepage',

  // ── Cart ──────────────────────────────────────────────
  CART: '/cart',
  CART_ITEMS: '/cart/items',
  CART_ITEM: (id) => `/cart/items/${id}`,
  CART_APPLY_COUPON: '/cart/apply-coupon',
  CART_CLEAR: '/cart/clear',

  // ── Addresses ─────────────────────────────────────────
  ADDRESSES: '/addresses',
  ADDRESS_DETAIL: (id) => `/addresses/${id}`,
  ADDRESS_DEFAULT: (id) => `/addresses/${id}/default`,

  // ── Profile ───────────────────────────────────────────
  PROFILE: '/profile',
  PROFILE_PASSWORD: '/profile/password',

  // ── Orders ────────────────────────────────────────────
  ORDERS: '/orders',
  ORDER_LOOKUP: '/orders/lookup',
  ORDER_DETAIL: (id) => `/orders/${id}`,
  ORDER_CANCEL: (id) => `/orders/${id}/cancel`,
  ORDER_CONFIRM_RECEIVED: (id) => `/orders/${id}/confirm-received`,

  // ── Shipping ──────────────────────────────────────────
  SHIPPING_METHODS: '/shipping-methods',

  // ── Payments ──────────────────────────────────────────
  PAYMENT_CONFIG: '/payments/config',
  PAYMENT_VNPAY_CREATE: '/payments/vnpay/create',
  PAYMENT_VNPAY_RETURN: '/payments/vnpay/return',
  PAYMENT_ZALOPAY_CREATE: '/payments/zalopay/create',
  PAYMENT_ZALOPAY_RETURN: '/payments/zalopay/return',
  PAYMENT_MOMO_CREATE: '/payments/momo/create',
  PAYMENT_MOMO_RETURN: '/payments/momo/return',
  PAYMENT_BANK_CREATE: '/payments/bank-transfer/create',
  PAYMENT_BANK_CONFIRM_PAID: '/payments/bank-transfer/confirm-paid',

  // ── Admin: Orders ─────────────────────────────────────
  ADMIN_ORDERS: '/admin/orders',
  ADMIN_ORDER_DETAIL: (id) => `/admin/orders/${id}`,
  ADMIN_ORDER_STATUS: (id) => `/admin/orders/${id}/status`,
  ADMIN_CONFIRM_BANK_TRANSFER: (id) => `/admin/orders/${id}/confirm-bank-transfer`,
  ADMIN_CONFIRM_PAYMENT_REFUND: (paymentId) => `/admin/payments/${paymentId}/confirm-refund`,
  ADMIN_REQUEST_PAYMENT_REFUND: (paymentId) => `/admin/payments/${paymentId}/refund`,
  ADMIN_REFRESH_PAYMENT_REFUND: (paymentId) => `/admin/payments/${paymentId}/refund/status`,
  ADMIN_SIMULATE_BANK_PAYMENT: '/admin/payments/bank-transfer/simulate',
  ADMIN_SIMULATE_MOMO_PAYMENT: '/admin/payments/momo/simulate',

  // ── Admin: Coupons ────────────────────────────────────
  ADMIN_COUPONS: '/admin/coupons',
  ADMIN_COUPON_DETAIL: (id) => `/admin/coupons/${id}`,
  ADMIN_FLASH_SALES: '/admin/flash-sales',
  ADMIN_FLASH_SALE_DETAIL: (id) => `/admin/flash-sales/${id}`,
  ADMIN_FLASH_SALE_VARIANTS: '/admin/flash-sales/variant-options',

  // ── Admin: Banners ────────────────────────────────────
  ADMIN_BANNERS: '/admin/banners',
  ADMIN_BANNER_DETAIL: (id) => `/admin/banners/${id}`,
  ADMIN_BANNER_UPLOAD: '/admin/banners/upload-image',

  // ── Admin: Homepage CMS ────────────────────────────────
  ADMIN_HOMEPAGE: '/admin/homepage',
  ADMIN_HOMEPAGE_SECTION: (id) => `/admin/homepage/sections/${id}`,
  ADMIN_HOMEPAGE_REORDER: '/admin/homepage/reorder',
  ADMIN_HOMEPAGE_PUBLISH: '/admin/homepage/publish',
  ADMIN_HOMEPAGE_REVERT: '/admin/homepage/revert',
  ADMIN_HOMEPAGE_RESET_DEFAULTS: '/admin/homepage/reset-defaults',
  ADMIN_HOMEPAGE_PREVIEW: '/admin/homepage/preview',
  ADMIN_HOMEPAGE_AUDIT_LOGS: '/admin/homepage/audit-logs',

  // ── Admin: Reviews ────────────────────────────────────
  ADMIN_REVIEWS: '/admin/reviews',
  ADMIN_REVIEW_STATUS: (id) => `/admin/reviews/${id}/status`,
  
  // ── Admin: Returns ────────────────────────────────────
  ADMIN_RETURNS: '/admin/returns',
  ADMIN_RETURN_DETAIL: (id) => `/admin/returns/${id}`,
  ADMIN_RETURN_APPROVE: (id) => `/admin/returns/${id}/approve`,
  ADMIN_RETURN_REJECT: (id) => `/admin/returns/${id}/reject`,
  ADMIN_RETURN_RECEIVE: (id) => `/admin/returns/${id}/receive`,
  ADMIN_RETURN_INSPECT: (id) => `/admin/returns/${id}/inspect`,
  ADMIN_RETURN_COMPLETE: (id) => `/admin/returns/${id}/complete`,


  // ── Admin: Dashboard ──────────────────────────────────
  ADMIN_DASHBOARD_STATS: '/admin/dashboard/stats',
  ADMIN_DASHBOARD_REVENUE_CHART: '/admin/dashboard/revenue-chart',

  // ── Admin: Products ───────────────────────────────────
  ADMIN_PRODUCTS: '/admin/products',
  ADMIN_PRODUCT_DETAIL: (id) => `/admin/products/${id}`,
  ADMIN_PRODUCTS_BULK_STATUS: '/admin/products/bulk-status',
  ADMIN_PRODUCTS_BULK_DELETE: '/admin/products/bulk-delete',

  // ── Admin: Inventories ────────────────────────────────
  INVENTORIES: '/inventories',
  INVENTORY_IMPORT: '/inventories/import',
  INVENTORY_EXPORT: '/inventories/export',
  INVENTORY_LOW_STOCK: '/inventories/low-stock',
  INVENTORY_TRANSACTIONS: '/inventories/transactions',

  // ── Admin: Users & Roles ──────────────────────────────
  ADMIN_USERS: '/admin/users',
  ADMIN_USER_DETAIL: (id) => `/admin/users/${id}`,
  ADMIN_USER_CREATE: '/admin/users',
  ADMIN_USER_UPDATE: (id) => `/admin/users/${id}`,
  ADMIN_USER_PASSWORD_RESET: (id) => `/admin/users/${id}/password`,
  ADMIN_USER_STATUS: (id) => `/admin/users/${id}/status`,
  ADMIN_USER_ROLES: (id) => `/admin/users/${id}/roles`,
  ADMIN_USER_ROLE_DELETE: (id, roleSlug) => `/admin/users/${id}/roles/${roleSlug}`,
  ADMIN_ROLES: '/admin/roles',
  ADMIN_ROLE_PERMISSIONS_SYNC: (roleId) => `/admin/roles/${roleId}/permissions`,
  ADMIN_PERMISSIONS: '/admin/permissions',

  // ── Admin: Spec Definitions ───────────────────────────
  SPEC_DEFINITIONS: '/admin/spec-definitions',
  SPEC_DEFINITIONS_BY_ID: (id) => `/admin/spec-definitions/${id}`,

  // ── Admin: Product Specifications ─────────────────────
  ADMIN_PRODUCT_SPECIFICATIONS: (id) => `/admin/products/${id}/specifications`,

  // ── Product Images ─────────────────────────────────────
  PRODUCT_IMAGES: '/product-images',
  PRODUCT_IMAGE_DETAIL: (id) => `/product-images/${id}`,
  PRODUCT_IMAGE_REORDER: '/product-images/reorder',
  PRODUCT_IMAGE_PRIMARY: (id) => `/product-images/${id}/primary`,
  ADMIN_PRODUCT_IMAGES: '/admin/product-images',
  ADMIN_PRODUCT_IMAGE_DETAIL: (id) => `/admin/product-images/${id}`,
  ADMIN_PRODUCT_IMAGE_REORDER: '/admin/product-images/reorder',
  ADMIN_PRODUCT_IMAGE_PRIMARY: (id) => `/admin/product-images/${id}/primary`,

  // ── Admin: Omnisearch / Command Palette ─────────────────
  ADMIN_OMNISEARCH: '/admin/omnisearch',

  // ── Admin: Trash / Recycle Bin ─────────────────────────
  ADMIN_TRASH_SUMMARY: '/admin/trash/summary',
  ADMIN_TRASH_LIST: (type) => `/admin/trash/${type}`,
  ADMIN_TRASH_RESTORE: (type, id) => `/admin/trash/${type}/${id}/restore`,
  ADMIN_TRASH_RESTORE_ALL: (type) => `/admin/trash/restore-all/${type}`,
  ADMIN_TRASH_FORCE_DELETE: (type, id) => `/admin/trash/${type}/${id}`,
  ADMIN_TRASH_EMPTY: (type) => `/admin/trash/empty/${type}`,
}

export default EP
