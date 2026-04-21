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
        'password',
        'role',
        'email_verified_at',
        'failed_attempts',
        'locked_until',
        'must_change_password',
        'password_changed_at',
        'temp_password_expires_at',
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
        'locked_until' => 'datetime',
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'temp_password_expires_at' => 'datetime',
        'mfa_enabled' => 'boolean',
    ];

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
