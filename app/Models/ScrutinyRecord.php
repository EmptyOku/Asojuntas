<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScrutinyRecord extends Model
{
    /**
     * Estado que toman los resultados por bloque según la decisión sobre el
     * acta. Solo "approved" y "reviewed" cuentan en el cuociente; un acta
     * rechazada deja sus votos fuera ("rejected").
     */
    /** Estados de un acta cuyos votos ya cuentan en los resultados. */
    public const APPROVED_STATUSES = ['approved', 'reviewed', 'consolidated'];

    /**
     * Con un acta aprobada la votación ya ocurrió: no se admiten planchas nuevas.
     */
    public static function electionHasApprovedActa(int $electionId): bool
    {
        return static::query()
            ->where('election_id', $electionId)
            ->whereIn('status', self::APPROVED_STATUSES)
            ->exists();
    }

    public const BLOCK_STATUS_BY_DECISION = [
        'approved' => 'approved',
        'reviewed' => 'reviewed',
        'rejected' => 'rejected',
    ];

    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'election_id',
        'polling_table_id',
        'created_by_user_id',
        'record_number',
        'record_date',
        'record_time',
        'source_type',
        'status',
        'quorum_attendees',
        'total_attendees',
        'observations',
        'metadata',
    ];

    protected $casts = [
        'record_date' => 'date',
        'record_time' => 'datetime:H:i:s',
        'quorum_attendees' => 'integer',
        'total_attendees' => 'integer',
        'metadata' => 'array',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function pollingTable(): BelongsTo
    {
        return $this->belongsTo(PollingTable::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ScrutinyRecordFile::class);
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(ScrutinyExtraction::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ScrutinyReview::class);
    }

    public function blockResults(): HasMany
    {
        return $this->hasMany(ScrutinyBlockResult::class);
    }

    public function electedPeople(): HasMany
    {
        return $this->hasMany(ScrutinyElectedPerson::class);
    }
}
