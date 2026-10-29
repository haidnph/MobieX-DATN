<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    private function configureLocalAdmin(
        string $name = 'MobieX Development Admin',
        string $email = 'admin@mobilex.local',
        string $password = 'StrongSecurePassword-2026',
    ): void {
        $this->app->detectEnvironment(
            static fn (): string => 'local',
        );

        config([
            'rbac.admin.name' => $name,
            'rbac.admin.email' => $email,
            'rbac.admin.password' => $password,
        ]);
    }

    public function test_it_does_not_create_an_admin_outside_local_environment(): void
    {
        /*
         * Do not call $this->seed() after switching to production.
         * Laravel may require console confirmation when seeding in production.
         * The admin seeder must exit before it accesses the database.
         */
        $this->app->detectEnvironment(
            static fn (): string => 'production',
        );

        config([
            'rbac.admin.name' => 'MobieX Development Admin',
            'rbac.admin.email' => 'admin@mobilex.local',
            'rbac.admin.password' => 'StrongSecurePassword-2026',
        ]);

        app(AdminUserSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('role_user', 0);
    }

    public function test_it_skips_when_all_admin_credentials_are_empty(): void
    {
        $this->configureLocalAdmin('', '', '');

        $this->seed(RolePermissionSeeder::class);

        app(AdminUserSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('role_user', 0);
    }

    public function test_it_creates_an_active_admin_with_a_hashed_password(): void
    {
        $password = 'StrongSecurePassword-2026';

        $this->configureLocalAdmin(
            'MobieX Development Admin',
            'admin@mobilex.local',
            $password,
        );

        $this->seed(RolePermissionSeeder::class);

        app(AdminUserSeeder::class)->run();

        $user = User::query()
            ->where('email', 'admin@mobilex.local')
            ->firstOrFail();

        $this->assertTrue((bool) $user->is_active);
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));

        $this->assertSame(
            ['admin'],
            $user->roles()->pluck('slug')->all(),
        );

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('role_user', 1);
    }

    public function test_reseeding_does_not_duplicate_the_user_or_reset_password(): void
    {
        $originalPassword = 'StrongSecurePassword-2026';

        $this->configureLocalAdmin(
            'MobieX Development Admin',
            'admin@mobilex.local',
            $originalPassword,
        );

        $this->seed(RolePermissionSeeder::class);

        app(AdminUserSeeder::class)->run();

        config([
            'rbac.admin.password' => 'AnotherSecurePassword-2026',
        ]);

        app(AdminUserSeeder::class)->run();

        $user = User::query()
            ->where('email', 'admin@mobilex.local')
            ->firstOrFail();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('role_user', 1);

        $this->assertTrue(
            Hash::check($originalPassword, $user->password),
        );

        $this->assertFalse(
            Hash::check('AnotherSecurePassword-2026', $user->password),
        );
    }

    public function test_it_does_not_reactivate_an_existing_inactive_admin(): void
    {
        $this->configureLocalAdmin();

        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create([
            'email' => 'admin@mobilex.local',
        ]);

        $user->forceFill([
            'is_active' => false,
        ])->save();

        try {
            app(AdminUserSeeder::class)->run();

            $this->fail('An inactive existing admin must not be reactivated.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'account is inactive',
                $exception->getMessage(),
            );
        }

        $this->assertFalse(
            (bool) $user->fresh()->is_active,
        );
    }

    public function test_partial_admin_configuration_fails_without_creating_a_user(): void
    {
        $this->configureLocalAdmin(
            'MobieX Development Admin',
            '',
            '',
        );

        try {
            app(AdminUserSeeder::class)->run();

            $this->fail('Partial admin configuration should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Configure RBAC_ADMIN_NAME',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_policy_is_enforced_before_user_creation(): void
    {
        $this->configureLocalAdmin(
            'MobieX Development Admin',
            'admin@mobilex.local',
            'short',
        );

        $this->seed(RolePermissionSeeder::class);

        try {
            app(AdminUserSeeder::class)->run();

            $this->fail('The configured password should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'at least 15 characters',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('users', 0);
    }
}
