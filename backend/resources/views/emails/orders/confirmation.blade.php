<!DOCTYPE html>
<html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Xác nhận đơn hàng #{{ $order->order_code }} - MobieX</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
        }
        table {
            border-collapse: separate;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            border: 0;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            padding: 32px 28px;
            text-align: center;
        }
        .btn-primary {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            text-align: center;
            letter-spacing: 0.3px;
        }
        .badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 9999px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        @media only screen and (max-width: 620px) {
            .container {
                width: 100% !important;
                border-radius: 0 !important;
            }
            .content-padding {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }
            .item-col {
                display: block !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 24px 0; background-color: #f1f5f9;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f1f5f9;">
    <tr>
        <td align="center" style="padding: 12px;">
            <!-- Main Card Container -->
            <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; max-width: 600px; width: 100%; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
                
                <!-- Brand Header -->
                <tr>
                    <td class="header" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); padding: 36px 28px; text-align: center;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center">
                                    <div style="font-size: 26px; font-weight: 800; color: #ffffff; letter-spacing: 1.5px; text-transform: uppercase;">
                                        MOBI<span style="color: #60a5fa;">E</span>X
                                    </div>
                                    <div style="font-size: 12px; color: #94a3b8; letter-spacing: 2px; margin-top: 4px; text-transform: uppercase;">
                                        Công nghệ & Phụ kiện chính hãng
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding-top: 24px;">
                                    <div style="display: inline-block; background-color: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; padding: 8px 16px;">
                                        <span style="color: #cbd5e1; font-size: 13px;">Mã đơn hàng:</span>
                                        <span style="color: #ffffff; font-weight: 700; font-size: 15px; margin-left: 6px; letter-spacing: 0.5px;">{{ $order->order_code }}</span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Content Area -->
                <tr>
                    <td class="content-padding" style="padding: 32px 32px 16px 32px;">
                        <!-- Greeting -->
                        <h1 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                            Kính chào quý khách {{ $order->shipping_name }},
                        </h1>
                        <p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.6; color: #334155;">
                            Cảm ơn quý khách đã tin tưởng mua sắm tại <strong>MobieX</strong>. Đơn hàng của quý khách đã được hệ thống ghi nhận thành công và đang được chuẩn bị để xử lý.
                        </p>

                        <!-- Order Status & Time Summary Box -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                            <tr>
                                <td style="padding: 16px 20px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="font-size: 13px; color: #64748b; padding-bottom: 6px;">Thời gian đặt hàng:</td>
                                            <td align="right" style="font-size: 13px; font-weight: 600; color: #0f172a; padding-bottom: 6px;">
                                                {{ $order->created_at ? $order->created_at->format('H:i d/m/Y') : now()->format('H:i d/m/Y') }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 13px; color: #64748b; padding-bottom: 6px;">Trạng thái đơn hàng:</td>
                                            <td align="right" style="padding-bottom: 6px;">
                                                <span class="badge" style="background-color: {{ $orderStatusLabel['badge_bg'] }}; color: {{ $orderStatusLabel['badge_color'] }}; font-size: 11px; padding: 3px 8px; border-radius: 4px;">
                                                    {{ $orderStatusLabel['text'] }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 13px; color: #64748b;">Phương thức thanh toán:</td>
                                            <td align="right" style="font-size: 13px; font-weight: 600; color: #0f172a;">
                                                {{ $paymentMethodLabel }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 13px; color: #64748b; padding-top: 6px;">Tình trạng thanh toán:</td>
                                            <td align="right" style="padding-top: 6px;">
                                                <span class="badge" style="background-color: {{ $paymentStatusLabel['badge_bg'] }}; color: {{ $paymentStatusLabel['badge_color'] }}; font-size: 11px; padding: 3px 8px; border-radius: 4px;">
                                                    {{ $paymentStatusLabel['text'] }}
                                                </span>
                                            </td>
                                        </tr>
                                        @if($transactionId)
                                        <tr>
                                            <td style="font-size: 13px; color: #64748b; padding-top: 6px;">Mã giao dịch:</td>
                                            <td align="right" style="font-size: 12px; font-family: monospace; font-weight: 600; color: #2563eb; padding-top: 6px;">
                                                {{ $transactionId }}
                                            </td>
                                        </tr>
                                        @endif
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- Payment guidance note -->
                        <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; border-radius: 4px; padding: 12px 16px; margin-bottom: 28px;">
                            <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #1e40af;">
                                💡 <strong>Ghi chú:</strong> {{ $paymentStatusLabel['description'] }}
                            </p>
                        </div>

                        <!-- Shipping & Recipient Section -->
                        <div style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                            📦 Thông tin người nhận & Địa chỉ giao hàng
                        </div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 28px;">
                            <tr>
                                <td style="padding: 4px 0; font-size: 14px; color: #64748b; width: 140px;">Người nhận:</td>
                                <td style="padding: 4px 0; font-size: 14px; font-weight: 600; color: #0f172a;">{{ $order->shipping_name }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; font-size: 14px; color: #64748b;">Số điện thoại:</td>
                                <td style="padding: 4px 0; font-size: 14px; font-weight: 600; color: #0f172a;">{{ $order->shipping_phone }}</td>
                            </tr>
                            @if($order->customer_email || $order->user?->email)
                            <tr>
                                <td style="padding: 4px 0; font-size: 14px; color: #64748b;">Email nhận tin:</td>
                                <td style="padding: 4px 0; font-size: 14px; color: #0f172a;">{{ $order->customer_email ?: $order->user?->email }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td style="padding: 4px 0; font-size: 14px; color: #64748b; vertical-align: top;">Địa chỉ nhận hàng:</td>
                                <td style="padding: 4px 0; font-size: 14px; color: #0f172a; line-height: 1.5;">
                                    {{ $order->shipping_address_line }}
                                    @if($order->shipping_ward_name || $order->shipping_province_name)
                                        , {{ implode(', ', array_filter([$order->shipping_ward_name, $order->shipping_province_name])) }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; font-size: 14px; color: #64748b;">Hình thức vận chuyển:</td>
                                <td style="padding: 4px 0; font-size: 14px; font-weight: 600; color: #0f172a;">
                                    {{ $order->shipping_method_name ?: 'Giao hàng tiêu chuẩn' }}
                                </td>
                            </tr>
                            @if(!empty($order->customer_note))
                            <tr>
                                <td style="padding: 4px 0; font-size: 14px; color: #64748b; vertical-align: top;">Ghi chú từ khách:</td>
                                <td style="padding: 4px 0; font-size: 14px; color: #475569; font-style: italic;">"{{ $order->customer_note }}"</td>
                            </tr>
                            @endif
                        </table>

                        <!-- Items Section -->
                        <div style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                            🛍️ Chi tiết sản phẩm đã đặt ({{ $order->items->count() }})
                        </div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                            <thead>
                                <tr style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                    <th align="left" style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Sản phẩm</th>
                                    <th align="center" style="padding: 12px 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 50px;">SL</th>
                                    <th align="right" style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 110px;">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                @php
                                    $itemTotal = (float) $item->price * (int) $item->quantity;
                                @endphp
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 14px; vertical-align: top;">
                                        <div style="font-size: 14px; font-weight: 600; color: #0f172a; line-height: 1.4;">
                                            {{ $item->product_name }}
                                        </div>
                                        @if(!empty($item->variant_name))
                                        <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                                            Phân loại: <span style="color: #334155;">{{ $item->variant_name }}</span>
                                        </div>
                                        @endif
                                        @if(!empty($item->sku))
                                        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                            SKU: {{ $item->sku }}
                                        </div>
                                        @endif
                                        <div style="font-size: 12px; color: #475569; margin-top: 4px;">
                                            Đơn giá: {{ number_format((float) $item->price, 0, ',', '.') }} ₫
                                        </div>
                                    </td>
                                    <td align="center" style="padding: 14px 8px; vertical-align: top; font-size: 14px; font-weight: 600; color: #0f172a;">
                                        ×{{ $item->quantity }}
                                    </td>
                                    <td align="right" style="padding: 14px; vertical-align: top; font-size: 14px; font-weight: 700; color: #0f172a; white-space: nowrap;">
                                        {{ number_format($itemTotal, 0, ',', '.') }} ₫
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- Order Financial Summary -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 32px;">
                            <tr>
                                <td style="width: 50%;"></td>
                                <td style="width: 50%;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Tạm tính:</td>
                                            <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #0f172a;">
                                                {{ number_format((float) $order->subtotal, 0, ',', '.') }} ₫
                                            </td>
                                        </tr>
                                        @if((float) $order->discount_amount > 0)
                                        <tr>
                                            <td style="padding: 6px 0; font-size: 14px; color: #16a34a;">
                                                Giảm giá @if($order->coupon_code)<span style="font-size: 12px;">({{ $order->coupon_code }})</span>@endif:
                                            </td>
                                            <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #16a34a;">
                                                -{{ number_format((float) $order->discount_amount, 0, ',', '.') }} ₫
                                            </td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Phí vận chuyển:</td>
                                            <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #0f172a;">
                                                @if((float) $order->shipping_fee > 0)
                                                    {{ number_format((float) $order->shipping_fee, 0, ',', '.') }} ₫
                                                @else
                                                    <span style="color: #16a34a;">Miễn phí</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr style="border-top: 2px solid #e2e8f0;">
                                            <td style="padding: 12px 0 6px 0; font-size: 16px; font-weight: 800; color: #0f172a;">TỔNG CỘNG:</td>
                                            <td align="right" style="padding: 12px 0 6px 0; font-size: 20px; font-weight: 800; color: #dc2626;">
                                                {{ number_format((float) $order->total_amount, 0, ',', '.') }} ₫
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- Call To Action Button -->
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 28px;">
                            <tr>
                                <td align="center">
                                    <!--[if mso]>
                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $viewOrderUrl }}" style="height:48px;v-text-anchor:middle;width:260px;" arcsize="16%" stroke="f" fillcolor="#2563eb">
                                    <w:anchorlock/>
                                    <center style="color:#ffffff;font-family:sans-serif;font-size:15px;font-weight:bold;">Xem chi tiết đơn hàng</center>
                                    </v:roundrect>
                                    <![endif]-->
                                    <!--[if !mso]><!-- -->
                                    <a href="{{ $viewOrderUrl }}" target="_blank" class="btn-primary" style="display: inline-block; background-color: #2563eb; color: #ffffff !important; font-weight: 700; font-size: 15px; text-decoration: none; padding: 14px 36px; border-radius: 8px; text-align: center; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);">
                                        XEM CHI TIẾT ĐƠN HÀNG →
                                    </a>
                                    <!--<![endif]-->
                                </td>
                            </tr>
                        </table>

                        <!-- Security & Assistance Note -->
                        <div style="background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 14px 18px; margin-bottom: 24px;">
                            <div style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                🛡️ Lưu ý bảo mật & Hướng dẫn nhận hàng:
                            </div>
                            <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #64748b; line-height: 1.6;">
                                <li>MobieX không bao giờ yêu cầu quý khách cung cấp mật khẩu hoặc mã xác thực OTP qua điện thoại hoặc tin nhắn.</li>
                                <li>Khi nhận hàng, quý khách vui lòng kiểm tra tình trạng nguyên vẹn của kiện hàng và tem niêm phong trước khi thanh toán cho shipper (nếu COD).</li>
                                <li>Mọi yêu cầu đổi trả hoặc bảo hành sản phẩm, quý khách vui lòng giữ lại hóa đơn và hộp sản phẩm trong vòng 30 ngày.</li>
                            </ul>
                        </div>

                    </td>
                </tr>

                <!-- Footer Section -->
                <tr>
                    <td style="background-color: #0f172a; padding: 32px 28px; text-align: center; color: #94a3b8;">
                        <div style="font-size: 15px; font-weight: 700; color: #ffffff; letter-spacing: 1px; margin-bottom: 8px;">
                            HỆ THỐNG BÁN LẺ ĐIỆN THOẠI & PHỤ KIỆN MOBIEX
                        </div>
                        <div style="font-size: 13px; line-height: 1.6; color: #94a3b8; margin-bottom: 12px;">
                            📍 Địa chỉ: Trịnh Văn Bô, Nam Từ Liêm, Hà Nội<br>
                            📞 Hotline hỗ trợ: <strong>1900 6868</strong> (8:00 - 21:30 hàng ngày)<br>
                            ✉️ Email hỗ trợ: <a href="mailto:support@mobiex.vn" style="color: #60a5fa; text-decoration: none;">support@mobiex.vn</a>
                        </div>
                        <div style="border-top: 1px solid #1e293b; padding-top: 16px; font-size: 11px; color: #64748b; line-height: 1.5;">
                            Đây là email tự động gửi từ hệ thống MobieX khi quý khách phát sinh đơn hàng.<br>
                            Vui lòng không phản hồi trực tiếp vào địa chỉ email này.<br>
                            &copy; {{ date('Y') }} MobieX Vietnam. Tất cả các quyền được bảo lưu.
                        </div>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
