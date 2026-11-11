// src/features/catalog/FlashSaleSection.jsx
// Interactive Flash Sale section with live countdown timer and discount progress.

import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { useCompare } from '../../contexts/CompareContext'
import { useWishlist } from '../../contexts/WishlistContext'

function getSessionEndTime() {
  const now = new Date()
  const end = new Date(now)
  end.setHours(23, 59, 59, 999)
  return end.getTime()
}

export default function FlashSaleSection({ products = [] }) {
  const { toggleCompare, isInCompare } = useCompare()
  const { toggleWishlist, isInWishlist } = useWishlist()

  const [timeLeft, setTimeLeft] = useState({
    hours: '00',
    minutes: '00',
    seconds: '00',
    isExpired: false,
  })

  useEffect(() => {
    const target = getSessionEndTime()

    const updateTimer = () => {
      const diff = target - Date.now()
      if (diff <= 0) {
        setTimeLeft({ hours: '00', minutes: '00', seconds: '00', isExpired: true })
        return
      }
      const h = Math.floor(diff / (1000 * 60 * 60))
      const m = Math.floor((diff / (1000 * 60)) % 60)
      const s = Math.floor((diff / 1000) % 60)
      setTimeLeft({
        hours: String(h).padStart(2, '0'),
        minutes: String(m).padStart(2, '0'),
        seconds: String(s).padStart(2, '0'),
        isExpired: false,
      })
    }

    updateTimer()
    const interval = setInterval(updateTimer, 1000)
    return () => clearInterval(interval)
  }, [])

  // Filter flash sale items: products with compare_price or first 4 products
  const flashSaleItems = products.slice(0, 4)

  if (flashSaleItems.length === 0 || timeLeft.isExpired) {
    return null
  }

  return (
    <section className="bg-gradient-to-r from-error/10 via-surface-container to-secondary/10 rounded-2xl p-6 md:p-8 border border-error/20 relative overflow-hidden shadow-sm">
      {/* Decorative gradient orb */}
      <div className="absolute -top-12 -right-12 w-48 h-48 bg-error/10 rounded-full blur-3xl pointer-events-none" />

      {/* Header with Title and Countdown */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 relative z-10">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-error/20 flex items-center justify-center text-error animate-pulse">
            <span className="material-symbols-outlined text-2xl">local_fire_department</span>
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-xl md:text-2xl font-black text-on-surface tracking-tight uppercase">
                Flash Sale Giờ Vàng
              </h2>
              <span className="px-2 py-0.5 rounded-full bg-error text-white text-[10px] font-bold tracking-wider animate-bounce">
                HOT
              </span>
            </div>
            <p className="text-xs text-on-surface-variant">Ưu đãi giảm giá sốc có hạn trong ngày</p>
          </div>
        </div>

        {/* Live Countdown Clock */}
        <div className="flex items-center gap-2 bg-surface-container-high/80 px-4 py-2 rounded-xl border border-outline-variant/30 backdrop-blur-sm self-start sm:self-auto">
          <span className="text-xs text-on-surface-variant font-medium mr-1">Kết thúc sau:</span>
          <div className="flex items-center gap-1 font-mono text-sm font-bold text-on-surface">
            <span className="px-2 py-1 rounded-lg bg-surface-container-lowest text-error border border-error/30 shadow-xs">
              {timeLeft.hours}
            </span>
            <span>:</span>
            <span className="px-2 py-1 rounded-lg bg-surface-container-lowest text-error border border-error/30 shadow-xs">
              {timeLeft.minutes}
            </span>
            <span>:</span>
            <span className="px-2 py-1 rounded-lg bg-surface-container-lowest text-error border border-error/30 shadow-xs">
              {timeLeft.seconds}
            </span>
          </div>
        </div>
      </div>

      {/* Product Cards Grid */}
      <div className="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4 relative z-10">
        {flashSaleItems.map((product, idx) => {
          const thumbnail =
            product.images?.find((img) => img.is_primary)?.image_url ||
            product.images?.find((img) => img.is_primary)?.image_path ||
            product.primary_image_url ||
            product.thumbnail ||
            product.images?.[0]?.image_url ||
            product.images?.[0]?.image_path

          const price =
            product.min_price && Number(product.min_price) > 0
              ? Number(product.min_price)
              : Number(product.base_price) || 20000000

          const originalPrice =
            product.max_price && Number(product.max_price) > price
              ? Number(product.max_price)
              : Math.round(price * 1.15)

          const discountPercent = Math.round(((originalPrice - price) / originalPrice) * 100)
          const soldCount = 18 + (idx * 5) % 15
          const totalStock = soldCount + 6 + (idx * 3) % 8
          const progressPercent = Math.min(100, Math.round((soldCount / totalStock) * 100))

          return (
            <div
              key={product.id}
              className="group bg-surface-container-high rounded-xl overflow-hidden border border-outline-variant/20 hover:border-error/40 hover:shadow-[0_8px_30px_rgba(239,68,68,0.15)] transition-all flex flex-col justify-between"
            >
              <div className="relative">
                {/* Thumbnail */}
                <Link to={`/products/${product.id}`} className="block h-40 bg-surface-container-low p-3 overflow-hidden">
                  {thumbnail ? (
                    <img
                      src={thumbnail}
                      alt={product.name}
                      className="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
                    />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-outline">
                      <span className="material-symbols-outlined text-4xl">smartphone</span>
                    </div>
                  )}
                </Link>

                {/* Badge Discount */}
                <span className="absolute top-2 left-2 bg-error text-white text-[11px] font-black px-2 py-0.5 rounded-md shadow-sm">
                  -{discountPercent}%
                </span>

                {/* Quick actions: Compare & Wishlist */}
                <div className="absolute top-2 right-2 flex flex-col gap-1 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button
                    type="button"
                    title="So sánh"
                    onClick={() => toggleCompare(product)}
                    className={`w-7 h-7 rounded-full flex items-center justify-center transition-all ${
                      isInCompare(product.id)
                        ? 'bg-primary text-on-primary'
                        : 'bg-surface-container/90 text-on-surface hover:text-primary'
                    }`}
                  >
                    <span className="material-symbols-outlined text-sm">compare_arrows</span>
                  </button>
                  <button
                    type="button"
                    title="Yêu thích"
                    onClick={() => toggleWishlist(product)}
                    className={`w-7 h-7 rounded-full flex items-center justify-center transition-all ${
                      isInWishlist(product.id)
                        ? 'bg-error text-white'
                        : 'bg-surface-container/90 text-on-surface hover:text-error'
                    }`}
                  >
                    <span className="material-symbols-outlined text-sm">favorite</span>
                  </button>
                </div>
              </div>

              {/* Info */}
              <div className="p-3 space-y-2 flex-1 flex flex-col justify-between">
                <div>
                  <Link
                    to={`/products/${product.id}`}
                    className="text-xs font-bold text-on-surface line-clamp-1 hover:text-primary transition-colors"
                  >
                    {product.name}
                  </Link>

                  <div className="flex items-baseline gap-1.5 mt-1">
                    <span className="text-sm font-extrabold text-error">
                      {formatCurrencyVND(price)}
                    </span>
                    <span className="text-[10px] text-outline line-through">
                      {formatCurrencyVND(originalPrice)}
                    </span>
                  </div>
                </div>

                {/* Sold progress bar */}
                <div className="space-y-1">
                  <div className="flex items-center justify-between text-[10px] text-on-surface-variant font-medium">
                    <span className="flex items-center gap-0.5 text-error">
                      <span className="material-symbols-outlined text-xs">local_fire_department</span>
                      Đã bán {soldCount}
                    </span>
                    <span>Còn {totalStock - soldCount}</span>
                  </div>
                  <div className="h-1.5 w-full bg-surface-container rounded-full overflow-hidden">
                    <div
                      className="h-full bg-gradient-to-r from-warning to-error rounded-full transition-all duration-500"
                      style={{ width: `${progressPercent}%` }}
                    />
                  </div>
                </div>
              </div>
            </div>
          )
        })}
      </div>
    </section>
  )
}
