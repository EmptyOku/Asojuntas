<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrutinyRecordFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'scrutiny_record_id',
        'uploaded_by_user_id',
        'file_type',
        'original_name',
        'storage_path',
        'mime_type',
        'file_size',
        'hash',
        'page_number',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'page_number' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function scrutinyRecord(): BelongsTo
    {
        return $this->belongsTo(ScrutinyRecord::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(ScrutinyExtraction::class);
    }
}
