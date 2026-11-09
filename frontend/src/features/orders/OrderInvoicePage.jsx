import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDateTime } from '../../utils/formatDate'
import Spinner from '../../components/common/Spinner'

export default function OrderInvoicePage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let isMounted = true

    // Fetch from customer endpoint first, fallback to admin endpoint if forbidden
    const fetchOrder = async () => {
      try {
        setLoading(true)
        let res
        try {
          res = await axiosClient.get(EP.ORDER_DETAIL(id))
        } catch (err) {
          if (err.response?.status === 403 || err.response?.status === 404) {
            res = await axiosClient.get(EP.ADMIN_ORDER_DETAIL(id))
          } else {
            throw err
          }
        }

        if (isMounted) {
          setOrder(res.data?.data || res.data)
        }
      } catch (err) {
        if (isMounted) {
          setError(err?.response?.data?.message || 'Không thể tải thông tin hóa đơn đơn hàng')
        }
      } finally {
        if (isMounted) {
          setLoading(false)
        }
      }
    }

    fetchOrder()

    return () => {
      isMounted = false
    }
  }, [id])

  const handlePrint = () => {
    window.print()
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 text-gray-900">
        <Spinner size="lg" />
      </div>
    )
  }

  if (error || !order) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center p-4 bg-gray-50 text-gray-900 space-y-4">
        <span className="material-symbols-outlined text-5xl text-red-500">receipt_long</span>
        <h2 className="text-xl font-bold">Không thể xuất hóa đơn</h2>
        <p className="text-sm text-gray-600">{error || 'Đơn hàng không tồn tại'}</p>
        <button
          onClick={() => navigate(-1)}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors"
        >
          Quay lại
        </button>
      </div>
    )
  }

  const items = order.items || []
  const subtotal = Number(order.subtotal_amount ?? order.subtotal ?? 0)
  const discount = Number(order.discount_amount ?? order.discount ?? 0)
  const total = Number(order.total_amount ?? order.total ?? order.grand_total ?? 0)

  return (
    <div className="min-h-screen bg-neutral-100 py-8 px-4 text-neutral-900 font-sans print:p-0 print:bg-white print:m-0">
      {/* Action Toolbar (Screen only) */}
      <div className="max-w-4xl mx-auto mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden bg-white p-4 rounded-xl shadow-sm border border-neutral-200">
        <div className="flex items-center gap-2">
          <button
            onClick={() => navigate(-1)}
            className="flex items-center gap-1 px-3 py-1.5 rounded-lg border border-neutral-300 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 transition-colors"
          >
            <span className="material-symbols-outlined text-base">arrow_back</span>
            Quay lại
          </button>
          <span className="text-xs text-neutral-500">
            Xem trước bản in hóa đơn A4 chuẩn
          </span>
        </div>

        <button
          onClick={handlePrint}
          className="flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-md hover:bg-blue-700 transition-colors"
        >
          <span className="material-symbols-outlined text-lg">print</span>
          In hóa đơn ngay
        </button>
      </div>

      {/* Printable Sheet (Standard A4 layout) */}
      <div className="max-w-4xl mx-auto bg-white p-8 md:p-12 rounded-xl shadow-lg border border-neutral-200 print:shadow-none print:border-none print:p-4 print:max-w-none text-neutral-900">
        {/* Header */}
        <div className="flex justify-between items-start border-b-2 border-neutral-800 pb-6 mb-6">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <span className="text-2xl font-black tracking-tight text-blue-600">MOBIEX</span>
              <span className="text-xs font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-800 uppercase tracking-widest">
                Official Retail
              </span>
            </div>
            <p className="text-xs text-neutral-600 font-medium">Hệ thống phân phối điện thoại & thiết bị thông minh MobieX</p>
            <p className="text-xs text-neutral-500">Tòa nhà FPT Polytechnic, P. Trịnh Văn Bô, Q. Nam Từ Liêm, TP. Hà Nội</p>
            <p className="text-xs text-neutral-500">Hotline: 1900 6868 • Email: support@mobiex.vn • Website: mobiex.vn</p>
          </div>

          <div className="text-right space-y-1">
            <h1 className="text-xl md:text-2xl font-black text-neutral-900 tracking-tight">
              HÓA ĐƠN BÁN LẺ
            </h1>
            <p className="text-xs font-mono font-bold text-neutral-700">
              MÃ ĐƠN: #{order.order_code || order.id}
            </p>
            <p className="text-xs text-neutral-500">
              Ngày lập: {formatDateTime(order.created_at)}
            </p>
          </div>
        </div>

        {/* Customer & Shipping Information */}
        <div className="grid grid-cols-2 gap-6 p-4 rounded-lg bg-neutral-50 border border-neutral-200 text-xs mb-6">
          <div className="space-y-1.5">
            <p className="font-bold uppercase tracking-wider text-neutral-500">Thông tin người nhận:</p>
            <p className="font-bold text-sm text-neutral-900">
              {order.recipient_name || order.shipping_name || order.user?.name || 'Khách lẻ'}
            </p>
            <p className="text-neutral-700">
              Số điện thoại: <span className="font-mono font-semibold">{order.recipient_phone || order.shipping_phone || order.user?.phone || '—'}</span>
            </p>
            <p className="text-neutral-700 leading-relaxed">
              Địa chỉ: {order.shipping_address || order.address || 'Tại cửa hàng'}
            </p>
          </div>

          <div className="space-y-1.5 border-l border-neutral-200 pl-6">
            <p className="font-bold uppercase tracking-wider text-neutral-500">Thông tin giao dịch:</p>
            <p className="text-neutral-700">
              Phương thức: <span className="font-bold text-neutral-900 uppercase">{order.payment_method || 'COD'}</span>
            </p>
            <p className="text-neutral-700">
              Trạng thái thanh toán:{' '}
              <span className={`font-bold ${order.payment_status === 'paid' ? 'text-green-600' : 'text-amber-600'}`}>
                {order.payment_status === 'paid' ? 'ĐÃ THANH TOÁN' : 'THU TIỀN KHI GIAO (COD)'}
              </span>
            </p>
            <p className="text-neutral-700">
              Trạng thái đơn hàng:{' '}
              <span className="font-semibold text-neutral-900 uppercase">
                {order.status || 'Đang xử lý'}
              </span>
            </p>
          </div>
        </div>

        {/* Items Table */}
        <div className="mb-6 overflow-hidden border border-neutral-200 rounded-lg">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="bg-neutral-100 border-b border-neutral-200 text-neutral-700 font-bold uppercase tracking-wider">
                <th className="p-3 w-12 text-center">STT</th>
                <th className="p-3">Tên sản phẩm & Cấu hình</th>
                <th className="p-3 w-28 text-right">Đơn giá</th>
                <th className="p-3 w-16 text-center">SL</th>
                <th className="p-3 w-32 text-right">Thành tiền</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 text-neutral-800">
              {items.map((item, idx) => {
                const itemPrice = Number(item.unit_price ?? item.price ?? 0)
                const itemQty = Number(item.quantity ?? 1)
                const itemTotal = Number(item.total_price ?? item.subtotal ?? itemPrice * itemQty)

                return (
                  <tr key={item.id || idx}>
                    <td className="p-3 text-center font-mono">{idx + 1}</td>
                    <td className="p-3">
                      <p className="font-bold text-neutral-900">
                        {item.product_name || item.product?.name || 'Điện thoại thông minh'}
                      </p>
                      {item.variant_name && (
                        <p className="text-[11px] text-neutral-500 font-mono">
                          Phân loại: {item.variant_name}
                        </p>
                      )}
                    </td>
                    <td className="p-3 text-right font-mono">{formatCurrencyVND(itemPrice)}</td>
                    <td className="p-3 text-center font-bold">{itemQty}</td>
                    <td className="p-3 text-right font-mono font-bold">{formatCurrencyVND(itemTotal)}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>

        {/* Financial Summary */}
        <div className="flex justify-end mb-8">
          <div className="w-72 space-y-2 text-xs border-t border-neutral-200 pt-3">
            <div className="flex justify-between text-neutral-600">
              <span>Tạm tính hàng hóa:</span>
              <span className="font-mono font-semibold text-neutral-900">{formatCurrencyVND(subtotal)}</span>
            </div>
            {discount > 0 && (
              <div className="flex justify-between text-green-600">
                <span>Voucher giảm giá:</span>
                <span className="font-mono font-semibold">-{formatCurrencyVND(discount)}</span>
              </div>
            )}
            <div className="flex justify-between text-neutral-600">
              <span>Cước phí vận chuyển:</span>
              <span className="font-mono font-semibold text-neutral-900">0 đ (Miễn phí)</span>
            </div>
            <div className="flex justify-between text-sm font-extrabold text-neutral-900 border-t border-neutral-300 pt-2">
              <span>TỔNG TIỀN THANH TOÁN:</span>
              <span className="font-mono text-blue-600 text-base">{formatCurrencyVND(total)}</span>
            </div>
          </div>
        </div>

        {/* Policy Notice */}
        <div className="border border-dashed border-neutral-300 rounded-lg p-3.5 text-[11px] text-neutral-600 space-y-1 mb-8 bg-neutral-50/50">
          <p className="font-bold text-neutral-800">Quy định bảo hành & Đổi trả MobieX:</p>
          <p>• Quý khách vui lòng kiểm tra kỹ phụ kiện, ngoại quan máy trước khi thanh toán hoặc nhận hàng.</p>
          <p>• Chính sách 1 đổi 1 trong vòng 30 ngày đối với các lỗi phần cứng do nhà sản xuất.</p>
          <p>• Bảo hành tiêu chuẩn 12 tháng tại tất cả các trung tâm ủy quyền toàn quốc.</p>
        </div>

        {/* Signatures */}
        <div className="grid grid-cols-2 gap-8 text-center text-xs text-neutral-700 pt-4">
          <div>
            <p className="font-bold uppercase tracking-wider">Người mua hàng</p>
            <p className="text-[11px] text-neutral-400 italic mb-16">(Ký & ghi rõ họ tên)</p>
            <p className="font-medium text-neutral-800">
              {order.recipient_name || order.shipping_name || order.user?.name || ''}
            </p>
          </div>

          <div>
            <p className="font-bold uppercase tracking-wider">Đại diện MobieX / Thủ kho</p>
            <p className="text-[11px] text-neutral-400 italic mb-16">(Ký, đóng dấu & ghi rõ họ tên)</p>
            <p className="font-medium text-neutral-800">Bộ phận giao dịch & Kho vận MobieX</p>
          </div>
        </div>
      </div>
    </div>
  )
}
