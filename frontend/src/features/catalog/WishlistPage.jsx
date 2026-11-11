import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useWishlist } from '../../contexts/WishlistContext'
import { useCart } from '../../contexts/CartContext'
import { useCompare } from '../../contexts/CompareContext'
import { useToast } from '../../contexts/ToastContext'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Button from '../../components/common/Button'

export default function WishlistPage() {
  const { wishlistItems, removeFromWishlist, clearWishlist } = useWishlist()
  const { addToCart } = useCart()
  const { toggleCompare, isInCompare } = useCompare()
  const { showToast } = useToast()
  const navigate = useNavigate()

  const [addingToCartId, setAddingToCartId] = useState(null)

  const handleAddToCart = async (item) => {
    try {
      setAddingToCartId(item.id)
      const variantId = item.variants?.[0]?.id
      if (!variantId) {
        showToast('Sản phẩm chưa có phiên bản kinh doanh', 'error')
        return
      }
      await addToCart(variantId, 1)
      showToast(`Đã thêm "${item.name}" vào giỏ hàng`, 'success')
    } catch (err) {
      showToast(err?.response?.data?.message || 'Không thể thêm vào giỏ hàng', 'error')
    } finally {
      setAddingToCartId(null)
    }
  }

  if (wishlistItems.length === 0) {
    return (
      <div className="max-w-4xl mx-auto py-16 px-4 text-center">
        <div className="w-20 h-20 mx-auto rounded-3xl bg-error/10 border border-error/20 flex items-center justify-center text-error mb-6">
          <span className="material-symbols-outlined text-4xl">favorite_border</span>
        </div>
        <h1 className="text-2xl md:text-3xl font-extrabold text-on-surface mb-3">
          Danh sách yêu thích trống
        </h1>
        <p className="text-sm md:text-base text-on-surface-variant max-w-md mx-auto mb-8">
          Bạn chưa lưu sản phẩm nào. Hãy bấm vào biểu tượng trái tim ở các sản phẩm bạn quan tâm để dễ dàng xem lại và mua sau.
        </p>
        <Button variant="primary" size="lg" onClick={() => navigate('/products')}>
          <span className="material-symbols-outlined text-xl">storefront</span>
          Khám phá danh sách sản phẩm
        </Button>
      </div>
    )
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-outline-variant/20">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-error uppercase tracking-wider mb-1">
            <span className="material-symbols-outlined text-base">favorite</span>
            Mục quan tâm & Lưu trữ
          </div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-on-surface">
            Sản phẩm yêu thích ({wishlistItems.length})
          </h1>
          <p className="text-xs md:text-sm text-on-surface-variant mt-1">
            Danh sách các sản phẩm bạn đã lưu lại để theo dõi giá và đặt mua.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <Button
            variant="ghost"
            size="sm"
            onClick={clearWishlist}
            className="text-error hover:bg-error/10 rounded-xl"
          >
            <span className="material-symbols-outlined text-base">delete_sweep</span>
            Xóa tất cả
          </Button>
        </div>
      </div>

      {/* Grid of Wishlist Items */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        {wishlistItems.map((item) => {
          const discountPercent =
            item.original_price && item.original_price > item.price
              ? Math.round(((item.original_price - item.price) / item.original_price) * 100)
              : 0

          return (
            <div
              key={item.id}
              className="group bg-surface-container rounded-2xl overflow-hidden border border-outline-variant/15 hover:border-primary/40 hover:shadow-xl transition-all duration-300 flex flex-col justify-between"
            >
              <div>
                {/* Image & Badges */}
                <div className="relative aspect-square bg-surface-container-high p-4 flex items-center justify-center overflow-hidden">
                  <Link to={`/products/${item.slug || item.id}`} className="w-full h-full flex items-center justify-center">
                    <img
                      src={item.image_url}
                      alt={item.name}
                      className="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-300"
                      onError={(e) => {
                        e.currentTarget.src = '/placeholder-phone.png'
                      }}
                    />
                  </Link>

                  {discountPercent > 0 && (
                    <span className="absolute top-3 left-3 bg-secondary-container text-on-secondary-container text-[11px] font-bold px-2 py-0.5 rounded-full">
                      -{discountPercent}%
                    </span>
                  )}

                  {/* Remove Button */}
                  <button
                    onClick={() => removeFromWishlist(item.id)}
                    title="Bỏ khỏi yêu thích"
                    className="absolute top-3 right-3 w-8 h-8 rounded-full bg-surface-container/80 text-error hover:bg-error/15 flex items-center justify-center transition-colors"
                  >
                    <span className="material-symbols-outlined text-lg">favorite</span>
                  </button>
                </div>

                {/* Details */}
                <div className="p-4 space-y-2">
                  <span className="text-xs font-semibold text-primary uppercase tracking-wider">
                    {item.brand_name || 'MobieX'}
                  </span>
                  <Link to={`/products/${item.slug || item.id}`}>
                    <h3 className="text-sm font-bold text-on-surface group-hover:text-primary transition-colors line-clamp-2 leading-snug">
                      {item.name}
                    </h3>
                  </Link>
                  <div className="flex items-baseline gap-2 pt-1">
                    <span className="text-base font-extrabold text-on-surface">
                      {formatCurrencyVND(item.price)}
                    </span>
                    {item.original_price && item.original_price > item.price && (
                      <span className="text-xs text-outline line-through">
                        {formatCurrencyVND(item.original_price)}
                      </span>
                    )}
                  </div>
                </div>
              </div>

              {/* Actions Footer */}
              <div className="p-4 pt-0 space-y-2">
                <Button
                  variant="primary"
                  size="sm"
                  className="w-full text-xs"
                  loading={addingToCartId === item.id}
                  disabled={addingToCartId === item.id}
                  onClick={() => handleAddToCart(item)}
                >
                  <span className="material-symbols-outlined text-base">shopping_cart</span>
                  Chuyển vào giỏ hàng
                </Button>

                <Button
                  variant={isInCompare(item.id) ? 'outline' : 'ghost'}
                  size="sm"
                  className={`w-full text-xs ${isInCompare(item.id) ? 'border-primary text-primary' : 'text-on-surface-variant'}`}
                  onClick={() => toggleCompare(item)}
                >
                  <span className="material-symbols-outlined text-base">compare_arrows</span>
                  {isInCompare(item.id) ? 'Đang so sánh' : 'Thêm vào so sánh'}
                </Button>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
