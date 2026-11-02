<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SanctumSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_issue_and_use_a_sanctum_bearer_token(): void
    {
        $user = User::factory()->create();

        $issuedToken = $user->createToken('auth-setup-test');

        $this->assertNotEmpty($issuedToken->plainTextToken);

        $tokenParts = explode('|', $issuedToken->plainTextToken, 2);
        $plainTextToken = $tokenParts[1] ?? $tokenParts[0];

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $issuedToken->accessToken->getKey(),
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'name' => 'auth-setup-test',
            'token' => hash('sha256', $plainTextToken),
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => $plainTextToken,
        ]);

        Route::middleware('auth:sanctum')->get(
            '/api/_tests/sanctum-authentication',
            function (Request $request) {
                return response()->json([
                    'user_id' => $request->user()->getKey(),
                ]);
            },
        );

        $this->withToken($issuedToken->plainTextToken)
            ->getJson('/api/_tests/sanctum-authentication')
            ->assertOk()
            ->assertJsonPath('user_id', $user->getKey());
    }
}
