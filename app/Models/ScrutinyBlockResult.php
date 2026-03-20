<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrutinyBlockResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'scrutiny_record_id',
        'election_id',
        'election_block_id',
        'slate_block_id',
        'scrutiny_extraction_id',
        'votes',
        'source_type',
        'status',
        'confidence_score',
        'notes',
    ];

    protected $casts = [
        'votes' => 'integer',
        'confidence_score' => 'decimal:2',
    ];

    public function scrutinyRecord(): BelongsTo
    {
        return $this->belongsTo(ScrutinyRecord::class);
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

    public function scrutinyExtraction(): BelongsTo
    {
        return $this->belongsTo(ScrutinyExtraction::class);
    }

    // If result typing is needed (slate/blank/null/unmarked), keep it at service level
    // since this table currently has no explicit result_type column.
}
