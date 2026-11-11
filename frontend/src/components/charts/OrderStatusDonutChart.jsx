// src/components/charts/OrderStatusDonutChart.jsx
// Interactive SVG Donut chart displaying order status breakdown with clickable segment links.

import { useState } from 'react'
import { Link } from 'react-router-dom'

const STATUS_CONFIG = {
  delivered: {
    label: 'Giao thành công',
    color: '#10b981',
    twBg: 'bg-emerald-500',
    twText: 'text-emerald-500',
    icon: 'check_circle',
  },
  shipping: {
    label: 'Đang vận chuyển',
    color: '#8b5cf6',
    twBg: 'bg-violet-500',
    twText: 'text-violet-500',
    icon: 'local_shipping',
  },
  confirmed: {
    label: 'Đã xác nhận',
    color: '#3b82f6',
    twBg: 'bg-blue-500',
    twText: 'text-blue-500',
    icon: 'verified',
  },
  pending: {
    label: 'Chờ duyệt',
    color: '#f59e0b',
    twBg: 'bg-amber-500',
    twText: 'text-amber-500',
    icon: 'pending',
  },
  cancelled: {
    label: 'Đã hủy',
    color: '#ef4444',
    twBg: 'bg-rose-500',
    twText: 'text-rose-500',
    icon: 'cancel',
  },
}

export default function OrderStatusDonutChart({
  distribution = {},
  totalOrders = 0,
  successRate = 0,
}) {
  const [hoveredKey, setHoveredKey] = useState(null)

  // Standard circumference calculation for SVG circle
  const radius = 64
  const strokeWidth = 18
  const circumference = 2 * Math.PI * radius // ≈ 402.12

  // Build segments array
  const keys = ['delivered', 'shipping', 'confirmed', 'pending', 'cancelled']
  let cumulativeOffset = 0

  const segments = keys.map((key) => {
    const item = distribution[key] || { count: 0, percentage: 0 }
    const pct = Number(item.percentage) || 0
    const count = Number(item.count) || 0
    const strokeDash = (pct / 100) * circumference
    const offset = cumulativeOffset
    cumulativeOffset += strokeDash

    return {
      key,
      count,
      percentage: pct,
      strokeDash,
      offset,
      config: STATUS_CONFIG[key] || { label: key, color: '#94a3b8' },
    }
  })

  const activeSegment = hoveredKey ? segments.find((s) => s.key === hoveredKey) : null

  return (
    <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 flex flex-col justify-between shadow-sm">
      {/* Header */}
      <div className="flex items-center justify-between pb-3 border-b border-outline-variant/15">
        <div>
          <h3 className="text-base font-bold text-on-surface">Phân bổ Trạng thái Đơn hàng</h3>
          <p className="text-xs text-on-surface-variant mt-0.5">
            Tỷ trọng theo tiến trình xử lý đơn hàng
          </p>
        </div>
        <span className="material-symbols-outlined text-outline">pie_chart</span>
      </div>

      {/* Center Donut + Legend */}
      <div className="flex flex-col sm:flex-row items-center justify-around gap-6 my-auto py-4">
        {/* SVG Donut Circle */}
        <div className="relative w-48 h-48 shrink-0 flex items-center justify-center">
          <svg className="w-full h-full -rotate-90" viewBox="0 0 160 160">
            {/* Background track circle */}
            <circle
              cx="80"
              cy="80"
              r={radius}
              fill="transparent"
              stroke="currentColor"
              strokeWidth={strokeWidth}
              className="text-outline-variant/15"
            />

            {/* Segments */}
            {totalOrders > 0 &&
              segments.map((seg) => {
                if (seg.strokeDash <= 0) return null
                const isHovered = hoveredKey === seg.key
                return (
                  <circle
                    key={seg.key}
                    cx="80"
                    cy="80"
                    r={radius}
                    fill="transparent"
                    stroke={seg.config.color}
                    strokeWidth={isHovered ? strokeWidth + 4 : strokeWidth}
                    strokeDasharray={`${seg.strokeDash} ${circumference}`}
                    strokeDashoffset={-seg.offset}
                    strokeLinecap="round"
                    className="transition-all duration-300 cursor-pointer"
                    onMouseEnter={() => setHoveredKey(seg.key)}
                    onMouseLeave={() => setHoveredKey(null)}
                  />
                )
              })}
          </svg>

          {/* Center Content */}
          <div className="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none px-2">
            {activeSegment ? (
              <>
                <span className={`text-[11px] font-bold ${activeSegment.config.twText}`}>
                  {activeSegment.config.label}
                </span>
                <span className="text-xl font-extrabold text-on-surface mt-0.5 font-mono">
                  {activeSegment.percentage}%
                </span>
                <span className="text-[10px] text-outline">
                  {activeSegment.count} đơn
                </span>
              </>
            ) : (
              <>
                <span className="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider">
                  Thành công
                </span>
                <span className="text-2xl font-black text-secondary font-mono tracking-tight">
                  {successRate}%
                </span>
                <span className="text-[10px] text-outline">
                  {totalOrders} tổng đơn
                </span>
              </>
            )}
          </div>
        </div>

        {/* Legend List with 1-click Filter Link */}
        <div className="w-full sm:max-w-xs space-y-2">
          {segments.map((seg) => {
            const isHovered = hoveredKey === seg.key
            return (
              <Link
                key={seg.key}
                to={`/admin/orders?status=${seg.key}`}
                onMouseEnter={() => setHoveredKey(seg.key)}
                onMouseLeave={() => setHoveredKey(null)}
                className={`flex items-center justify-between p-2 rounded-xl transition-all ${
                  isHovered
                    ? 'bg-surface-container-high ring-1 ring-outline-variant/40'
                    : 'hover:bg-surface-container-high/60'
                }`}
              >
                <div className="flex items-center gap-2.5 min-w-0">
                  <span
                    className="w-2.5 h-2.5 rounded-full shrink-0"
                    style={{ backgroundColor: seg.config.color }}
                  />
                  <span className="text-xs font-semibold text-on-surface truncate">
                    {seg.config.label}
                  </span>
                </div>

                <div className="flex items-center gap-2 shrink-0">
                  <span className="font-mono text-xs font-bold text-on-surface">
                    {seg.count}
                  </span>
                  <span className="text-[11px] font-mono text-outline w-10 text-right">
                    {seg.percentage}%
                  </span>
                  <span className="material-symbols-outlined text-outline-variant text-sm">
                    arrow_forward
                  </span>
                </div>
              </Link>
            )
          })}
        </div>
      </div>

      {/* Footer Info */}
      <div className="pt-3 border-t border-outline-variant/15 flex items-center justify-between text-xs text-on-surface-variant">
        <span>Bấm vào trạng thái để lọc nhanh danh sách đơn</span>
        <Link to="/admin/orders" className="text-primary font-semibold hover:underline">
          Tất cả đơn →
        </Link>
      </div>
    </div>
  )
}
