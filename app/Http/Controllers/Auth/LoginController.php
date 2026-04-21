<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';  // Changed from RouteServiceProvider::HOME to '/dashboard'

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $maxAttempts = (int) config('auth.login_lockout.max_attempts', 5);
        $lockMinutes = (int) config('auth.login_lockout.lock_minutes', 5);
        $user = User::where('email', $credentials['email'])->first();

        if ($user !== null && $user->locked_until !== null) {
            if ($user->locked_until->isFuture()) {
                $remainingMinutes = max(1, now()->diffInMinutes($user->locked_until));

                Log::channel('audit')->warning('auth.login.locked_blocked', [
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

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

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

                    return back()->withErrors([
                        'email' => 'Your temporary password has expired. Please contact the administrator for assistance.',
                    ])->onlyInput('email');
                }

                Log::channel('audit')->info('security.forced_password_change.triggered', [
                    'user_id' => $authenticatedUser->id,
                    'email' => $authenticatedUser->email,
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]);

                return redirect()->route('password.force.change');
            }

            // Redirect based on user role
            $user = Auth::user();
            if ($user->isDelivery()) {
                return redirect()->route('deliveries.index');
            } elseif ($user->isHelper()) {
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

            Log::channel('audit')->warning('auth.login.failed', [
                'user_id' => $user->id,
                'email' => $credentials['email'],
                'failed_attempts' => $failedAttempts,
                'attempts_remaining' => max(0, $maxAttempts - $failedAttempts),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            if ($isLocked) {
                Log::channel('audit')->warning('auth.account.locked', [
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
            Log::channel('audit')->warning('auth.login.failed', [
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

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
