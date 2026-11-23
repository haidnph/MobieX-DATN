<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeededDemoAccountSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_only_seeds_reference_authorization_data_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('roles', 6);
        $this->assertDatabaseHas('permissions', ['slug' => 'dashboard.view']);
    }

    public function test_migration_disables_legacy_demo_users_and_revokes_their_tokens(): void
    {
        $legacyEmails = [
            'admin@mobilex.tech',
            'superadmin@gmail.com',
            'customer@mobilex.tech',
        ];
        $legacyUsers = collect($legacyEmails)->map(fn (string $email) => User::factory()->create([
            'email' => $email,
            'is_active' => true,
        ]));
        $legacyUsers->each(fn (User $user) => $user->createToken('legacy-demo-token'));
        $unrelatedUser = User::factory()->create(['email' => 'owner@example.test', 'is_active' => true]);
        $unrelatedUser->createToken('valid-token');

        $migration = require database_path('migrations/2026_11_23_000001_disable_legacy_demo_accounts.php');
        $migration->up();

        foreach ($legacyEmails as $email) {
            $this->assertDatabaseHas('users', ['email' => $email, 'is_active' => false]);
        }
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $unrelatedUser->id,
            'name' => 'valid-token',
        ]);
    }
}
