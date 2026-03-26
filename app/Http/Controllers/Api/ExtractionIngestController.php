<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyRecord;
use App\Models\ScrutinyRecordFile;
use App\Services\ScrutinyExtractionImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtractionIngestController extends Controller
{
    public function uploadFile(Request $request): JsonResponse
    {
        $maxFileSizeKb = (int) config('services.extractor.max_upload_kb', 10240);

        $validated = $request->validate([
            'scrutiny_record_id' => 'required|exists:scrutiny_records,id',
            'document_file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:'.$maxFileSizeKb,
            'page_number' => 'required|integer|min:1',
            'is_primary' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $record = ScrutinyRecord::findOrFail((int) $validated['scrutiny_record_id']);
        $file = $request->file('document_file');

        $fileHash = hash_file('sha256', $file->getRealPath());
        $alreadyUploaded = ScrutinyRecordFile::where('hash', $fileHash)->first();

        if ($alreadyUploaded) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo ya existe en el sistema.',
                'data' => [
                    'scrutiny_record_file_id' => $alreadyUploaded->id,
                    'storage_path' => $alreadyUploaded->storage_path,
                    'hash' => $alreadyUploaded->hash,
                ],
            ], 409);
        }

        $path = $file->store("actas/{$record->election_id}/{$record->id}", 'local');

        $recordFile = ScrutinyRecordFile::create([
            'scrutiny_record_id' => $record->id,
            'uploaded_by_user_id' => null,
            'file_type' => $file->getClientOriginalExtension(),
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'hash' => $fileHash,
            'page_number' => (int) $validated['page_number'],
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Archivo recibido y almacenado correctamente.',
            'data' => [
                'scrutiny_record_file_id' => $recordFile->id,
                'storage_path' => $recordFile->storage_path,
                'hash' => $recordFile->hash,
            ],
        ], 201);
    }

    public function ingestExtraction(Request $request, ScrutinyExtractionImporter $importer): JsonResponse
    {
        $validated = $request->validate([
            'scrutiny_record_id' => 'required|exists:scrutiny_records,id',
            'scrutiny_record_file_id' => 'nullable|exists:scrutiny_record_files,id',
            'based_on_extraction_id' => 'nullable|exists:scrutiny_extractions,id',
            'source_type' => 'nullable|in:ai,manual,api',
            'engine_name' => 'nullable|string|max:50',
            'engine_version' => 'nullable|string|max:30',
            'confidence_score' => 'nullable|numeric|min:0|max:1',
            'status' => 'nullable|string|max:20',
            'raw_payload' => 'nullable|array',
            'normalized_payload' => 'required|array',
            'normalized_payload.block_results' => 'nullable|array',
            'normalized_payload.elected_people' => 'nullable|array',
            'notes' => 'nullable|string|max:1500',
        ]);

        $result = $importer->import($validated, null);
        $extraction = $result['extraction'];

        return response()->json([
            'success' => true,
            'message' => 'Extracción importada y persistida correctamente.',
            'data' => [
                'extraction_id' => $extraction->id,
                'round_number' => $extraction->round_number,
                'summary' => $result['summary'],
            ],
        ], 201);
    }
}
