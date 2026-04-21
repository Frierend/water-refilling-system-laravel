<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VerifiedEmailMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_is_redirected_from_verified_protected_routes(): void
    {
        $user = $this->createUser(emailVerifiedAt: null);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_verified_protected_routes(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }

    private function createUser(?string $role = 'owner', $emailVerifiedAt = null): User
    {
        return User::create([
            'name' => 'Verified Middleware User',
            'email' => 'verified-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'email_verified_at' => $emailVerifiedAt ?? now(),
            'must_change_password' => false,
            'mfa_enabled' => false,
        ]);
    }
}
