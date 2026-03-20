<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'person_id',
        'slate_block_id',
        'election_block_position_id',
        'ballot_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function slateBlock(): BelongsTo
    {
        return $this->belongsTo(SlateBlock::class);
    }

    public function electionBlockPosition(): BelongsTo
    {
        return $this->belongsTo(ElectionBlockPosition::class);
    }

    public function seatAllocations(): HasMany
    {
        return $this->hasMany(SeatAllocation::class);
    }
}
