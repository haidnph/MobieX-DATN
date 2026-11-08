<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Display approved reviews for a given product.
     */
    public function index(Request $request, int|string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $query = Review::where('product_id', $product->id)
            ->where('status', 'approved')
            ->with([
                'user:id,name',
                'images',
            ])
            ->latest();

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $reviews = $query->paginate($perPage);

        return response()->json($reviews);
    }

    /**
     * Submit a verified purchase review for a product.
     */
    public function store(Request $request, int|string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ]);

        $orderItem = OrderItem::with('order')->findOrFail($validated['order_item_id']);

        if ((int) $orderItem->product_id !== (int) $product->id) {
            return response()->json([
                'message' => 'Món hàng không thuộc sản phẩm này.',
            ], 422);
        }

        $userId = $request->user()->id;

        if ((int) $orderItem->order->user_id !== (int) $userId) {
            return response()->json([
                'message' => 'Bạn không thể đánh giá đơn hàng của người khác.',
            ], 403);
        }

        if ($orderItem->order->status !== 'delivered') {
            return response()->json([
                'message' => 'Chỉ có thể đánh giá sản phẩm khi đơn hàng đã được giao thành công (delivered).',
            ], 422);
        }

        if (Review::where('order_item_id', $orderItem->id)->exists()) {
            return response()->json([
                'message' => 'Món hàng này đã được đánh giá.',
            ], 422);
        }

        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'order_item_id' => $orderItem->id,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Gửi đánh giá thành công. Đánh giá của bạn đang chờ kiểm duyệt.',
            'data' => $review,
        ], 201);
    }
}
