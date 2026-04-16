<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\DocumentType;
use App\Models\ElectionBlockPosition;
use App\Models\Person;
use App\Models\Position;
use App\Models\Slate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class OcrCandidateController extends Controller
{
    public function process(Request $request)
    {
        $request->validate([
            'slate_id'   => 'required|exists:slates,id',
            'imagenes.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120'
        ]);

        $slateId = $request->slate_id;
        
        // 1. Crear directorio temporal para este lote de fotos
        $tempDir = 'temp_ocr_' . uniqid();
        Storage::disk('local')->makeDirectory($tempDir);
        $fullTempPath = storage_path('app/' . $tempDir);

        // 2. Mover las imágenes al directorio temporal
        foreach ($request->file('imagenes') as $imagen) {
            $imagen->move($fullTempPath, $imagen->getClientOriginalName());
        }

        // 3. Ejecutar el script de Python dentro del entorno resuelto.
        $scriptPath = base_path('data_extraction/extraer_candidatos.py');
        $pythonPath = $this->resolvePythonBinary();

        $process = new Process([$pythonPath, $scriptPath, $fullTempPath], base_path(), $this->buildExtractorProcessEnvironment());
        $process->setTimeout(180); // Dar tiempo suficiente a Bedrock
        $process->run();

        if (!$process->isSuccessful()) {
            Storage::disk('local')->deleteDirectory($tempDir);
            return response()->json([
                'error' => 'No se pudo ejecutar el extractor de candidatos.',
                'detail' => trim($process->getErrorOutput() ?: $process->getOutput()),
                'python_bin' => $pythonPath,
            ], 422);
        }

        // 4. Capturar la salida de la consola de Python
        $output = $process->getOutput();
        
        // Extraer solo el JSON (ignorando los logs de [INFO] que imprime el script)
        preg_match('/\{.*\}/s', $output, $matches);
        $jsonString = $matches[0] ?? '{}';
        
        $resultadoOcr = json_decode($jsonString, true);

        if (!is_array($resultadoOcr)) {
            Storage::disk('local')->deleteDirectory($tempDir);
            return response()->json([
                'error' => 'La salida del extractor no es JSON valido.',
                'raw' => $output,
            ], 422);
        }

        // 5. Guardar en Base de Datos
        if (isset($resultadoOcr['status']) && $resultadoOcr['status'] === '200_OK') {
            DB::beginTransaction();
            try {
                $summary = $this->persistExtractedCandidates($slateId, $resultadoOcr);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Storage::disk('local')->deleteDirectory($tempDir);
                return response()->json(['error' => 'Fallo al guardar en BD: ' . $e->getMessage()], 500);
            }
        }

        // 6. Limpiar archivos temporales
        Storage::disk('local')->deleteDirectory($tempDir);

        return response()->json([
            'message' => 'Candidatos procesados exitosamente',
            'data'    => $resultadoOcr,
            'summary' => $summary ?? [
                'processed' => 0,
                'persons_created' => 0,
                'candidates_created' => 0,
                'candidates_existing' => 0,
                'skipped' => 0,
                'issues' => [],
            ],
        ]);
    }

    private function persistExtractedCandidates(int $slateId, array $resultadoOcr): array
    {
        $slate = Slate::query()
            ->with(['slateBlocks.electionBlock.block'])
            ->findOrFail($slateId);

        $documentTypeId = $this->resolveDefaultDocumentTypeId();

        $slateBlocksByCode = [];
        foreach ($slate->slateBlocks as $slateBlock) {
            $code = Str::upper((string) data_get($slateBlock, 'electionBlock.block.code', ''));
            if ($code !== '') {
                $slateBlocksByCode[$code] = $slateBlock;
            }
        }

        $processed = 0;
        $personsCreated = 0;
        $candidatesCreated = 0;
        $candidatesExisting = 0;
        $skipped = 0;
        $issues = [];

        $payloadBlocks = data_get($resultadoOcr, 'normalized_payload.plancha_blocks', []);
        if (!is_array($payloadBlocks) || empty($payloadBlocks)) {
            $payloadBlocks = data_get($resultadoOcr, 'payload', []);
        }

        foreach ((array) $payloadBlocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            foreach ((array) ($block['cargos'] ?? $block['candidatos'] ?? []) as $candidateRow) {
                if (!is_array($candidateRow)) {
                    continue;
                }

                $cargoLabel = (string) ($candidateRow['puesto'] ?? $candidateRow['cargo'] ?? '');
                $fullName = trim((string) ($candidateRow['nombre'] ?? ''));
                $documentNumber = $this->normalizeDocumentNumber((string) ($candidateRow['identificacion'] ?? ''));

                if ($fullName === '' || $documentNumber === null) {
                    $skipped++;
                    $issues[] = [
                        'cargo' => $cargoLabel,
                        'reason' => 'Registro sin nombre o identificacion valida.',
                    ];
                    continue;
                }

                $positionContext = $this->resolvePositionContextForCargo($cargoLabel);
                $positionId = $positionContext['position_id'];
                $blockCode = $positionContext['block_code'];

                if (!$positionId || !$blockCode || !isset($slateBlocksByCode[$blockCode])) {
                    $skipped++;
                    $issues[] = [
                        'cargo' => $cargoLabel,
                        'document_number' => $documentNumber,
                        'reason' => 'No se pudo mapear el cargo a bloque/posicion de la eleccion.',
                    ];
                    continue;
                }

                $slateBlock = $slateBlocksByCode[$blockCode];

                $electionBlockPositionId = ElectionBlockPosition::query()
                    ->where('election_block_id', $slateBlock->election_block_id)
                    ->where('position_id', $positionId)
                    ->value('id');

                if (!$electionBlockPositionId) {
                    $skipped++;
                    $issues[] = [
                        'cargo' => $cargoLabel,
                        'document_number' => $documentNumber,
                        'reason' => 'No existe election_block_position para la combinacion detectada.',
                    ];
                    continue;
                }

                $nameParts = $this->splitName($fullName);

                $person = Person::query()->firstOrCreate(
                    [
                        'document_type_id' => $documentTypeId,
                        'document_number' => $documentNumber,
                    ],
                    [
                        'first_name' => $nameParts['first_name'],
                        'middle_name' => $nameParts['middle_name'],
                        'last_name' => $nameParts['last_name'],
                        'second_last_name' => $nameParts['second_last_name'],
                        'phone' => $this->normalizeNullable((string) ($candidateRow['celular'] ?? '')),
                        'email' => $this->normalizeNullable((string) ($candidateRow['correo'] ?? '')),
                        'is_active' => true,
                    ]
                );

                if ($person->wasRecentlyCreated) {
                    $personsCreated++;
                }

                $candidate = Candidate::query()->firstOrCreate(
                    [
                        'election_id' => $slate->election_id,
                        'person_id' => $person->id,
                    ],
                    [
                        'slate_block_id' => $slateBlock->id,
                        'election_block_position_id' => $electionBlockPositionId,
                        'ballot_number' => null,
                        'is_active' => true,
                    ]
                );

                if ($candidate->wasRecentlyCreated) {
                    $candidatesCreated++;
                } else {
                    $candidatesExisting++;
                }

                $processed++;
            }
        }

        return [
            'processed' => $processed,
            'persons_created' => $personsCreated,
            'candidates_created' => $candidatesCreated,
            'candidates_existing' => $candidatesExisting,
            'skipped' => $skipped,
            'issues' => $issues,
        ];
    }

    private function resolveDefaultDocumentTypeId(): int
    {
        $byCode = DocumentType::query()
            ->whereRaw('UPPER(code) = ?', ['CC'])
            ->value('id');

        if ($byCode) {
            return (int) $byCode;
        }

        $fallback = DocumentType::query()->orderBy('id')->value('id');
        if ($fallback) {
            return (int) $fallback;
        }

        throw new RuntimeException('No hay tipos de documento configurados para registrar personas.');
    }

    private function resolvePositionContextForCargo(string $cargoLabel): array
    {
        $normalized = Str::upper(trim($cargoLabel));

        $map = [
            'PRESIDENTE' => [['DIR_PRES'], 'DIR'],
            'VICEPRESIDENTE' => [['DIR_VICE'], 'DIR'],
            'TESORERO' => [['DIR_TESO'], 'DIR'],
            'SECRETARIO' => [['DIR_SECR', 'DIR_SEC'], 'DIR'],
            'DELEGADO ASOJUNTAS 1' => [['DEL_AJ_1', 'DEL_1'], 'DEL'],
            'DELEGADO ASOJUNTAS 2' => [['DEL_AJ_2', 'DEL_2'], 'DEL'],
            'DELEGADO ASOJUNTAS 3' => [['DEL_AJ_3', 'DEL_3'], 'DEL'],
            'FISCAL' => [['FIS_PRIN'], 'FIS'],
            'CONCILIADOR 1' => [['CYC_CONC_1'], 'CYC'],
            'CONCILIADOR 2' => [['CYC_CONC_2'], 'CYC'],
            'CONCILIADOR 3' => [['CYC_CONC_3'], 'CYC'],
            'COMISION EMPRESARIAL' => [['CYC_EMP_COORD'], 'CYC'],
        ];

        if (!isset($map[$normalized])) {
            return ['position_id' => null, 'block_code' => null];
        }

        [$positionCodes, $blockCode] = $map[$normalized];

        $position = Position::query()
            ->whereIn('code', $positionCodes)
            ->orderBy('id')
            ->first();

        return [
            'position_id' => $position?->id,
            'block_code' => $blockCode,
        ];
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        return [
            'first_name' => $parts[0] ?? '',
            'middle_name' => $parts[1] ?? null,
            'last_name' => $parts[2] ?? ($parts[1] ?? ''),
            'second_last_name' => $parts[3] ?? null,
        ];
    }

    private function normalizeDocumentNumber(string $value): ?string
    {
        $digits = preg_replace('/[^\d]/', '', $value) ?? '';
        return $digits !== '' ? $digits : null;
    }

    private function normalizeNullable(string $value): ?string
    {
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function resolvePythonBinary(): string
    {
        $configured = trim((string) env('EXTRACTOR_PYTHON_BIN', ''));
        $candidates = [];

        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $candidates[] = base_path('.venv/Scripts/python.exe');
        $candidates[] = base_path('.venv/Scripts/python');
        $candidates[] = base_path('.venv/bin/python');
        $candidates[] = 'python';
        $candidates[] = 'python3';

        foreach ($candidates as $candidate) {
            if (!$this->canRunPythonBinary($candidate)) {
                continue;
            }

            return $candidate;
        }

        throw new RuntimeException(
            'No se encontro un ejecutable de Python valido para OCR. '
            .'Crea .venv en la raiz del proyecto o configura EXTRACTOR_PYTHON_BIN con la ruta local de tu equipo.'
        );
    }

    private function canRunPythonBinary(string $binary): bool
    {
        if (str_contains($binary, DIRECTORY_SEPARATOR) && !is_file($binary)) {
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

    private function buildExtractorProcessEnvironment(): array
    {
        $resolvedRegion = (string) (env('AWS_REGION') ?: env('AWS_DEFAULT_REGION') ?: config('services.ses.region', 'us-east-1'));

        $baseEnv = array_merge($_SERVER, $_ENV);

        if (PHP_OS_FAMILY === 'Windows') {
            $baseEnv['SystemRoot'] = $baseEnv['SystemRoot'] ?? getenv('SystemRoot') ?: 'C:\\Windows';
            $baseEnv['WINDIR'] = $baseEnv['WINDIR'] ?? getenv('WINDIR') ?: $baseEnv['SystemRoot'];
        }

        $env = [
            'APP_ENV' => (string) config('app.env', 'production'),
            'AWS_ACCESS_KEY_ID' => (string) env('AWS_ACCESS_KEY_ID', ''),
            'AWS_SECRET_ACCESS_KEY' => (string) env('AWS_SECRET_ACCESS_KEY', ''),
            'AWS_SESSION_TOKEN' => (string) env('AWS_SESSION_TOKEN', ''),
            'AWS_REGION' => $resolvedRegion,
            'AWS_DEFAULT_REGION' => $resolvedRegion,
            'BEDROCK_MODEL_ID' => (string) env('BEDROCK_MODEL_ID', ''),
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
}