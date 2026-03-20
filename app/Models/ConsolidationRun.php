<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsolidationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'created_by_user_id',
        'run_type',
        'status',
        'started_at',
        'finished_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function consolidatedBlockResults(): HasMany
    {
        return $this->hasMany(ConsolidatedBlockResult::class);
    }

    public function seatAllocations(): HasMany
    {
        return $this->hasMany(SeatAllocation::class);
    }
}
