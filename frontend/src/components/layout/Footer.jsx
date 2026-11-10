// src/components/layout/Footer.jsx
// Site-wide footer with links and branding.

import { Link } from 'react-router-dom'

export default function Footer() {
  return (
    <footer className="bg-surface-container-lowest border-t border-outline-variant/20 mt-auto">
      <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-10">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* Brand */}
          <div className="md:col-span-1">
            <span className="text-xl font-bold text-on-surface">
              Mobile<span className="text-primary">X</span>
            </span>
            <p className="mt-3 text-sm text-on-surface-variant leading-relaxed">
              Cửa hàng công nghệ uy tín hàng đầu Việt Nam. Chính hãng, giá tốt, bảo hành chính thức.
            </p>
          </div>

          {/* Products */}
          <div>
            <h3 className="text-sm font-semibold text-on-surface mb-3">Sản phẩm</h3>
            <ul className="space-y-2">
              {['Điện thoại', 'Phụ kiện', 'Khuyến mãi'].map((label) => (
                <li key={label}>
                  <Link to="/products" className="text-sm text-on-surface-variant hover:text-on-surface transition-colors">
                    {label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Support */}
          <div>
            <h3 className="text-sm font-semibold text-on-surface mb-3">Hỗ trợ & Tra cứu</h3>
            <ul className="space-y-2">
              <li>
                <Link
                  to="/tracking"
                  className="text-sm text-primary font-medium hover:underline inline-flex items-center gap-1"
                >
                  <span className="material-symbols-outlined text-sm">search</span>
                  Tra cứu đơn hàng
                </Link>
              </li>
              {['Chính sách đổi trả', 'Hướng dẫn mua hàng', 'Chính sách bảo hành'].map((label) => (
                <li key={label}>
                  <span className="text-sm text-on-surface-variant">{label}</span>
                </li>
              ))}
            </ul>
          </div>

          {/* Contact */}
          <div>
            <h3 className="text-sm font-semibold text-on-surface mb-3">Liên hệ</h3>
            <ul className="space-y-2 text-sm text-on-surface-variant">
              <li className="flex items-center gap-2">
                <span className="material-symbols-outlined text-base text-primary">location_on</span>
                FPT Polytechnic, Hà Nội
              </li>
              <li className="flex items-center gap-2">
                <span className="material-symbols-outlined text-base text-primary">email</span>
                support@mobilex.vn
              </li>
              <li className="flex items-center gap-2">
                <span className="material-symbols-outlined text-base text-primary">phone</span>
                1800 6789
              </li>
            </ul>
          </div>
        </div>

        <div className="mt-8 pt-6 border-t border-outline-variant/20 flex flex-col md:flex-row items-center justify-between gap-3">
          <p className="text-xs text-on-surface-variant">
            © 2026 MobieX. DATN — FPT Polytechnic. Mọi quyền được bảo lưu.
          </p>
          <div className="flex items-center gap-4">
            <span className="text-xs text-on-surface-variant">Phương thức thanh toán:</span>
            <span className="text-xs font-medium text-primary">VNPay</span>
            <span className="text-xs font-medium text-on-surface-variant">COD</span>
          </div>
        </div>
      </div>
    </footer>
  )
}
