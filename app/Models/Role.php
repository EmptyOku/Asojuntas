<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    // Forzamos a Spatie a trabajar siempre bajo el guard 'web'
    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function legacyPermissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->using(RolePermission::class)
            ->withPivot(['id', 'assigned_at', 'assigned_by'])
            ->withTimestamps();
    }
}