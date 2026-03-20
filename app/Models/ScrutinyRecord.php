<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrutinyRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'polling_table_id',
        'created_by_user_id',
        'record_number',
        'record_date',
        'record_time',
        'source_type',
        'status',
        'quorum_attendees',
        'total_attendees',
        'observations',
        'metadata',
    ];

    protected $casts = [
        'record_date' => 'date',
        'record_time' => 'datetime:H:i:s',
        'quorum_attendees' => 'integer',
        'total_attendees' => 'integer',
        'metadata' => 'array',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function pollingTable(): BelongsTo
    {
        return $this->belongsTo(PollingTable::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ScrutinyRecordFile::class);
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(ScrutinyExtraction::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ScrutinyReview::class);
    }

    public function blockResults(): HasMany
    {
        return $this->hasMany(ScrutinyBlockResult::class);
    }

    public function electedPeople(): HasMany
    {
        return $this->hasMany(ScrutinyElectedPerson::class);
    }
}
