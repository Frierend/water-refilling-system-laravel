<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_access_create_user_page(): void
    {
        $owner = $this->createUser('owner');

        $response = $this->actingAs($owner)->get(route('users.create'));

        $response->assertOk();
        $response->assertSee('Create User Account');
    }

    public function test_non_owner_cannot_access_create_user_page(): void
    {
        $helper = $this->createUser('helper');

        $response = $this->actingAs($helper)->get(route('users.create'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }


    public function test_non_owner_cannot_create_user_accounts(): void
    {
        $delivery = $this->createUser('delivery');

        $response = $this->actingAs($delivery)->post(route('users.store'), [
            'name' => 'Blocked User',
            'email' => 'blocked-user@example.com',
            'role' => 'helper',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('users', ['email' => 'blocked-user@example.com']);
    }

    public function test_owner_can_assign_only_delivery_or_helper_roles(): void
    {
        $owner = $this->createUser('owner');

        $response = $this->actingAs($owner)->from(route('users.create'))->post(route('users.store'), [
            'name' => 'Invalid Role User',
            'email' => 'invalid-role@example.com',
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        $response->assertRedirect(route('users.create'));
        $response->assertSessionHasErrors(['role']);
        $this->assertDatabaseMissing('users', ['email' => 'invalid-role@example.com']);
    }

    public function test_owner_can_create_user_with_hashed_password_and_lifecycle_fields(): void
    {
        $owner = $this->createUser('owner');

        $response = $this->actingAs($owner)->from(route('users.create'))->post(route('users.store'), [
            'name' => 'Delivery Agent',
            'email' => 'delivery-agent@example.com',
            'role' => 'delivery',
            'email_verified_at' => now(),
        ]);

        $response->assertRedirect(route('users.create'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('temporary_password');
        $response->assertSessionHas('temporary_password_user_email', 'delivery-agent@example.com');

        $temporaryPassword = session('temporary_password');
        $this->assertIsString($temporaryPassword);

        $createdUser = User::where('email', 'delivery-agent@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertNotSame($temporaryPassword, $createdUser->password);
        $this->assertTrue(Hash::check($temporaryPassword, $createdUser->password));
        $this->assertTrue($createdUser->must_change_password);
        $this->assertNull($createdUser->password_changed_at);
        $this->assertNotNull($createdUser->temp_password_expires_at);
        $this->assertTrue($createdUser->temp_password_expires_at->isFuture());

        if (Schema::hasColumn('users', 'email_verified_at')) {
            $this->assertNull($createdUser->email_verified_at);
        }
    }

    public function test_temporary_password_flash_is_shown_once(): void
    {
        $owner = $this->createUser('owner');

        $this->actingAs($owner)->from(route('users.create'))->post(route('users.store'), [
            'name' => 'Helper Agent',
            'email' => 'helper-agent@example.com',
            'role' => 'helper',
        ])->assertRedirect(route('users.create'));

        $firstLoad = $this->actingAs($owner)->get(route('users.create'));
        $firstLoad->assertOk();
        $firstLoad->assertSee('This temporary password is shown once');

        $secondLoad = $this->actingAs($owner)->get(route('users.create'));
        $secondLoad->assertOk();
        $secondLoad->assertDontSee('This temporary password is shown once');
    }

    public function test_audit_events_are_logged_without_plaintext_password(): void
    {
        $owner = $this->createUser('owner');

        Log::shouldReceive('channel')->with('security')->twice()->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function (string $event, array $context): bool {
            return $event === 'security.user.created_by_owner'
                && isset($context['actor_id'], $context['user_id'], $context['role'], $context['email'])
                && ! array_key_exists('temporary_password', $context);
        });
        Log::shouldReceive('info')->once()->withArgs(function (string $event, array $context): bool {
            return $event === 'security.temporary_password.issued'
                && isset($context['actor_id'], $context['user_id'], $context['temp_password_expires_at'])
                && ! array_key_exists('temporary_password', $context);
        });

        $response = $this->actingAs($owner)->from(route('users.create'))->post(route('users.store'), [
            'name' => 'Audit Test User',
            'email' => 'audit-test@example.com',
            'role' => 'delivery',
            'email_verified_at' => now(),
        ]);

        $response->assertRedirect(route('users.create'));
    }

    private function createUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
