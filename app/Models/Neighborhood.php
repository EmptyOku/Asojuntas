<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Neighborhood extends Model
{
    use HasFactory;

    protected $fillable = [
        'commune_id',
        'name',
        'code',
    ];

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function persons(): HasMany
    {
        return $this->hasMany(Person::class);
    }



    public function elections(): HasMany
    {
        return $this->hasMany(Election::class);
    }
}
