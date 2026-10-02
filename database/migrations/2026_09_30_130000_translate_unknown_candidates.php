<?php

use App\Support\UnknownCandidate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Datos ya capturados con nombres ilegibles del OCR:
 *  - "<UNKNOWN>" pasa a "<DESCONOCIDO>" en borradores y personas.
 *  - los borradores pendientes sin documento reciben un número provisional
 *    único (00000000001, …) para poder oficializarlos.
 * Ver App\Support\UnknownCandidate.
 */
return new class extends Migration
{
    private const NAME_COLUMNS = ['first_name', 'middle_name', 'last_name', 'second_last_name'];

    public function up(): void
    {
        foreach (['candidate_drafts', 'persons'] as $table) {
            $rows = DB::table($table)
                ->where(function ($query) {
                    foreach (self::NAME_COLUMNS as $column) {
                        $query->orWhereRaw("UPPER({$column}) LIKE ?", ['%UNKNOWN%']);
                    }
                })
                ->get(['id', ...self::NAME_COLUMNS]);

            foreach ($rows as $row) {
                $changes = [];
                foreach (self::NAME_COLUMNS as $column) {
                    if ($row->{$column} !== null && stripos($row->{$column}, 'unknown') !== false) {
                        $changes[$column] = UnknownCandidate::normalizeName($row->{$column});
                    }
                }
                DB::table($table)->where('id', $row->id)->update($changes);
            }
        }

        $withoutDocument = DB::table('candidate_drafts')
            ->where('is_processed', false)
            ->where(fn ($q) => $q->whereNull('document_number')->orWhere('document_number', ''))
            ->orderBy('id')
            ->pluck('id');

        foreach ($withoutDocument as $id) {
            DB::table('candidate_drafts')->where('id', $id)
                ->update(['document_number' => UnknownCandidate::nextPlaceholderDocument()]);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: "<DESCONOCIDO>" y los documentos provisionales son datos válidos.
    }
};
