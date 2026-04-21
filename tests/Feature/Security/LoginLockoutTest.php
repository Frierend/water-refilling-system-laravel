<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_attempts_increment_and_lock_on_fifth_failure(): void
    {
        $user = $this->createUser();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $response = $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

            $response->assertRedirect('/login');
            $response->assertSessionHasErrors(['email']);
            $this->assertGuest();

            $user->refresh();
            $this->assertSame($attempt, (int) $user->failed_attempts);
            $this->assertNull($user->locked_until);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();

        $user->refresh();
        $this->assertSame(5, (int) $user->failed_attempts);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
    }

    public function test_locked_user_cannot_login_with_correct_password(): void
    {
        $user = $this->createUser([
            'failed_attempts' => 5,
            'locked_until' => now()->addMinutes(5),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();

        $user->refresh();
        $this->assertSame(5, (int) $user->failed_attempts);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
    }

    public function test_successful_login_resets_failed_attempts_and_lock_fields(): void
    {
        $user = $this->createUser([
            'failed_attempts' => 3,
            'locked_until' => null,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame(0, (int) $user->failed_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_expired_lock_is_cleared_and_user_can_login(): void
    {
        $user = $this->createUser([
            'failed_attempts' => 5,
            'locked_until' => now()->subMinute(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame(0, (int) $user->failed_attempts);
        $this->assertNull($user->locked_until);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        $baseAttributes = [
            'name' => 'Lockout Test User',
            'email' => 'lockout-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ];

        $user = User::create($baseAttributes);
        $user->forceFill(array_merge([
            'failed_attempts' => 0,
            'locked_until' => null,
        ], $overrides))->save();

        return $user;
    }
}
