<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrutinyReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'scrutiny_record_id',
        'scrutiny_extraction_id',
        'reviewed_by_user_id',
        'decision',
        'reviewed_at',
        'comments',
        'changes_payload',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'changes_payload' => 'array',
    ];

    public function scrutinyRecord(): BelongsTo
    {
        return $this->belongsTo(ScrutinyRecord::class);
    }

    public function scrutinyExtraction(): BelongsTo
    {
        return $this->belongsTo(ScrutinyExtraction::class);
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
