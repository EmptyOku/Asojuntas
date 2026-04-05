<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AuditTrailLogger
{
    /**
     * Keys that should never be stored in audit payloads.
     */
    private const EXCLUDED_KEYS = [
        'password',
        'remember_token',
        'password_confirmation',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function recordModelEvent(string $action, Model $model, array $metadata = []): void
    {
        if ($this->shouldSkipLogging($model)) {
            return;
        }

        $changes = $this->extractChanges($model, $action);

        $this->write([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'old_values' => $changes['old_values'],
            'new_values' => $changes['new_values'],
            'metadata' => array_merge($this->contextMetadata($model), $metadata),
        ]);
    }

    public function recordSystemEvent(string $action, array $metadata = []): void
    {
        $this->write([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => null,
            'auditable_id' => null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'old_values' => null,
            'new_values' => null,
            'metadata' => array_merge($this->contextMetadata(), $metadata),
        ]);
    }

    private function shouldSkipLogging(Model $model): bool
    {
        if ($model instanceof AuditLog) {
            return true;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return true;
        }

        return false;
    }

    private function extractChanges(Model $model, string $action): array
    {
        $current = $this->sanitizeAttributes($model->getAttributes());
        $original = $this->sanitizeAttributes($model->getOriginal());

        return match ($action) {
            'created' => [
                'old_values' => null,
                'new_values' => $current,
            ],
            'deleted' => [
                'old_values' => $original ?: $current,
                'new_values' => null,
            ],
            default => [
                'old_values' => $original,
                'new_values' => $current,
            ],
        };
    }

    private function sanitizeAttributes(array $attributes): array
    {
        $sanitized = Arr::except($attributes, self::EXCLUDED_KEYS);

        foreach ($sanitized as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $sanitized[$key] = $value->format(DATE_ATOM);
            }
        }

        return $sanitized;
    }

    private function contextMetadata(?Model $model = null): array
    {
        $request = request();

        $metadata = [
            'model' => $model ? $model::class : null,
            'model_name' => $model ? class_basename($model) : null,
            'route' => $request?->route()?->getName(),
            'method' => $request?->method(),
            'path' => $request?->path(),
        ];

        return array_filter($metadata, static fn ($value) => $value !== null && $value !== '');
    }

    private function write(array $payload): void
    {
        try {
            AuditLog::create($payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}