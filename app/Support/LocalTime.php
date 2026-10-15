<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Fechas y horas como las lee una persona en Colombia.
 *
 * La base guarda todo en UTC (config app.timezone). Para mostrarlo se pasa a
 * la zona de la organización (config app.display_timezone, por defecto
 * America/Bogota): antes la bitácora mostraba las horas 5 horas adelantadas.
 */
final class LocalTime
{
    public static function zone(): string
    {
        return (string) config('app.display_timezone', 'America/Bogota');
    }

    /** El instante en la hora local, o null si no hay fecha. */
    public static function make(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse((string) $value, (string) config('app.timezone', 'UTC'));
        } catch (Throwable) {
            return null;
        }

        return $date->setTimezone(self::zone())->locale('es');
    }

    /**
     * Una fecha lista para pantalla.
     *
     * @return array{iso: string, fecha: string, hora: string, texto: string, relativo: string}|null
     */
    public static function present(mixed $value): ?array
    {
        $date = self::make($value);
        if (! $date) {
            return null;
        }

        return [
            'iso' => $date->toIso8601String(),
            'fecha' => $date->isoFormat('D [de] MMMM [de] YYYY'),
            'hora' => $date->isoFormat('h:mm a'),
            'texto' => $date->isoFormat('D [de] MMMM [de] YYYY, h:mm a'),
            'relativo' => $date->diffForHumans(),
        ];
    }

    /** "2026-10-15 15:20" en hora local (para archivos CSV y Excel). */
    public static function plain(mixed $value): string
    {
        return self::make($value)?->format('Y-m-d H:i') ?? '';
    }
}
