<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Security\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Show the owner user creation form.
     */
    public function create(): View
    {
        return view('users.create-user-account');
    }

    /**
     * Store a newly created user with a temporary password.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => 'required|in:delivery,helper',
            'temporary_password' => PasswordPolicy::rules(requireConfirmation: false, required: false),
        ]);

        $temporaryPassword = (string) ($validated['temporary_password'] ?? $this->generateTemporaryPassword());
        $expiryHours = max(1, (int) config('security.temporary_password.expires_hours', 24));

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => $validated['role'],
        ]);

        $lifecycleAttributes = [
            'must_change_password' => true,
            'password_changed_at' => null,
            'temp_password_expires_at' => now()->addHours($expiryHours),
        ];

        // Keep compatibility for later email verification feature.
        if (Schema::hasColumn('users', 'email_verified_at')) {
            $lifecycleAttributes['email_verified_at'] = null;
        }

        $user->forceFill($lifecycleAttributes)->save();

        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        Log::channel('security')->info('security.user.created_by_owner', [
            'actor_id' => auth()->id(),
            'user_id' => $user->id,
            'role' => $user->role,
            'email' => $user->email,
        ]);

        Log::channel('security')->info('security.temporary_password.issued', [
            'actor_id' => auth()->id(),
            'user_id' => $user->id,
            'temp_password_expires_at' => $user->temp_password_expires_at?->toDateTimeString(),
        ]);

        return redirect()->route('users.create')
            ->with('success', 'User account created successfully. Temporary password is shown below once.')
            ->with('temporary_password', $temporaryPassword)
            ->with('temporary_password_user_email', $user->email);
    }

    private function generateTemporaryPassword(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = method_exists(Str::class, 'password')
                ? Str::password(20, true, true, true, false)
                : Str::random(24) . '!aA1';

            if (PasswordPolicy::isCompliant($candidate)) {
                return $candidate;
            }
        }

        // Final deterministic fallback that still satisfies the minimum policy.
        return 'Temp!Aa1' . Str::random(20);
    }
}
