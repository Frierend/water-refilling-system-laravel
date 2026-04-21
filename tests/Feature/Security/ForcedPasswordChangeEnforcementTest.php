<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ForcedPasswordChangeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_to_forced_change_when_password_change_is_required(): void
    {
        Log::shouldReceive('channel')->with('security')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function (string $event, array $context): bool {
            return $event === 'security.forced_password_change.triggered'
                && isset($context['user_id'], $context['email'], $context['ip'], $context['user_agent']);
        });

        $user = $this->createUser([
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours(24),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('password.force.change'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_blocked_when_temporary_password_has_expired(): void
    {
        $user = $this->createUser([
            'must_change_password' => true,
            'temp_password_expires_at' => now()->subMinute(),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status');
        $this->assertGuest();
    }

    public function test_owner_with_expired_temporary_password_is_redirected_to_forgot_password_recovery_route(): void
    {
        $owner = $this->createUser([
            'role' => 'owner',
            'must_change_password' => true,
            'temp_password_expires_at' => now()->subMinute(),
        ]);

        $loginResponse = $this->post('/login', [
            'email' => $owner->email,
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect(route('password.request'));
        $this->assertGuest();

        $this->actingAs($owner);
        $routeResponse = $this->get('/dashboard');
        $routeResponse->assertRedirect(route('password.request'));
        $this->assertGuest();
    }

    public function test_flagged_user_is_blocked_from_normal_protected_routes(): void
    {
        $user = $this->createUser([
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('password.force.change'));
    }

    public function test_flagged_user_can_access_forced_change_route(): void
    {
        $user = $this->createUser([
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($user)->get(route('password.force.change'));

        $response->assertOk();
        $response->assertSee('Password Change Required');
    }

    public function test_forced_change_update_rejects_password_that_fails_security_policy(): void
    {
        $user = $this->createUser([
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($user)->from(route('password.force.change'))->post(route('password.force.update'), [
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ]);

        $response->assertRedirect(route('password.force.change'));
        $response->assertSessionHasErrors(['password']);

        $user->refresh();
        $this->assertTrue($user->must_change_password);
    }

    public function test_forced_change_update_rejects_reuse_of_temporary_password(): void
    {
        $user = $this->createUser([
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($user)->from(route('password.force.change'))->post(route('password.force.update'), [
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('password.force.change'));
        $response->assertSessionHasErrors(['password']);

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->password_changed_at);
        $this->assertNotNull($user->temp_password_expires_at);
    }

    public function test_forced_change_update_succeeds_and_clears_lifecycle_flags(): void
    {
        Log::shouldReceive('channel')->with('security')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function (string $event, array $context): bool {
            return $event === 'security.password.changed.success'
                && isset($context['user_id'], $context['email'], $context['ip'], $context['user_agent']);
        });

        $user = $this->createUser([
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($user)->post(route('password.force.update'), [
            'password' => 'Stronger#Password1',
            'password_confirmation' => 'Stronger#Password1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertNull($user->temp_password_expires_at);
        $this->assertTrue(Hash::check('Stronger#Password1', $user->password));
    }

    public function test_non_flagged_user_is_redirected_away_from_forced_change_page(): void
    {
        $user = $this->createUser([
            'must_change_password' => false,
            'temp_password_expires_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('password.force.change'));

        $response->assertRedirect(route('dashboard'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        $user = User::create([
            'name' => 'Forced Change User',
            'email' => 'forced-change-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'helper',
            'email_verified_at' => now(),
        ]);

        $user->forceFill(array_merge([
            'must_change_password' => false,
            'temp_password_expires_at' => null,
            'password_changed_at' => null,
            'failed_attempts' => 0,
            'locked_until' => null,
        ], $overrides))->save();

        return $user;
    }
}
