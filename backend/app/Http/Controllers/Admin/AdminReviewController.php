
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminReviewController extends Controller
{
    /**
     * Display reviews for admin management.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                'string',
                Rule::in(['pending', 'approved', 'hidden']),
            ],
            'product_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'rating' => [
                'nullable',
                'integer',
                'between:1,5',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'between:1,100',
            ],
        ]);

        $query = Review::query()
            ->with([
                'user:id,name,email',
                'product:id,name,slug',
                'orderItem:id,product_name,variant_name,sku',
                'moderator:id,name',
            ])
            ->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        if (!empty($validated['rating'])) {
            $query->where('rating', $validated['rating']);
        }

        $perPage = $validated['per_page'] ?? 15;

        $reviews = $query->paginate($perPage);

        return response()->json($reviews);
    }

    /**
     * Display review details.
     */
    public function show(int|string $id): JsonResponse
    {
        $review = Review::query()
            ->with([
                'user:id,name,email',
                'product',
                'orderItem',
                'moderator:id,name',
                'images',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $review,
        ]);
    }

    /**
     * Update review moderation status and admin reply.
     */
    public function updateStatus(
        Request $request,
        int|string $id
    ): JsonResponse {

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(['pending', 'approved', 'hidden']),
            ],
            'moderation_note' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'admin_reply' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        $review = DB::transaction(function () use (
            $id,
            $validated,
            $request
        ) {

            $review = Review::query()
                ->lockForUpdate()
                ->findOrFail($id);

            // Update moderation information.
            $review->status = $validated['status'];
            $review->moderated_by_user_id = $request->user()->id;
            $review->moderated_at = now();

            // Only update moderation note when provided.
            if (array_key_exists('moderation_note', $validated)) {
                $review->moderation_note =
                    $validated['moderation_note'];
            }

            // Only update admin reply when provided.
            if (array_key_exists('admin_reply', $validated)) {

                $newReply = $validated['admin_reply'];

                if ($review->admin_reply !== $newReply) {
                    $review->admin_reply = $newReply;

                    $review->replied_at = !empty($newReply)
                        ? now()
                        : null;
                }
            }

            $review->save();

            return $review;
        });

        return response()->json([
            'message' => 'Cập nhật trạng thái đánh giá thành công.',
            'data' => $review->fresh([
                'user:id,name',
                'moderator:id,name',
            ]),
        ]);
    }
}
