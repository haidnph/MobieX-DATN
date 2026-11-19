<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\OrderEmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderConfirmationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public array $backoff = [15, 60, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $orderId,
        public bool $force = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::with(['items.variant.product', 'shippingMethod', 'payments', 'coupon', 'user'])
            ->find($this->orderId);

        if (! $order) {
            Log::warning("[OrderConfirmationEmailJob] Order #{$this->orderId} not found. Aborting email delivery.");

            return;
        }

        $recipientEmail = trim((string) ($order->effective_customer_email ?? ''));

        if (empty($recipientEmail) || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Địa chỉ email người nhận không hợp lệ hoặc bị trống: '{$recipientEmail}'";
            Log::warning("[OrderConfirmationEmailJob] Order #{$order->order_code}: {$errorMessage}");

            $order->update([
                'confirmation_email_status' => 'failed',
                'confirmation_email_error' => $errorMessage,
            ]);

            OrderEmailLog::create([
                'order_id' => $order->id,
                'recipient_email' => $recipientEmail ?: 'N/A',
                'email_type' => 'order_confirmation',
                'subject' => "[MobieX] Xác nhận đơn hàng #{$order->order_code}",
                'payment_status_snapshot' => $order->payment_status,
                'status' => 'failed',
                'error_message' => $errorMessage,
            ]);

            return;
        }

        // Idempotency check: avoid duplicate sends unless forced
        if (! $this->force && $order->confirmation_email_status === 'sent') {
            Log::info("[OrderConfirmationEmailJob] Order #{$order->order_code} already has confirmation email sent at {$order->confirmation_email_sent_at}. Skipping duplicate.");

            return;
        }

        if (! $this->force && $order->payment_status === 'paid') {
            $alreadySentPaid = OrderEmailLog::where('order_id', $order->id)
                ->where('status', 'sent')
                ->where('payment_status_snapshot', 'paid')
                ->exists();

            if ($alreadySentPaid) {
                Log::info("[OrderConfirmationEmailJob] Order #{$order->order_code}: Paid confirmation email already sent. Skipping duplicate from race condition.");

                return;
            }
        }

        $subject = ($order->payment_status === 'paid')
            ? "[MobieX] Xác nhận đơn hàng & thanh toán thành công #{$order->order_code}"
            : "[MobieX] Xác nhận đơn hàng thành công #{$order->order_code}";

        // Create pending log entry
        $emailLog = OrderEmailLog::create([
            'order_id' => $order->id,
            'recipient_email' => $recipientEmail,
            'email_type' => 'order_confirmation',
            'subject' => $subject,
            'payment_status_snapshot' => $order->payment_status,
            'status' => 'pending',
        ]);

        try {
            Mail::to($recipientEmail)->send(new OrderConfirmationMail($order));

            $sentAt = now();

            $order->update([
                'confirmation_email_status' => 'sent',
                'confirmation_email_sent_at' => $sentAt,
                'confirmation_email_error' => null,
            ]);

            $emailLog->update([
                'status' => 'sent',
                'sent_at' => $sentAt,
            ]);

            Log::info("[OrderConfirmationEmailJob] Successfully sent order confirmation email for order #{$order->order_code} to {$recipientEmail}");
        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::error("[OrderConfirmationEmailJob] Failed to send email for order #{$order->order_code} to {$recipientEmail}: {$errorMsg}", [
                'exception' => $e,
            ]);

            $emailLog->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
            ]);

            $order->update([
                'confirmation_email_status' => 'failed',
                'confirmation_email_error' => $errorMsg,
            ]);

            // Re-throw so Laravel queue manager can retry according to $tries & $backoff
            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $errorMsg = $exception ? $exception->getMessage() : 'Exhausted maximum retry attempts';
        Log::error("[OrderConfirmationEmailJob] Job permanently failed for order #{$this->orderId}: {$errorMsg}");

        try {
            Order::where('id', $this->orderId)->update([
                'confirmation_email_status' => 'failed',
                'confirmation_email_error' => "Đã thử gửi lại {$this->tries} lần nhưng thất bại: {$errorMsg}",
            ]);
        } catch (Throwable) {
            // Silently ignore DB errors during failed callback to preserve state
        }
    }
}
