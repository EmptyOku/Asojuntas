<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyRecord;
use App\Models\ScrutinyRecordFile;
use App\Services\ScrutinyExtractionImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $pageNumber = (int) $validated['page_number'];

        $fileHash = hash_file('sha256', $file->getRealPath());
        $existingPageFile = ScrutinyRecordFile::query()
            ->where('scrutiny_record_id', $record->id)
            ->where('page_number', $pageNumber)
            ->latest('id')
            ->first();
        $alreadyUploaded = ScrutinyRecordFile::where('hash', $fileHash)->first();

        if (
            $alreadyUploaded
            && $alreadyUploaded->scrutiny_record_id === $record->id
            && (int) $alreadyUploaded->page_number === $pageNumber
        ) {
            return response()->json([
                'success' => true,
                'message' => 'Archivo ya existente para esa página. Se reutiliza el registro previo.',
                'data' => [
                    'scrutiny_record_file_id' => $alreadyUploaded->id,
                    'storage_path' => $alreadyUploaded->storage_path,
                    'hash' => $alreadyUploaded->hash,
                ],
            ], 200);
        }

        $path = $file->store("actas/{$record->election_id}/{$record->id}", 'local');

        if ($existingPageFile) {
            if (Storage::disk('local')->exists($existingPageFile->storage_path)) {
                Storage::disk('local')->delete($existingPageFile->storage_path);
            }

            $existingPageFile->fill([
                'uploaded_by_user_id' => null,
                'file_type' => Str::lower((string) $file->getClientOriginalExtension()),
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'hash' => $fileHash,
                'page_number' => $pageNumber,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'notes' => $validated['notes'] ?? null,
            ]);
            $existingPageFile->save();
            $recordFile = $existingPageFile;
        } else {
            $recordFile = ScrutinyRecordFile::create([
                'scrutiny_record_id' => $record->id,
                'uploaded_by_user_id' => null,
                'file_type' => Str::lower((string) $file->getClientOriginalExtension()),
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'hash' => $fileHash,
                'page_number' => $pageNumber,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'notes' => $validated['notes'] ?? null,
            ]);
        }

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
