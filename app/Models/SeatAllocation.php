<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'consolidation_run_id',
        'election_id',
        'election_block_id',
        'election_block_position_id',
        'slate_block_id',
        'candidate_id',
        'allocated_seats',
        'allocation_order',
        'allocation_method',
        'notes',
    ];

    protected $casts = [
        'allocated_seats' => 'integer',
        'allocation_order' => 'integer',
    ];

    public function consolidationRun(): BelongsTo
    {
        return $this->belongsTo(ConsolidationRun::class);
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function electionBlock(): BelongsTo
    {
        return $this->belongsTo(ElectionBlock::class);
    }

    public function electionBlockPosition(): BelongsTo
    {
        return $this->belongsTo(ElectionBlockPosition::class);
    }

    public function slateBlock(): BelongsTo
    {
        return $this->belongsTo(SlateBlock::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
