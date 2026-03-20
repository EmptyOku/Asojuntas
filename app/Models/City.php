<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'state_id',
        'name',
        'code',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
