<?php

namespace App\Models\Concerns;

/**
 * "Buscar o crear" que también mira la papelera.
 *
 * Con borrado suave, firstOrCreate() no ve una fila eliminada, intenta crear
 * otra igual y la base la rechaza por duplicada (mismo documento, mismo
 * código). Aquí, si la fila existe pero está en la papelera, se restaura: es
 * el mismo registro que vuelve a usarse.
 *
 * Para modelos con SoftDeletes.
 */
trait RestoresWhenRecreated
{
    public static function firstOrCreateRestoring(array $attributes, array $values = []): static
    {
        $model = static::withTrashed()->firstOrCreate($attributes, $values);

        if ($model->trashed()) {
            $model->restore();

            // Al eliminar quedó inactivo: si se vuelve a usar, vuelve a estar activo.
            if (array_key_exists('is_active', $model->getAttributes())) {
                $model->forceFill(['is_active' => true])->save();
            }
        }

        return $model;
    }
}
