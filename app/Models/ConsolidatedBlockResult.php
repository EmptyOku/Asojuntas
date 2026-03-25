<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidatedBlockResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'consolidation_run_id',
        'election_id',
        'election_block_id',
        'slate_block_id',
        'total_votes',
        'vote_percentage',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_votes' => 'integer',
        'vote_percentage' => 'decimal:4',
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

    public function slateBlock(): BelongsTo
    {
        return $this->belongsTo(SlateBlock::class);
    }

}
