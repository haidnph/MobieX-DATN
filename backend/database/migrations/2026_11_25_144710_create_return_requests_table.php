
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();

            // Mã yêu cầu trả hàng
            $table->string('return_code', 50)->unique();

            // Đơn hàng và khách hàng
            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Thông tin yêu cầu trả hàng
            $table->string('reason', 100);
            $table->text('description')->nullable();
            $table->text('customer_note')->nullable();
            $table->json('attachments')->nullable();

            // Trạng thái yêu cầu
            $table->string('status', 30)->default('pending');

            // Thông tin vận chuyển hàng trả về
            $table->string('shipping_carrier', 100)->nullable();
            $table->string('return_tracking_code', 100)->nullable();

            // Thông tin xử lý của quản trị viên
            $table->text('admin_note')->nullable();

            // Thời gian xử lý
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Index
            $table->index(
                ['user_id', 'created_at'],
                'idx_return_requests_user_created'
            );

            $table->index(
                ['order_id', 'status'],
                'idx_return_requests_order_status'
            );

            $table->index(
                ['status', 'created_at'],
                'idx_return_requests_status_created'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
