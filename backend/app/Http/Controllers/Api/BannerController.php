<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * Display a listing of active banners for public storefront.
     */
    public function index(Request $request): JsonResponse
    {
        $now = now();

        $query = Banner::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            });

        if ($request->filled('position')) {
            $query->where('position', (string) $request->query('position'));
        }

        $banners = $query->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'data' => $banners,
        ]);
    }
}
