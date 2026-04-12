<?php

namespace App\Support;

final class CandidateDraftWorkflow
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    public static function canApplyDecision(string $currentStatus, string $targetStatus, bool $isProcessed): bool
    {
        if ($isProcessed) {
            return false;
        }

        if ($currentStatus !== self::STATUS_PENDING) {
            return false;
        }

        return in_array($targetStatus, [self::STATUS_APPROVED, self::STATUS_REJECTED], true);
    }
}
