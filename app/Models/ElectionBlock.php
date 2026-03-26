<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ElectionBlock extends Pivot
{
    use HasFactory;

    protected $table = 'election_blocks';

    public $incrementing = true;

    protected $fillable = [
        'election_id',
        'block_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }


    public function electionBlockPositions(): HasMany
    {
        return $this->hasMany(ElectionBlockPosition::class);
    }

    public function slateBlocks(): HasMany
    {
        return $this->hasMany(SlateBlock::class);
    }

    public function scrutinyBlockResults(): HasMany
    {
        return $this->hasMany(ScrutinyBlockResult::class);
    }

    public function scrutinyElectedPeople(): HasMany
    {
        return $this->hasMany(ScrutinyElectedPerson::class);
    }

    public function consolidatedBlockResults(): HasMany
    {
        return $this->hasMany(ConsolidatedBlockResult::class);
    }

    public function seatAllocations(): HasMany
    {
        return $this->hasMany(SeatAllocation::class);
    }
}
