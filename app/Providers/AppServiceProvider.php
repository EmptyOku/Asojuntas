<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\ScrutinyBlockResult;
use App\Models\ScrutinyExtraction;
use App\Models\ScrutinyRecordFile;
use Illuminate\Support\Facades\URL;
use App\Services\AuditTrailLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Modelos que nunca generan una fila automática en la bitácora: son ruido técnico
     * (el token de sesión de Sanctum, que se crea/actualiza en cada request) o sub-pasos
     * internos del pipeline de OCR ya trazados en sus propias tablas, no acciones que
     * alguien deba auditar. `AuditLog` se excluye para no auto-registrarse en bucle.
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(AuditTrailLogger $auditTrailLogger): void
    {
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

        if (
            request()->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || str_starts_with((string) config('app.url'), 'https://')
        ) {
            URL::forceScheme('https');
        }
    }
}
