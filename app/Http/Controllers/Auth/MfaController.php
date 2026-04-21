<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function __construct(private readonly TotpService $totpService)
    {
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->user()?->mfa_enabled) {
            Log::channel('security')->warning('security.mfa.challenge.blocked', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('dashboard');
        }

        Log::channel('security')->info('security.mfa.challenge.requested', [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        return view('auth.mfa-challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        if (! $user || ! $user->mfa_enabled || ! is_string($user->mfa_secret)) {
            return redirect()->route('dashboard');
        }

        $secret = Crypt::decryptString($user->mfa_secret);

        if (! $this->totpService->verifyCode($secret, $validated['code'])) {
            Log::channel('security')->warning('security.mfa.challenge.failed', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $request->session()->put('mfa_passed_for_user_id', $user->id);

        Log::channel('security')->info('security.mfa.challenge.passed', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function setup(Request $request): View
    {
        $user = $request->user();
        $secret = $this->totpService->generateSecret();
        $request->session()->put('mfa_setup_secret', $secret);

        $otpauth = $this->totpService->provisioningUri($user->email, $secret);

        Log::channel('security')->info('security.otp.setup.issued', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return view('auth.mfa-setup', [
            'secret' => $secret,
            'otpauth' => $otpauth,
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $secret = (string) $request->session()->get('mfa_setup_secret', '');
        if ($secret === '') {
            Log::channel('security')->warning('security.otp.setup.expired', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('mfa.setup')->withErrors(['code' => 'MFA setup expired. Start again.']);
        }

        if (! $this->totpService->verifyCode($secret, $validated['code'])) {
            Log::channel('security')->warning('security.otp.setup.verification.failed', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $user = $request->user();
        $user->forceFill([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_enabled' => true,
        ])->save();

        $request->session()->put('mfa_passed_for_user_id', $user->id);
        $request->session()->forget('mfa_setup_secret');

        Log::channel('security')->info('security.mfa.enabled', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('dashboard')->with('success', 'MFA enabled successfully.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($validated['password'], (string) $request->user()->password)) {
            Log::channel('security')->warning('security.mfa.disable.failed', [
                'user_id' => $request->user()?->id,
                'reason' => 'invalid_password',
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['password' => 'Invalid current password.']);
        }

        $user = $request->user();
        $user->forceFill([
            'mfa_secret' => null,
            'mfa_enabled' => false,
        ])->save();

        $request->session()->forget('mfa_passed_for_user_id');

        Log::channel('security')->info('security.mfa.disabled', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('dashboard')->with('success', 'MFA disabled.');
    }
}
