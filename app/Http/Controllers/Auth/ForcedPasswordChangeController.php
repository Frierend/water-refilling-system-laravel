<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Security\PasswordPolicy;
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
            $user->applyLifecycleLock('temporary_password_expired');

            Log::channel('security')->warning('security.lifecycle.locked_forced_password_change_denied', [
                'category' => 'security',
                'user_id' => $user->id,
                'email' => $user->email,
                'lock_reason' => $user->lifecycle_lock_reason,
                'temp_password_expires_at' => $user->temp_password_expires_at?->toDateTimeString(),
                'failed_attempts' => (int) $user->failed_attempts,
                'locked_until' => $user->locked_until?->toDateTimeString(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Log::channel('security')->warning('security.account.lifecycle.expiry.locked', [
                'user_id' => $user->id,
                'email' => $user->email,
                'expired_at' => $user->temp_password_expires_at?->toDateTimeString(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'source' => 'forced_password_change.show',
            ]);

            return redirect()->route('password.request')->with('status', $user->lifecycleLockMessage());
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
            $user->applyLifecycleLock('temporary_password_expired');

            Log::channel('security')->warning('security.lifecycle.locked_forced_password_change_denied', [
                'category' => 'security',
                'user_id' => $user->id,
                'email' => $user->email,
                'lock_reason' => $user->lifecycle_lock_reason,
                'temp_password_expires_at' => $user->temp_password_expires_at?->toDateTimeString(),
                'failed_attempts' => (int) $user->failed_attempts,
                'locked_until' => $user->locked_until?->toDateTimeString(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Log::channel('security')->warning('security.account.lifecycle.expiry.locked', [
                'user_id' => $user->id,
                'email' => $user->email,
                'expired_at' => $user->temp_password_expires_at?->toDateTimeString(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'source' => 'forced_password_change.update',
            ]);

            return redirect()->route('password.request')->with('status', $user->lifecycleLockMessage());
        }

        $validated = $request->validate([
            'password' => PasswordPolicy::rules(),
        ], [
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'The password does not meet the required security policy.',
        ]);

        if (Hash::check($validated['password'], (string) $user->password)) {
            Log::channel('security')->warning('security.forced_password_change.failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => 'new_password_matches_current',
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

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
        $user->clearLifecycleLock();

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
}
