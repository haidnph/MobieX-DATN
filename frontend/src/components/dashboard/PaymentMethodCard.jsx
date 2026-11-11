// src/components/dashboard/PaymentMethodCard.jsx
// Visual breakdown of orders by payment gateway (VNPay, ZaloPay, MoMo, COD).

import { formatCurrencyVND } from '../../utils/formatCurrencyVND'

const GATEWAY_CONFIG = {
  vnpay: {
    label: 'Cổng VNPAY-QR',
    color: '#0284c7', // Sky-600
    badgeClass: 'bg-sky-500/15 text-sky-500 border-sky-500/30',
    barClass: 'bg-sky-500',
    icon: 'credit_card',
  },
  momo: {
    label: 'Ví Điện tử MoMo',
    color: '#d946ef', // Fuchsia-500
    badgeClass: 'bg-fuchsia-500/15 text-fuchsia-500 border-fuchsia-500/30',
    barClass: 'bg-fuchsia-500',
    icon: 'account_balance_wallet',
  },
  zalopay: {
    label: 'Ví ZaloPay',
    color: '#06b6d4', // Cyan-500
    badgeClass: 'bg-cyan-500/15 text-cyan-500 border-cyan-500/30',
    barClass: 'bg-cyan-500',
    icon: 'qr_code_scanner',
  },
  cod: {
    label: 'Tiền mặt khi nhận (COD)',
    color: '#f59e0b', // Amber-500
    badgeClass: 'bg-amber-500/15 text-amber-500 border-amber-500/30',
    barClass: 'bg-amber-500',
    icon: 'local_shipping',
  },
}

export default function PaymentMethodCard({ distribution = {} }) {
  const methods = ['vnpay', 'momo', 'zalopay', 'cod']

  return (
    <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 flex flex-col justify-between shadow-sm">
      <div className="flex items-center justify-between pb-3 border-b border-outline-variant/15">
        <div>
          <h3 className="text-base font-bold text-on-surface">Thị phần Cổng Thanh toán</h3>
          <p className="text-xs text-on-surface-variant mt-0.5">
            Tỷ trọng các kênh thanh toán qua đơn hàng
          </p>
        </div>
        <span className="material-symbols-outlined text-outline">payments</span>
      </div>

      <div className="py-2 space-y-4 my-auto">
        {methods.map((key) => {
          const item = distribution[key] || { count: 0, total_amount: 0, percentage: 0 }
          const cfg = GATEWAY_CONFIG[key]
          const pct = Number(item.percentage) || 0

          return (
            <div key={key} className="space-y-1.5">
              <div className="flex items-center justify-between text-xs">
                <div className="flex items-center gap-2">
                  <span
                    className={`p-1 rounded-lg border flex items-center justify-center ${cfg.badgeClass}`}
                  >
                    <span className="material-symbols-outlined text-sm">{cfg.icon}</span>
                  </span>
                  <span className="font-semibold text-on-surface">{cfg.label}</span>
                </div>

                <div className="flex items-center gap-2 font-mono">
                  <span className="text-[11px] text-on-surface-variant">
                    {item.count} đơn
                  </span>
                  <span className="font-bold text-on-surface">
                    {pct}%
                  </span>
                </div>
              </div>

              {/* Progress Bar */}
              <div className="h-2 w-full bg-surface-container-high rounded-full overflow-hidden">
                <div
                  style={{ width: `${pct}%` }}
                  className={`h-full ${cfg.barClass} rounded-full transition-all duration-500`}
                />
              </div>

              {/* Revenue Subtotal */}
              <div className="flex justify-end text-[11px] text-outline font-mono">
                Tổng thanh toán: {formatCurrencyVND(Number(item.total_amount) || 0)}
              </div>
            </div>
          )
        })}
      </div>

      <div className="pt-3 border-t border-outline-variant/15 flex items-center justify-between text-xs text-on-surface-variant">
        <span>Bảo mật chuẩn mã hóa thanh toán</span>
        <span className="text-secondary font-semibold flex items-center gap-1">
          <span className="material-symbols-outlined text-sm">lock</span> 100% mã hóa
        </span>
      </div>
    </div>
  )
}
