<?php

namespace Tests\Feature\Security;

use App\Models\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecuritySystemLogEmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_log_is_emitted_for_role_based_authorization_denial(): void
    {
        Log::shouldReceive('channel')->with('security')->once()->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(function (string $event, array $context): bool {
            return $event === 'security.authorization.denied'
                && ($context['required_role'] ?? null) === 'owner'
                && isset($context['user_id']);
        });

        $helper = $this->createUser('helper');

        $response = $this->actingAs($helper)->get(route('users.create'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_system_log_is_emitted_for_inventory_creation(): void
    {
        Log::shouldReceive('channel')->with('system')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function (string $event, array $context): bool {
            return $event === 'inventory.item.created'
                && isset($context['item_id'], $context['actor_id'], $context['item_name']);
        });

        $owner = $this->createUser('owner');

        $response = $this->actingAs($owner)->post(route('inventory.store'), [
            'name' => 'Blue Cap',
            'description' => 'Cap item',
            'quantity' => 10,
            'price' => 15.5,
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas((new Inventory())->getTable(), [
            'name' => 'Blue Cap',
        ]);
    }

    private function createUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . '-log-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'email_verified_at' => now(),
            'must_change_password' => false,
            'mfa_enabled' => false,
        ]);
    }
}
