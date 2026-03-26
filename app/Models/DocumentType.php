<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function persons(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function candidateDrafts(): HasMany
    {
        return $this->hasMany(CandidateDraft::class);
    }

    public function scrutinyElectedPeople(): HasMany
    {
        return $this->hasMany(ScrutinyElectedPerson::class);
    }
}
