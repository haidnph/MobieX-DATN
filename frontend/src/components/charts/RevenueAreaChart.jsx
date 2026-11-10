// src/components/charts/RevenueAreaChart.jsx
// Interactive SVG-based Area and Bar chart for Revenue & Order analytics.
// Native React 19 + Tailwind CSS, zero external chart dependencies.

import { useState, useId } from 'react'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'

export default function RevenueAreaChart({
  data = [],
  days = 7,
  onDaysChange,
  summary,
  loading = false,
}) {
  const [activeMode, setActiveMode] = useState('revenue') // 'revenue' | 'orders' | 'dual'
  const [hoverIndex, setHoverIndex] = useState(null)
  const gradId = useId()

  const safeData = Array.isArray(data) && data.length > 0 ? data : []

  // Metrics extraction
  const maxRevenueRaw = Math.max(...safeData.map((d) => Number(d.revenue) || 0), 1000000)
  const maxRevenue = Math.ceil(maxRevenueRaw * 1.1) // 10% headroom

  const maxOrdersRaw = Math.max(...safeData.map((d) => Number(d.orders) || 0), 5)
  const maxOrders = Math.ceil(maxOrdersRaw * 1.2) // headroom

  // Dimensions
  const svgWidth = 840
  const svgHeight = 280
  const padLeft = 70
  const padRight = 30
  const padTop = 30
  const padBottom = 40
  const plotWidth = svgWidth - padLeft - padRight
  const plotHeight = svgHeight - padTop - padBottom

  // Point mapping helper
  const getPoints = (isOrders = false) => {
    if (safeData.length === 0) return []
    const max = isOrders ? maxOrders : maxRevenue
    return safeData.map((d, i) => {
      const val = isOrders ? Number(d.orders) || 0 : Number(d.revenue) || 0
      const x = padLeft + (safeData.length === 1 ? plotWidth / 2 : (i / (safeData.length - 1)) * plotWidth)
      const y = padTop + plotHeight - (val / (max || 1)) * plotHeight
      return { x, y, val, item: d, index: i }
    })
  }

  const revenuePoints = getPoints(false)
  const orderPoints = getPoints(true)

  // Smooth SVG cubic bezier path builder
  const buildSmoothPath = (points) => {
    if (points.length === 0) return ''
    if (points.length === 1) return `M ${points[0].x} ${points[0].y}`

    let d = `M ${points[0].x} ${points[0].y}`
    for (let i = 0; i < points.length - 1; i++) {
      const p0 = i > 0 ? points[i - 1] : points[i]
      const p1 = points[i]
      const p2 = points[i + 1]
      const p3 = i != points.length - 2 ? points[i + 2] : p2

      const cp1x = p1.x + (p2.x - p0.x) / 6
      const cp1y = p1.y + (p2.y - p0.y) / 6
      const cp2x = p2.x - (p3.x - p1.x) / 6
      const cp2y = p2.y - (p3.y - p1.y) / 6

      d += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`
    }
    return d
  }

  const revenueLinePath = buildSmoothPath(revenuePoints)
  const revenueAreaPath =
    revenuePoints.length > 0
      ? `${revenueLinePath} L ${revenuePoints[revenuePoints.length - 1].x} ${padTop + plotHeight} L ${revenuePoints[0].x} ${padTop + plotHeight} Z`
      : ''

  const orderLinePath = buildSmoothPath(orderPoints)

  // Y-axis label formatter
  const formatYAxis = (val, isOrders = false) => {
    if (isOrders) return Math.round(val)
    if (val >= 1000000000) return `${(val / 1000000000).toFixed(1)} Tỷ`
    if (val >= 1000000) return `${(val / 1000000).toFixed(1)} Tr`
    if (val >= 1000) return `${(val / 1000).toFixed(0)} K`
    return Math.round(val)
  }

  // Hover tracker
  const handleMouseMove = (e) => {
    if (safeData.length === 0) return
    const rect = e.currentTarget.getBoundingClientRect()
    const mouseX = ((e.clientX - rect.left) / rect.width) * svgWidth
    const clampedX = Math.max(padLeft, Math.min(svgWidth - padRight, mouseX))
    const ratio = (clampedX - padLeft) / plotWidth
    const idx = Math.round(ratio * (safeData.length - 1))
    setHoverIndex(Math.max(0, Math.min(safeData.length - 1, idx)))
  }

  const activeItem = hoverIndex !== null ? safeData[hoverIndex] : null
  const activeRevPoint = hoverIndex !== null ? revenuePoints[hoverIndex] : null
  const activeOrdPoint = hoverIndex !== null ? orderPoints[hoverIndex] : null

  return (
    <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 flex flex-col justify-between relative shadow-sm">
      {/* Top Header & Controls */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-outline-variant/15">
        <div>
          <div className="flex items-center gap-2">
            <h3 className="text-base font-bold text-on-surface">
              Phân tích Biến động Doanh số & Đơn hàng
            </h3>
            {loading && (
              <span className="inline-block w-3.5 h-3.5 border-2 border-primary border-t-transparent rounded-full animate-spin" />
            )}
          </div>
          <p className="text-xs text-on-surface-variant mt-0.5">
            Dữ liệu thống kê chuẩn theo thời gian giao dịch thực tế
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {/* Mode Switcher */}
          <div className="bg-surface-container-high rounded-xl p-1 flex items-center border border-outline-variant/20 text-xs">
            <button
              type="button"
              onClick={() => setActiveMode('revenue')}
              className={`px-3 py-1.5 rounded-lg font-semibold transition-all ${
                activeMode === 'revenue'
                  ? 'bg-primary text-on-primary shadow-sm'
                  : 'text-on-surface-variant hover:text-on-surface'
              }`}
            >
              Doanh thu
            </button>
            <button
              type="button"
              onClick={() => setActiveMode('orders')}
              className={`px-3 py-1.5 rounded-lg font-semibold transition-all ${
                activeMode === 'orders'
                  ? 'bg-secondary text-on-secondary shadow-sm'
                  : 'text-on-surface-variant hover:text-on-surface'
              }`}
            >
              Số đơn
            </button>
            <button
              type="button"
              onClick={() => setActiveMode('dual')}
              className={`px-3 py-1.5 rounded-lg font-semibold transition-all ${
                activeMode === 'dual'
                  ? 'bg-primary-container text-on-primary-container font-bold shadow-sm'
                  : 'text-on-surface-variant hover:text-on-surface'
              }`}
            >
              Cả hai
            </button>
          </div>

          {/* Timeframe Filter */}
          {onDaysChange && (
            <div className="flex items-center gap-1 bg-surface-container-high rounded-xl p-1 border border-outline-variant/20 text-xs">
              {[7, 14, 30, 90].map((d) => (
                <button
                  key={d}
                  type="button"
                  onClick={() => onDaysChange(d)}
                  className={`px-2.5 py-1.5 rounded-lg font-medium transition-all ${
                    days === d
                      ? 'bg-surface-bright text-primary font-bold shadow-xs'
                      : 'text-on-surface-variant hover:text-on-surface'
                  }`}
                >
                  {d} ngày
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* SVG Canvas Area */}
      <div className="relative mt-4">
        {safeData.length === 0 ? (
          <div className="h-64 flex flex-col items-center justify-center text-sm text-on-surface-variant gap-2">
            <span className="material-symbols-outlined text-4xl text-outline">query_stats</span>
            <span>Không có dữ liệu giao dịch trong khoảng thời gian đã chọn.</span>
          </div>
        ) : (
          <div
            className="w-full cursor-crosshair relative"
            onMouseMove={handleMouseMove}
            onMouseLeave={() => setHoverIndex(null)}
          >
            <svg
              viewBox={`0 0 ${svgWidth} ${svgHeight}`}
              className="w-full h-auto overflow-visible select-none"
            >
              <defs>
                {/* Gradient for Revenue Area */}
                <linearGradient id={`${gradId}-rev`} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#3b82f6" stopOpacity="0.38" />
                  <stop offset="85%" stopColor="#3b82f6" stopOpacity="0.02" />
                  <stop offset="100%" stopColor="#3b82f6" stopOpacity="0.0" />
                </linearGradient>

                {/* Gradient for Orders Bars */}
                <linearGradient id={`${gradId}-bar`} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#10b981" stopOpacity="0.85" />
                  <stop offset="100%" stopColor="#059669" stopOpacity="0.4" />
                </linearGradient>
              </defs>

              {/* Grid Lines & Y-Axis Labels */}
              {[0, 0.25, 0.5, 0.75, 1].map((pct, i) => {
                const y = padTop + plotHeight * (1 - pct)
                const revVal = maxRevenue * pct
                const ordVal = maxOrders * pct
                return (
                  <g key={i}>
                    <line
                      x1={padLeft}
                      y1={y}
                      x2={svgWidth - padRight}
                      y2={y}
                      stroke="currentColor"
                      strokeDasharray="4 4"
                      className="text-outline-variant/20"
                    />
                    {/* Left Y label: Revenue */}
                    {(activeMode === 'revenue' || activeMode === 'dual') && (
                      <text
                        x={padLeft - 10}
                        y={y + 4}
                        textAnchor="end"
                        className="text-[10px] font-mono fill-current text-outline"
                      >
                        {formatYAxis(revVal, false)}
                      </text>
                    )}
                    {/* Right Y label: Orders (when in orders or dual mode) */}
                    {(activeMode === 'orders' || activeMode === 'dual') && (
                      <text
                        x={svgWidth - padRight + 10}
                        y={y + 4}
                        textAnchor="start"
                        className="text-[10px] font-mono fill-current text-secondary"
                      >
                        {formatYAxis(ordVal, true)}
                      </text>
                    )}
                  </g>
                )
              })}

              {/* X-Axis Ticks & Labels */}
              {safeData.map((d, i) => {
                // To avoid label crowding on 30d/90d:
                const step = safeData.length > 30 ? 6 : safeData.length > 14 ? 3 : 1
                if (i % step !== 0 && i !== safeData.length - 1) return null
                const x = padLeft + (safeData.length === 1 ? plotWidth / 2 : (i / (safeData.length - 1)) * plotWidth)
                return (
                  <g key={i}>
                    <line
                      x1={x}
                      y1={padTop + plotHeight}
                      x2={x}
                      y2={padTop + plotHeight + 5}
                      stroke="currentColor"
                      className="text-outline-variant/40"
                    />
                    <text
                      x={x}
                      y={padTop + plotHeight + 18}
                      textAnchor="middle"
                      className="text-[11px] font-mono fill-current text-on-surface-variant font-medium"
                    >
                      {d.label || d.date?.slice(5) || i + 1}
                    </text>
                  </g>
                )
              })}

              {/* Order Bars (when in 'orders' or 'dual' mode) */}
              {(activeMode === 'orders' || activeMode === 'dual') &&
                orderPoints.map((p, i) => {
                  const barWidth = Math.max(3, Math.min(22, (plotWidth / safeData.length) * 0.55))
                  const barHeight = (p.val / (maxOrders || 1)) * plotHeight
                  const barY = padTop + plotHeight - barHeight
                  const isHovered = hoverIndex === i
                  return (
                    <rect
                      key={i}
                      x={p.x - barWidth / 2}
                      y={barY}
                      width={barWidth}
                      height={barHeight}
                      rx={barWidth > 8 ? 3 : 1.5}
                      fill={`url(#${gradId}-bar)`}
                      className={`transition-opacity duration-200 ${
                        isHovered ? 'opacity-100 ring-2 ring-secondary' : 'opacity-70 hover:opacity-100'
                      }`}
                    />
                  )
                })}

              {/* Revenue Area & Spline Line (when in 'revenue' or 'dual' mode) */}
              {(activeMode === 'revenue' || activeMode === 'dual') && (
                <>
                  <path
                    d={revenueAreaPath}
                    fill={`url(#${gradId}-rev)`}
                    className="transition-all duration-300"
                  />
                  <path
                    d={revenueLinePath}
                    fill="none"
                    stroke="#3b82f6"
                    strokeWidth="2.75"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="drop-shadow-[0_2px_8px_rgba(59,130,246,0.35)]"
                  />
                </>
              )}

              {/* Interactive Vertical Cursor Line & Glow Dots */}
              {hoverIndex !== null && (
                <g>
                  <line
                    x1={revenuePoints[hoverIndex]?.x}
                    y1={padTop}
                    x2={revenuePoints[hoverIndex]?.x}
                    y2={padTop + plotHeight}
                    stroke="currentColor"
                    strokeWidth="1.2"
                    strokeDasharray="3 3"
                    className="text-primary"
                  />

                  {/* Revenue glow circle */}
                  {(activeMode === 'revenue' || activeMode === 'dual') && activeRevPoint && (
                    <>
                      <circle
                        cx={activeRevPoint.x}
                        cy={activeRevPoint.y}
                        r="6"
                        fill="#3b82f6"
                        className="animate-ping opacity-60"
                      />
                      <circle
                        cx={activeRevPoint.x}
                        cy={activeRevPoint.y}
                        r="5"
                        fill="#3b82f6"
                        stroke="#ffffff"
                        strokeWidth="2"
                      />
                    </>
                  )}

                  {/* Orders glow circle */}
                  {(activeMode === 'orders' || activeMode === 'dual') && activeOrdPoint && (
                    <circle
                      cx={activeOrdPoint.x}
                      cy={activeOrdPoint.y}
                      r="4.5"
                      fill="#10b981"
                      stroke="#ffffff"
                      strokeWidth="2"
                    />
                  )}
                </g>
              )}
            </svg>

            {/* Floating Glassmorphism Tooltip */}
            {hoverIndex !== null && activeItem && (
              <div
                style={{
                  left: `${(activeRevPoint.x / svgWidth) * 100}%`,
                  transform: 'translate(-50%, -120%)',
                  top: `${(Math.min(activeRevPoint.y, activeOrdPoint?.y || 200) / svgHeight) * 100}%`,
                }}
                className="absolute pointer-events-none z-30 bg-surface-container-highest/95 backdrop-blur-md px-3.5 py-2.5 rounded-xl shadow-2xl border border-outline-variant/30 text-xs min-w-[140px]"
              >
                <div className="font-bold text-on-surface text-[11px] pb-1 border-b border-outline-variant/20 mb-1.5 flex items-center justify-between">
                  <span>{activeItem.date || activeItem.label}</span>
                  <span className="text-[10px] text-outline font-normal">Chi tiết</span>
                </div>
                <div className="space-y-1">
                  <div className="flex items-center justify-between gap-3 text-on-surface">
                    <span className="flex items-center gap-1.5 text-primary text-[11px] font-medium">
                      <span className="w-2 h-2 rounded-full bg-primary" />
                      Doanh thu:
                    </span>
                    <span className="font-extrabold font-mono text-primary">
                      {formatCurrencyVND(Number(activeItem.revenue) || 0)}
                    </span>
                  </div>
                  <div className="flex items-center justify-between gap-3 text-on-surface">
                    <span className="flex items-center gap-1.5 text-secondary text-[11px] font-medium">
                      <span className="w-2 h-2 rounded-full bg-secondary" />
                      Số đơn hàng:
                    </span>
                    <span className="font-bold font-mono text-secondary">
                      {activeItem.orders || 0} đơn
                    </span>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Summary Footer Ribbon */}
      {summary && (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-4 mt-3 border-t border-outline-variant/15 text-xs">
          <div className="flex items-center gap-2.5">
            <span className="p-2 rounded-xl bg-primary/10 text-primary material-symbols-outlined text-lg">
              account_balance_wallet
            </span>
            <div>
              <p className="text-[11px] text-on-surface-variant">Tổng doanh số trong kỳ</p>
              <p className="text-sm font-extrabold text-on-surface">
                {formatCurrencyVND(summary.total_revenue || 0)}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2.5">
            <span className="p-2 rounded-xl bg-secondary/10 text-secondary material-symbols-outlined text-lg">
              shopping_cart_checkout
            </span>
            <div>
              <p className="text-[11px] text-on-surface-variant">Tổng số đơn hoàn tất</p>
              <p className="text-sm font-extrabold text-on-surface">
                {summary.total_orders || 0} đơn
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2.5">
            <span className="p-2 rounded-xl bg-amber-500/10 text-amber-500 material-symbols-outlined text-lg">
              hotel_class
            </span>
            <div>
              <p className="text-[11px] text-on-surface-variant">Đỉnh doanh thu ngày ({summary.peak_date || '—'})</p>
              <p className="text-sm font-extrabold text-amber-500">
                {formatCurrencyVND(summary.peak_revenue || 0)}
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
