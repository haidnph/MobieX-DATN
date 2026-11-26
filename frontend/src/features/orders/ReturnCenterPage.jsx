import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Spinner from '../../components/common/Spinner'

const STATUS_CONFIG = {
  pending: { label: 'Chờ duyệt', color: 'bg-amber-100 text-amber-800 border-amber-200' },
  approved: { label: 'Đã duyệt — Chờ gửi hàng', color: 'bg-blue-100 text-blue-800 border-blue-200' },
  shipping_back: { label: 'Đang gửi hàng về kho', color: 'bg-indigo-100 text-indigo-800 border-indigo-200' },
  received: { label: 'Kho đã nhận hàng', color: 'bg-cyan-100 text-cyan-800 border-cyan-200' },
  inspecting: { label: 'Đang kiểm tra / thẩm định', color: 'bg-violet-100 text-violet-800 border-violet-200' },
  completed: { label: 'Hoàn tất', color: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
  rejected: { label: 'Bị từ chối', color: 'bg-red-100 text-red-800 border-red-200' },
  cancelled: { label: 'Đã hủy', color: 'bg-gray-100 text-gray-700 border-gray-200' },
}

const STEPS = [
  { key: 'pending', label: 'Gửi yêu cầu' },
  { key: 'approved', label: 'Duyệt yêu cầu' },
  { key: 'shipping_back', label: 'Gửi hàng về' },
  { key: 'received', label: 'Kho nhận hàng' },
  { key: 'inspecting', label: 'Thẩm định' },
  { key: 'completed', label: 'Hoàn tất' },
]

export default function ReturnCenterPage() {
  const [rows, setRows] = useState([])
  const [statusFilter, setStatusFilter] = useState('all')
  const [loading, setLoading] = useState(true)
  const [shippingDrafts, setShippingDrafts] = useState({})
  const [submittingId, setSubmittingId] = useState(null)
  const [expandedHistories, setExpandedHistories] = useState({})
  const { showToast } = useToast()

  const load = () => {
    setLoading(true)
    axiosClient
      .get(EP.RETURNS, { params: { per_page: 50 } })
      .then(({ data }) => setRows(data.data || []))
      .catch(() => showToast('Không tải được lịch sử đổi trả.', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
  }, [])

  const updateDraft = (id, field, value) => {
    setShippingDrafts((old) => ({
      ...old,
      [id]: { ...(old[id] || {}), [field]: value },
    }))
  }

  const submitShipping = async (id) => {
    const draft = shippingDrafts[id] || {}
    if (!draft.shipping_carrier?.trim() || !draft.return_tracking_code?.trim()) {
      return showToast('Vui lòng nhập tên đơn vị vận chuyển và mã vận đơn gửi hàng về.', 'warning')
    }
    setSubmittingId(id)
    try {
      const { data } = await axiosClient.patch(EP.RETURN_SHIPPING(id), {
        shipping_carrier: draft.shipping_carrier.trim(),
        return_tracking_code: draft.return_tracking_code.trim(),
      })
      showToast(data.message || 'Đã cập nhật thông tin vận chuyển hàng trả về kho.', 'success')
      load()
    } catch (error) {
      showToast(error.response?.data?.message || 'Không thể cập nhật thông tin vận chuyển.', 'error')
    } finally {
      setSubmittingId(null)
    }
  }

  const cancel = async (id) => {
    if (!window.confirm('Bạn có chắc chắn muốn hủy yêu cầu đổi/trả hàng này không?')) return
    setSubmittingId(id)
    try {
      const { data } = await axiosClient.patch(EP.RETURN_CANCEL(id))
      showToast(data.message || 'Đã hủy yêu cầu đổi/trả hàng.', 'success')
      load()
    } catch (error) {
      showToast(error.response?.data?.message || 'Không thể hủy yêu cầu.', 'error')
    } finally {
      setSubmittingId(null)
    }
  }

  const toggleHistory = (id) => {
    setExpandedHistories((prev) => ({ ...prev, [id]: !prev[id] }))
  }

  const filteredRows =
    statusFilter === 'all' ? rows : rows.filter((r) => r.status === statusFilter)

  if (loading) {
    return (
      <div className="min-h-[50vh] grid place-items-center">
        <Spinner size="lg" />
      </div>
    )
  }

  return (
    <main className="max-w-5xl mx-auto px-4 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Trung tâm Đổi trả & Hoàn tiền</h1>
          <p className="text-sm text-on-surface-variant">
            Theo dõi tiến trình thẩm định, thông tin vận chuyển và kết quả giải quyết yêu cầu.
          </p>
        </div>
        <Link
          to="/orders"
          className="text-xs px-3.5 py-2 rounded-xl border border-outline-variant font-semibold hover:bg-surface-container transition-colors"
        >
          ← Danh sách đơn hàng
        </Link>
      </div>

      {/* Status Filters */}
      <div className="flex items-center gap-2 overflow-x-auto pb-2 border-b border-outline-variant/15 text-xs font-semibold">
        <button
          onClick={() => setStatusFilter('all')}
          className={`px-3 py-1.5 rounded-lg whitespace-nowrap transition-colors ${
            statusFilter === 'all'
              ? 'bg-primary text-on-primary'
              : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'
          }`}
        >
          Tất cả ({rows.length})
        </button>
        {Object.entries(STATUS_CONFIG).map(([key, cfg]) => {
          const count = rows.filter((r) => r.status === key).length
          if (count === 0 && statusFilter !== key) return null
          return (
            <button
              key={key}
              onClick={() => setStatusFilter(key)}
              className={`px-3 py-1.5 rounded-lg whitespace-nowrap transition-colors ${
                statusFilter === key
                  ? 'bg-primary text-on-primary'
                  : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'
              }`}
            >
              {cfg.label} ({count})
            </button>
          )
        })}
      </div>

      {/* List */}
      {filteredRows.length === 0 ? (
        <div className="rounded-2xl border border-outline-variant/20 bg-surface-container p-12 text-center space-y-3">
          <span className="material-symbols-outlined text-5xl text-outline">assignment_return</span>
          <p className="font-semibold text-on-surface">Không có yêu cầu đổi trả nào</p>
          <p className="text-xs text-on-surface-variant">
            {statusFilter === 'all'
              ? 'Bạn chưa tạo yêu cầu đổi trả nào. Nếu cần đổi trả sản phẩm, hãy vào Chi tiết đơn hàng đã giao.'
              : 'Không có yêu cầu nào phù hợp với bộ lọc đã chọn.'}
          </p>
          <div className="pt-2">
            <Link to="/orders" className="text-primary text-sm font-semibold hover:underline">
              Xem đơn hàng của tôi
            </Link>
          </div>
        </div>
      ) : (
        <div className="space-y-6">
          {filteredRows.map((row) => {
            const statusMeta = STATUS_CONFIG[row.status] || {
              label: row.status,
              color: 'bg-surface text-on-surface',
            }
            const isTerminal = ['rejected', 'cancelled'].includes(row.status)
            const draft = shippingDrafts[row.id] || {}

            return (
              <article
                key={row.id}
                className="rounded-2xl border border-outline-variant/20 bg-surface-container p-5 md:p-6 space-y-5"
              >
                {/* Top card info */}
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/15 pb-4">
                  <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                      <strong className="text-base text-on-surface font-mono">{row.return_code}</strong>
                      <span
                        className={`rounded-full px-2.5 py-0.5 text-xs font-semibold border ${statusMeta.color}`}
                      >
                        {statusMeta.label}
                      </span>
                    </div>
                    <p className="text-xs text-on-surface-variant">
                      Đơn hàng #{row.order?.order_code || row.order_id} · Tạo lúc:{' '}
                      {new Date(row.created_at).toLocaleString('vi-VN')}
                    </p>
                  </div>

                  <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold px-2.5 py-1 rounded-lg bg-surface border border-outline-variant/30 text-on-surface">
                      Phương án: {row.return_type === 'replacement' ? 'Đổi hàng mới' : 'Hoàn tiền'}
                    </span>
                    {row.refund_amount > 0 && (
                      <span className="text-xs font-bold text-primary px-2.5 py-1 rounded-lg bg-primary/10">
                        {row.refund_status === 'refunded' ? 'Đã hoàn:' : 'Ước tính hoàn:'}{' '}
                        {Number(row.refund_amount).toLocaleString('vi-VN')} đ
                      </span>
                    )}
                  </div>
                </div>

                {/* Progress Stepper (if not rejected or cancelled) */}
                {!isTerminal && (
                  <div className="py-2 overflow-x-auto">
                    <div className="flex items-center justify-between min-w-[500px] text-xs">
                      {STEPS.map((step, idx) => {
                        const stepOrder = ['pending', 'approved', 'shipping_back', 'received', 'inspecting', 'completed']
                        const currentIndex = stepOrder.indexOf(row.status)
                        const isCurrent = row.status === step.key
                        const isPast = currentIndex >= idx

                        return (
                          <div key={step.key} className="flex-1 flex flex-col items-center relative text-center">
                            {idx > 0 && (
                              <div
                                className={`absolute top-2.5 -left-1/2 w-full h-0.5 ${
                                  isPast ? 'bg-primary' : 'bg-outline-variant/30'
                                }`}
                              />
                            )}
                            <div
                              className={`w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px] z-10 transition-colors ${
                                isCurrent
                                  ? 'bg-primary text-on-primary ring-4 ring-primary/20'
                                  : isPast
                                  ? 'bg-primary text-on-primary'
                                  : 'bg-surface-container-high text-outline border border-outline-variant/40'
                              }`}
                            >
                              {isPast ? '✓' : idx + 1}
                            </div>
                            <span
                              className={`mt-1 font-medium text-[11px] ${
                                isCurrent ? 'text-primary font-bold' : isPast ? 'text-on-surface' : 'text-outline'
                              }`}
                            >
                              {step.label}
                            </span>
                          </div>
                        )
                      })}
                    </div>
                  </div>
                )}

                {/* Returned Products */}
                <div className="space-y-2">
                  <p className="text-xs font-bold text-on-surface-variant uppercase tracking-wider">
                    Sản phẩm yêu cầu đổi trả ({(row.items || []).length})
                  </p>
                  <div className="divide-y divide-outline-variant/10 rounded-xl bg-surface border border-outline-variant/20 overflow-hidden">
                    {(row.items || []).map((line) => (
                      <div key={line.id} className="p-3.5 flex items-center justify-between gap-3 text-sm">
                        <div className="min-w-0">
                          <p className="font-semibold text-on-surface truncate">
                            {line.order_item?.product_name || 'Sản phẩm'}
                          </p>
                          <p className="text-xs text-on-surface-variant">
                            {line.order_item?.variant_name || 'Mặc định'} · Đơn giá:{' '}
                            {Number(line.order_item?.price || 0).toLocaleString('vi-VN')} đ
                          </p>
                        </div>
                        <div className="text-right shrink-0">
                          <p className="font-bold text-on-surface">SL trả: {line.quantity}</p>
                          {line.inspection_condition && (
                            <span className="text-[11px] text-primary font-medium">
                              Tình trạng: {line.inspection_condition}
                            </span>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Reason & Description */}
                <div className="text-xs space-y-1.5 p-3.5 rounded-xl bg-surface border border-outline-variant/20">
                  <p>
                    <strong className="text-on-surface">Lý do:</strong> {row.reason}
                  </p>
                  {row.description && (
                    <p>
                      <strong className="text-on-surface">Mô tả của bạn:</strong> {row.description}
                    </p>
                  )}
                  {row.bank_account_number && (
                    <p>
                      <strong className="text-on-surface">Tài khoản nhận hoàn:</strong> {row.bank_name} · STK:{' '}
                      <span className="font-mono">{row.bank_account_number}</span> · Chủ TK:{' '}
                      <span className="uppercase">{row.bank_account_name}</span>
                    </p>
                  )}
                  {row.shipping_carrier && (
                    <p>
                      <strong className="text-on-surface">Vận đơn gửi về:</strong> {row.shipping_carrier} · Mã:{' '}
                      <span className="font-mono">{row.return_tracking_code}</span>
                    </p>
                  )}
                  {row.replacement_tracking_code && (
                    <p className="text-emerald-700 font-semibold">
                      <strong>Vận đơn gửi hàng đổi mới:</strong> {row.replacement_carrier} · Mã:{' '}
                      <span className="font-mono">{row.replacement_tracking_code}</span>
                    </p>
                  )}
                </div>

                {/* Evidence Photos */}
                {row.attachments?.length > 0 && (
                  <div className="space-y-1.5">
                    <p className="text-xs font-semibold text-on-surface-variant">Ảnh minh chứng:</p>
                    <div className="flex flex-wrap gap-2">
                      {row.attachments.map((path, idx) => (
                        <a
                          key={path}
                          href={path.startsWith('http') ? path : `/storage/${path}`}
                          target="_blank"
                          rel="noreferrer"
                          className="group relative block"
                        >
                          <img
                            src={path.startsWith('http') ? path : `/storage/${path}`}
                            alt={`Ảnh ${idx + 1}`}
                            className="w-16 h-16 object-cover rounded-lg border border-outline-variant/30 group-hover:opacity-80 transition-opacity"
                          />
                        </a>
                      ))}
                    </div>
                  </div>
                )}

                {/* Store Response & Notifications */}
                {row.admin_note && (
                  <div
                    className={`p-3.5 rounded-xl text-xs space-y-1 border ${
                      row.status === 'rejected'
                        ? 'bg-red-50 text-red-900 border-red-200'
                        : 'bg-primary/5 text-on-surface border-primary/20'
                    }`}
                  >
                    <p className="font-bold flex items-center gap-1.5">
                      <span className="material-symbols-outlined text-sm">
                        {row.status === 'rejected' ? 'error' : 'storefront'}
                      </span>
                      Phản hồi từ cửa hàng:
                    </p>
                    <p className="whitespace-pre-wrap">{row.admin_note}</p>
                  </div>
                )}

                {/* Action: Enter Shipping Back Info (when approved) */}
                {row.status === 'approved' && (
                  <div className="p-4 rounded-xl border border-primary/30 bg-primary/5 space-y-3">
                    <div className="flex items-center gap-2 text-primary font-bold text-xs">
                      <span className="material-symbols-outlined text-sm">local_shipping</span>
                      Yêu cầu đã được duyệt — Vui lòng gửi sản phẩm về kho và cập nhật vận đơn
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <label className="block text-[11px] font-semibold text-on-surface mb-1">
                          Đơn vị vận chuyển (Viettel Post, GHTK, GHN...) *
                        </label>
                        <input
                          type="text"
                          value={draft.shipping_carrier || ''}
                          onChange={(e) => updateDraft(row.id, 'shipping_carrier', e.target.value)}
                          placeholder="Ví dụ: Giao Hàng Tiết Kiệm"
                          className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs"
                        />
                      </div>
                      <div>
                        <label className="block text-[11px] font-semibold text-on-surface mb-1">Mã vận đơn *</label>
                        <input
                          type="text"
                          value={draft.return_tracking_code || ''}
                          onChange={(e) => updateDraft(row.id, 'return_tracking_code', e.target.value)}
                          placeholder="Ví dụ: S12345678VN"
                          className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs font-mono"
                        />
                      </div>
                    </div>
                    <button
                      onClick={() => submitShipping(row.id)}
                      disabled={submittingId === row.id}
                      className="rounded-lg bg-primary text-on-primary px-4 py-2 text-xs font-bold hover:opacity-95 disabled:opacity-50"
                    >
                      {submittingId === row.id ? 'Đang cập nhật...' : 'Xác nhận đã gửi hàng về'}
                    </button>
                  </div>
                )}

                {/* Action: Cancel request (when pending) */}
                {row.status === 'pending' && (
                  <div className="flex justify-end pt-2">
                    <button
                      onClick={() => cancel(row.id)}
                      disabled={submittingId === row.id}
                      className="text-xs font-semibold text-error hover:underline flex items-center gap-1"
                    >
                      <span className="material-symbols-outlined text-sm">cancel</span>
                      Hủy yêu cầu đổi/trả này
                    </button>
                  </div>
                )}

                {/* Audit History Toggle */}
                {(row.histories || []).length > 0 && (
                  <div className="border-t border-outline-variant/15 pt-3">
                    <button
                      onClick={() => toggleHistory(row.id)}
                      className="text-xs font-semibold text-on-surface-variant hover:text-on-surface flex items-center gap-1"
                    >
                      <span className="material-symbols-outlined text-sm">
                        {expandedHistories[row.id] ? 'expand_less' : 'history'}
                      </span>
                      {expandedHistories[row.id] ? 'Thu gọn lịch sử xử lý' : 'Xem lịch sử xử lý'}
                    </button>

                    {expandedHistories[row.id] && (
                      <div className="mt-3 space-y-2 p-3 rounded-xl bg-surface text-xs text-on-surface-variant border border-outline-variant/15">
                        {row.histories.map((h) => (
                          <div key={h.id} className="flex items-start justify-between gap-3 border-b border-outline-variant/10 pb-2 last:border-0 last:pb-0">
                            <div>
                              <p className="font-semibold text-on-surface">
                                {STATUS_CONFIG[h.status]?.label || h.status}
                              </p>
                              <p className="text-[11px] text-on-surface-variant">{h.note}</p>
                            </div>
                            <span className="text-[11px] text-outline shrink-0">
                              {new Date(h.created_at).toLocaleString('vi-VN')}
                            </span>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </article>
            )
          })}
        </div>
      )}
    </main>
  )
}
