<?php

use App\Support\PersonData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lleva los datos ya guardados al formato único de App\Support\PersonData:
 * nombres "Juan Pérez de la Cruz" y documentos sin puntos ni espacios.
 *
 * Si al quitar los puntos un documento choca con otra persona que ya lo tiene
 * (la misma persona registrada dos veces), esa fila NO se toca y queda en el
 * log para revisarla a mano: fusionar personas no es algo que deba decidir
 * una migración.
 */
return new class extends Migration
{
    private const NAME_COLUMNS = ['first_name', 'middle_name', 'last_name', 'second_last_name'];

    public function up(): void
    {
        foreach (['persons', 'candidate_drafts'] as $table) {
            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $changes = [];
                    foreach (self::NAME_COLUMNS as $column) {
                        $formatted = PersonData::name($row->{$column});
                        if ($formatted !== $row->{$column}) {
                            $changes[$column] = $formatted;
                        }
                    }

                    $document = PersonData::document($row->document_number);
                    if ($document !== $row->document_number) {
                        $duplicated = $table === 'persons' && DB::table('persons')
                            ->where('document_type_id', $row->document_type_id)
                            ->where('document_number', $document)
                            ->where('id', '<>', $row->id)
                            ->exists();

                        if ($duplicated) {
                            Log::warning('Documento duplicado al estandarizar: revisar a mano.', [
                                'person_id' => $row->id,
                                'document_number' => $row->document_number,
                            ]);
                        } else {
                            $changes['document_number'] = $document;
                        }
                    }

                    if ($changes !== []) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: el formato anterior no se conserva.
    }
};
