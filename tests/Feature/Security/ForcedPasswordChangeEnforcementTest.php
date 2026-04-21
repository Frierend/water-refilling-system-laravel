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
            'failed_attempts' => 2,
            'locked_until' => null,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', 'Your temporary password has expired. Please contact the owner for assistance.');
        $this->assertGuest();
    }

    public function test_owner_with_expired_temporary_password_is_redirected_to_password_recovery_from_all_paths(): void
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
        $loginResponse->assertSessionHas('status', 'Your temporary password has expired. Recover your account using Forgot Password and the owner email path.');
        $this->assertGuest();

        $this->actingAs($owner);
        $routeResponse = $this->from('/dashboard')->get('/dashboard');
        $routeResponse->assertRedirect(route('password.request'));
        $routeResponse->assertSessionHas('status', 'Your temporary password has expired. Recover your account using Forgot Password and the owner email path.');
        $this->assertGuest();

        $owner = $this->createUser([
            'role' => 'owner',
            'must_change_password' => true,
            'temp_password_expires_at' => now()->subMinute(),
        ]);

        $showResponse = $this->actingAs($owner)->get(route('password.force.change'));

        $showResponse->assertRedirect(route('password.request'));
        $showResponse->assertSessionHas('status', 'Your temporary password has expired. Recover your account using Forgot Password and the owner email path.');
        $this->assertGuest();

        $owner->refresh();
        $this->assertNotNull($owner->lifecycle_locked_at);
        $this->assertSame('temporary_password_expired', $owner->lifecycle_lock_reason);
    }

    public function test_forced_password_update_with_expired_temporary_password_redirects_to_password_recovery(): void
    {
        $user = $this->createUser([
            'must_change_password' => true,
            'temp_password_expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->post(route('password.force.update'), [
            'password' => 'Stronger#Password1',
            'password_confirmation' => 'Stronger#Password1',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', 'Your temporary password has expired. Please contact the owner for assistance.');
        $this->assertGuest();

        $owner->refresh();
        $this->assertNotNull($owner->lifecycle_locked_at);
        $this->assertSame('temporary_password_expired', $owner->lifecycle_lock_reason);
        $this->assertSame(0, (int) $owner->failed_attempts);
        $this->assertNull($owner->locked_until);
    }

    public function test_lifecycle_lock_message_for_non_owner_on_login(): void
    {
        $user = $this->createUser([
            'role' => 'helper',
            'lifecycle_locked_at' => now()->subMinute(),
            'lifecycle_lock_reason' => 'temporary_password_expired',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'Your temporary password has expired. Please contact the owner for assistance.',
        ]);
        $this->assertGuest();
    }

    public function test_lifecycle_lock_message_for_owner_on_login(): void
    {
        $user = $this->createUser([
            'role' => 'owner',
            'lifecycle_locked_at' => now()->subMinute(),
            'lifecycle_lock_reason' => 'temporary_password_expired',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'Your temporary password has expired. Recover your account using Forgot Password and the owner email path.',
        ]);
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
        $this->assertNull($user->lifecycle_locked_at);
        $this->assertNull($user->lifecycle_lock_reason);
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
            'lifecycle_locked_at' => null,
            'lifecycle_lock_reason' => null,
        ], $overrides))->save();

        return $user;
    }
}
