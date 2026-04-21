<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => $this->temporaryPasswordExpiredMessage(),
            ]);
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
