<?php
// app/Http/Middleware/CheckRole.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            Log::channel('security')->warning('security.authorization.denied', [
                'user_id' => $request->user()?->id,
                'route' => optional($request->route())->getName(),
                'path' => $request->path(),
                'required_roles' => $roles,
                'actual_role' => $request->user()?->role,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
