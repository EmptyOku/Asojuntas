<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use HasFactory;

    /**
     * Por defecto Laravel busca la tabla "people".
     * Como la migración está declarado "persons", se declara estrictamente aquí.
     */
    protected $table = 'persons';

    protected $fillable = [
        'document_type_id',
        'neighborhood_id',
        'document_number',
        'first_name',
        'middle_name',
        'last_name',
        'second_last_name',
        'birth_date',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function candidateDrafts(): HasMany
    {
        return $this->hasMany(CandidateDraft::class);
    }

    public function scrutinyElectedPeople(): HasMany
    {
        return $this->hasMany(ScrutinyElectedPerson::class);
    }
}
