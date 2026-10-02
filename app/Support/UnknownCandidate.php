<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Candidatos que el OCR no pudo leer.
 *
 * El modelo de IA escribe "<Unknown>" cuando un nombre es ilegible y deja la
 * identificación vacía. Sin documento no se podía oficializar la plancha
 * ("Sin documento para deduplicar persona"). Aquí:
 *  - "<Unknown>" se guarda como "<DESCONOCIDO>" (en español, como el resto).
 *  - un documento vacío recibe un número PROVISIONAL único de 11 dígitos
 *    (00000000001, 00000000002, …) para poder guardarlo y oficializarlo;
 *    la pantalla lo marca como provisional para que se corrija después.
 *
 * Ninguna cédula colombiana real empieza con seis ceros, así que el prefijo
 * no choca con documentos verdaderos.
 */
class UnknownCandidate
{
    public const NAME = '<DESCONOCIDO>';

    public const PLACEHOLDER_PREFIX = '000000';

    public const PLACEHOLDER_LENGTH = 11;

    /** Reemplaza "<Unknown>" (o "Unknown" suelto) por "<DESCONOCIDO>". */
    public static function normalizeName(string $name): string
    {
        $normalized = preg_replace('/<\s*unknown\s*>|\bunknown\b/i', self::NAME, $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    public static function isPlaceholderDocument(?string $document): bool
    {
        return $document !== null
            && strlen($document) === self::PLACEHOLDER_LENGTH
            && str_starts_with($document, self::PLACEHOLDER_PREFIX);
    }

    /**
     * Siguiente documento provisional libre, mirando personas y borradores.
     * Se consulta cada vez: cada borrador se guarda antes de pedir el siguiente,
     * así que la consulta ya lo ve (y no hay estado que envejezca entre peticiones).
     */
    public static function nextPlaceholderDocument(): string
    {
        $max = 0;
        foreach (['persons', 'candidate_drafts'] as $table) {
            $value = DB::table($table)
                ->where('document_number', 'like', self::PLACEHOLDER_PREFIX.'%')
                ->whereRaw('LENGTH(document_number) = ?', [self::PLACEHOLDER_LENGTH])
                ->max('document_number');
            $max = max($max, (int) $value);
        }

        return str_pad((string) ($max + 1), self::PLACEHOLDER_LENGTH, '0', STR_PAD_LEFT);
    }
}
