<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailLogger;
use App\Services\DatabaseBackup;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Descarga de la copia de seguridad de la base de datos (botón del Panel).
 * Exige el permiso database.backup y deja cada descarga en la bitácora.
 */
class DatabaseBackupController extends Controller
{
    public function download(DatabaseBackup $backup, AuditTrailLogger $audit): BinaryFileResponse|JsonResponse
    {
        // Una base grande puede tardar más que el límite normal de una petición.
        @set_time_limit(300);

        try {
            $file = $backup->create();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar la copia de seguridad. Revisa el registro de errores del servidor.',
            ], 500);
        }

        $audit->recordSystemEvent('database_backup', [
            'filename' => $file['filename'],
            'format' => $file['format'],
            'size_bytes' => filesize($file['path']) ?: 0,
        ]);

        return response()
            ->download($file['path'], $file['filename'], [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'no-store, private',
                'X-Backup-Format' => $file['format'],
            ])
            ->deleteFileAfterSend(true);
    }
}
