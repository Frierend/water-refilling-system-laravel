<?php
// app/Models/User.php
namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, MustVerifyEmailTrait;

    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'password',
        'role',
        'email_verified_at',
        'mfa_secret',
        'mfa_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'mobile_verified_at' => 'datetime',
        'locked_until' => 'datetime',
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'temp_password_expires_at' => 'datetime',
        'lifecycle_locked_at' => 'datetime',
        'mfa_enabled' => 'boolean',
    ];

    public function applyLifecycleLock(string $reason): void
    {
        $this->forceFill([
            'lifecycle_locked_at' => now(),
            'lifecycle_lock_reason' => $reason,
        ])->save();
    }

    public function clearLifecycleLock(): void
    {
        if ($this->lifecycle_locked_at === null && $this->lifecycle_lock_reason === null) {
            return;
        }

        $this->forceFill([
            'lifecycle_locked_at' => null,
            'lifecycle_lock_reason' => null,
        ])->save();
    }

    public function lifecycleLockMessage(): string
    {
        if ($this->isOwner()) {
            return 'Your temporary password has expired. Recover your account using Forgot Password and the owner email path.';
        }

        return 'Your temporary password has expired. Please contact the owner for assistance.';
    }

    public function isOwner()
    {
        return $this->role === 'owner';
    }

    public function isAdmin()
    {
        return $this->isOwner();
    }

    public function isDelivery()
    {
        return $this->role === 'delivery';
    }

    public function isHelper()
    {
        return $this->role === 'helper';
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Order::class, 'delivery_user_id');
    }
}
