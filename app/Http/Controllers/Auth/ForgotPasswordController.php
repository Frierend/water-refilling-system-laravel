<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Log::channel('security')->info('security.password.forgot.requested', [
            'email' => $validated['email'],
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $status = Password::sendResetLink(['email' => $validated['email']]);

        if ($status === Password::RESET_LINK_SENT) {
            Log::channel('security')->info('security.password.forgot.sent', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
            ]);
        } else {
            Log::channel('security')->warning('security.password.forgot.failed', [
                'email' => $validated['email'],
                'status' => $status,
                'ip' => $request->ip(),
            ]);
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
