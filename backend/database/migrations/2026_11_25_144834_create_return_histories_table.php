
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_histories', function (Blueprint $table) {
            $table->id();

            // Yêu cầu trả hàng
            $table->foreignId('return_request_id')
                ->constrained('return_requests')
                ->cascadeOnDelete();

            // Người thực hiện thao tác
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Trạng thái trước khi thay đổi
            $table->string('from_status', 30)->nullable();

            // Trạng thái sau khi thay đổi
            $table->string('status', 30);

            // Ghi chú thay đổi trạng thái
            $table->text('note')->nullable();

            // Model ReturnHistory sử dụng timestamps = false
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['return_request_id', 'created_at'],
                'idx_return_histories_request_created'
            );

            $table->index('user_id', 'idx_return_histories_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_histories');
    }
};
