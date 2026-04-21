<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OtpMobileVerificationLifecycleTest extends TestCase
{
    public function test_mobile_otp_verification_routes_are_not_yet_implemented(): void
    {
        $this->assertFalse(Route::has('mobile.otp.send'));
        $this->assertFalse(Route::has('mobile.otp.verify'));

        $this->markTestSkipped('Mobile OTP verification lifecycle is not implemented in the current codebase.');
    }
}
