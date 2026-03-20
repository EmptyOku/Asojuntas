<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slate extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function slateBlocks(): HasMany
    {
        return $this->hasMany(SlateBlock::class);
    }

    public function candidateDrafts(): HasMany
    {
        return $this->hasMany(CandidateDraft::class);
    }
}
