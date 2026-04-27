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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ImportScrutinyExtractionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;
    public int $timeout = 240;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60, 120];

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

        $payload = $this->payload;

        if (! is_array($payload['normalized_payload'] ?? null)) {
            $extractionOutput = $this->runExtractorForRecordFile($recordFile);

            $payload = array_merge($payload, [
                'scrutiny_record_id' => $this->scrutinyRecordId,
                'scrutiny_record_file_id' => $this->scrutinyRecordFileId,
                'source_type' => 'ai',
                'engine_name' => 'motor_extraction.py',
                'engine_version' => 'queue-worker',
                'status' => 'pending_review',
                'raw_payload' => [
                    'extractor' => $extractionOutput['extractor'],
                    'stdout_size' => $extractionOutput['stdout_size'],
                ],
                'normalized_payload' => $extractionOutput['normalized_payload'],
            ]);
        }

        $importer->import($payload, $this->createdByUserId);

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

    /**
     * @return array{normalized_payload: array<string,mixed>, extractor: string, stdout_size: int}
     */
    private function runExtractorForRecordFile(ScrutinyRecordFile $recordFile): array
    {
        $localFilePath = $this->materializeInputFile($recordFile);

        try {
            $pythonBinary = $this->resolvePythonBinary();
            $extractorScript = base_path('data_extraction/motor_extraction.py');

            $process = new Process([
                $pythonBinary,
                $extractorScript,
                '--image',
                $localFilePath,
                '--dry-run',
            ], base_path(), $this->buildExtractorProcessEnvironment(), null, 180);

            $process->run();

            if (! $process->isSuccessful()) {
                $errorDetail = trim($process->getErrorOutput() ?: $process->getOutput());
                $classified = $this->classifyExtractorError($errorDetail);
                throw new RuntimeException($classified['message']);
            }

            $stdout = trim($process->getOutput());
            $json = json_decode($stdout, true);

            if (! is_array($json)) {
                throw new RuntimeException('La salida del extractor no es JSON valido.');
            }

            $normalizedPayload = $json['normalized_payload'] ?? null;
            if (! is_array($normalizedPayload)) {
                throw new RuntimeException('El extractor no devolvio normalized_payload valido.');
            }

            return [
                'normalized_payload' => $normalizedPayload,
                'extractor' => 'motor_extraction.py',
                'stdout_size' => strlen($stdout),
            ];
        } finally {
            if (is_file($localFilePath)) {
                @unlink($localFilePath);
            }
        }
    }

    private function materializeInputFile(ScrutinyRecordFile $recordFile): string
    {
        $storagePath = (string) $recordFile->storage_path;
        $disk = $this->resolveStorageDisk($storagePath);

        if ($disk === null) {
            throw new RuntimeException('No se encontro el archivo de acta en el storage configurado.');
        }

        $tmpDir = storage_path('app/private/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $tmpPath = $tmpDir.DIRECTORY_SEPARATOR.'scrutiny_job_'.uniqid('', true).'_'.$recordFile->id;

        $readStream = Storage::disk($disk)->readStream($storagePath);
        if (! is_resource($readStream)) {
            throw new RuntimeException('No fue posible leer el archivo cargado para OCR.');
        }

        $writeStream = fopen($tmpPath, 'wb');
        if (! is_resource($writeStream)) {
            fclose($readStream);
            throw new RuntimeException('No fue posible preparar el archivo temporal para OCR.');
        }

        stream_copy_to_stream($readStream, $writeStream);
        fclose($readStream);
        fclose($writeStream);

        return $tmpPath;
    }

    private function resolveStorageDisk(string $path): ?string
    {
        $disks = array_values(array_unique([
            (string) config('services.extractor.storage_disk', config('filesystems.default', 'local')),
            'local',
        ]));

        foreach ($disks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    private function resolvePythonBinary(): string
    {
        $configured = trim((string) env('EXTRACTOR_PYTHON_BIN', ''));
        $candidates = [];

        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $candidates[] = base_path('.venv/Scripts/python.exe');
        $candidates[] = base_path('.venv/bin/python');
        $candidates[] = 'python';
        $candidates[] = 'python3';

        foreach ($candidates as $candidate) {
            if (! $this->canRunPythonBinary($candidate)) {
                continue;
            }

            return $candidate;
        }

        throw new RuntimeException(
            'No se encontro un ejecutable de Python valido para OCR en el worker. '
            .'Configura EXTRACTOR_PYTHON_BIN o crea .venv en la raiz del proyecto.'
        );
    }

    private function canRunPythonBinary(string $binary): bool
    {
        if (str_contains($binary, DIRECTORY_SEPARATOR) && ! is_file($binary)) {
            return false;
        }

        try {
            $process = new Process([$binary, '--version'], base_path(), null, null, 10);
            $process->run();
            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildExtractorProcessEnvironment(): array
    {
        $resolvedRegion = (string) (env('AWS_REGION')
            ?: env('AWS_DEFAULT_REGION')
            ?: config('services.ses.region', 'us-east-1'));

        $baseEnv = array_merge($_SERVER, $_ENV);

        if (PHP_OS_FAMILY === 'Windows') {
            $baseEnv['SystemRoot'] = $baseEnv['SystemRoot'] ?? getenv('SystemRoot') ?: 'C:\\Windows';
            $baseEnv['WINDIR'] = $baseEnv['WINDIR'] ?? getenv('WINDIR') ?: $baseEnv['SystemRoot'];
        }

        $env = [
            'AWS_ACCESS_KEY_ID' => (string) env('AWS_ACCESS_KEY_ID', ''),
            'AWS_SECRET_ACCESS_KEY' => (string) env('AWS_SECRET_ACCESS_KEY', ''),
            'AWS_SESSION_TOKEN' => (string) env('AWS_SESSION_TOKEN', ''),
            'AWS_REGION' => $resolvedRegion,
            'AWS_DEFAULT_REGION' => $resolvedRegion,
            'BEDROCK_CONNECT_TIMEOUT_SECONDS' => (string) env('BEDROCK_CONNECT_TIMEOUT_SECONDS', ''),
            'BEDROCK_READ_TIMEOUT_SECONDS' => (string) env('BEDROCK_READ_TIMEOUT_SECONDS', ''),
            'BEDROCK_MAX_RETRIES' => (string) env('BEDROCK_MAX_RETRIES', ''),
            'BEDROCK_RETRY_BASE_SECONDS' => (string) env('BEDROCK_RETRY_BASE_SECONDS', ''),
            'HTTPS_PROXY' => (string) env('HTTPS_PROXY', ''),
            'HTTP_PROXY' => (string) env('HTTP_PROXY', ''),
            'NO_PROXY' => (string) env('NO_PROXY', ''),
            'PATH' => (string) (getenv('PATH') ?: ''),
            'PYTHONUTF8' => '1',
        ];

        $normalizedBaseEnv = [];
        foreach ($baseEnv as $key => $value) {
            if (! is_string($key) || $key === '' || is_array($value) || is_object($value) || $value === null) {
                continue;
            }

            $normalizedBaseEnv[$key] = (string) $value;
        }

        return array_filter(
            array_merge($normalizedBaseEnv, $env),
            static fn ($value): bool => $value !== ''
        );
    }

    /**
     * @return array{status:int,error_code:string,retriable:bool,message:string,detail:string}
     */
    private function classifyExtractorError(string $errorDetail): array
    {
        $detail = trim($errorDetail);
        $message = $detail;

        $decoded = json_decode($detail, true);
        if (is_array($decoded)) {
            $message = trim((string) ($decoded['error'] ?? $decoded['message'] ?? $decoded['detail'] ?? $detail));
            $detail = trim((string) ($decoded['detail'] ?? $decoded['error'] ?? $detail));
        }

        if ($message === '') {
            $message = 'Error desconocido del extractor.';
        }

        $normalized = Str::lower($message.' '.$detail);

        if (
            str_contains($normalized, 'no se pudo conectar a aws bedrock')
            || str_contains($normalized, 'could not connect to the endpoint url')
            || str_contains($normalized, 'proxy')
            || str_contains($normalized, 'ssl')
            || str_contains($normalized, 'timed out')
            || str_contains($normalized, 'timeout')
        ) {
            return [
                'status' => 503,
                'error_code' => 'bedrock_connectivity_error',
                'retriable' => true,
                'message' => $message,
                'detail' => $detail,
            ];
        }

        if (
            str_contains($normalized, 'faltan credenciales aws')
            || str_contains($normalized, 'credenciales aws')
            || str_contains($normalized, 'accessdeniedexception')
            || str_contains($normalized, 'unrecognizedclientexception')
        ) {
            return [
                'status' => 503,
                'error_code' => 'bedrock_credentials_error',
                'retriable' => false,
                'message' => $message,
                'detail' => $detail,
            ];
        }

        return [
            'status' => 422,
            'error_code' => 'extractor_process_failed',
            'retriable' => false,
            'message' => $message,
            'detail' => $detail,
        ];
    }
}
