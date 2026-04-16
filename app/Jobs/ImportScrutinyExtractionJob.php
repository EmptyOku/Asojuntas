<?php

namespace App\Jobs;

use App\Models\ScrutinyRecord;
use App\Models\ScrutinyRecordFile;
use App\Services\ScrutinyExtractionImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportScrutinyExtractionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $scrutinyRecordId,
        public int $scrutinyRecordFileId,
        public int $createdByUserId,
        public string $expectedHash,
        public array $payload,
    ) {
    }

    public function handle(ScrutinyExtractionImporter $importer): void
    {
        $this->updatePageProcessingState('processing');

        $recordFile = ScrutinyRecordFile::query()
            ->where('id', $this->scrutinyRecordFileId)
            ->where('scrutiny_record_id', $this->scrutinyRecordId)
            ->first();

        if (! $recordFile) {
            $this->updatePageProcessingState('failed', 'Archivo de acta no encontrado para procesar.');
            Log::warning('ImportScrutinyExtractionJob skipped: record file not found.', [
                'scrutiny_record_id' => $this->scrutinyRecordId,
                'scrutiny_record_file_id' => $this->scrutinyRecordFileId,
            ]);
            return;
        }

        if ((string) $recordFile->hash !== $this->expectedHash) {
            $this->updatePageProcessingState('superseded');
            Log::info('ImportScrutinyExtractionJob skipped: stale page upload replaced by a newer file.', [
                'scrutiny_record_id' => $this->scrutinyRecordId,
                'scrutiny_record_file_id' => $this->scrutinyRecordFileId,
            ]);
            return;
        }

        $importer->import($this->payload, $this->createdByUserId);

        $record = ScrutinyRecord::find($this->scrutinyRecordId);
        if ($record && in_array($record->status, ['draft', 'pending'], true)) {
            $record->status = 'pending_review';
            $record->save();
        }

        $this->updatePageProcessingState('completed');
    }

    public function failed(Throwable $exception): void
    {
        $this->updatePageProcessingState('failed', $exception->getMessage());

        Log::error('ImportScrutinyExtractionJob failed.', [
            'scrutiny_record_id' => $this->scrutinyRecordId,
            'scrutiny_record_file_id' => $this->scrutinyRecordFileId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function updatePageProcessingState(string $status, ?string $error = null): void
    {
        $record = ScrutinyRecord::find($this->scrutinyRecordId);
        $recordFile = ScrutinyRecordFile::find($this->scrutinyRecordFileId);

        if (! $record || ! $recordFile) {
            return;
        }

        $metadata = is_array($record->metadata) ? $record->metadata : [];
        $processing = is_array($metadata['ingest_processing'] ?? null) ? $metadata['ingest_processing'] : [];
        $pageKey = (string) ($recordFile->page_number ?? $recordFile->id);

        $processing[$pageKey] = [
            'status' => $status,
            'scrutiny_record_file_id' => $recordFile->id,
            'updated_at' => now()->toIso8601String(),
        ];

        if ($error !== null && $error !== '') {
            $processing[$pageKey]['error'] = $error;
        }

        $metadata['ingest_processing'] = $processing;
        $record->metadata = $metadata;
        $record->save();
    }
}
