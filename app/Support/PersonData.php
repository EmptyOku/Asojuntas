<?php

namespace App\Support;

/**
 * Formato único para nombres y documentos de personas y candidatos.
 *
 * Antes cada pantalla guardaba como llegaba: "JUAN PEREZ" desde el OCR,
 * "juan perez" a mano, cédulas "1.070.622.867" o "1070622867". Eso rompía
 * las búsquedas y permitía registrar dos veces a la misma persona.
 *
 *  - Nombres: "Juan Pérez de la Cruz" (cada palabra con mayúscula inicial,
 *    partículas en minúscula, sin espacios dobles).
 *  - Documentos: sin puntos, comas, espacios ni guiones ("1070622867").
 *
 * Se aplica al guardar (trait NormalizesPersonData) y antes de validar
 * unicidad, para que "1.070.622" y "1070622" se reconozcan como el mismo.
 */
class PersonData
{
    /** Partículas que van en minúscula cuando no son la primera palabra. */
    private const PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y'];

    public static function name(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($clean === '') {
            return $clean;
        }

        $words = explode(' ', $clean);
        foreach ($words as $index => $word) {
            // Marcadores del sistema (<DESCONOCIDO>, SIN_APELLIDO) se dejan tal cual.
            if (preg_match('/[<>_]/', $word) === 1) {
                $words[$index] = mb_strtoupper($word);

                continue;
            }

            $lower = mb_strtolower($word);
            $words[$index] = ($index > 0 && in_array($lower, self::PARTICLES, true))
                ? $lower
                : mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $words);
    }

    /** Deja el documento de la petición ya normalizado, antes de validar. */
    public static function normalizeRequest(\Illuminate\Http\Request $request): void
    {
        if (is_string($request->input('document_number'))) {
            $request->merge(['document_number' => self::document($request->input('document_number'))]);
        }
    }

    public static function document(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Solo se quitan separadores: un pasaporte con letras se conserva (en mayúsculas).
        $clean = preg_replace('/[\s.,\'’`´\-]/u', '', $value) ?? $value;

        return mb_strtoupper($clean);
    }
}
