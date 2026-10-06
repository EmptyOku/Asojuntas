<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Separa las planchas que quedaron fusionadas en "Plancha 1".
 *
 * La pantalla de registro enviaba siempre "Plancha 1", así que la segunda y
 * tercera plancha de un barrio se guardaban sobre la primera: en las planchas
 * oficiales aparecía una sola plancha con 34 o 51 cargos.
 *
 * Solo toca las elecciones donde una misma plancha tiene borradores de más de
 * un lote de captura. En ellas, cada lote (por orden de captura) pasa a ser
 * la Plancha 1, 2, 3…, y sus borradores y candidatos oficiales se mueven a
 * esa plancha. El número real ya no se puede saber: si no coincide con el del
 * tarjetón, se corrige a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        $collisions = DB::table('candidate_drafts')
            ->whereNull('deleted_at')
            ->whereNotNull('slate_id')
            ->whereNotNull('capture_batch_uuid')
            ->where('review_status', '<>', 'rejected')
            ->groupBy('election_id', 'slate_id')
            ->havingRaw('COUNT(DISTINCT capture_batch_uuid) > 1')
            ->pluck('election_id')
            ->unique();

        foreach ($collisions as $electionId) {
            DB::transaction(fn () => $this->splitElection((int) $electionId));
        }
    }

    private function splitElection(int $electionId): void
    {
        $batches = DB::table('candidate_drafts')
            ->where('election_id', $electionId)
            ->whereNull('deleted_at')
            ->whereNotNull('capture_batch_uuid')
            ->where('review_status', '<>', 'rejected')
            ->groupBy('capture_batch_uuid')
            ->orderByRaw('MIN(id)')
            ->pluck('capture_batch_uuid');

        $now = now();

        foreach ($batches->values() as $index => $batchUuid) {
            $number = $index + 1;
            $slateId = DB::table('slates')->where('election_id', $electionId)->where('code', 'P'.$number)->value('id')
                ?? DB::table('slates')->insertGetId([
                    'election_id' => $electionId,
                    'code' => 'P'.$number,
                    'name' => 'Plancha '.$number,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            $drafts = DB::table('candidate_drafts')
                ->where('capture_batch_uuid', $batchUuid)
                ->whereNull('deleted_at')
                ->get(['id', 'slate_id', 'slate_block_id', 'person_id', 'is_processed']);

            foreach ($drafts as $draft) {
                $newSlateBlockId = null;

                if ($draft->slate_block_id) {
                    $electionBlockId = DB::table('slate_blocks')->where('id', $draft->slate_block_id)->value('election_block_id');
                    $newSlateBlockId = DB::table('slate_blocks')
                        ->where('election_id', $electionId)
                        ->where('slate_id', $slateId)
                        ->where('election_block_id', $electionBlockId)
                        ->value('id')
                        ?? DB::table('slate_blocks')->insertGetId([
                            'election_id' => $electionId,
                            'slate_id' => $slateId,
                            'election_block_id' => $electionBlockId,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                    // El candidato oficial que salió de este borrador se mueve con él.
                    if ($draft->is_processed && $draft->person_id && $newSlateBlockId !== (int) $draft->slate_block_id) {
                        DB::table('candidates')
                            ->where('election_id', $electionId)
                            ->where('person_id', $draft->person_id)
                            ->where('slate_block_id', $draft->slate_block_id)
                            ->update(['slate_block_id' => $newSlateBlockId, 'updated_at' => $now]);
                    }
                }

                DB::table('candidate_drafts')->where('id', $draft->id)->update([
                    'slate_id' => $slateId,
                    'slate_block_id' => $newSlateBlockId ?? $draft->slate_block_id,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: volver a fusionar las planchas sería reintroducir el error.
    }
};
