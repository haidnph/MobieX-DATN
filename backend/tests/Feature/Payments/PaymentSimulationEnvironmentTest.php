<?php

namespace Tests\Feature\Payments;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSimulationEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_simulation_endpoints_are_hidden_in_production(): void
    {
        $permission = Permission::create([
            'name' => 'Manage orders',
            'slug' => 'orders.manage',
        ]);
        $role = Role::create([
            'name' => 'Order manager',
            'slug' => 'order-manager-simulation-test',
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $this->app->detectEnvironment(fn () => 'production');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/payments/bank-transfer/simulate', [])
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/payments/momo/simulate', [])
            ->assertNotFound();
    }
}
