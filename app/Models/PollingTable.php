<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollingTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'name',
        'code',
        'location',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function scrutinyRecords(): HasMany
    {
        return $this->hasMany(ScrutinyRecord::class);
    }
}
