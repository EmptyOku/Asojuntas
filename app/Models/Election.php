<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    // Eliminar marca la fila (deleted_at) en vez de borrarla: se puede restaurar.
    use SoftDeletes;

    use HasFactory;

    protected $fillable = [
        'neighborhood_id',
        'name',
        'code',
        'election_date',
        'period_year',
        'is_active',
        'closed_at',
        'description',
    ];

    protected $casts = [
        'election_date' => 'date',
        'period_year' => 'integer',
        'is_active' => 'boolean',
        'closed_at' => 'datetime',
    ];

    /**
     * La hora de cierre se anota sola: al cerrar (desde Geografía, el Panel o
     * donde sea) queda la hora; al reabrir se borra.
     */
    protected static function booted(): void
    {
        static::saving(function (Election $election): void {
            if ($election->isDirty('is_active') || ! $election->exists) {
                $election->closed_at = $election->is_active ? null : ($election->closed_at ?? now());
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function pollingTables(): HasMany
    {
        return $this->hasMany(PollingTable::class);
    }

    public function electionBlocks(): HasMany
    {
        return $this->hasMany(ElectionBlock::class);
    }

    public function blocks(): BelongsToMany
    {
        return $this->belongsToMany(Block::class, 'election_blocks')
            ->using(ElectionBlock::class)
            ->withPivot(['id', 'is_active'])
            ->withTimestamps();
    }

    public function slates(): HasMany
    {
        return $this->hasMany(Slate::class);
    }

    public function slateBlocks(): HasMany
    {
        return $this->hasMany(SlateBlock::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }



    public function candidateDrafts(): HasMany
    {
        return $this->hasMany(CandidateDraft::class);
    }

    public function scrutinyRecords(): HasMany
    {
        return $this->hasMany(ScrutinyRecord::class);
    }

    public function scrutinyBlockResults(): HasMany
    {
        return $this->hasMany(ScrutinyBlockResult::class);
    }

    public function scrutinyElectedPeople(): HasMany
    {
        return $this->hasMany(ScrutinyElectedPerson::class);
    }

    public function consolidationRuns(): HasMany
    {
        return $this->hasMany(ConsolidationRun::class);
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
