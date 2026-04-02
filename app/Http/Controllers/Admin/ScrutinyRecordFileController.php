<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyRecordFile;
use App\Models\ScrutinyRecord;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ScrutinyRecordFileController extends Controller
{
    /**
     * Almacena el archivo físico, calcula su hash y guarda el registro.
     */
    public function store(Request $request): RedirectResponse
    {
        $storageDisk = $this->storageDisk();
        $validated = $request->validate([
            'scrutiny_record_id' => 'required|exists:scrutiny_records,id',
            'document_file'      => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240', // Máx 10MB
            'page_number'        => 'required|integer|min:1',
            'is_primary'         => 'boolean',
            'notes'              => 'nullable|string|max:500',
        ]);

        $file = $request->file('document_file');

        // 1. Auditoría Criptográfica: Calculamos el hash SHA-256 antes de guardarlo
        $fileHash = hash_file('sha256', $file->getRealPath());

        // 2. Verificamos que esta misma foto no haya sido subida antes (evita duplicados de actas)
        if (ScrutinyRecordFile::where('hash', $fileHash)->exists()) {
            return back()->with('error', 'Auditoría: Fraude o Duplicidad detectada. Este archivo exacto ya fue subido al sistema previamente.');
        }

        // 3. Almacenamiento seguro en disco (Ej: storage/app/actas/eleccion_id/mesa_id)
        $scrutinyRecord = ScrutinyRecord::findOrFail($validated['scrutiny_record_id']);
        $path = $file->store("actas/{$scrutinyRecord->election_id}", $storageDisk);

        // 4. Registrar en base de datos
        ScrutinyRecordFile::create([
            'scrutiny_record_id'  => $validated['scrutiny_record_id'],
            'uploaded_by_user_id' => Auth::id(),
            'file_type'           => $file->getClientOriginalExtension(),
            'original_name'       => $file->getClientOriginalName(),
            'storage_path'        => $path,
            'mime_type'           => $file->getMimeType(),
            'file_size'           => $file->getSize(),
            'hash'                => $fileHash,
            'page_number'         => $validated['page_number'],
            'is_primary'          => $request->has('is_primary'),
            'notes'               => $validated['notes'],
        ]);

        return back()->with('success', 'Evidencia fotográfica asegurada y registrada.');
    }

    /**
     * Descarga segura del archivo para revisión manual o envío a Python.
     */
    public function show(ScrutinyRecordFile $scrutinyRecordFile)
    {
        $storageDisk = $this->resolveStorageDisk($scrutinyRecordFile->storage_path);

        if ($storageDisk === null) {
            abort(404, 'El archivo físico no se encuentra en el servidor.');
        }

        return Storage::disk($storageDisk)->download(
            $scrutinyRecordFile->storage_path,
            $scrutinyRecordFile->original_name
        );
    }

    /**
     * Elimina el archivo SOLO si la IA no lo ha procesado aún.
     */
    public function destroy(ScrutinyRecordFile $scrutinyRecordFile): RedirectResponse
    {
        // Bloqueo de Cadena de Custodia: Si Python ya extrajo datos de aquí, es intocable.
        if ($scrutinyRecordFile->extractions()->exists()) {
            return back()->with('error', 'Auditoría: Cadena de custodia activa. Este archivo ya fue procesado por el motor de IA. No puede ser destruido.');
        }

        // Eliminamos el archivo físico del disco
        $storageDisk = $this->storageDisk();

        if (Storage::disk($storageDisk)->exists($scrutinyRecordFile->storage_path)) {
            Storage::disk($storageDisk)->delete($scrutinyRecordFile->storage_path);
        }

        // Eliminamos el registro de la BD
        $scrutinyRecordFile->delete();

        return back()->with('success', 'Archivo eliminado correctamente antes del procesamiento.');
    }

    private function storageDisk(): string
    {
        return (string) config('services.extractor.storage_disk', config('filesystems.default', 'local'));
    }

    private function resolveStorageDisk(string $path): ?string
    {
        $disks = array_values(array_unique([
            $this->storageDisk(),
            'local',
        ]));

        foreach ($disks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }
}
