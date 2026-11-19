=================================================================
MOBIEX — CÔNG NGHỆ & PHỤ KIỆN CHÍNH HÃNG
XÁC NHẬN ĐƠN HÀNG #{{ $order->order_code }}
=================================================================

Kính chào quý khách {{ $order->shipping_name }},

Cảm ơn quý khách đã tin tưởng mua sắm tại MobieX!
Đơn hàng của quý khách đã được hệ thống ghi nhận thành công.

THÔNG TIN ĐƠN HÀNG:
- Mã đơn hàng: {{ $order->order_code }}
- Thời gian đặt: {{ $order->created_at ? $order->created_at->format('H:i d/m/Y') : now()->format('H:i d/m/Y') }}
- Trạng thái: {{ $orderStatusLabel['text'] }}
- Phương thức thanh toán: {{ $paymentMethodLabel }}
- Tình trạng thanh toán: {{ $paymentStatusLabel['text'] }}
@if($transactionId)
- Mã giao dịch: {{ $transactionId }}
@endif
- Lưu ý: {{ $paymentStatusLabel['description'] }}

THÔNG TIN GIAO HÀNG:
- Người nhận: {{ $order->shipping_name }}
- Điện thoại: {{ $order->shipping_phone }}
- Địa chỉ: {{ $order->shipping_address_line }}{{ $order->shipping_ward_name ? ', '.$order->shipping_ward_name : '' }}{{ $order->shipping_province_name ? ', '.$order->shipping_province_name : '' }}
- Hình thức giao: {{ $order->shipping_method_name ?: 'Giao hàng tiêu chuẩn' }}
@if(!empty($order->customer_note))
- Ghi chú: {{ $order->customer_note }}
@endif

CHI TIẾT SẢN PHẨM:
-----------------------------------------------------------------
@foreach($order->items as $index => $item)
{{ $index + 1 }}. {{ $item->product_name }}
@if(!empty($item->variant_name))
   Phân loại: {{ $item->variant_name }}
@endif
@if(!empty($item->sku))
   SKU: {{ $item->sku }}
@endif
   Số lượng: {{ $item->quantity }} x {{ number_format((float) $item->price, 0, ',', '.') }} VND
   Thành tiền: {{ number_format((float) $item->price * (int) $item->quantity, 0, ',', '.') }} VND
-----------------------------------------------------------------
@endforeach

TỔNG KẾT TÀI CHÍNH:
- Tạm tính: {{ number_format((float) $order->subtotal, 0, ',', '.') }} VND
@if((float) $order->discount_amount > 0)
- Giảm giá ({{ $order->coupon_code }}): -{{ number_format((float) $order->discount_amount, 0, ',', '.') }} VND
@endif
- Phí vận chuyển: {{ (float) $order->shipping_fee > 0 ? number_format((float) $order->shipping_fee, 0, ',', '.').' VND' : 'Miễn phí' }}
-----------------------------------------------------------------
TỔNG CỘNG THANH TOÁN: {{ number_format((float) $order->total_amount, 0, ',', '.') }} VND
-----------------------------------------------------------------

Xem chi tiết đơn hàng trực tuyến tại:
{{ $viewOrderUrl }}

-----------------------------------------------------------------
HỖ TRỢ KHÁCH HÀNG:
Hotline: 1900 6868 (8:00 - 21:30 hàng ngày)
Email: support@mobiex.vn
Địa chỉ: Trịnh Văn Bô, Nam Từ Liêm, Hà Nội

(Đây là email tự động, vui lòng không trả lời trực tiếp email này).
© {{ date('Y') }} MobieX Vietnam. All rights reserved.
