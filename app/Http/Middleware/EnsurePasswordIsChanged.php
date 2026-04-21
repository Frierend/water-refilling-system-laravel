<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
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

        if ($request->routeIs('password.force.change') || $request->routeIs('password.force.update')) {
            return $next($request);
        }

        return redirect()->route('password.force.change')
            ->with('info', 'You must change your temporary password before accessing the system.');
    }

    private function isTemporaryPasswordExpired(object $user): bool
    {
        return $user->temp_password_expires_at !== null
            && $user->temp_password_expires_at->isPast();
    }
}
