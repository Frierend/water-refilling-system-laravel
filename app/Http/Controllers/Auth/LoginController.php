<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    protected $redirectTo = '/dashboard';

    public function __construct(private readonly RecaptchaService $recaptchaService)
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'g-recaptcha-response' => ['nullable', 'string'],
        ]);

        if (! $this->recaptchaService->verify($request->input('g-recaptcha-response'), $request->ip())) {
            Log::channel('security')->warning('security.recaptcha.login.failed', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            return back()->withErrors([
                'email' => 'reCAPTCHA verification failed. Please try again.',
            ])->onlyInput('email');
        }

        $maxAttempts = (int) config('auth.login_lockout.max_attempts', 5);
        $lockMinutes = (int) config('auth.login_lockout.lock_minutes', 5);
        $user = User::where('email', $credentials['email'])->first();

        if ($user !== null && $user->locked_until !== null) {
            if ($user->locked_until->isFuture()) {
                $remainingMinutes = max(1, now()->diffInMinutes($user->locked_until));

                Log::channel('security')->warning('auth.login.locked_blocked', [
                    'user_id' => $user->id,
                    'email' => $credentials['email'],
                    'locked_until' => $user->locked_until->toDateTimeString(),
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]);

                return back()->withErrors([
                    'email' => "Your account is temporarily locked. Please try again in {$remainingMinutes} minute(s).",
                ])->onlyInput('email');
            }

            $user->forceFill([
                'failed_attempts' => 0,
                'locked_until' => null,
            ])->save();
        }

        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $request->filled('remember'))) {
            $request->session()->regenerate();
            $request->session()->forget('mfa_passed_for_user_id');

            $authenticatedUser = Auth::user();
            if (
                $authenticatedUser !== null
                && ((int) $authenticatedUser->failed_attempts > 0 || $authenticatedUser->locked_until !== null)
            ) {
                $authenticatedUser->forceFill([
                    'failed_attempts' => 0,
                    'locked_until' => null,
                ])->save();
            }

            if ($authenticatedUser !== null && $authenticatedUser->must_change_password) {
                if (
                    $authenticatedUser->temp_password_expires_at !== null
                    && $authenticatedUser->temp_password_expires_at->isPast()
                ) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    Log::channel('security')->warning('security.account.lifecycle.expiry.locked', [
                        'user_id' => $authenticatedUser->id,
                        'email' => $authenticatedUser->email,
                        'expired_at' => $authenticatedUser->temp_password_expires_at?->toDateTimeString(),
                        'ip' => $request->ip(),
                        'user_agent' => (string) $request->userAgent(),
                    ]);

                    return back()->withErrors([
                        'email' => 'Your temporary password has expired. Please contact the owner for assistance.',
                    ])->onlyInput('email');
                }

                Log::channel('security')->info('security.forced_password_change.triggered', [
                    'user_id' => $authenticatedUser->id,
                    'email' => $authenticatedUser->email,
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]);

                return redirect()->route('password.force.change');
            }

            if ($authenticatedUser !== null && ! $authenticatedUser->hasVerifiedEmail()) {
                $authenticatedUser->sendEmailVerificationNotification();

                return redirect()->route('verification.notice');
            }

            if ($authenticatedUser !== null && $authenticatedUser->mfa_enabled) {
                return redirect()->route('mfa.challenge');
            }

            if ($authenticatedUser?->isDelivery()) {
                return redirect()->route('deliveries.index');
            }

            if ($authenticatedUser?->isHelper()) {
                return redirect()->route('orders.create');
            }

            return redirect()->intended($this->redirectTo);
        }

        if ($user !== null) {
            $failedAttempts = ((int) $user->failed_attempts) + 1;
            $updates = ['failed_attempts' => $failedAttempts];
            $isLocked = $failedAttempts >= $maxAttempts;

            if ($isLocked) {
                $updates['locked_until'] = now()->addMinutes($lockMinutes);
            }

            $user->forceFill($updates)->save();

            Log::channel('security')->warning('auth.login.failed', [
                'user_id' => $user->id,
                'email' => $credentials['email'],
                'failed_attempts' => $failedAttempts,
                'attempts_remaining' => max(0, $maxAttempts - $failedAttempts),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            if ($isLocked) {
                Log::channel('security')->warning('auth.account.locked', [
                    'user_id' => $user->id,
                    'email' => $credentials['email'],
                    'failed_attempts' => $failedAttempts,
                    'lock_minutes' => $lockMinutes,
                    'locked_until' => $user->locked_until?->toDateTimeString(),
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]);

                return back()->withErrors([
                    'email' => 'Your account has been temporarily locked due to multiple failed login attempts.',
                ])->onlyInput('email');
            }
        } else {
            Log::channel('security')->warning('auth.login.failed', [
                'user_id' => null,
                'email' => $credentials['email'],
                'failed_attempts' => null,
                'attempts_remaining' => null,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
