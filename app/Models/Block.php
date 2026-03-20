<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function electionBlocks(): HasMany
    {
        return $this->hasMany(ElectionBlock::class);
    }

    public function elections(): BelongsToMany
    {
        return $this->belongsToMany(Election::class, 'election_blocks')
            ->using(ElectionBlock::class)
            ->withPivot(['id', 'is_active'])
            ->withTimestamps();
    }

    public function electionBlockPositions(): HasMany
    {
        return $this->hasMany(ElectionBlockPosition::class);
    }

    public function candidateDrafts(): HasMany
    {
        return $this->hasMany(CandidateDraft::class);
    }
}
