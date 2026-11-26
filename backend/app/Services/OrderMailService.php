<?php

namespace App\Services;

use App\Jobs\SendOrderConfirmationEmailJob;
use App\Models\Order;
use App\Models\OrderEmailLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderMailService
{
    /**
     * Dispatch order confirmation email.
     * Safe execution: Even if mail driver fails, order creation and checkout WILL NOT crash.
     */
    public function sendOrderConfirmation(Order $order, bool $force = false): bool
    {
        $recipient = trim((string) ($order->effective_customer_email ?? ''));

        if (empty($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Log::warning("[OrderMailService] Order #{$order->order_code}: Missing or invalid recipient email ('{$recipient}')");

            $order->update([
                'confirmation_email_status' => 'failed',
                'confirmation_email_error' => 'Chưa có địa chỉ email hợp lệ để gửi xác nhận.',
            ]);

            return false;
        }

        // Avoid duplicate paid confirmations between return redirect, QueryDR, and webhook IPN,
        // while still sending the paid receipt if only the initial pending email was sent at checkout.
        if ($order->payment_status === 'paid') {
            $alreadySentPaid = OrderEmailLog::where('order_id', $order->id)
                ->where('status', 'sent')
                ->where('payment_status_snapshot', 'paid')
                ->exists();

            if ($alreadySentPaid && ! $force) {
                Log::info("[OrderMailService] Order #{$order->order_code}: Paid confirmation email already sent. Skipping duplicate gateway callback.");

                return true;
            }
        } elseif (! $force && $order->confirmation_email_status === 'sent') {
            Log::info("[OrderMailService] Order #{$order->order_code}: Email already sent at {$order->confirmation_email_sent_at}. Skipping.");

            return true;
        }

        try {
            // Dispatch queued job (if sync, executes immediately; if database/redis, executes in background)
            SendOrderConfirmationEmailJob::dispatch($order->id, $force);

            return true;
        } catch (Throwable $e) {
            Log::error("[OrderMailService] Error dispatching email job for order #{$order->order_code}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            $order->update([
                'confirmation_email_status' => 'failed',
                'confirmation_email_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resend order confirmation email manually (e.g. from Admin).
     */
    public function resendConfirmationEmail(Order $order): array
    {
        $recipient = trim((string) ($order->effective_customer_email ?? ''));

        if (empty($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Đơn hàng không có địa chỉ email hợp lệ để gửi thông báo.',
                'recipient' => $recipient,
            ];
        }

        try {
            // Force resend bypasses previous 'sent' check
            SendOrderConfirmationEmailJob::dispatchSync($order->id, true);

            $order->refresh();

            return [
                'success' => $order->confirmation_email_status === 'sent',
                'message' => $order->confirmation_email_status === 'sent'
                    ? "Đã gửi lại email xác nhận đơn hàng thành công đến {$recipient}."
                    : 'Không thể gửi email: '.($order->confirmation_email_error ?? 'Lỗi không xác định'),
                'recipient' => $recipient,
                'sent_at' => $order->confirmation_email_sent_at,
            ];
        } catch (Throwable $e) {
            Log::error("[OrderMailService] Manual resend failed for order #{$order->order_code}: ".$e->getMessage());

            return [
                'success' => false,
                'message' => 'Lỗi dịch vụ email: '.$e->getMessage(),
                'recipient' => $recipient,
            ];
        }
    }

    /**
     * Retrieve audit history of emails sent for an order.
     *
     * @return Collection<int, OrderEmailLog>
     */
    public function getEmailHistory(Order $order): Collection
    {
        return $order->emailLogs()->latest()->get();
    }
}
