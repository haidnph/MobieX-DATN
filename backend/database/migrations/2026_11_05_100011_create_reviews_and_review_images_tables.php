<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create reviews and review_images tables.
     */
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Reviews
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) use ($isMysql) {
                $table->id();
                $table->foreignId('product_id')->constrained('products');
                $table->foreignId('user_id')->constrained('users');
                $table->foreignId('order_item_id')->unique('uq_reviews_order_item')->constrained('order_items');
                $table->unsignedTinyInteger('rating');
                $table->string('title', 255)->nullable();
                $table->text('content')->nullable();
                $table->enum('status', ['pending', 'approved', 'hidden'])->default('pending');
                $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('moderated_at')->nullable();
                $table->string('moderation_note', 255)->nullable();
                $table->text('admin_reply')->nullable();
                $table->timestamp('replied_at')->nullable();
                $table->timestamps();

                $table->index(['order_item_id', 'product_id'], 'idx_reviews_order_item_product_fk');
                $table->index(['product_id', 'status', 'created_at'], 'idx_reviews_product_status_created');
                $table->index('user_id', 'idx_reviews_user');
                $table->index('moderated_by_user_id', 'idx_reviews_moderator');

                if ($isMysql) {
                    $table->foreign(['order_item_id', 'product_id'], 'fk_reviews_order_item_product')
                        ->references(['id', 'product_id'])
                        ->on('order_items');
                }
            });
        }

        // 2. Review Images
        if (! Schema::hasTable('review_images')) {
            Schema::create('review_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
                $table->string('image_path', 255);
                $table->string('alt_text', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('review_id', 'idx_review_images_review');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_images');
        Schema::dropIfExists('reviews');
    }
};
