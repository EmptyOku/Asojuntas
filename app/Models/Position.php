<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'block_id',
        'name',
        'code',
        'order_number',
        'description',
        'is_active',
    ];

    protected $casts = [
        'order_number' => 'integer',
        'is_active' => 'boolean',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function electionBlockPositions(): HasMany
    {
        return $this->hasMany(ElectionBlockPosition::class);
    }

    public function candidateDrafts(): HasMany
    {
        return $this->hasMany(CandidateDraft::class);
    }
}
