// src/routes/AppRoutes.jsx
// Central routing definition for the MobieX SPA.
// Public routes, customer-protected routes, and admin-protected routes.

import { lazy, Suspense } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import StorefrontLayout from '../layouts/StorefrontLayout'
import AdminLayout from '../layouts/AdminLayout'
import ProtectedRoute from './ProtectedRoute'
import PermissionRoute from './PermissionRoute'
import Spinner from '../components/common/Spinner'

// ── Lazy imports ──────────────────────────────────────────────────────────────
// Auth
const LoginPage      = lazy(() => import('../features/auth/LoginPage'))
const RegisterPage   = lazy(() => import('../features/auth/RegisterPage'))

// Catalog / Storefront
const HomePage           = lazy(() => import('../features/catalog/HomePage'))
const ProductListPage    = lazy(() => import('../features/catalog/ProductListPage'))
const ProductDetailPage  = lazy(() => import('../features/catalog/ProductDetailPage'))
const ProductComparePage = lazy(() => import('../features/catalog/ProductComparePage'))
const WishlistPage       = lazy(() => import('../features/catalog/WishlistPage'))

// Cart & Checkout
const CartPage          = lazy(() => import('../features/cart/CartPage'))
const CheckoutPage      = lazy(() => import('../features/checkout/CheckoutPage'))
const VNPayPage         = lazy(() => import('../features/checkout/VNPayPage'))
const PaymentReturnPage = lazy(() => import('../features/checkout/PaymentReturnPage'))
const ZaloPayPage       = lazy(() => import('../features/checkout/ZaloPayPage'))
const ZaloPayReturnPage = lazy(() => import('../features/checkout/ZaloPayReturnPage'))
const MoMoPage          = lazy(() => import('../features/checkout/MoMoPage'))
const MoMoReturnPage    = lazy(() => import('../features/checkout/MoMoReturnPage'))

// Orders
const OrderHistoryPage = lazy(() => import('../features/orders/OrderHistoryPage'))
const OrderDetailPage  = lazy(() => import('../features/orders/OrderDetailPage'))
const OrderInvoicePage = lazy(() => import('../features/orders/OrderInvoicePage'))

// Account
const ProfilePage      = lazy(() => import('../features/account/ProfilePage'))
const AddressBookPage  = lazy(() => import('../features/account/AddressBookPage'))

// Admin
const AdminDashboard       = lazy(() => import('../features/admin/AdminDashboard'))
const AdminOrderListPage   = lazy(() => import('../features/admin/AdminOrderListPage'))
const AdminOrderDetailPage = lazy(() => import('../features/admin/AdminOrderDetailPage'))
const AdminProductPage     = lazy(() => import('../features/admin/AdminProductPage'))
const AdminCategoryPage    = lazy(() => import('../features/admin/AdminCategoryPage'))
const AdminBrandPage       = lazy(() => import('../features/admin/AdminBrandPage'))
const AdminInventoryPage   = lazy(() => import('../features/admin/AdminInventoryPage'))
const AdminUserPage        = lazy(() => import('../features/admin/AdminUserPage'))
const AdminCouponPage      = lazy(() => import('../features/admin/AdminCouponPage'))
const AdminReviewPage      = lazy(() => import('../features/admin/AdminReviewPage'))
const AdminBannerPage      = lazy(() => import('../features/admin/AdminBannerPage'))
const AdminSpecPage        = lazy(() => import('../features/admin/AdminSpecPage'))

// ── Loading fallback ───────────────────────────────────────────────────────────
function PageLoader() {
  return (
    <div className="flex items-center justify-center min-h-[60vh]">
      <Spinner size="lg" />
    </div>
  )
}

// ── Not Found ──────────────────────────────────────────────────────────────────
function NotFoundPage() {
  return (
    <div className="flex flex-col items-center justify-center min-h-[70vh] gap-4 text-center px-4">
      <span className="text-8xl font-extrabold text-primary opacity-30">404</span>
      <h1 className="text-2xl font-bold text-on-surface">Trang không tồn tại</h1>
      <p className="text-on-surface-variant text-sm">
        Địa chỉ bạn truy cập không tồn tại hoặc đã bị di chuyển.
      </p>
      <a href="/" className="px-5 py-2.5 bg-primary-container text-on-primary-container rounded-xl text-sm font-semibold hover:bg-primary hover:text-on-primary transition-colors">
        Về trang chủ
      </a>
    </div>
  )
}

// ── Admin Guard ────────────────────────────────────────────────────────────────
function AdminGuard({ children }) {
  return (
    <ProtectedRoute>
      <PermissionRoute permission="orders.view">
        {children}
      </PermissionRoute>
    </ProtectedRoute>
  )
}

// ── Main Routes ────────────────────────────────────────────────────────────────
export default function AppRoutes() {
  return (
    <Suspense fallback={<PageLoader />}>
      <Routes>
        {/* ── Auth pages (no layout wrapper) ── */}
        <Route path="/login"    element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />

        {/* ── Storefront pages ── */}
        <Route element={<StorefrontLayout />}>
          <Route index element={<HomePage />} />
          <Route path="products"     element={<ProductListPage />} />
          <Route path="products/:id" element={<ProductDetailPage />} />
          <Route path="compare"      element={<ProductComparePage />} />
          <Route path="wishlist"     element={<WishlistPage />} />
          <Route path="cart"         element={<CartPage />} />

          {/* ── Customer-protected routes ── */}
          <Route
            path="checkout"
            element={
              <ProtectedRoute>
                <CheckoutPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="payment/vnpay"
            element={
              <ProtectedRoute>
                <VNPayPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="checkout/vnpay"
            element={
              <ProtectedRoute>
                <VNPayPage />
              </ProtectedRoute>
            }
          />
          <Route path="payment/vnpay/return" element={<PaymentReturnPage />} />

          <Route
            path="payment/zalopay"
            element={
              <ProtectedRoute>
                <ZaloPayPage />
              </ProtectedRoute>
            }
          />
          <Route path="payment/zalopay/return" element={<ZaloPayReturnPage />} />

          <Route
            path="payment/momo"
            element={
              <ProtectedRoute>
                <MoMoPage />
              </ProtectedRoute>
            }
          />
          <Route path="payment/momo/return" element={<MoMoReturnPage />} />

          <Route
            path="orders"
            element={
              <ProtectedRoute>
                <OrderHistoryPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="orders/:id"
            element={
              <ProtectedRoute>
                <OrderDetailPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="orders/:id/invoice"
            element={
              <ProtectedRoute>
                <OrderInvoicePage />
              </ProtectedRoute>
            }
          />

          {/* ── Customer Account ── */}
          <Route
            path="account/profile"
            element={
              <ProtectedRoute>
                <ProfilePage />
              </ProtectedRoute>
            }
          />
          <Route
            path="account/addresses"
            element={
              <ProtectedRoute>
                <AddressBookPage />
              </ProtectedRoute>
            }
          />

          {/* ── 404 inside storefront ── */}
          <Route path="*" element={<NotFoundPage />} />
        </Route>

        {/* ── Admin portal ── */}
        <Route
          path="/admin"
          element={
            <AdminGuard>
              <AdminLayout />
            </AdminGuard>
          }
        >
          <Route index                 element={<AdminDashboard />} />
          <Route path="orders"         element={<AdminOrderListPage />} />
          <Route path="orders/:id"     element={<AdminOrderDetailPage />} />
          <Route path="products"       element={<AdminProductPage />} />
          <Route path="categories"     element={<AdminCategoryPage />} />
          <Route path="brands"         element={<AdminBrandPage />} />
          <Route path="inventories"    element={<AdminInventoryPage />} />
          <Route path="users"          element={<AdminUserPage />} />
          <Route path="coupons"        element={<AdminCouponPage />} />
          <Route path="reviews"        element={<AdminReviewPage />} />
          <Route path="banners"        element={<AdminBannerPage />} />
          <Route path="specs"          element={<AdminSpecPage />} />
          <Route path="*"              element={<Navigate to="/admin" replace />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
