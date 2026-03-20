<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlateBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'slate_id',
        'election_block_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function slate(): BelongsTo
    {
        return $this->belongsTo(Slate::class);
    }

    public function electionBlock(): BelongsTo
    {
        return $this->belongsTo(ElectionBlock::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function candidateDrafts(): HasMany
    {
        return $this->hasMany(CandidateDraft::class);
    }

    public function scrutinyBlockResults(): HasMany
    {
        return $this->hasMany(ScrutinyBlockResult::class);
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
