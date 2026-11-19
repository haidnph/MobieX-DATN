<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HomepageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageController extends Controller
{
    public function __construct(
        protected HomepageService $homepageService
    ) {}

    /**
     * Retrieve published homepage content and section layout for public storefront.
     */
    public function index(Request $request): JsonResponse
    {
        $fresh = $request->boolean('fresh');
        $data = $this->homepageService->getStorefrontData($fresh);

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }
}
