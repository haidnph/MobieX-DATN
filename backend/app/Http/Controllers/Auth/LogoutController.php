<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        if (! ($accessToken instanceof PersonalAccessToken)) {
            return response()->json([
                'message' => 'Logout requires a Bearer access token.',
            ], 401);
        }

        $accessToken->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }
}
