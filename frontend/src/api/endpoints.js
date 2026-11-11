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

  // ── Banners ───────────────────────────────────────────
  BANNERS: '/banners',

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
  ORDER_INVOICE: (id) => `/orders/${id}/invoice`,

  // ── Payments ──────────────────────────────────────────
  PAYMENT_VNPAY_CREATE: '/payments/vnpay/create',
  PAYMENT_VNPAY_RETURN: '/payments/vnpay/return',
  PAYMENT_ZALOPAY_CREATE: '/payments/zalopay/create',
  PAYMENT_ZALOPAY_RETURN: '/payments/zalopay/return',
  PAYMENT_MOMO_CREATE: '/payments/momo/create',
  PAYMENT_MOMO_RETURN: '/payments/momo/return',

  // ── Admin: Orders ─────────────────────────────────────
  ADMIN_ORDERS: '/admin/orders',
  ADMIN_ORDER_DETAIL: (id) => `/admin/orders/${id}`,
  ADMIN_ORDER_STATUS: (id) => `/admin/orders/${id}/status`,

  // ── Admin: Coupons ────────────────────────────────────
  ADMIN_COUPONS: '/admin/coupons',
  ADMIN_COUPON_DETAIL: (id) => `/admin/coupons/${id}`,

  // ── Admin: Banners ────────────────────────────────────
  ADMIN_BANNERS: '/admin/banners',
  ADMIN_BANNER_DETAIL: (id) => `/admin/banners/${id}`,

  // ── Admin: Reviews ────────────────────────────────────
  ADMIN_REVIEWS: '/admin/reviews',
  ADMIN_REVIEW_STATUS: (id) => `/admin/reviews/${id}/status`,

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
  ADMIN_USER_STATUS: (id) => `/admin/users/${id}/status`,
  ADMIN_USER_ROLES: (id) => `/admin/users/${id}/roles`,
  ADMIN_USER_ROLE_DELETE: (id, roleSlug) => `/admin/users/${id}/roles/${roleSlug}`,

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
