<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute; // IMPORTANTE PARA LOS ACCESSORS

class Neighborhood extends Model
{
    use HasFactory;

    protected $fillable = [
        'commune_id',
        'name',
        'code',
        'type',
        'source_name',
        'is_verified',
        'notes',
    ];

    /**
     * ATENCIÓN: Esto obliga a Laravel a inyectar estos dos campos
     * en el JSON que le manda a Vue (Axios).
     */
    protected $appends = [
        'president_name',
        'vicepresident_name'
    ];

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

    // --- ACCESSORS (Campos Virtuales para Vue) ---

    protected function presidentName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getWinningDignitary('DIR_PRES', 'DIR'),
        );
    }

    protected function vicepresidentName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getWinningDignitary('DIR_VICE', 'DIR'),
        );
    }

    // --- LÓGICA INTERNA DE ESCRUTINIO REAL ---

    /**
     * Calcula quién ganó buscando la plancha con más votos en las actas.
     */
    private function getWinningDignitary($positionCode, $blockCode)
    {
        // 1. Buscar la elección activa más reciente de este barrio
        $election = $this->elections()->where('is_active', true)->latest('election_date')->first();
        if (!$election) return null;

        // 2. Buscar el bloque de plancha GANADOR (El que tiene más votos en scrutiny_block_results)
        $winningResult = \App\Models\ScrutinyBlockResult::where('election_id', $election->id)
            ->whereHas('electionBlock.block', function($query) use ($blockCode) {
                $query->where('code', $blockCode);
            })
            ->orderByDesc('votes') // Ordenamos de mayor a menor cantidad de votos
            ->first(); // Tomamos el primero (el ganador)

        if (!$winningResult || !$winningResult->slate_block_id) {
            return null; // Si no hay actas escrutadas o hubo empate a 0, no hay ganador
        }

        // 3. Buscar al candidato de ESA plancha ganadora que tenga el cargo buscado (ej. DIR_PRES)
        $winner = \App\Models\Candidate::where('election_id', $election->id)
            ->where('slate_block_id', $winningResult->slate_block_id)
            ->whereHas('electionBlockPosition.position', function($query) use ($positionCode) {
                $query->where('code', $positionCode);
            })
            ->with('person') // Traemos a la persona real
            ->first();

        // 4. Devolver el nombre completo
        if ($winner && $winner->person) {
            return trim($winner->person->first_name . ' ' . $winner->person->last_name);
        }

        return null;
    }
}
