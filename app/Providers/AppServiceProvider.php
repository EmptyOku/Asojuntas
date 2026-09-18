<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\ScrutinyBlockResult;
use App\Models\ScrutinyExtraction;
use App\Models\ScrutinyRecordFile;
use Illuminate\Support\Facades\URL;
use App\Services\AuditTrailLogger;
use App\Services\ElectoralAccessGuard;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Modelos que nunca generan una fila automática en la bitácora: son ruido técnico
     * o sub-pasos internos del pipeline de OCR ya trazados en sus propias tablas.
     */
    private const AUDIT_EXCLUDED_MODELS = [
        AuditLog::class,
        PersonalAccessToken::class,
        ScrutinyExtraction::class,
        ScrutinyBlockResult::class,
        ScrutinyRecordFile::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton de tu compañero para el memo de permisos
        $this->app->singleton(ElectoralAccessGuard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(AuditTrailLogger $auditTrailLogger): void
    {
        // ---------------------------------------------------------------------
        // 1. MACROS DE BASE DE DATOS (De la rama sonnet4)
        // ---------------------------------------------------------------------
        $whereLike = function (string $column, string $value) {
            /** @var EloquentBuilder|QueryBuilder $this */
            return $this->whereRaw('LOWER('.$column.') LIKE ?', [mb_strtolower($value)]);
        };
        $orWhereLike = function (string $column, string $value) {
            /** @var EloquentBuilder|QueryBuilder $this */
            return $this->orWhereRaw('LOWER('.$column.') LIKE ?', [mb_strtolower($value)]);
        };

        EloquentBuilder::macro('whereLike', $whereLike);
        EloquentBuilder::macro('orWhereLike', $orWhereLike);
        QueryBuilder::macro('whereLike', $whereLike);
        QueryBuilder::macro('orWhereLike', $orWhereLike);

        // ---------------------------------------------------------------------
        // 2. REGLAS DE VALIDACIÓN (De la rama sonnet4)
        // ---------------------------------------------------------------------
        Validator::extend('active_exists', function (string $attribute, $value, array $parameters): bool {
            $table = $parameters[0] ?? null;

            if (! $table || $value === null || $value === '') {
                return false;
            }

            return DB::table($table)
                ->where($parameters[1] ?? 'id', $value)
                ->whereNull('deleted_at')
                ->exists();
        }, 'El campo :attribute seleccionado no existe o fue eliminado.');

        // ---------------------------------------------------------------------
        // 3. AUDITORÍA OPTIMIZADA (De tu rama correciones)
        // ---------------------------------------------------------------------
        $recordForAction = function (string $action) use ($auditTrailLogger): callable {
            return function (string $eventName, array $data) use ($action, $auditTrailLogger): void {
                $model = $data[0] ?? null;

                if (! $model instanceof Model || in_array($model::class, self::AUDIT_EXCLUDED_MODELS, true)) {
                    return;
                }

                $auditTrailLogger->recordModelEvent($action, $model);
            };
        };

        Event::listen('eloquent.created: *', $recordForAction('created'));
        Event::listen('eloquent.updated: *', $recordForAction('updated'));
        Event::listen('eloquent.deleted: *', $recordForAction('deleted'));

        // ---------------------------------------------------------------------
        // 4. FORZAR HTTPS (Común en ambas ramas)
        // ---------------------------------------------------------------------
        if (
            request()->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || str_starts_with((string) config('app.url'), 'https://')
        ) {
            URL::forceScheme('https');
        }
    }
}