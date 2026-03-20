<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionBlockPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_block_id',
        'block_id',
        'position_id',
        'vacancies',
        'is_active',
    ];

    protected $casts = [
        'vacancies' => 'integer',
        'is_active' => 'boolean',
    ];

    public function electionBlock(): BelongsTo
    {
        return $this->belongsTo(ElectionBlock::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function scrutinyElectedPeople(): HasMany
    {
        return $this->hasMany(ScrutinyElectedPerson::class);
    }

    public function seatAllocations(): HasMany
    {
        return $this->hasMany(SeatAllocation::class);
    }
}
