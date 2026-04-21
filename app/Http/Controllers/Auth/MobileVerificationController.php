<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MobileVerificationOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MobileVerificationController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.mobile-verification', [
            'mobileNumber' => (string) old('mobile_number', (string) $request->user()?->mobile_number),
        ]);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mobile_number' => ['required', 'string', 'regex:/^\+?[1-9]\d{9,14}$/'],
        ]);

        $user = $request->user();
        $mobileNumber = $this->normalizeMobile($validated['mobile_number']);
        $plainOtp = (string) random_int(100000, 999999);

        MobileVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('mobile_number', $mobileNumber)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        MobileVerificationOtp::create([
            'user_id' => $user->id,
            'mobile_number' => $mobileNumber,
            'otp_hash' => Hash::make($plainOtp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        Log::channel('security')->info('security.mobile_verification.otp_generated', [
            'user_id' => $user->id,
            'mobile_number' => $mobileNumber,
            'ip' => $request->ip(),
        ]);

        $response = redirect()->route('mobile.verification.notice')
            ->with('status', 'A verification code has been generated for your mobile number.');

        if (app()->environment('local')) {
            $response->with('local_mobile_otp_preview', [
                'otp' => $plainOtp,
                'mobile_number' => $mobileNumber,
                'expires_in_minutes' => 5,
            ]);
        }

        return $response;
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mobile_number' => ['required', 'string', 'regex:/^\+?[1-9]\d{9,14}$/'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();
        $mobileNumber = $this->normalizeMobile($validated['mobile_number']);

        $challenge = MobileVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('mobile_number', $mobileNumber)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $challenge || $challenge->expires_at->isPast()) {
            return back()->withErrors([
                'otp_code' => 'Verification code is missing or expired. Request a new code.',
            ])->withInput();
        }

        if ($challenge->attempts >= 5) {
            return back()->withErrors([
                'otp_code' => 'Too many failed attempts. Request a new verification code.',
            ])->withInput();
        }

        if (! Hash::check($validated['otp_code'], $challenge->otp_hash)) {
            $challenge->increment('attempts');

            Log::channel('security')->warning('security.mobile_verification.otp_failed', [
                'user_id' => $user->id,
                'mobile_number' => $mobileNumber,
                'attempts' => $challenge->attempts + 1,
                'ip' => $request->ip(),
            ]);

            return back()->withErrors([
                'otp_code' => 'Invalid verification code.',
            ])->withInput();
        }

        $challenge->forceFill([
            'consumed_at' => now(),
        ])->save();

        $user->forceFill([
            'mobile_number' => $mobileNumber,
            'mobile_verified_at' => now(),
        ])->save();

        Log::channel('security')->info('security.mobile_verification.verified', [
            'user_id' => $user->id,
            'mobile_number' => $mobileNumber,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('dashboard')->with('success', 'Mobile number verified successfully.');
    }

    private function normalizeMobile(string $mobile): string
    {
        return preg_replace('/\s+|-|\(|\)/', '', trim($mobile)) ?? trim($mobile);
    }
}
