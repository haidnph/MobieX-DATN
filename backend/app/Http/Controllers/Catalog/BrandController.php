<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * Retrieve visible brands with pagination.
     *
     * Records are ordered by name and then ID
     * to ensure stable pagination.
     */
    public function index(): AnonymousResourceCollection
    {
        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return BrandResource::collection($brands)
            ->additional([
                'message' => 'Brands retrieved successfully.',
            ]);
    }

    /**
     * Retrieve a visible brand by its ID.
     *
     * Hidden brands are treated as nonexistent.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $brand = Brand::query()
            ->where('status', true)
            ->findOrFail($id);

        return response()->json([
            'message' => 'Brand retrieved successfully.',
            'data' => [
                'brand' => BrandResource::make($brand)
                    ->resolve($request),
            ],
        ]);
    }
}
