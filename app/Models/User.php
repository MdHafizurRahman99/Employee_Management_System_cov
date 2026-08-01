<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'auth_source',
        'password',
        'odoo_user_id',
        'odoo_employee_id',
        'odoo_resource_id',
        'odoo_last_synced_at',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'odoo_last_synced_at' => 'datetime',
        'password' => 'hashed',
    ];

    // public function roles()
    // {
    //     return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id')->where('model_type', 'App\Models\User');
    // }
    public function userRoles()
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id')
            ->where('model_type', 'App\Models\User'); // Specify the model type
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class)->orderByDesc('started_at');
    }

    public function isOdooUser(): bool
    {
        return $this->auth_source === 'odoo'
            && ($this->odoo_user_id !== null || $this->odoo_employee_id !== null);
    }

    public function isOdooManager(): bool
    {
        return $this->isOdooUser() && $this->role === 'manager';
    }

    public function isManagerLike(): bool
    {
        if ($this->isOdooManager()) {
            return true;
        }

        if ($this->isOdooUser()) {
            return false;
        }

        if ($this->role === 'admin') {
            return true;
        }

        return $this->hasAnyRole(['Manager', 'Admin', 'Super Admin']);
    }
}
