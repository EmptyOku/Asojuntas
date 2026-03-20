<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrutinyElectedPerson extends Model
{
    use HasFactory;

    protected $table = 'scrutiny_elected_people';

    protected $fillable = [
        'scrutiny_record_id',
        'election_id',
        'election_block_id',
        'election_block_position_id',
        'document_type_id',
        'person_id',
        'document_number',
        'first_name',
        'middle_name',
        'last_name',
        'second_last_name',
        'phone',
        'email',
        'signature_path',
        'source_type',
        'confidence_score',
        'review_status',
        'notes',
    ];

    protected $casts = [
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

    public function electionBlockPosition(): BelongsTo
    {
        return $this->belongsTo(ElectionBlockPosition::class);
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
