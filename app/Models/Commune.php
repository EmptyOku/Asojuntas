<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends Model
{
    // Eliminar marca la fila (deleted_at) en vez de borrarla: se puede restaurar.
    use SoftDeletes;
    use Concerns\RestoresWhenRecreated;

    use HasFactory;

    protected $fillable = [
        'city_id',
        'name',
        'code',
        'boundary',
    ];

    protected $casts = [
        'boundary' => 'array',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function neighborhoods(): HasMany
    {
        return $this->hasMany(Neighborhood::class);
    }
}
