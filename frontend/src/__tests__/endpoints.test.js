import { describe, it, expect } from 'vitest'
import EP from '../api/endpoints'

describe('API Endpoints Definition', () => {
  it('contains valid authentication endpoints', () => {
    expect(EP.AUTH_LOGIN).toBe('/auth/login')
    expect(EP.AUTH_REGISTER).toBe('/auth/register')
    expect(EP.AUTH_ME).toBe('/me')
    expect(EP.AUTH_LOGOUT).toBe('/auth/logout')
  })

  it('generates dynamic product detail endpoint correctly', () => {
    expect(EP.PRODUCT_COMPARE).toBe('/products/compare')
    expect(EP.PRODUCT_DETAIL(42)).toBe('/products/42')
  })

  it('generates dynamic order endpoints correctly', () => {
    expect(EP.ORDER_DETAIL(101)).toBe('/orders/101')
    expect(EP.ORDER_CANCEL(101)).toBe('/orders/101/cancel')
  })

  it('generates dynamic admin detail endpoints correctly', () => {
    expect(EP.ADMIN_ORDER_DETAIL(5)).toBe('/admin/orders/5')
    expect(EP.ADMIN_ORDER_STATUS(5)).toBe('/admin/orders/5/status')
    expect(EP.ADMIN_COUPON_DETAIL(3)).toBe('/admin/coupons/3')
    expect(EP.ADMIN_BANNER_DETAIL(2)).toBe('/admin/banners/2')
    expect(EP.ADMIN_PRODUCT_DETAIL(1)).toBe('/admin/products/1')
  })

  it('contains cart and payment endpoints', () => {
    expect(EP.CART).toBe('/cart')
    expect(EP.CART_ITEMS).toBe('/cart/items')
    expect(EP.PAYMENT_VNPAY_CREATE).toBe('/payments/vnpay/create')
    expect(EP.PAYMENT_VNPAY_RETURN).toBe('/payments/vnpay/return')
    expect(EP.PAYMENT_ZALOPAY_CREATE).toBe('/payments/zalopay/create')
    expect(EP.PAYMENT_ZALOPAY_RETURN).toBe('/payments/zalopay/return')
    expect(EP.PAYMENT_MOMO_CREATE).toBe('/payments/momo/create')
    expect(EP.PAYMENT_MOMO_RETURN).toBe('/payments/momo/return')
  })

  it('contains inventory management endpoints', () => {
    expect(EP.INVENTORIES).toBe('/inventories')
    expect(EP.INVENTORY_IMPORT).toBe('/inventories/import')
    expect(EP.INVENTORY_EXPORT).toBe('/inventories/export')
    expect(EP.INVENTORY_LOW_STOCK).toBe('/inventories/low-stock')
    expect(EP.INVENTORY_TRANSACTIONS).toBe('/inventories/transactions')
  })

  it('contains admin user management endpoints', () => {
    expect(EP.ADMIN_USERS).toBe('/admin/users')
    expect(EP.ADMIN_USER_DETAIL(7)).toBe('/admin/users/7')
    expect(EP.ADMIN_USER_STATUS(7)).toBe('/admin/users/7/status')
    expect(EP.ADMIN_USER_ROLES(7)).toBe('/admin/users/7/roles')
    expect(EP.ADMIN_USER_ROLE_DELETE(7, 'admin')).toBe('/admin/users/7/roles/admin')
  })

  it('contains product image management endpoints', () => {
    expect(EP.PRODUCT_IMAGES).toBe('/product-images')
    expect(EP.PRODUCT_IMAGE_DETAIL(5)).toBe('/product-images/5')
    expect(EP.PRODUCT_IMAGE_REORDER).toBe('/product-images/reorder')
    expect(EP.PRODUCT_IMAGE_PRIMARY(5)).toBe('/product-images/5/primary')
  })
})
