<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotResetPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_route_accepts_email_and_returns_status(): void
    {
        $user = $this->createUser();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status');
    }

    public function test_reset_password_enforces_configured_min_length_policy(): void
    {
        config()->set('security.password_policy.min_length', 12);

        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $response = $this->from(route('password.reset', ['token' => $token]))->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Short#1a',
            'password_confirmation' => 'Short#1a',
        ]);

        $response->assertRedirect(route('password.reset', ['token' => $token]));
        $response->assertSessionHasErrors(['password']);
    }

    public function test_successful_reset_clears_temporary_password_lifecycle_flags(): void
    {
        config()->set('security.password_policy.min_length', 8);

        $user = $this->createUser([
            'must_change_password' => true,
            'password_changed_at' => null,
            'temp_password_expires_at' => now()->addHour(),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPolicy#Pass123',
            'password_confirmation' => 'NewPolicy#Pass123',
        ]);

        $response->assertRedirect(route('login'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertNull($user->temp_password_expires_at);
        $this->assertTrue(Hash::check('NewPolicy#Pass123', $user->password));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        $user = User::create([
            'name' => 'Reset User',
            'email' => 'reset-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        $user->forceFill(array_merge([
            'must_change_password' => false,
            'password_changed_at' => null,
            'temp_password_expires_at' => null,
            'mfa_enabled' => false,
        ], $overrides))->save();

        return $user;
    }
}
