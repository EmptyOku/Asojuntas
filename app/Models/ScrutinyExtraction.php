<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrutinyExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'scrutiny_record_id',
        'scrutiny_record_file_id',
        'based_on_extraction_id',
        'created_by_user_id',
        'source_type',
        'engine_name',
        'engine_version',
        'confidence_score',
        'status',
        'round_number',
        'raw_payload',
        'normalized_payload',
        'notes',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'round_number' => 'integer',
        'raw_payload' => 'array',
        'normalized_payload' => 'array',
    ];

    public function scrutinyRecord(): BelongsTo
    {
        return $this->belongsTo(ScrutinyRecord::class);
    }

    public function scrutinyRecordFile(): BelongsTo
    {
        return $this->belongsTo(ScrutinyRecordFile::class);
    }

    public function basedOnExtraction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'based_on_extraction_id');
    }

    public function derivedExtractions(): HasMany
    {
        return $this->hasMany(self::class, 'based_on_extraction_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ScrutinyReview::class);
    }

    public function blockResults(): HasMany
    {
        return $this->hasMany(ScrutinyBlockResult::class);
    }
}
