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
  received: {
    label: 'Khách đã nhận hàng',
    color: '#06b6d4',
    twBg: 'bg-cyan-500',
    twText: 'text-cyan-500',
    icon: 'inventory',
  },
  processing: {
    label: 'Đang chuẩn bị',
    color: '#3b82f6',
    twBg: 'bg-blue-500',
    twText: 'text-blue-500',
    icon: 'package_2',
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
  const strokeWidth = 16
  const circumference = 2 * Math.PI * radius // ≈ 402.12

  // Build segments array
  const keys = ['delivered', 'received', 'shipping', 'processing', 'confirmed', 'pending', 'cancelled']
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
    <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-5 md:p-6 flex flex-col justify-between shadow-xs h-full">
      {/* ── Top Header ── */}
      <div className="flex items-center justify-between pb-3.5 border-b border-outline-variant/15">
        <div>
          <div className="flex items-center gap-2">
            <span className="w-1.5 h-6 bg-secondary rounded-full" />
            <h3 className="text-base md:text-lg font-bold text-on-surface">
              Phân bổ Trạng thái Đơn
            </h3>
          </div>
          <p className="text-xs text-on-surface-variant mt-0.5 pl-3.5">
            Tỷ trọng theo tiến trình xử lý đơn hàng
          </p>
        </div>
        <span className="p-2 rounded-xl bg-surface-container-high text-outline material-symbols-outlined text-lg">
          pie_chart
        </span>
      </div>

      {/* ── Donut Chart (Centered) ── */}
      <div className="py-3 flex flex-col items-center justify-center">
        <div className="relative w-44 h-44 shrink-0 flex items-center justify-center select-none">
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
          <div className="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none px-3">
            {activeSegment ? (
              <div className="space-y-0.5 animate-fadeIn">
                <span className={`text-[11px] font-bold block ${activeSegment.config.twText}`}>
                  {activeSegment.config.label}
                </span>
                <span className="text-2xl font-black text-on-surface font-mono block">
                  {activeSegment.percentage}%
                </span>
                <span className="text-[11px] text-outline font-medium block">
                  {activeSegment.count} đơn
                </span>
              </div>
            ) : (
              <div className="space-y-0.5">
                <span className="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider block">
                  Thành công
                </span>
                <span className="text-2xl font-black text-secondary font-mono tracking-tight block">
                  {successRate}%
                </span>
                <span className="text-[11px] text-outline font-medium block">
                  {totalOrders} tổng đơn
                </span>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* ── Status Breakdown List with Progress Bars & Links ── */}
      <div className="space-y-2 pt-2">
        {segments.map((seg) => {
          const isHovered = hoveredKey === seg.key
          return (
            <Link
              key={seg.key}
              to={`/admin/orders?status=${seg.key}`}
              onMouseEnter={() => setHoveredKey(seg.key)}
              onMouseLeave={() => setHoveredKey(null)}
              className={`block p-2.5 rounded-xl border cursor-pointer active:scale-[0.99] transition-all ${
                isHovered
                  ? 'bg-surface-container-high border-outline-variant/50 ring-1 ring-outline-variant/30 shadow-xs'
                  : 'bg-surface-container-low border-outline-variant/20 hover:border-outline-variant/40'
              }`}
            >
              <div className="flex items-center justify-between gap-2">
                {/* Left: Indicator dot + Status name */}
                <div className="flex items-center gap-2 min-w-0">
                  <span
                    className="w-2.5 h-2.5 rounded-full shrink-0 shadow-2xs"
                    style={{ backgroundColor: seg.config.color }}
                  />
                  <span className="text-xs font-semibold text-on-surface truncate">
                    {seg.config.label}
                  </span>
                </div>

                {/* Right: Count + Percentage + Arrow */}
                <div className="flex items-center gap-2 shrink-0 text-xs">
                  <span className="font-mono font-bold text-on-surface">
                    {seg.count} <span className="text-[10px] text-outline font-normal">đơn</span>
                  </span>
                  <span className="font-mono text-[11px] font-semibold text-outline px-1.5 py-0.5 rounded bg-surface-container-high/60">
                    {seg.percentage}%
                  </span>
                  <span className="material-symbols-outlined text-outline text-sm transition-transform group-hover:translate-x-0.5">
                    arrow_forward
                  </span>
                </div>
              </div>

              {/* Mini Percentage Progress Bar */}
              <div className="w-full h-1.5 bg-surface-container-high rounded-full overflow-hidden mt-2">
                <div
                  className="h-full rounded-full transition-all duration-500"
                  style={{
                    width: `${Math.min(100, Math.max(seg.count > 0 ? 3 : 0, seg.percentage))}%`,
                    backgroundColor: seg.config.color,
                  }}
                />
              </div>
            </Link>
          )
        })}
      </div>

      {/* ── Footer ── */}
      <div className="pt-3 mt-3 border-t border-outline-variant/15 flex items-center justify-between text-xs text-on-surface-variant">
        <span>Bấm vào để lọc đơn hàng</span>
        <Link
          to="/admin/orders"
          className="text-primary font-bold hover:underline inline-flex items-center gap-0.5 cursor-pointer"
        >
          <span>Tất cả đơn</span>
          <span className="material-symbols-outlined text-sm">arrow_forward</span>
        </Link>
      </div>
    </div>
  )
}
