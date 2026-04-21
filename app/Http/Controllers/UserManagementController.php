<?php

namespace App\Http\Controllers;

use App\Models\User;
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
        ]);

        $temporaryPassword = $this->generateTemporaryPassword();
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

        Log::channel('audit')->info('security.user.created_by_owner', [
            'actor_id' => auth()->id(),
            'user_id' => $user->id,
            'role' => $user->role,
            'email' => $user->email,
        ]);

        Log::channel('audit')->info('security.temporary_password.issued', [
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
        if (method_exists(Str::class, 'password')) {
            return Str::password(20, true, true, true, false);
        }

        // Fallback keeps entropy high and includes mixed characters.
        return Str::random(24) . '!A1';
    }
}
