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
            $user->applyLifecycleLock('temporary_password_expired');

            Log::channel('security')->warning('security.lifecycle.locked_session_denied', [
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

            return redirect()->route('password.request')->with('status', $user->lifecycleLockMessage());
        }

        if ($request->routeIs('password.force.change') || $request->routeIs('password.force.update')) {
            return $next($request);
        }

        Log::channel('security')->info('security.forced_password_change.triggered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'route' => optional($request->route())->getName(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'source' => 'middleware.password.changed',
        ]);

        return redirect()->route('password.force.change')
            ->with('info', 'You must change your temporary password before accessing the system.');
    }

    private function isTemporaryPasswordExpired(object $user): bool
    {
        return $user->temp_password_expires_at !== null
            && $user->temp_password_expires_at->isPast();
    }

    private function temporaryPasswordExpiredMessage(): string
    {
        $ownerName = trim((string) config('security.owner.name', 'owner'));
        $ownerName = $ownerName !== '' ? $ownerName : 'owner';

        $ownerEmail = trim((string) config('security.owner.email', ''));
        if ($ownerEmail !== '') {
            return sprintf('Your temporary password has expired. Please contact %s at %s for assistance.', $ownerName, $ownerEmail);
        }

        return sprintf('Your temporary password has expired. Please contact the %s for assistance.', $ownerName);
    }
}
