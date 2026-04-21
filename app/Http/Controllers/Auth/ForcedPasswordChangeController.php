<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ForcedPasswordChangeController extends Controller
{
    /**
     * Show the forced password change page.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        if ($this->isTemporaryPasswordExpired($user)) {
            Log::channel('security')->warning('security.temporary_password.expired_recovery_required', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'recovery_route' => route('password.request'),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('password.request')->with('status',
                'Your temporary password has expired. Recover access via Forgot Password, then verify your email after signing in.'
            );
        }

        return view('auth.force-password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        if ($this->isTemporaryPasswordExpired($user)) {
            Log::channel('security')->warning('security.temporary_password.expired_recovery_required', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'recovery_route' => route('password.request'),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('password.request')->with('status',
                'Your temporary password has expired. Recover access via Forgot Password, then verify your email after signing in.'
            );
        }

        $validated = $request->validate([
            'password' => $this->passwordRules(),
        ], [
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'The password does not meet the required security policy.',
            'password.regex' => 'The password does not meet the required security policy.',
        ]);

        if (Hash::check($validated['password'], (string) $user->password)) {
            return back()->withErrors([
                'password' => 'Please choose a different password.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'temp_password_expires_at' => null,
        ])->save();

        Log::channel('security')->info('security.password.changed.success', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Password changed successfully.');
    }

    private function isTemporaryPasswordExpired(object $user): bool
    {
        return $user->temp_password_expires_at !== null
            && $user->temp_password_expires_at->isPast();
    }

    /**
     * @return list<string>
     */
    private function passwordRules(): array
    {
        $rules = [
            'required',
            'string',
            'confirmed',
            'min:' . max(1, (int) config('security.password_policy.min_length', 8)),
        ];

        if ((bool) config('security.password_policy.require_uppercase', true)) {
            $rules[] = 'regex:/[A-Z]/';
        }

        if ((bool) config('security.password_policy.require_lowercase', true)) {
            $rules[] = 'regex:/[a-z]/';
        }

        if ((bool) config('security.password_policy.require_numbers', true)) {
            $rules[] = 'regex:/[0-9]/';
        }

        if ((bool) config('security.password_policy.require_symbols', true)) {
            $rules[] = 'regex:/[^A-Za-z0-9]/';
        }

        return $rules;
    }
}
