<?php

namespace App\Models\Concerns;

use App\Support\PersonData;

/**
 * Nombres y documento siempre en el mismo formato, sin importar por dónde
 * entren (asistente de usuarios, CRUD de personas, OCR de planchas, edición).
 * Ver App\Support\PersonData.
 */
trait NormalizesPersonData
{
    public static function bootNormalizesPersonData(): void
    {
        static::saving(function ($model): void {
            foreach (['first_name', 'middle_name', 'last_name', 'second_last_name'] as $column) {
                if ($model->isDirty($column) && $model->{$column} !== null) {
                    $model->{$column} = PersonData::name($model->{$column});
                }
            }

            if ($model->isDirty('document_number') && $model->document_number !== null) {
                $model->document_number = PersonData::document($model->document_number);
            }
        });
    }
}
