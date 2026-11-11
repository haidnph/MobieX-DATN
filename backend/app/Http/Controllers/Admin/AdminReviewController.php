<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Review::query()
            ->with([
                'user:id,name,email',
                'product:id,name,slug',
                'orderItem:id,product_name,variant_name,sku',
                'moderator:id,name',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', strtolower((string) $request->query('status')));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->query('rating'));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $reviews = $query->paginate($perPage);

        return response()->json($reviews);
    }

    public function show(int|string $id): JsonResponse
    {
        $review = Review::with([
            'user:id,name,email',
            'product',
            'orderItem',
            'moderator:id,name',
            'images',
        ])->findOrFail($id);

        return response()->json([
            'data' => $review,
        ]);
    }

    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $review = Review::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'hidden'])],
            'moderation_note' => ['nullable', 'string', 'max:255'],
            'admin_reply' => ['nullable', 'string'],
        ]);

        $review->status = $validated['status'];
        $review->moderated_by_user_id = $request->user()->id;
        $review->moderated_at = now();

        if (array_key_exists('moderation_note', $validated)) {
            $review->moderation_note = $validated['moderation_note'];
        }

        if (! empty($validated['admin_reply'])) {
            $review->admin_reply = $validated['admin_reply'];
            $review->replied_at = now();
        }

        $review->save();

        return response()->json([
            'message' => 'Cập nhật trạng thái đánh giá thành công.',
            'data' => $review->fresh(['user:id,name', 'moderator:id,name']),
        ]);
    }
}
