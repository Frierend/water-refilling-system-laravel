<?php

namespace Tests\Feature\Security;

use App\Models\MobileVerificationOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OtpMobileVerificationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_verification_routes_use_current_names(): void
    {
        $this->assertTrue(Route::has('mobile.verification.notice'));
        $this->assertTrue(Route::has('mobile.verification.send'));
        $this->assertTrue(Route::has('mobile.verification.verify'));

        $this->assertSame('/mobile/verify', route('mobile.verification.notice', absolute: false));
        $this->assertSame('/mobile/verify/send', route('mobile.verification.send', absolute: false));
        $this->assertSame('/mobile/verify/confirm', route('mobile.verification.verify', absolute: false));
    }

    public function test_send_flow_creates_otp_challenge_with_five_minute_expiry_and_hash_only_storage(): void
    {
        config(['app.env' => 'local']);
        Carbon::setTestNow($now = Carbon::parse('2026-04-21 10:00:00'));
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('mobile.verification.send'), [
            'mobile_number' => '+639171234567',
        ]);

        $response->assertRedirect(route('mobile.verification.notice'));
        $response->assertSessionHas('local_mobile_otp_preview');

        $challenge = MobileVerificationOtp::query()->first();
        $this->assertNotNull($challenge);
        $this->assertSame($user->id, $challenge->user_id);
        $this->assertSame('+639171234567', $challenge->mobile_number);
        $this->assertSame(0, $challenge->attempts);
        $this->assertNull($challenge->consumed_at);
        $this->assertTrue($challenge->expires_at->equalTo($now->copy()->addMinutes(5)));

        $otp = (string) session('local_mobile_otp_preview.otp');
        $this->assertNotSame('', $otp);
        $this->assertTrue(Hash::check($otp, $challenge->otp_hash));
        $this->assertFalse(Schema::hasColumn('mobile_verification_otps', 'otp'));

        Carbon::setTestNow();
    }

    public function test_failed_attempts_increment_and_are_capped_at_five(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->post(route('mobile.verification.send'), [
            'mobile_number' => '+639171234567',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->from(route('mobile.verification.notice'))
                ->actingAs($user)
                ->post(route('mobile.verification.verify'), [
                    'mobile_number' => '+639171234567',
                    'otp_code' => '111111',
                ]);

            $response->assertRedirect(route('mobile.verification.notice'));
            $response->assertSessionHasErrors(['otp_code']);

            $challenge = MobileVerificationOtp::query()->first();
            $this->assertNotNull($challenge);
            $this->assertSame($attempt, (int) $challenge->attempts);
        }

        $blockedResponse = $this->from(route('mobile.verification.notice'))
            ->actingAs($user)
            ->post(route('mobile.verification.verify'), [
                'mobile_number' => '+639171234567',
                'otp_code' => '111111',
            ]);

        $blockedResponse->assertRedirect(route('mobile.verification.notice'));
        $blockedResponse->assertSessionHasErrors(['otp_code']);
        $this->assertSame(
            'Too many failed attempts. Request a new verification code.',
            session('errors')->first('otp_code')
        );

        $challenge = MobileVerificationOtp::query()->first();
        $this->assertNotNull($challenge);
        $this->assertSame(5, (int) $challenge->attempts);
    }

    public function test_successful_verification_sets_mobile_verified_at_and_consumes_otp(): void
    {
        config(['app.env' => 'local']);
        $user = $this->createUser();

        $sendResponse = $this->actingAs($user)->post(route('mobile.verification.send'), [
            'mobile_number' => '+639171234567',
        ]);
        $sendResponse->assertRedirect(route('mobile.verification.notice'));

        $otp = (string) session('local_mobile_otp_preview.otp');
        $this->assertNotSame('', $otp);

        $verifyResponse = $this->actingAs($user)->post(route('mobile.verification.verify'), [
            'mobile_number' => '+639171234567',
            'otp_code' => $otp,
        ]);

        $verifyResponse->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertSame('+639171234567', $user->mobile_number);
        $this->assertNotNull($user->mobile_verified_at);

        $challenge = MobileVerificationOtp::query()->first();
        $this->assertNotNull($challenge);
        $this->assertNotNull($challenge->consumed_at);
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'OTP Lifecycle User',
            'email' => 'otp-lifecycle-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }
}
