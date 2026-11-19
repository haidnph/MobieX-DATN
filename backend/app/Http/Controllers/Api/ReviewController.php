
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Display approved reviews for a given product.
     */
    public function index(Request $request, int|string $productId): JsonResponse
    {
        $product = is_numeric($productId)
            ? Product::findOrFail((int) $productId)
            : Product::where('slug', $productId)->firstOrFail();

        $perPage = min(
            max((int) $request->query('per_page', 10), 1),
            50
        );

        $reviews = Review::query()
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->with([
                'user:id,name',
                'images',
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json($reviews);
    }

    /**
     * Submit a verified purchase review for a product.
     */
    public function store(Request $request, int|string $productId): JsonResponse
    {
        $product = is_numeric($productId)
            ? Product::findOrFail((int) $productId)
            : Product::where('slug', $productId)->firstOrFail();

        $validated = $request->validate([
            'order_item_id' => [
                'required',
                'integer',
                'exists:order_items,id',
            ],
            'rating' => [
                'required',
                'integer',
                'between:1,5',
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'content' => [
                'nullable',
                'string',
            ],
        ]);

        $userId = $request->user()->id;

        $review = DB::transaction(function () use (
            $validated,
            $product,
            $userId
        ) {
            // Lock order item to prevent simultaneous duplicate reviews.
            $orderItem = OrderItem::query()
                ->with('order')
                ->lockForUpdate()
                ->findOrFail($validated['order_item_id']);

            // Check product ownership.
            if ((int) $orderItem->product_id !== (int) $product->id) {
                throw ValidationException::withMessages([
                    'order_item_id' => [
                        'Món hàng không thuộc sản phẩm này.',
                    ],
                ]);
            }

            // Check order existence.
            if (!$orderItem->order) {
                throw ValidationException::withMessages([
                    'order_item_id' => [
                        'Không tìm thấy đơn hàng tương ứng.',
                    ],
                ]);
            }

            // Check order ownership.
            if ((int) $orderItem->order->user_id !== (int) $userId) {
                abort(403, 'Bạn không thể đánh giá đơn hàng của người khác.');
            }

            // Only delivered orders can be reviewed.
            if ($orderItem->order->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'order_item_id' => [
                        'Chỉ có thể đánh giá sản phẩm khi đơn hàng đã được giao thành công.',
                    ],
                ]);
            }

            // Prevent duplicate reviews.
            $alreadyReviewed = Review::query()
                ->where('order_item_id', $orderItem->id)
                ->exists();

            if ($alreadyReviewed) {
                throw ValidationException::withMessages([
                    'order_item_id' => [
                        'Món hàng này đã được đánh giá.',
                    ],
                ]);
            }

            return Review::create([
                'product_id' => $product->id,
                'user_id' => $userId,
                'order_item_id' => $orderItem->id,
                'rating' => $validated['rating'],
                'title' => $validated['title'] ?? null,
                'content' => $validated['content'] ?? null,
                'status' => 'pending',
            ]);
        });

        return response()->json([
            'message' => 'Gửi đánh giá thành công. Đánh giá của bạn đang chờ kiểm duyệt.',
            'data' => $review,
        ], 201);
    }
}
