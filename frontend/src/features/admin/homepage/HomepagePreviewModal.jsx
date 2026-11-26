// src/features/admin/homepage/HomepagePreviewModal.jsx
// Interactive modal rendering storefront preview of draft homepage configuration.

import { useEffect, useState } from 'react'
import axiosClient from '../../../api/axiosClient'
import EP from '../../../api/endpoints'
import Modal from '../../../components/common/Modal'
import Spinner from '../../../components/common/Spinner'
import { resolveImageUrl } from '../../../utils/resolveImageUrl'
import { formatCurrencyVND } from '../../../utils/formatCurrencyVND'

export default function HomepagePreviewModal({ open = false, onClose }) {
  const [previewData, setPreviewData] = useState(null)
  const [loading, setLoading] = useState(false)
  const [device, setDevice] = useState('desktop')

  useEffect(() => {
    if (open) {
      setLoading(true)
      axiosClient
        .get(EP.ADMIN_HOMEPAGE_PREVIEW)
        .then(({ data }) => {
          setPreviewData(data?.data || null)
        })
        .catch(() => {})
        .finally(() => setLoading(false))
    }
  }, [open])

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Xem trước Trang chủ (Bản nháp / Draft Preview)"
      size="xl"
    >
      <div className="space-y-4">
        {/* Switch Device Bar */}
        <div className="flex items-center justify-between pb-3 border-b border-outline-variant/15">
          <div className="flex items-center gap-2">
            <span className="w-2.5 h-2.5 rounded-full bg-warning animate-pulse" />
            <span className="text-xs text-on-surface-variant font-medium">
              Đang mô phỏng các thay đổi chưa xuất bản
            </span>
          </div>
          <div className="flex items-center gap-1 bg-surface-container-low p-1 rounded-xl border border-outline-variant/30">
            <button
              type="button"
              onClick={() => setDevice('desktop')}
              className={`flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-semibold cursor-pointer transition-all ${
                device === 'desktop' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'
              }`}
            >
              <span className="material-symbols-outlined text-sm">desktop_windows</span>
              Desktop
            </button>
            <button
              type="button"
              onClick={() => setDevice('mobile')}
              className={`flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-semibold cursor-pointer transition-all ${
                device === 'mobile' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'
              }`}
            >
              <span className="material-symbols-outlined text-sm">phone_iphone</span>
              Mobile
            </button>
          </div>
        </div>

        {/* Viewport Frame */}
        <div className="flex justify-center bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/20 max-h-[70vh] overflow-y-auto">
          {loading ? (
            <div className="flex flex-col items-center justify-center py-24 gap-3">
              <Spinner size="lg" />
              <span className="text-xs text-on-surface-variant">Đang dựng layout xem trước...</span>
            </div>
          ) : !previewData || !previewData.sections?.length ? (
            <div className="py-20 text-center text-on-surface-variant text-xs">
              Chưa có dữ liệu section nào được kích hoạt
            </div>
          ) : (
            <div
              className={`bg-background rounded-2xl overflow-hidden border border-outline-variant/40 shadow-xl transition-all duration-300 space-y-8 p-4 md:p-6 ${
                device === 'mobile' ? 'w-[380px]' : 'w-full'
              }`}
            >
              {previewData.sections.map((sec, idx) => {
                if (!sec.is_active) return null

                return (
                  <section
                    key={sec.id || idx}
                    className="p-4 rounded-xl border border-outline-variant/20 bg-surface-container-high/30 relative"
                  >
                    <div className="flex items-center justify-between mb-3">
                      <div>
                        <span className="text-[10px] uppercase font-bold text-primary tracking-wider">
                          Section #{idx + 1} · {sec.section_type}
                        </span>
                        <h4 className="text-sm font-bold text-on-surface">{sec.title}</h4>
                        {sec.subtitle && (
                          <p className="text-[11px] text-on-surface-variant">{sec.subtitle}</p>
                        )}
                      </div>
                      <span className="text-[10px] px-2 py-0.5 rounded-full bg-surface-container border border-outline-variant/30 text-on-surface-variant">
                        {sec.layout_type}
                      </span>
                    </div>

                    {/* Section Item Previews */}
                    {sec.section_type === 'banner_slider' && (
                      <div className="h-40 rounded-xl bg-surface-container-low border border-outline-variant/20 overflow-hidden flex items-center justify-center relative">
                        {sec.items?.[0] ? (
                          <img
                            src={resolveImageUrl(sec.items[0].image_path || sec.items[0].image_url)}
                            alt={sec.items[0].title}
                            className="w-full h-full object-cover"
                          />
                        ) : (
                          <span className="text-xs text-outline">Chưa có banner hoạt động</span>
                        )}
                      </div>
                    )}

                    {sec.section_type === 'products' && (
                      <div className={`grid gap-3 ${device === 'mobile' ? 'grid-cols-2' : 'grid-cols-4'}`}>
                        {(sec.items || []).slice(0, 4).map((p) => {
                          const thumb = resolveImageUrl(
                            p.images?.find((img) => img.is_primary)?.image_url ||
                            p.thumbnail ||
                            p.images?.[0]?.image_url
                          )
                          return (
                            <div
                              key={p.id}
                              className="bg-surface-container rounded-lg p-2.5 border border-outline-variant/15 text-xs flex flex-col justify-between"
                            >
                              <div className="h-24 bg-surface-container-lowest rounded-md flex items-center justify-center overflow-hidden mb-2">
                                {thumb ? (
                                  <img src={thumb} alt={p.name} className="h-full object-contain" />
                                ) : (
                                  <span className="material-symbols-outlined text-outline">smartphone</span>
                                )}
                              </div>
                              <p className="font-semibold text-on-surface line-clamp-1">{p.name}</p>
                              <p className="text-primary font-bold mt-1">
                                {formatCurrencyVND(Number(p.min_price || p.base_price || 0))}
                              </p>
                            </div>
                          )
                        })}
                      </div>
                    )}

                    {sec.section_type === 'categories' && (
                      <div className="grid grid-cols-3 md:grid-cols-6 gap-2">
                        {(sec.items || []).slice(0, 6).map((c) => (
                          <div
                            key={c.id}
                            className="p-2 rounded-lg bg-surface-container text-center text-[11px] font-semibold text-on-surface border border-outline-variant/20"
                          >
                            <span className="material-symbols-outlined text-xl text-primary block mb-0.5">
                              category
                            </span>
                            <span className="line-clamp-1">{c.name}</span>
                          </div>
                        ))}
                      </div>
                    )}

                    {sec.section_type === 'brands' && (
                      <div className="grid grid-cols-4 gap-2">
                        {(sec.items || []).slice(0, 4).map((b) => (
                          <div
                            key={b.id}
                            className="p-2 rounded-lg bg-surface-container text-center text-xs font-bold text-on-surface border border-outline-variant/20"
                          >
                            {b.name}
                          </div>
                        ))}
                      </div>
                    )}

                    {sec.section_type === 'vouchers' && (
                      <div className={`grid gap-2 ${device === 'mobile' ? 'grid-cols-1' : 'grid-cols-2'}`}>
                        {(sec.items || []).map((coupon) => (
                          <div key={coupon.id} className="rounded-lg border border-primary/30 bg-primary/5 p-3 text-xs">
                            <p className="font-bold text-primary">{coupon.code}</p>
                            <p className="font-semibold text-on-surface">{coupon.name || coupon.discount_value}{coupon.discount_type === 'percent' ? '%' : 'đ'}</p>
                            <p className="text-on-surface-variant">Đơn tối thiểu {formatCurrencyVND(Number(coupon.min_order_amount || 0))}</p>
                          </div>
                        ))}
                        {(sec.items || []).length === 0 && <p className="text-xs text-outline">Chưa chọn voucher để hiển thị.</p>}
                      </div>
                    )}

                    {sec.section_type === 'banner_promo' && (
                      <div className="p-4 rounded-xl bg-gradient-to-r from-primary-container/20 to-surface-container flex items-center justify-between">
                        <div>
                          <span className="text-[10px] font-bold text-primary uppercase">
                            {sec.items?.badge || 'Khuyến mãi'}
                          </span>
                          <h5 className="text-xs font-bold text-on-surface mt-0.5">{sec.title}</h5>
                        </div>
                        <span className="px-3 py-1 bg-primary text-on-primary rounded-lg text-xs font-semibold">
                          {sec.items?.cta_label || 'Khám phá'}
                        </span>
                      </div>
                    )}
                  </section>
                )
              })}
            </div>
          )}
        </div>
      </div>
    </Modal>
  )
}
