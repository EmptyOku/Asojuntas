<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue al suplente del principal en una plancha.
 *
 * Hasta ahora "SUPLENTE DE PRESIDENTE" se guardaba en el mismo cargo que el
 * presidente, sin nada que los diferenciara: una plancha quedaba con dos
 * presidentes y el cálculo de dignatarios podía tomar al suplente.
 *
 * También ordena los cargos que el mapeo OCR creó sin order_number (quedaban
 * sin jerarquía dentro de su bloque).
 */
return new class extends Migration
{
    /** Orden de los cargos creados por el mapeo OCR (el resto ya lo tiene). */
    private const POSITION_ORDER = [
        'DIR_SECR' => 4,
        'DEL_AJ_1' => 1,
        'DEL_AJ_2' => 2,
        'DEL_AJ_3' => 3,
        'CYC_CONC_1' => 1,
        'CYC_CONC_2' => 2,
        'CYC_CONC_3' => 3,
        'CYC_EMP_COORD' => 4,
    ];

    public function up(): void
    {
        Schema::table('candidate_drafts', function (Blueprint $table) {
            $table->boolean('is_substitute')->default(false)->after('position_id');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->boolean('is_substitute')->default(false)->after('election_block_position_id');
        });

        // Los borradores guardan el cargo leído en la nota: "... | Cargo: SUPLENTE DE PRESIDENTE".
        DB::table('candidate_drafts')
            ->whereRaw('UPPER(notes) LIKE ?', ['%CARGO: SUPLENTE%'])
            ->update(['is_substitute' => true]);

        // Un candidato es suplente si salió de un borrador suplente de la misma elección.
        DB::table('candidates')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('candidate_drafts')
                    ->whereColumn('candidate_drafts.election_id', 'candidates.election_id')
                    ->whereColumn('candidate_drafts.person_id', 'candidates.person_id')
                    ->where('candidate_drafts.is_substitute', true)
                    ->whereNull('candidate_drafts.deleted_at');
            })
            ->update(['is_substitute' => true]);

        foreach (self::POSITION_ORDER as $code => $order) {
            DB::table('positions')
                ->where('code', $code)
                ->whereNull('order_number')
                ->update(['order_number' => $order]);
        }
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('is_substitute');
        });

        Schema::table('candidate_drafts', function (Blueprint $table) {
            $table->dropColumn('is_substitute');
        });
    }
};
