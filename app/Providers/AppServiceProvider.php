<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Services\AuditTrailLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        Event::listen('eloquent.created: *', function (string $eventName, array $data) use ($auditTrailLogger): void {
            $model = $data[0] ?? null;

            if (! $model instanceof Model || $model instanceof AuditLog) {
                return;
            }

            $auditTrailLogger->recordModelEvent('created', $model);
        });

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
