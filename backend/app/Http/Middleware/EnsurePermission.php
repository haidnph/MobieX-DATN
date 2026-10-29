<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Require an authenticated, active user to possess every permission
     * specified in the middleware parameters.
     *
     * Example:
     * permission:users.view
     * permission:users.view,users.manage
     *
     * Multiple permissions use AND semantics: all must be granted.
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$permissions,
    ): Response {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
         * Defense in depth: an inactive account must not pass this
         * middleware even if a route accidentally omits "active".
         */
        if (! $user->is_active) {
            return response()->json([
                'message' => 'This account is inactive.',
            ], 403);
        }

        if ($permissions === []) {
            throw new LogicException(
                'The permission middleware requires at least one permission slug.',
            );
        }

        foreach ($permissions as $permission) {
            $permission = trim($permission);

            if ($permission === '') {
                throw new LogicException(
                    'Permission middleware arguments must not be empty.',
                );
            }

            if (! $user->hasPermission($permission)) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }
        }

        return $next($request);
    }
}
