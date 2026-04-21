<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class MfaLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_mfa_enabled_user_is_redirected_to_challenge_for_guarded_routes(): void
    {
        $user = $this->createUser([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('BASE32SECRET'),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('mfa.challenge'));
    }

    public function test_user_can_setup_enable_and_disable_mfa(): void
    {
        $totp = Mockery::mock(TotpService::class);
        $totp->shouldReceive('generateSecret')->once()->andReturn('BASE32SECRET');
        $totp->shouldReceive('provisioningUri')->once()->andReturn('otpauth://totp/example');
        $totp->shouldReceive('verifyCode')->once()->with('BASE32SECRET', '123456')->andReturn(true);
        $this->app->instance(TotpService::class, $totp);

        Log::shouldReceive('channel')->with('security')->twice()->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(fn (string $event, array $context): bool => $event === 'security.mfa.enabled' && isset($context['user_id']));
        Log::shouldReceive('info')->once()->withArgs(fn (string $event, array $context): bool => $event === 'security.mfa.disabled' && isset($context['user_id']));

        $user = $this->createUser();

        $setup = $this->actingAs($user)->get(route('mfa.setup'));
        $setup->assertOk();
        $setup->assertSee('BASE32SECRET');

        $enable = $this->actingAs($user)->post(route('mfa.enable'), [
            'code' => '123456',
        ]);

        $enable->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue($user->mfa_enabled);
        $this->assertNotNull($user->mfa_secret);

        $disable = $this->actingAs($user)->post(route('mfa.disable'), [
            'password' => 'password',
        ]);

        $disable->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->mfa_enabled);
        $this->assertNull($user->mfa_secret);
    }

    public function test_mfa_challenge_logs_failed_attempt_and_blocks_access(): void
    {
        $totp = Mockery::mock(TotpService::class);
        $totp->shouldReceive('verifyCode')->once()->with('BASE32SECRET', '654321')->andReturn(false);
        $this->app->instance(TotpService::class, $totp);

        Log::shouldReceive('channel')->with('security')->once()->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(fn (string $event, array $context): bool => $event === 'security.mfa.challenge.failed' && isset($context['user_id']));

        $user = $this->createUser([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('BASE32SECRET'),
        ]);

        $response = $this->actingAs($user)->from(route('mfa.challenge'))->post(route('mfa.challenge.verify'), [
            'code' => '654321',
        ]);

        $response->assertRedirect(route('mfa.challenge'));
        $response->assertSessionHasErrors(['code']);
        $this->assertNotSame($user->id, session('mfa_passed_for_user_id'));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        $user = User::create([
            'name' => 'MFA User',
            'email' => 'mfa-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $user->forceFill(array_merge([
            'mfa_enabled' => false,
            'mfa_secret' => null,
        ], $overrides))->save();

        return $user;
    }
}
