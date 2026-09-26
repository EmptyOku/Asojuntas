<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Neighborhood extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'commune_id',
        'name',
        'code',
        'type',
        'source_name',
        'is_verified',
        'notes',
        'latitude',
        'longitude',
        'map_order',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'map_order' => 'integer',
    ];

    // Sin $appends: presidente/vicepresidente se calculan en lote en
    // NeighborhoodDirectoryController::toNeighborhoodRow(). Un accessor
    // anexado aqui disparaba ~8 consultas por cada barrio serializado.

    // --- RELACIONES ESTÁNDAR ---

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
