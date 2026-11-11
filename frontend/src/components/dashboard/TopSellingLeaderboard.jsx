// src/components/dashboard/TopSellingLeaderboard.jsx
// Visual leaderboard displaying top 5 best selling products by units and revenue.

import { Link } from 'react-router-dom'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'

const RANK_BADGES = [
  { rank: 1, bg: 'bg-amber-400 text-amber-950 ring-2 ring-amber-300', icon: 'emoji_events' },
  { rank: 2, bg: 'bg-slate-300 text-slate-900 ring-2 ring-slate-200', icon: 'military_tech' },
  { rank: 3, bg: 'bg-amber-700/80 text-white ring-2 ring-amber-600/50', icon: 'workspace_premium' },
]

export default function TopSellingLeaderboard({ items = [] }) {
  const safeItems = Array.isArray(items) ? items : []
  const maxSold = Math.max(...safeItems.map((i) => Number(i.total_sold) || 0), 1)

  return (
    <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 flex flex-col justify-between shadow-sm">
      <div className="flex items-center justify-between pb-3 border-b border-outline-variant/15">
        <div>
          <h3 className="text-base font-bold text-on-surface">Top 5 Sản phẩm Bán chạy</h3>
          <p className="text-xs text-on-surface-variant mt-0.5">
            Xếp hạng theo sản lượng máy và doanh số thực tế
          </p>
        </div>
        <span className="material-symbols-outlined text-amber-500">trophy</span>
      </div>

      <div className="py-2 space-y-3 my-auto">
        {safeItems.length === 0 ? (
          <div className="py-8 text-center text-sm text-on-surface-variant">
            Chưa có phát sinh đơn hàng thành công để tính xếp hạng sản phẩm.
          </div>
        ) : (
          safeItems.map((prod, idx) => {
            const sold = Number(prod.total_sold) || 0
            const pct = Math.max(8, Math.round((sold / maxSold) * 100))
            const rankBadge = RANK_BADGES[idx]

            return (
              <div
                key={prod.product_id || idx}
                className="p-2.5 rounded-xl bg-surface-container-high/40 hover:bg-surface-container-high transition-colors flex items-center gap-3 group"
              >
                {/* Rank Badge */}
                <div
                  className={`w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 ${
                    rankBadge ? rankBadge.bg : 'bg-surface-container text-outline'
                  }`}
                >
                  {rankBadge ? (
                    <span className="material-symbols-outlined text-sm">{rankBadge.icon}</span>
                  ) : (
                    idx + 1
                  )}
                </div>

                {/* Thumbnail */}
                <div className="w-10 h-10 rounded-lg bg-surface-container-low shrink-0 overflow-hidden border border-outline-variant/30 flex items-center justify-center p-1">
                  {prod.image_path ? (
                    <img
                      src={prod.image_path}
                      alt={prod.product_name}
                      className="w-full h-full object-contain"
                    />
                  ) : (
                    <span className="material-symbols-outlined text-outline text-lg">smartphone</span>
                  )}
                </div>

                {/* Details & Progress Bar */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-xs font-bold text-on-surface truncate group-hover:text-primary transition-colors">
                      {prod.product_name}
                    </p>
                    <span className="text-xs font-mono font-extrabold text-primary shrink-0">
                      {formatCurrencyVND(Number(prod.total_revenue) || 0)}
                    </span>
                  </div>

                  <div className="flex items-center gap-2 mt-1.5">
                    <div className="flex-1 h-1.5 bg-surface-container rounded-full overflow-hidden">
                      <div
                        style={{ width: `${pct}%` }}
                        className="h-full bg-gradient-to-r from-primary to-secondary rounded-full transition-all duration-500"
                      />
                    </div>
                    <span className="text-[11px] font-mono font-semibold text-on-surface-variant shrink-0">
                      {sold} máy
                    </span>
                  </div>
                </div>
              </div>
            )
          })
        )}
      </div>

      <div className="pt-3 border-t border-outline-variant/15 flex items-center justify-between text-xs text-on-surface-variant">
        <span>Cập nhật dựa trên hóa đơn thành công</span>
        <Link to="/admin/products" className="text-primary font-semibold hover:underline">
          Kho sản phẩm →
        </Link>
      </div>
    </div>
  )
}
