<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'person_id',
        'username',
        'email',
        'password',
        'email_verified_at',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function assignedUserRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function assignedRolesByUser(): HasMany
    {
        return $this->hasMany(UserRole::class, 'assigned_by');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->using(UserRole::class)
            ->withPivot(['id', 'assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function createdScrutinyRecords(): HasMany
    {
        return $this->hasMany(ScrutinyRecord::class, 'created_by_user_id');
    }

    public function uploadedScrutinyRecordFiles(): HasMany
    {
        return $this->hasMany(ScrutinyRecordFile::class, 'uploaded_by_user_id');
    }

    public function createdScrutinyExtractions(): HasMany
    {
        return $this->hasMany(ScrutinyExtraction::class, 'created_by_user_id');
    }

    public function reviewedScrutinyReviews(): HasMany
    {
        return $this->hasMany(ScrutinyReview::class, 'reviewed_by_user_id');
    }

    public function consolidationRunsCreated(): HasMany
    {
        return $this->hasMany(ConsolidationRun::class, 'created_by_user_id');
    }

    public function rolePermissionsAssigned(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'assigned_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
