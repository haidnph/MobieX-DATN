<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        // Ensure relationships are loaded
        $order->loadMissing([
            'items.variant.images',
            'items.variant.product.images',
            'items.product.images',
            'shippingMethod',
            'payments',
            'coupon',
            'user',
        ]);

        $this->order = $order;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $orderCode = $this->order->order_code;
        $isPaid = $this->order->payment_status === 'paid';

        $subject = $isPaid
            ? "[MobieX] Xác nhận đơn hàng & thanh toán thành công #{$orderCode}"
            : "[MobieX] Xác nhận đơn hàng thành công #{$orderCode}";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.confirmation',
            text: 'emails.orders.confirmation_plain',
            with: [
                'order' => $this->order,
                'viewOrderUrl' => $this->buildViewOrderUrl(),
                'paymentMethodLabel' => $this->getPaymentMethodLabel(),
                'paymentStatusLabel' => $this->getPaymentStatusLabel(),
                'orderStatusLabel' => $this->getOrderStatusLabel(),
                'transactionId' => $this->getSuccessfulTransactionId(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Build appropriate frontend order URL for user or guest.
     */
    private function buildViewOrderUrl(): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');

        if ($this->order->user_id) {
            return "{$frontendUrl}/orders/{$this->order->id}";
        }

        // Guest lookup URL with prefilled parameters
        $orderCode = urlencode($this->order->order_code);
        $phone = urlencode($this->order->shipping_phone);

        return "{$frontendUrl}/orders/lookup?code={$orderCode}&phone={$phone}";
    }

    /**
     * Human-readable label for payment method in Vietnamese.
     */
    private function getPaymentMethodLabel(): string
    {
        return match (strtolower((string) $this->order->payment_method)) {
            'cod' => 'Thanh toán khi nhận hàng (COD)',
            'vnpay' => 'Cổng thanh toán VNPay',
            'zalopay' => 'Ví điện tử ZaloPay',
            'momo' => 'Ví điện tử MoMo',
            'bank_transfer' => 'Chuyển khoản ngân hàng (VietQR)',
            default => strtoupper((string) $this->order->payment_method),
        };
    }

    /**
     * Human-readable label for payment status in Vietnamese.
     */
    private function getPaymentStatusLabel(): array
    {
        $status = strtolower((string) $this->order->payment_status);

        return match ($status) {
            'paid' => [
                'text' => 'Đã thanh toán thành công',
                'badge_color' => '#16a34a', // green-600
                'badge_bg' => '#dcfce7',    // green-100
                'description' => 'Đơn hàng đã được xác nhận thanh toán qua cổng thanh toán.',
            ],
            'failed' => [
                'text' => 'Thanh toán thất bại',
                'badge_color' => '#dc2626', // red-600
                'badge_bg' => '#fee2e2',    // red-100
                'description' => 'Giao dịch thanh toán chưa thành công. Vui lòng liên hệ hỗ trợ hoặc thanh toán lại.',
            ],
            'refunded' => [
                'text' => 'Đã hoàn tiền',
                'badge_color' => '#475569', // slate-600
                'badge_bg' => '#f1f5f9',    // slate-100
                'description' => 'Số tiền của đơn hàng đã được hoàn trả.',
            ],
            default => [
                'text' => strtolower((string) $this->order->payment_method) === 'cod'
                    ? 'Chưa thanh toán (Thanh toán khi nhận hàng)'
                    : 'Chờ thanh toán',
                'badge_color' => '#d97706', // amber-600
                'badge_bg' => '#fef3c7',    // amber-100
                'description' => strtolower((string) $this->order->payment_method) === 'cod'
                    ? 'Quý khách vui lòng chuẩn bị đúng số tiền mặt khi nhân viên giao hàng liên hệ.'
                    : 'Đơn hàng đang chờ hoàn tất giao dịch thanh toán trực tuyến.',
            ],
        };
    }

    /**
     * Human-readable label for order status in Vietnamese.
     */
    private function getOrderStatusLabel(): array
    {
        $status = strtolower((string) $this->order->status);

        return match ($status) {
            'confirmed' => [
                'text' => 'Đã xác nhận',
                'badge_color' => '#2563eb',
                'badge_bg' => '#dbeafe',
            ],
            'processing' => [
                'text' => 'Đang chuẩn bị hàng',
                'badge_color' => '#7c3aed',
                'badge_bg' => '#ede9fe',
            ],
            'shipping' => [
                'text' => 'Đang vận chuyển',
                'badge_color' => '#0284c7',
                'badge_bg' => '#e0f2fe',
            ],
            'received' => [
                'text' => 'Khách đã nhận hàng',
                'badge_color' => '#0891b2',
                'badge_bg' => '#cffafe',
            ],
            'delivered' => [
                'text' => 'Giao hàng thành công',
                'badge_color' => '#16a34a',
                'badge_bg' => '#dcfce7',
            ],
            'cancelled' => [
                'text' => 'Đã hủy',
                'badge_color' => '#dc2626',
                'badge_bg' => '#fee2e2',
            ],
            default => [
                'text' => 'Chờ xử lý',
                'badge_color' => '#d97706',
                'badge_bg' => '#fef3c7',
            ],
        };
    }

    /**
     * Find successful gateway transaction ID if available.
     */
    private function getSuccessfulTransactionId(): ?string
    {
        $successfulPayment = $this->order->payments
            ->where('status', 'success')
            ->first();

        return $successfulPayment?->gateway_transaction_id ?: $successfulPayment?->transaction_code;
    }
}
