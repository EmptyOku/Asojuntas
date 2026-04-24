<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CandidateDraft extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'election_id',
        'block_id',
        'position_id',
        'slate_id',
        'slate_block_id',
        'capture_batch_uuid',
        'document_type_id',
        'person_id',
        'document_number',
        'first_name',
        'middle_name',
        'last_name',
        'second_last_name',
        'phone',
        'email',
        'source_type',
        'confidence_score',
        'review_status',
        'is_processed',
        'processed_at',
        'notes',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function slate(): BelongsTo
    {
        return $this->belongsTo(Slate::class);
    }

    public function slateBlock(): BelongsTo
    {
        return $this->belongsTo(SlateBlock::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
