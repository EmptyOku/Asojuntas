<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateDraftFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'capture_batch_uuid',
        'election_id',
        'uploaded_by_user_id',
        'original_name',
        'storage_path',
        'mime_type',
        'file_size',
        'hash',
        'page_number',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'page_number' => 'integer',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
