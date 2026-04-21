<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaIsVerified
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->mfa_enabled) {
            return $next($request);
        }

        if ($request->routeIs('mfa.challenge') || $request->routeIs('mfa.challenge.verify') || $request->routeIs('logout')) {
            return $next($request);
        }

        if ((int) $request->session()->get('mfa_passed_for_user_id') === (int) $user->id) {
            return $next($request);
        }

        return redirect()->route('mfa.challenge');
    }
}
