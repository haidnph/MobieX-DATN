import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Spinner from '../../components/common/Spinner'

const STATUS = {
  pending: { label: 'Chờ duyệt', color: 'bg-amber-100 text-amber-800 border-amber-200' },
  approved: { label: 'Đã duyệt — Chờ hàng', color: 'bg-blue-100 text-blue-800 border-blue-200' },
  shipping_back: { label: 'Đang gửi về kho', color: 'bg-indigo-100 text-indigo-800 border-indigo-200' },
  received: { label: 'Kho đã nhận', color: 'bg-cyan-100 text-cyan-800 border-cyan-200' },
  inspecting: { label: 'Đang kiểm tra', color: 'bg-violet-100 text-violet-800 border-violet-200' },
  completed: { label: 'Hoàn tất', color: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
  rejected: { label: 'Từ chối', color: 'bg-red-100 text-red-800 border-red-200' },
  cancelled: { label: 'Đã hủy', color: 'bg-gray-100 text-gray-700 border-gray-200' },
}

export default function AdminReturnPage() {
  const [rows, setRows] = useState([])
  const [selected, setSelected] = useState(null)
  const [status, setStatus] = useState('')
  const [returnTypeFilter, setReturnTypeFilter] = useState('')
  const [keyword, setKeyword] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)

  // Inspection form states
  const [inspectionNote, setInspectionNote] = useState('')
  const [conditions, setConditions] = useState({})
  const [itemNotes, setItemNotes] = useState({})
  const [restockables, setRestockables] = useState({})

  // Refund execution states
  const [refundAmount, setRefundAmount] = useState('')
  const [refundMethod, setRefundMethod] = useState('bank_transfer')
  const [refundTxId, setRefundTxId] = useState('')
  const [refundNote, setRefundNote] = useState('')

  // Replacement execution states
  const [replacementCarrier, setReplacementCarrier] = useState('Viettel Post')
  const [replacementTrackingCode, setReplacementTrackingCode] = useState('')
  const [replacementNote, setReplacementNote] = useState('')

  // Approve / Reject notes
  const [actionNote, setActionNote] = useState('')

  const { showToast } = useToast()

  const load = () => {
    setLoading(true)
    axiosClient
      .get(EP.ADMIN_RETURNS, {
        params: {
          status: status || undefined,
          return_type: returnTypeFilter || undefined,
          q: keyword || undefined,
          per_page: 50,
        },
      })
      .then(({ data }) => {
        const list = data.data || []
        setRows(list)
        if (selected) {
          const fresh = list.find((r) => r.id === selected.id)
          if (fresh) openDetail(fresh.id)
        }
      })
      .catch((e) => showToast(e.response?.data?.message || 'Không tải được danh sách yêu cầu đổi trả.', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
  }, [status, returnTypeFilter])

  const openDetail = async (id) => {
    try {
      const { data } = await axiosClient.get(EP.ADMIN_RETURN_DETAIL(id))
      const row = data.data
      setSelected(row)
      setInspectionNote(row.admin_note || '')
      setActionNote('')

      // Set initial item conditions & restock checks
      const nextConds = {}
      const nextNotes = {}
      const nextRestock = {}
      let calcRefund = 0

      ;(row.items || []).forEach((i) => {
        nextConds[i.id] = i.inspection_condition || 'good'
        nextNotes[i.id] = i.inspection_note || ''
        nextRestock[i.id] = i.is_restocked ?? true
        calcRefund += Number(i.refund_amount || (Number(i.order_item?.price || 0) * i.quantity))
      })

      setConditions(nextConds)
      setItemNotes(nextNotes)
      setRestockables(nextRestock)

      setRefundAmount(row.refund_amount ? String(row.refund_amount) : String(calcRefund))
      setRefundMethod(row.refund_method || (row.order?.payment_method === 'cod' ? 'bank_transfer' : 'original_gateway'))
      setRefundTxId(row.refund_transaction_id || '')
      setRefundNote('')

      setReplacementCarrier(row.replacement_carrier || 'Viettel Post')
      setReplacementTrackingCode(row.replacement_tracking_code || '')
      setReplacementNote('')
    } catch (e) {
      showToast(e.response?.data?.message || 'Không tải được chi tiết yêu cầu.', 'error')
    }
  }

  const act = async (url, payload = {}) => {
    if (!selected) return
    setBusy(true)
    try {
      const { data } = await axiosClient.patch(url, payload)
      setSelected(data.data)
      showToast(data.message || 'Đã cập nhật yêu cầu thành công.', 'success')
      load()
    } catch (e) {
      const msg =
        e.response?.data?.message ||
        Object.values(e.response?.data?.errors || {}).flat()[0] ||
        'Không thể cập nhật yêu cầu.'
      showToast(msg, 'error')
    } finally {
      setBusy(false)
    }
  }

  const handleApprove = () => {
    act(EP.ADMIN_RETURN_APPROVE(selected.id), {
      admin_note: actionNote.trim() || undefined,
    })
  }

  const handleReject = () => {
    if (!actionNote.trim()) {
      return showToast('Vui lòng nhập lý do từ chối yêu cầu đổi trả.', 'warning')
    }
    act(EP.ADMIN_RETURN_REJECT(selected.id), { admin_note: actionNote.trim() })
  }

  const handleReceive = () => {
    act(EP.ADMIN_RETURN_RECEIVE(selected.id), {
      admin_note: actionNote.trim() || undefined,
    })
  }

  const handleSaveInspection = () => {
    if (!inspectionNote.trim()) {
      return showToast('Vui lòng nhập ghi chú kết quả thẩm định tình trạng sản phẩm.', 'warning')
    }
    act(EP.ADMIN_RETURN_INSPECT(selected.id), {
      admin_note: inspectionNote.trim(),
      items: selected.items.map((item) => ({
        id: item.id,
        inspection_condition: conditions[item.id] || 'good',
        inspection_note: itemNotes[item.id] || '',
        is_restocked: Boolean(restockables[item.id]),
      })),
    })
  }

  const handleCompleteRefund = () => {
    const amt = Number(refundAmount)
    if (isNaN(amt) || amt <= 0) {
      return showToast('Vui lòng nhập số tiền hoàn hợp lệ (> 0).', 'warning')
    }
    if (!refundTxId.trim()) {
      return showToast('Vui lòng nhập mã giao dịch hoàn tiền hoặc mã tham chiếu chuyển khoản.', 'warning')
    }
    act(EP.ADMIN_RETURN_COMPLETE(selected.id), {
      refund_amount: amt,
      refund_method: refundMethod,
      refund_transaction_id: refundTxId.trim(),
      admin_note: refundNote.trim() || undefined,
    })
  }

  const handleCompleteReplacement = () => {
    if (!replacementCarrier.trim() || !replacementTrackingCode.trim()) {
      return showToast('Vui lòng nhập đơn vị vận chuyển và mã vận đơn gửi hàng đổi mới.', 'warning')
    }
    act(EP.ADMIN_RETURN_COMPLETE(selected.id), {
      replacement_carrier: replacementCarrier.trim(),
      replacement_tracking_code: replacementTrackingCode.trim(),
      admin_note: replacementNote.trim() || undefined,
    })
  }

  return (
    <main className="p-4 md:p-7 space-y-6">
      {/* Top Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý Đổi trả & Hoàn tiền</h1>
          <p className="text-xs text-on-surface-variant">
            Tiếp nhận, xét duyệt, xác nhận nhận hàng, thẩm định sản phẩm và thực hiện hoàn tiền hoặc đổi hàng.
          </p>
        </div>
        <button
          onClick={load}
          className="text-xs px-3.5 py-2 rounded-xl border border-outline-variant hover:bg-surface-container font-semibold flex items-center gap-1.5 transition-colors"
        >
          <span className="material-symbols-outlined text-sm">refresh</span>
          Làm mới
        </button>
      </div>

      {/* Filter and Search Bar */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div className="md:col-span-5 flex gap-2">
          <input
            value={keyword}
            onChange={(e) => setKeyword(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && load()}
            placeholder="Tìm theo mã RTN, mã đơn hàng, tên khách, email..."
            className="flex-1 rounded-xl border border-outline-variant bg-surface px-3.5 py-2 text-xs text-on-surface"
          />
          <button
            onClick={load}
            className="rounded-xl bg-primary text-on-primary px-4 py-2 text-xs font-semibold hover:opacity-95"
          >
            Tìm
          </button>
        </div>

        <div className="md:col-span-4 flex gap-2">
          <select
            value={status}
            onChange={(e) => setStatus(e.target.value)}
            className="flex-1 rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface"
          >
            <option value="">Tất cả trạng thái</option>
            {Object.entries(STATUS).map(([key, cfg]) => (
              <option key={key} value={key}>
                {cfg.label}
              </option>
            ))}
          </select>
        </div>

        <div className="md:col-span-3">
          <select
            value={returnTypeFilter}
            onChange={(e) => setReturnTypeFilter(e.target.value)}
            className="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface"
          >
            <option value="">Tất cả loại yêu cầu</option>
            <option value="refund">Hoàn tiền</option>
            <option value="replacement">Đổi hàng mới</option>
          </select>
        </div>
      </div>

      {/* Main Grid: Left list + Right details */}
      <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(420px,1.4fr)] gap-6 items-start">
        {/* Left: Request List */}
        <section className="rounded-2xl border border-outline-variant/20 bg-surface-container overflow-hidden">
          <div className="p-4 border-b border-outline-variant/15 font-semibold text-xs text-on-surface-variant flex items-center justify-between">
            <span>Danh sách yêu cầu ({rows.length})</span>
          </div>

          {loading ? (
            <div className="p-12 grid place-items-center">
              <Spinner />
            </div>
          ) : rows.length === 0 ? (
            <div className="p-8 text-center text-xs text-on-surface-variant">Không có yêu cầu đổi trả phù hợp.</div>
          ) : (
            <div className="divide-y divide-outline-variant/10 max-h-[750px] overflow-y-auto">
              {rows.map((row) => {
                const isCur = selected?.id === row.id
                const meta = STATUS[row.status] || { label: row.status, color: 'bg-surface text-on-surface' }
                return (
                  <button
                    key={row.id}
                    onClick={() => openDetail(row.id)}
                    className={`w-full text-left p-4 hover:bg-surface-container-high transition-colors ${
                      isCur ? 'bg-primary/10 border-l-4 border-primary' : ''
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <strong className="text-xs font-mono text-on-surface">{row.return_code}</strong>
                      <span className={`text-[11px] font-semibold px-2 py-0.5 rounded-full border ${meta.color}`}>
                        {meta.label}
                      </span>
                    </div>

                    <div className="flex items-center justify-between gap-2 mt-1">
                      <p className="text-xs font-semibold text-on-surface truncate">
                        {row.user?.name || 'Khách hàng'} · Đơn #{row.order?.order_code || row.order_id}
                      </p>
                      <span className="text-[11px] font-medium text-primary shrink-0">
                        {row.return_type === 'replacement' ? 'Đổi hàng' : 'Hoàn tiền'}
                      </span>
                    </div>

                    <p className="text-[11px] text-on-surface-variant mt-1 line-clamp-1">
                      Lý do: {row.reason}
                    </p>

                    <div className="flex items-center justify-between gap-2 text-[10px] text-outline mt-1.5 pt-1 border-t border-outline-variant/10">
                      <span>{new Date(row.created_at).toLocaleDateString('vi-VN')}</span>
                      <span>{(row.items || []).length} sản phẩm</span>
                    </div>
                  </button>
                )
              })}
            </div>
          )}
        </section>

        {/* Right: Selected Details & Action Panel */}
        <section className="rounded-2xl border border-outline-variant/20 bg-surface-container p-5 md:p-6 space-y-6">
          {!selected ? (
            <div className="py-24 text-center space-y-2">
              <span className="material-symbols-outlined text-5xl text-outline">touch_app</span>
              <p className="text-sm font-semibold text-on-surface">Chưa chọn yêu cầu nào</p>
              <p className="text-xs text-on-surface-variant">Chọn một yêu cầu bên trái để xem chi tiết và xử lý.</p>
            </div>
          ) : (
            <>
              {/* Header */}
              <div className="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/15 pb-4">
                <div>
                  <div className="flex items-center gap-2">
                    <h2 className="text-lg font-bold font-mono text-on-surface">{selected.return_code}</h2>
                    <span
                      className={`text-xs font-semibold px-2.5 py-0.5 rounded-full border ${
                        STATUS[selected.status]?.color || ''
                      }`}
                    >
                      {STATUS[selected.status]?.label || selected.status}
                    </span>
                  </div>
                  <p className="text-xs text-on-surface-variant mt-0.5">
                    Đơn hàng{' '}
                    <Link
                      to={`/admin/orders/${selected.order_id}`}
                      className="font-mono text-primary font-bold hover:underline"
                    >
                      #{selected.order?.order_code || selected.order_id}
                    </Link>{' '}
                    · {selected.user?.name} ({selected.user?.email})
                  </p>
                </div>

                <div className="text-right">
                  <span className="text-xs font-bold px-3 py-1 rounded-lg bg-surface border border-outline-variant/30 text-on-surface">
                    {selected.return_type === 'replacement' ? 'Phương án: Đổi hàng mới' : 'Phương án: Trả hàng & Hoàn tiền'}
                  </span>
                </div>
              </div>

              {/* Items Table */}
              <div className="space-y-2">
                <h3 className="text-xs font-bold text-on-surface-variant uppercase tracking-wider">
                  Sản phẩm yêu cầu trả ({(selected.items || []).length})
                </h3>
                <div className="divide-y divide-outline-variant/10 rounded-xl bg-surface border border-outline-variant/20 overflow-hidden">
                  {(selected.items || []).map((line) => {
                    const isInspectingState = ['received', 'inspecting'].includes(selected.status)
                    return (
                      <div key={line.id} className="p-3.5 space-y-3">
                        <div className="flex items-center justify-between gap-3 text-xs">
                          <div className="min-w-0">
                            <p className="font-semibold text-on-surface truncate">
                              {line.order_item?.product_name || 'Sản phẩm'}
                            </p>
                            <p className="text-[11px] text-on-surface-variant">
                              {line.order_item?.variant_name || 'Mặc định'} · Đơn giá:{' '}
                              {Number(line.order_item?.price || 0).toLocaleString('vi-VN')} đ
                            </p>
                          </div>
                          <div className="text-right shrink-0">
                            <p className="font-bold text-on-surface">SL yêu cầu: {line.quantity}</p>
                            <p className="text-[11px] text-primary font-semibold">
                              {(Number(line.order_item?.price || 0) * line.quantity).toLocaleString('vi-VN')} đ
                            </p>
                          </div>
                        </div>

                        {/* Inspection inputs if received/inspecting */}
                        {isInspectingState && (
                          <div className="pt-2 border-t border-outline-variant/10 grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs bg-surface-container/30 p-2.5 rounded-lg">
                            <div className="sm:col-span-5">
                              <label className="block text-[11px] font-medium text-on-surface-variant mb-1">
                                Tình trạng thẩm định:
                              </label>
                              <select
                                value={conditions[line.id] || 'good'}
                                onChange={(e) => setConditions((old) => ({ ...old, [line.id]: e.target.value }))}
                                className="w-full rounded-lg border border-outline-variant bg-surface px-2.5 py-1.5 text-xs"
                              >
                                <option value="good">Tốt / Nguyên seal như mới</option>
                                <option value="opened">Đã bóc hộp / sử dụng nhẹ</option>
                                <option value="damaged">Hư hỏng / trầy xước</option>
                                <option value="missing_accessory">Thiếu phụ kiện</option>
                                <option value="defective">Lỗi kỹ thuật nhà sản xuất</option>
                              </select>
                            </div>

                            <div className="sm:col-span-4">
                              <label className="block text-[11px] font-medium text-on-surface-variant mb-1">
                                Ghi chú chi tiết:
                              </label>
                              <input
                                type="text"
                                placeholder="Ghi nhận lỗi, trầy xước..."
                                value={itemNotes[line.id] || ''}
                                onChange={(e) => setItemNotes((old) => ({ ...old, [line.id]: e.target.value }))}
                                className="w-full rounded-lg border border-outline-variant bg-surface px-2.5 py-1.5 text-xs"
                              />
                            </div>

                            <div className="sm:col-span-3 flex items-end pb-1.5">
                              <label className="flex items-center gap-2 cursor-pointer text-xs font-semibold text-primary">
                                <input
                                  type="checkbox"
                                  checked={Boolean(restockables[line.id])}
                                  onChange={(e) =>
                                    setRestockables((old) => ({ ...old, [line.id]: e.target.checked }))
                                  }
                                  className="rounded text-primary focus:ring-primary"
                                />
                                Nhập lại kho
                              </label>
                            </div>
                          </div>
                        )}
                      </div>
                    )
                  })}
                </div>
              </div>

              {/* Reason, Description & Bank info */}
              <div className="text-xs space-y-2 p-4 rounded-xl bg-surface border border-outline-variant/20">
                <p>
                  <strong className="text-on-surface">Lý do yêu cầu:</strong> {selected.reason}
                </p>
                <p>
                  <strong className="text-on-surface">Mô tả của khách:</strong> {selected.description || 'Không có'}
                </p>
                {selected.customer_note && (
                  <p>
                    <strong className="text-on-surface">Ghi chú thêm:</strong> {selected.customer_note}
                  </p>
                )}
                {selected.bank_account_number && (
                  <div className="p-2.5 rounded-lg bg-surface-container font-mono text-xs">
                    <span className="font-bold font-sans text-on-surface">Tài khoản hoàn tiền COD:</span>{' '}
                    {selected.bank_name} · STK: <strong>{selected.bank_account_number}</strong> · Chủ TK:{' '}
                    <strong>{selected.bank_account_name}</strong>
                  </div>
                )}
                {selected.shipping_carrier && (
                  <p>
                    <strong className="text-on-surface">Vận chuyển trả hàng:</strong> {selected.shipping_carrier} · Mã:{' '}
                    <span className="font-mono">{selected.return_tracking_code}</span>
                  </p>
                )}
                {selected.replacement_tracking_code && (
                  <p className="text-emerald-700 font-bold">
                    Vận chuyển đổi mới: {selected.replacement_carrier} · Mã:{' '}
                    <span className="font-mono">{selected.replacement_tracking_code}</span>
                  </p>
                )}
                {selected.refund_transaction_id && (
                  <p className="text-primary font-bold">
                    Đã hoàn tiền: {Number(selected.refund_amount).toLocaleString('vi-VN')} đ · Phương thức:{' '}
                    {selected.refund_method} · Mã GD: <span className="font-mono">{selected.refund_transaction_id}</span>
                  </p>
                )}
              </div>

              {/* Evidence Photos */}
              {selected.attachments?.length > 0 && (
                <div className="space-y-1.5">
                  <p className="text-xs font-bold text-on-surface-variant">Ảnh minh chứng khách gửi:</p>
                  <div className="flex flex-wrap gap-2.5">
                    {selected.attachments.map((path, idx) => (
                      <a
                        key={path}
                        href={path.startsWith('http') ? path : `/storage/${path}`}
                        target="_blank"
                        rel="noreferrer"
                        className="group relative block"
                      >
                        <img
                          src={path.startsWith('http') ? path : `/storage/${path}`}
                          alt={`Minh chứng ${idx + 1}`}
                          className="w-20 h-20 object-cover rounded-xl border border-outline-variant/30 group-hover:opacity-80 transition-opacity"
                        />
                      </a>
                    ))}
                  </div>
                </div>
              )}

              {/* ACTION PANELS BASED ON CURRENT STATUS */}
              <div className="border-t border-outline-variant/20 pt-4 space-y-4">
                <h3 className="text-xs font-bold text-on-surface uppercase tracking-wider">Thao tác xử lý</h3>

                {/* 1. If Pending: Approve or Reject */}
                {selected.status === 'pending' && (
                  <div className="p-4 rounded-xl border border-outline-variant/30 bg-surface space-y-3">
                    <div>
                      <label className="block text-xs font-semibold text-on-surface mb-1">
                        Ghi chú phê duyệt / Hướng dẫn gửi hàng (hoặc Lý do từ chối nếu từ chối):
                      </label>
                      <textarea
                        value={actionNote}
                        onChange={(e) => setActionNote(e.target.value)}
                        rows={2}
                        placeholder="Nhập hướng dẫn địa chỉ kho nhận hàng, hoặc ghi chú lý do..."
                        className="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs"
                      />
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <button
                        disabled={busy}
                        onClick={handleApprove}
                        className="rounded-xl bg-emerald-600 text-white px-4 py-2 text-xs font-bold hover:bg-emerald-700 disabled:opacity-50"
                      >
                        Duyệt yêu cầu (Chấp thuận trả hàng)
                      </button>
                      <button
                        disabled={busy}
                        onClick={handleReject}
                        className="rounded-xl bg-red-600 text-white px-4 py-2 text-xs font-bold hover:bg-red-700 disabled:opacity-50"
                      >
                        Từ chối yêu cầu
                      </button>
                    </div>
                  </div>
                )}

                {/* 2. If Approved / Shipping Back: Confirm Receive */}
                {['approved', 'shipping_back'].includes(selected.status) && (
                  <div className="p-4 rounded-xl border border-primary/30 bg-primary/5 space-y-3">
                    <p className="text-xs font-semibold text-primary">
                      Khách hàng đang gửi hoặc đã gửi kiện hàng về kho. Khi nhận được hàng, bấm xác nhận bên dưới:
                    </p>
                    <textarea
                      value={actionNote}
                      onChange={(e) => setActionNote(e.target.value)}
                      rows={2}
                      placeholder="Ghi chú khi nhận kiện hàng (tình trạng gói hàng bên ngoài)..."
                      className="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs"
                    />
                    <button
                      disabled={busy}
                      onClick={handleReceive}
                      className="rounded-xl bg-primary text-on-primary px-5 py-2.5 text-xs font-bold hover:opacity-95 disabled:opacity-50"
                    >
                      Xác nhận kho đã nhận kiện hàng
                    </button>
                  </div>
                )}

                {/* 3. If Received / Inspecting: Save Inspection & Complete */}
                {['received', 'inspecting'].includes(selected.status) && (
                  <div className="space-y-4">
                    {/* Inspection note & save */}
                    <div className="p-4 rounded-xl border border-outline-variant/30 bg-surface space-y-3">
                      <div>
                        <label className="block text-xs font-semibold text-on-surface mb-1">
                          Kết quả thẩm định tổng thể *
                        </label>
                        <textarea
                          value={inspectionNote}
                          onChange={(e) => setInspectionNote(e.target.value)}
                          rows={2}
                          placeholder="Mô tả kết quả thẩm định kỹ thuật, tình trạng sản phẩm..."
                          className="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs"
                          required
                        />
                      </div>
                      <button
                        disabled={busy}
                        onClick={handleSaveInspection}
                        className="rounded-xl bg-surface-container-high border border-outline-variant px-4 py-2 text-xs font-bold hover:bg-surface-container-highest disabled:opacity-50"
                      >
                        Lưu kết quả thẩm định
                      </button>
                    </div>

                    {/* Complete resolution: Replacement or Refund */}
                    {selected.return_type === 'replacement' ? (
                      <div className="p-4 rounded-xl border border-emerald-500/30 bg-emerald-50/50 space-y-3">
                        <p className="text-xs font-bold text-emerald-800">
                          Xử lý gửi sản phẩm đổi mới cho khách hàng:
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                          <div>
                            <label className="block text-[11px] font-semibold text-on-surface mb-1">
                              Đơn vị vận chuyển *
                            </label>
                            <input
                              type="text"
                              value={replacementCarrier}
                              onChange={(e) => setReplacementCarrier(e.target.value)}
                              placeholder="Ví dụ: Viettel Post"
                              className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs"
                            />
                          </div>
                          <div>
                            <label className="block text-[11px] font-semibold text-on-surface mb-1">
                              Mã vận đơn mới *
                            </label>
                            <input
                              type="text"
                              value={replacementTrackingCode}
                              onChange={(e) => setReplacementTrackingCode(e.target.value)}
                              placeholder="Ví dụ: VTP987654321"
                              className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs font-mono"
                            />
                          </div>
                          <div className="sm:col-span-2">
                            <label className="block text-[11px] font-semibold text-on-surface mb-1">Ghi chú thêm</label>
                            <input
                              type="text"
                              value={replacementNote}
                              onChange={(e) => setReplacementNote(e.target.value)}
                              placeholder="Ví dụ: Sản phẩm đổi mới nguyên seal..."
                              className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs"
                            />
                          </div>
                        </div>
                        <button
                          disabled={busy}
                          onClick={handleCompleteReplacement}
                          className="rounded-xl bg-emerald-600 text-white px-5 py-2.5 text-xs font-bold hover:bg-emerald-700 disabled:opacity-50"
                        >
                          Xác nhận gửi hàng đổi mới & Hoàn tất
                        </button>
                      </div>
                    ) : (
                      <div className="p-4 rounded-xl border border-primary/30 bg-primary/5 space-y-3">
                        <p className="text-xs font-bold text-primary">
                          Xác nhận hoàn tiền cho đơn hàng:
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                          <div>
                            <label className="block text-[11px] font-semibold text-on-surface mb-1">
                              Số tiền hoàn (VND) *
                            </label>
                            <input
                              type="number"
                              min="0"
                              max={selected.order?.total_amount}
                              value={refundAmount}
                              onChange={(e) => setRefundAmount(e.target.value)}
                              className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs font-bold font-mono"
                            />
                          </div>
                          <div>
                            <label className="block text-[11px] font-semibold text-on-surface mb-1">
                              Phương thức hoàn tiền *
                            </label>
                            <select
                              value={refundMethod}
                              onChange={(e) => setRefundMethod(e.target.value)}
                              className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs"
                            >
                              <option value="bank_transfer">Chuyển khoản ngân hàng</option>
                              <option value="original_gateway">Cổng thanh toán ban đầu (VNPay/MoMo/ZaloPay)</option>
                              <option value="cash">Tiền mặt tại cửa hàng</option>
                              <option value="other">Khác</option>
                            </select>
                          </div>
                          <div className="sm:col-span-2">
                            <label className="block text-[11px] font-semibold text-on-surface mb-1">
                              Mã giao dịch hoàn tiền / Số bút toán ngân hàng *
                            </label>
                            <input
                              type="text"
                              value={refundTxId}
                              onChange={(e) => setRefundTxId(e.target.value)}
                              placeholder="Ví dụ: FT26118999 hoặc mã GD cổng"
                              className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs font-mono"
                              required
                            />
                          </div>
                          <div className="sm:col-span-2">
                            <label className="block text-[11px] font-semibold text-on-surface mb-1">Ghi chú đối soát</label>
                            <input
                              type="text"
                              value={refundNote}
                              onChange={(e) => setRefundNote(e.target.value)}
                              placeholder="Ví dụ: Đã chuyển khoản theo STK khách cung cấp..."
                              className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs"
                            />
                          </div>
                        </div>
                        <button
                          disabled={busy}
                          onClick={handleCompleteRefund}
                          className="rounded-xl bg-primary text-on-primary px-5 py-2.5 text-xs font-bold hover:opacity-95 disabled:opacity-50"
                        >
                          Xác nhận hoàn tiền & Hoàn tất
                        </button>
                      </div>
                    )}
                  </div>
                )}
              </div>

              {/* Audit History Timeline */}
              <div className="border-t border-outline-variant/15 pt-4 space-y-3">
                <h3 className="text-xs font-bold text-on-surface-variant uppercase tracking-wider">
                  Lịch sử xử lý & nhật ký kiểm soát ({(selected.histories || []).length})
                </h3>
                <div className="space-y-2">
                  {(selected.histories || []).map((h) => (
                    <div
                      key={h.id}
                      className="p-3 rounded-xl bg-surface border border-outline-variant/15 text-xs space-y-1"
                    >
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-semibold text-on-surface">
                          {STATUS[h.status]?.label || h.status}
                        </span>
                        <span className="text-[11px] text-outline">
                          {new Date(h.created_at).toLocaleString('vi-VN')}
                        </span>
                      </div>
                      <p className="text-on-surface-variant text-[11px]">{h.note}</p>
                      {h.user && (
                        <p className="text-[10px] text-outline">Thực hiện bởi: {h.user.name}</p>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            </>
          )}
        </section>
      </div>
    </main>
  )
}
