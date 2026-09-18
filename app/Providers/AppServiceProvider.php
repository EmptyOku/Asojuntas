<?php

namespace App\Providers;

use App\Models\AuditLog;
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

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton para que el memo de permisos viva lo que dura el request.
        $this->app->singleton(ElectoralAccessGuard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(AuditTrailLogger $auditTrailLogger): void
    {
        // Busqueda "LIKE" case-insensitive portable entre PostgreSQL (VPS) y
        // SQLite (local). Sustituye el operador 'ilike', exclusivo de Postgres,
        // que rompia con "syntax error" en SQLite.
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

        // `exists` consulta la tabla en crudo y acepta filas borradas en blando.
        // `active_exists` es su equivalente para las tablas con SoftDeletes.
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

        Event::listen('eloquent.created: *', function (string $eventName, array $data) use ($auditTrailLogger): void {
            $model = $data[0] ?? null;

            if (! $model instanceof Model || $model instanceof AuditLog) {
                return;
            }

            $auditTrailLogger->recordModelEvent('created', $model);
        });

        if (
            request()->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || str_starts_with((string) config('app.url'), 'https://')
        ) {
            URL::forceScheme('https');
        }

        Event::listen('eloquent.updated: *', function (string $eventName, array $data) use ($auditTrailLogger): void {
            $model = $data[0] ?? null;

            if (! $model instanceof Model || $model instanceof AuditLog) {
                return;
            }

            $auditTrailLogger->recordModelEvent('updated', $model);
        });

        Event::listen('eloquent.deleted: *', function (string $eventName, array $data) use ($auditTrailLogger): void {
            $model = $data[0] ?? null;

            if (! $model instanceof Model || $model instanceof AuditLog) {
                return;
            }

            $auditTrailLogger->recordModelEvent('deleted', $model);
        });
    }
}
