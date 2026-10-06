<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado suave en las tablas que se administran desde el Panel.
 *
 * "Eliminar" marca la fila con la fecha (deleted_at) en vez de borrarla: no se
 * pierde nada, no se rompen los registros que la usan, queda en la bitácora
 * quién la eliminó y se puede restaurar desde la papelera.
 *
 * Barrios, actas y candidatos en revisión ya lo tenían.
 */
return new class extends Migration
{
    private const TABLES = [
        'communes', 'document_types', 'blocks', 'positions',
        'persons', 'users', 'roles',
        'elections', 'polling_tables', 'slates', 'candidates',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
