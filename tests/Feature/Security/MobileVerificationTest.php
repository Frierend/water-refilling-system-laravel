<?php

namespace Tests\Feature\Security;

use App\Models\MobileVerificationOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_stores_hashed_challenge_for_mobile_number(): void
    {
        config(['app.env' => 'local']);
        $user = $this->createUser();

        $response = $this->actingAs($user)->post('/mobile/verify/send', [
            'mobile_number' => '+639171234567',
        ]);

        $response->assertRedirect('/mobile/verify');
        $response->assertSessionHas('status');
        $response->assertSessionHas('local_mobile_otp_preview');

        $challenge = MobileVerificationOtp::query()->first();

        $this->assertNotNull($challenge);
        $this->assertSame('+639171234567', $challenge->mobile_number);
        $this->assertTrue($challenge->expires_at->isFuture());
        $this->assertFalse(Hash::check('000000', $challenge->otp_hash));
    }

    public function test_user_can_verify_mobile_number_with_valid_otp(): void
    {
        config(['app.env' => 'local']);
        $user = $this->createUser();

        $sendResponse = $this->actingAs($user)->post('/mobile/verify/send', [
            'mobile_number' => '+639171234567',
        ]);
        $sendResponse->assertRedirect('/mobile/verify');

        $otp = (string) session('local_mobile_otp_preview.otp');
        $this->assertNotSame('', $otp);

        $verifyResponse = $this->actingAs($user)->post('/mobile/verify/confirm', [
            'mobile_number' => '+639171234567',
            'otp_code' => $otp,
        ]);

        $verifyResponse->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertSame('+639171234567', $user->mobile_number);
        $this->assertNotNull($user->mobile_verified_at);
    }

    public function test_wrong_otp_increments_attempt_count_and_does_not_verify_mobile(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post('/mobile/verify/send', [
            'mobile_number' => '+639171234567',
        ]);

        $response = $this->from('/mobile/verify')->actingAs($user)->post('/mobile/verify/confirm', [
            'mobile_number' => '+639171234567',
            'otp_code' => '123456',
        ]);

        $response->assertRedirect('/mobile/verify');
        $response->assertSessionHasErrors(['otp_code']);

        $challenge = MobileVerificationOtp::query()->first();
        $this->assertNotNull($challenge);
        $this->assertSame(1, $challenge->attempts);

        $user->refresh();
        $this->assertNull($user->mobile_verified_at);
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Mobile Verification User',
            'email' => 'mobile-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }
}
