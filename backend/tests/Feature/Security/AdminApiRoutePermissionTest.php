<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class AdminApiRoutePermissionTest extends TestCase
{
    public function test_every_admin_api_route_requires_a_specific_permission(): void
    {
        $adminRoutes = collect(app('router')->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/admin/'));

        $this->assertNotEmpty($adminRoutes, 'Admin API routes must be registered.');

        foreach ($adminRoutes as $route) {
            $hasPermissionMiddleware = collect($route->gatherMiddleware())
                ->contains(fn (string $middleware): bool => str_starts_with($middleware, 'permission:'));

            $this->assertTrue(
                $hasPermissionMiddleware,
                sprintf('Admin route [%s] %s has no permission middleware.', implode('|', $route->methods()), $route->uri()),
            );
        }
    }
}
