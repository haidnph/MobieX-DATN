// src/features/admin/AdminInventoryPage.jsx
// Admin warehouse and inventory management: stock monitoring, low-stock alerts, manual import/export, and audit logs.

import { useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDateTime } from '../../utils/formatDate'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Modal from '../../components/common/Modal'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'
import Pagination from '../../components/common/Pagination'

const TRANSACTION_TYPES = {
  import: { label: 'Nhập kho', variant: 'success', icon: 'arrow_downward' },
  export: { label: 'Xuất kho', variant: 'error', icon: 'arrow_upward' },
  dispatch: { label: 'Xuất đơn hàng', variant: 'neutral', icon: 'local_shipping' },
  reserve: { label: 'Giữ hàng', variant: 'warning', icon: 'lock' },
  release: { label: 'Hoàn kho hủy đơn', variant: 'info', icon: 'undo' },
}

export default function AdminInventoryPage() {
  const { showToast } = useToast()
  const [searchParams] = useSearchParams()

  const [activeTab, setActiveTab] = useState('inventory') // 'inventory' | 'transactions'
  const [inventories, setInventories] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState(() => searchParams.get('search') || '')
  const [lowStockOnly, setLowStockOnly] = useState(false)

  // Synchronize search with query param if it updates
  useEffect(() => {
    const q = searchParams.get('search')
    if (q !== null && q !== undefined) {
      setSearch(q)
    }
  }, [searchParams])

  // Transactions state
  const [transactions, setTransactions] = useState([])
  const [transactionPage, setTransactionPage] = useState(1)
  const [transactionLastPage, setTransactionLastPage] = useState(1)
  const [transactionTypeFilter, setTransactionTypeFilter] = useState('')
  const [transactionsLoading, setTransactionsLoading] = useState(false)

  // Import Modal
  const [importModalOpen, setImportModalOpen] = useState(false)
  const [selectedInventory, setSelectedInventory] = useState(null)
  const [importForm, setImportForm] = useState({
    quantity: '',
    cost_price: '',
    note: '',
  })
  const [submittingImport, setSubmittingImport] = useState(false)

  // Export Modal
  const [exportModalOpen, setExportModalOpen] = useState(false)
  const [exportForm, setExportForm] = useState({
    quantity: '',
    note: '',
  })
  const [submittingExport, setSubmittingExport] = useState(false)

  // ── Fetch Inventories ──────────────────────────────────────────────────────
  const fetchInventories = useCallback(async () => {
    setLoading(true)
    try {
      const url = lowStockOnly ? EP.INVENTORY_LOW_STOCK : EP.INVENTORIES
      const { data } = await axiosClient.get(url)
      const list = data.data ?? data
      setInventories(Array.isArray(list) ? list : [])
    } catch {
      showToast('Không thể tải dữ liệu tồn kho.', 'error')
    } finally {
      setLoading(false)
    }
  }, [lowStockOnly, showToast])

  useEffect(() => {
    fetchInventories()
  }, [fetchInventories])

  // ── Fetch Transactions ────────────────────────────────────────────────────
  const fetchTransactions = useCallback(async () => {
    setTransactionsLoading(true)
    try {
      const params = { page: transactionPage }
      if (transactionTypeFilter) params.type = transactionTypeFilter
      const { data } = await axiosClient.get(EP.INVENTORY_TRANSACTIONS, { params })
      const payload = data.data ?? data
      setTransactions(payload.data ?? [])
      setTransactionLastPage(payload.last_page ?? 1)
    } catch {
      showToast('Không thể tải nhật ký giao dịch kho.', 'error')
    } finally {
      setTransactionsLoading(false)
    }
  }, [transactionPage, transactionTypeFilter, showToast])

  useEffect(() => {
    if (activeTab === 'transactions') {
      fetchTransactions()
    }
  }, [activeTab, fetchTransactions])

  // ── Import Action ─────────────────────────────────────────────────────────
  const handleOpenImport = (inv) => {
    setSelectedInventory(inv)
    setImportForm({
      quantity: '10',
      cost_price: inv.variant?.cost_price ? String(inv.variant.cost_price) : '',
      note: 'Nhập hàng bổ sung định kỳ',
    })
    setImportModalOpen(true)
  }

  const handleSubmitImport = async (e) => {
    e.preventDefault()
    if (!selectedInventory) return
    setSubmittingImport(true)

    const payload = {
      product_variant_id: selectedInventory.product_variant_id,
      quantity: Number(importForm.quantity),
      cost_price: importForm.cost_price ? Number(importForm.cost_price) : undefined,
      note: importForm.note?.trim() || 'Nhập kho thủ công',
    }

    try {
      await axiosClient.post(EP.INVENTORY_IMPORT, payload)
      showToast(`Đã nhập ${payload.quantity} sản phẩm vào kho thành công!`)
      setImportModalOpen(false)
      fetchInventories()
      if (activeTab === 'transactions') fetchTransactions()
    } catch (err) {
      const msg = err.response?.data?.message ?? 'Nhập kho thất bại.'
      showToast(msg, 'error')
    } finally {
      setSubmittingImport(false)
    }
  }

  // ── Export Action ─────────────────────────────────────────────────────────
  const handleOpenExport = (inv) => {
    setSelectedInventory(inv)
    setExportForm({
      quantity: '1',
      note: 'Xuất điều chỉnh / Hàng mẫu',
    })
    setExportModalOpen(true)
  }

  const handleSubmitExport = async (e) => {
    e.preventDefault()
    if (!selectedInventory) return
    setSubmittingExport(true)

    const payload = {
      product_variant_id: selectedInventory.product_variant_id,
      quantity: Number(exportForm.quantity),
      note: exportForm.note?.trim() || 'Xuất kho điều chỉnh',
    }

    try {
      await axiosClient.post(EP.INVENTORY_EXPORT, payload)
      showToast(`Đã xuất ${payload.quantity} sản phẩm khỏi kho!`)
      setExportModalOpen(false)
      fetchInventories()
      if (activeTab === 'transactions') fetchTransactions()
    } catch (err) {
      const msg = err.response?.data?.message ?? 'Xuất kho thất bại.'
      showToast(msg, 'error')
    } finally {
      setSubmittingExport(false)
    }
  }

  // ── Filtered Inventories ───────────────────────────────────────────────────
  const filteredInventories = useMemo(() => {
    if (!search.trim()) return inventories
    const q = search.toLowerCase().trim()
    return inventories.filter((inv) => {
      const prodName = inv.variant?.product?.name?.toLowerCase() || ''
      const sku = inv.variant?.sku?.toLowerCase() || ''
      const options = inv.variant?.option_signature?.toLowerCase() || ''
      return prodName.includes(q) || sku.includes(q) || options.includes(q)
    })
  }, [inventories, search])

  // Overview metrics
  const metrics = useMemo(() => {
    let totalOnHand = 0
    let totalReserved = 0
    let lowStockCount = 0

    inventories.forEach((inv) => {
      totalOnHand += Number(inv.on_hand_quantity ?? 0)
      totalReserved += Number(inv.reserved_quantity ?? 0)
      if (Number(inv.on_hand_quantity ?? 0) <= Number(inv.min_stock ?? 5)) {
        lowStockCount++
      }
    })

    return {
      totalOnHand,
      totalReserved,
      totalAvailable: Math.max(0, totalOnHand - totalReserved),
      lowStockCount,
    }
  }, [inventories])

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý Kho & Tồn kho</h1>
          <p className="text-sm text-outline mt-1">
            Theo dõi tồn kho thực tế, số lượng giữ cho đơn hàng, nhập/xuất kho và kiểm toán giao dịch
          </p>
        </div>
      </div>

      {/* Metrics Row */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col justify-between">
          <div className="flex items-center justify-between text-outline">
            <span className="text-xs font-semibold uppercase tracking-wider">Tổng Tồn Thực Tế</span>
            <span className="material-symbols-outlined text-primary">warehouse</span>
          </div>
          <p className="text-2xl font-bold text-on-surface mt-2">{metrics.totalOnHand}</p>
          <span className="text-xs text-outline mt-1">Hàng đang có trong kho</span>
        </div>

        <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col justify-between">
          <div className="flex items-center justify-between text-outline">
            <span className="text-xs font-semibold uppercase tracking-wider">Đang Giữ (Orders)</span>
            <span className="material-symbols-outlined text-warning">lock</span>
          </div>
          <p className="text-2xl font-bold text-warning mt-2">{metrics.totalReserved}</p>
          <span className="text-xs text-outline mt-1">Khách đã đặt chưa xuất</span>
        </div>

        <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col justify-between">
          <div className="flex items-center justify-between text-outline">
            <span className="text-xs font-semibold uppercase tracking-wider">Khả Dụng Bán</span>
            <span className="material-symbols-outlined text-secondary">check_circle</span>
          </div>
          <p className="text-2xl font-bold text-secondary mt-2">{metrics.totalAvailable}</p>
          <span className="text-xs text-outline mt-1">Sẵn sàng mở bán online</span>
        </div>

        <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col justify-between">
          <div className="flex items-center justify-between text-outline">
            <span className="text-xs font-semibold uppercase tracking-wider">Cảnh Báo Hết Hàng</span>
            <span className="material-symbols-outlined text-error">warning</span>
          </div>
          <p className="text-2xl font-bold text-error mt-2">{metrics.lowStockCount}</p>
          <span className="text-xs text-outline mt-1">Dưới mức tối thiểu</span>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-outline-variant/20 gap-6">
        <button
          onClick={() => setActiveTab('inventory')}
          className={[
            'pb-3 text-sm font-semibold transition-all relative flex items-center gap-2',
            activeTab === 'inventory'
              ? 'text-primary border-b-2 border-primary'
              : 'text-outline hover:text-on-surface',
          ].join(' ')}
        >
          <span className="material-symbols-outlined text-lg">inventory_2</span>
          Bảng Tồn Kho Biến Thể
        </button>
        <button
          onClick={() => setActiveTab('transactions')}
          className={[
            'pb-3 text-sm font-semibold transition-all relative flex items-center gap-2',
            activeTab === 'transactions'
              ? 'text-primary border-b-2 border-primary'
              : 'text-outline hover:text-on-surface',
          ].join(' ')}
        >
          <span className="material-symbols-outlined text-lg">history</span>
          Lịch Sử Giao Dịch Kho
        </button>
      </div>

      {/* TAB 1: INVENTORY TABLE */}
      {activeTab === 'inventory' && (
        <div className="space-y-4">
          {/* Controls Bar */}
          <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div className="relative w-full sm:w-80">
              <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">
                search
              </span>
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Tìm theo sản phẩm, SKU..."
                className="w-full pl-9 pr-4 py-2 bg-surface-container-low text-on-surface text-sm rounded-xl border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
              />
            </div>

            <div className="flex items-center gap-3 w-full sm:w-auto justify-end">
              <button
                onClick={() => setLowStockOnly(!lowStockOnly)}
                className={[
                  'px-3.5 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-colors border',
                  lowStockOnly
                    ? 'bg-error text-on-error border-error'
                    : 'bg-surface-container-low text-on-surface-variant border-outline-variant/30 hover:bg-surface-container-high',
                ].join(' ')}
              >
                <span className="material-symbols-outlined text-sm">warning</span>
                Chỉ hiển thị sắp hết hàng
              </button>
            </div>
          </div>

          {/* Table */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-sm">
            {loading ? (
              <div className="flex items-center justify-center py-16">
                <Spinner size="lg" />
              </div>
            ) : filteredInventories.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-16 text-outline gap-2">
                <span className="material-symbols-outlined text-4xl">inventory_2</span>
                <p className="text-sm">Không tìm thấy bản ghi tồn kho nào phù hợp</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-outline-variant/20 bg-surface-container-high/50 text-xs font-semibold text-outline uppercase tracking-wider">
                      <th className="py-3 px-4">Sản phẩm & Phiên bản</th>
                      <th className="py-3 px-4">SKU</th>
                      <th className="py-3 px-4 text-center">Tồn thực tế</th>
                      <th className="py-3 px-4 text-center">Đang giữ</th>
                      <th className="py-3 px-4 text-center">Khả dụng</th>
                      <th className="py-3 px-4 text-center">Cảnh báo tối thiểu</th>
                      <th className="py-3 px-4 text-center">Tình trạng</th>
                      <th className="py-3 px-4 text-right">Hành động</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-outline-variant/10 text-on-surface-variant">
                    {filteredInventories.map((inv) => {
                      const onHand = Number(inv.on_hand_quantity ?? 0)
                      const reserved = Number(inv.reserved_quantity ?? 0)
                      const available = Math.max(0, onHand - reserved)
                      const minStock = Number(inv.min_stock ?? 5)
                      const isLow = onHand <= minStock
                      const isOut = onHand === 0

                      return (
                        <tr key={inv.id} className="hover:bg-surface-container-high/30 transition-colors">
                          <td className="py-3.5 px-4 font-medium text-on-surface">
                            <p className="font-semibold text-on-surface">
                              {inv.variant?.product?.name || 'Sản phẩm'}
                            </p>
                            <p className="text-xs text-outline mt-0.5">
                              {inv.variant?.option_signature || 'Bản tiêu chuẩn'}
                            </p>
                          </td>
                          <td className="py-3.5 px-4 font-mono text-xs text-primary font-medium">
                            {inv.variant?.sku || '—'}
                          </td>
                          <td className="py-3.5 px-4 text-center font-bold text-on-surface">{onHand}</td>
                          <td className="py-3.5 px-4 text-center font-semibold text-warning">{reserved}</td>
                          <td className="py-3.5 px-4 text-center font-bold text-secondary">{available}</td>
                          <td className="py-3.5 px-4 text-center text-xs text-outline font-mono">{minStock}</td>
                          <td className="py-3.5 px-4 text-center">
                            {isOut ? (
                              <Badge variant="error">Hết hàng</Badge>
                            ) : isLow ? (
                              <Badge variant="warning">Sắp hết</Badge>
                            ) : (
                              <Badge variant="success">Ổn định</Badge>
                            )}
                          </td>
                          <td className="py-3.5 px-4 text-right">
                            <div className="flex items-center justify-end gap-2">
                              <Button
                                size="sm"
                                variant="primary"
                                onClick={() => handleOpenImport(inv)}
                                className="flex items-center gap-1 text-xs px-2.5 py-1"
                              >
                                <span className="material-symbols-outlined text-sm">add</span>
                                Nhập kho
                              </Button>
                              <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => handleOpenExport(inv)}
                                className="flex items-center gap-1 text-xs px-2.5 py-1 hover:text-error"
                              >
                                <span className="material-symbols-outlined text-sm">remove</span>
                                Xuất kho
                              </Button>
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* TAB 2: TRANSACTIONS AUDIT */}
      {activeTab === 'transactions' && (
        <div className="space-y-4">
          <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div className="flex items-center gap-2">
              <span className="text-xs text-outline font-medium">Lọc theo loại:</span>
              <select
                value={transactionTypeFilter}
                onChange={(e) => {
                  setTransactionTypeFilter(e.target.value)
                  setTransactionPage(1)
                }}
                className="bg-surface-container-low text-on-surface rounded-xl px-3 py-1.5 text-xs border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option value="">Tất cả loại giao dịch</option>
                <option value="import">Nhập kho (Import)</option>
                <option value="export">Xuất kho (Export)</option>
                <option value="dispatch">Xuất đơn hàng (Dispatch)</option>
                <option value="reserve">Giữ hàng (Reserve)</option>
                <option value="release">Hoàn kho (Release)</option>
              </select>
            </div>
          </div>

          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-sm">
            {transactionsLoading ? (
              <div className="flex items-center justify-center py-16">
                <Spinner size="lg" />
              </div>
            ) : transactions.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-16 text-outline gap-2">
                <span className="material-symbols-outlined text-4xl">history</span>
                <p className="text-sm">Chưa có giao dịch kho nào được ghi nhận</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-outline-variant/20 bg-surface-container-high/50 text-xs font-semibold text-outline uppercase tracking-wider">
                      <th className="py-3 px-4">Thời gian</th>
                      <th className="py-3 px-4">Sản phẩm / Biến thể</th>
                      <th className="py-3 px-4 text-center">Loại giao dịch</th>
                      <th className="py-3 px-4 text-center">Biến động Tồn kho</th>
                      <th className="py-3 px-4 text-right">Giá vốn</th>
                      <th className="py-3 px-4">Ghi chú & Tham chiếu</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-outline-variant/10 text-on-surface-variant">
                    {transactions.map((tx) => {
                      const typeConfig = TRANSACTION_TYPES[tx.type] || {
                        label: tx.type,
                        variant: 'neutral',
                        icon: 'sync',
                      }
                      const isPositive = (tx.quantity_delta ?? 0) > 0
                      const isNegative = (tx.quantity_delta ?? 0) < 0

                      return (
                        <tr key={tx.id} className="hover:bg-surface-container-high/30 transition-colors">
                          <td className="py-3.5 px-4 text-xs font-mono text-outline">
                            {formatDateTime(tx.created_at)}
                          </td>
                          <td className="py-3.5 px-4">
                            <p className="font-semibold text-on-surface">{tx.variant?.product?.name || '—'}</p>
                            <p className="text-xs text-outline">{tx.variant?.sku || tx.variant?.option_signature}</p>
                          </td>
                          <td className="py-3.5 px-4 text-center">
                            <Badge variant={typeConfig.variant} className="inline-flex items-center gap-1 text-[11px]">
                              <span className="material-symbols-outlined text-xs">{typeConfig.icon}</span>
                              {typeConfig.label}
                            </Badge>
                          </td>
                          <td className="py-3.5 px-4 text-center font-bold">
                            {isPositive && <span className="text-secondary font-mono">+{tx.quantity_delta}</span>}
                            {isNegative && <span className="text-error font-mono">{tx.quantity_delta}</span>}
                            {!isPositive && !isNegative && <span className="text-outline font-mono">0</span>}
                          </td>
                          <td className="py-3.5 px-4 text-right font-mono text-xs">
                            {tx.unit_cost ? formatCurrencyVND(tx.unit_cost) : '—'}
                          </td>
                          <td className="py-3.5 px-4 text-xs text-on-surface-variant">
                            <p>{tx.note || '—'}</p>
                            {tx.reference_type && (
                              <span className="text-[10px] text-outline font-mono">
                                Ref: {tx.reference_type} #{tx.reference_id}
                              </span>
                            )}
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {transactionLastPage > 1 && (
            <div className="flex justify-center mt-4">
              <Pagination
                page={transactionPage}
                totalPages={transactionLastPage}
                onPageChange={setTransactionPage}
              />
            </div>
          )}
        </div>
      )}

      {/* IMPORT MODAL */}
      <Modal
        open={importModalOpen}
        onClose={() => setImportModalOpen(false)}
        title={`Nhập hàng vào kho: ${selectedInventory?.variant?.product?.name || ''}`}
        size="md"
      >
        <form onSubmit={handleSubmitImport} className="space-y-4">
          <div className="p-3 rounded-xl bg-surface-container-high/40 text-xs text-on-surface-variant flex flex-col gap-1 border border-outline-variant/20">
            <p>
              <strong className="text-on-surface">Phiên bản:</strong>{' '}
              {selectedInventory?.variant?.option_signature || 'Bản tiêu chuẩn'}
            </p>
            <p>
              <strong className="text-on-surface">SKU:</strong> {selectedInventory?.variant?.sku || '—'}
            </p>
            <p>
              <strong className="text-on-surface">Tồn kho hiện tại:</strong>{' '}
              <span className="font-bold text-primary">{selectedInventory?.on_hand_quantity ?? 0} sản phẩm</span>
            </p>
          </div>

          <Input
            label="Số lượng nhập kho *"
            type="number"
            min="1"
            value={importForm.quantity}
            onChange={(e) => setImportForm({ ...importForm, quantity: e.target.value })}
            placeholder="Ví dụ: 20"
            required
          />

          <Input
            label="Giá vốn đơn vị (VND) — Cập nhật giá nhập mới"
            type="number"
            min="0"
            value={importForm.cost_price}
            onChange={(e) => setImportForm({ ...importForm, cost_price: e.target.value })}
            placeholder="Ví dụ: 24500000"
          />

          <Input
            label="Ghi chú nhập hàng"
            value={importForm.note}
            onChange={(e) => setImportForm({ ...importForm, note: e.target.value })}
            placeholder="Ví dụ: Nhập hàng đợt 2 từ kho NPP Apple"
          />

          <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
            <Button type="button" variant="ghost" onClick={() => setImportModalOpen(false)} disabled={submittingImport}>
              Hủy
            </Button>
            <Button type="submit" variant="primary" loading={submittingImport}>
              Xác nhận Nhập kho
            </Button>
          </div>
        </form>
      </Modal>

      {/* EXPORT MODAL */}
      <Modal
        open={exportModalOpen}
        onClose={() => setExportModalOpen(false)}
        title={`Xuất kho điều chỉnh: ${selectedInventory?.variant?.product?.name || ''}`}
        size="md"
      >
        <form onSubmit={handleSubmitExport} className="space-y-4">
          <div className="p-3 rounded-xl bg-surface-container-high/40 text-xs text-on-surface-variant flex flex-col gap-1 border border-outline-variant/20">
            <p>
              <strong className="text-on-surface">Phiên bản:</strong>{' '}
              {selectedInventory?.variant?.option_signature || 'Bản tiêu chuẩn'}
            </p>
            <p>
              <strong className="text-on-surface">Khả dụng xuất:</strong>{' '}
              <span className="font-bold text-secondary">
                {Math.max(
                  0,
                  Number(selectedInventory?.on_hand_quantity ?? 0) - Number(selectedInventory?.reserved_quantity ?? 0)
                )}{' '}
                sản phẩm
              </span>
            </p>
          </div>

          <Input
            label="Số lượng xuất kho *"
            type="number"
            min="1"
            max={selectedInventory?.on_hand_quantity ?? 9999}
            value={exportForm.quantity}
            onChange={(e) => setExportForm({ ...exportForm, quantity: e.target.value })}
            placeholder="Ví dụ: 1"
            required
          />

          <Input
            label="Lý do xuất hàng *"
            value={exportForm.note}
            onChange={(e) => setExportForm({ ...exportForm, note: e.target.value })}
            placeholder="Ví dụ: Hàng kiểm định, Hàng trưng bày showroom..."
            required
          />

          <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
            <Button type="button" variant="ghost" onClick={() => setExportModalOpen(false)} disabled={submittingExport}>
              Hủy
            </Button>
            <Button type="submit" variant="primary" loading={submittingExport}>
              Xác nhận Xuất kho
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
