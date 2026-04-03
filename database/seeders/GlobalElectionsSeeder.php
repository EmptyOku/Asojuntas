<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Neighborhood;

class GlobalElectionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $documentTypeId = DB::table('document_types')->where('code', 'CC')->value('id');
        $adminId = DB::table('users')->first()->id ?? null;

        // AQUÍ ESTÁ LA CLAVE: Traemos TODOS los barrios de la base de datos
        $neighborhoods = Neighborhood::all();

        if ($neighborhoods->isEmpty() || !$documentTypeId || !$adminId) {
            $this->command->error("Faltan datos maestros (Barrios, Tipo CC o usuario Admin).");
            return;
        }

        $blocks = DB::table('blocks')->whereIn('code', ['DIR', 'DEL', 'FIS'])->pluck('id', 'code');
        $positions = DB::table('positions')->whereIn('code', ['DIR_PRES', 'DIR_VICE'])->pluck('id', 'code');

        $this->command->info("Iniciando simulación masiva de elecciones para " . $neighborhoods->count() . " barrios...");

        // Usamos una transacción para que si algo falla, no deje la base de datos a medias
        DB::beginTransaction();

        try {
            foreach ($neighborhoods as $barrio) {
                $electionCode = 'ELEC-2026-' . $barrio->code;

                // 1. Elección del Barrio
                $electionId = DB::table('elections')->insertGetId([
                    'code' => $electionCode,
                    'neighborhood_id' => $barrio->id,
                    'name' => 'Elección JAC 2026 - ' . $barrio->name,
                    'election_date' => '2026-04-26',
                    'period_year' => 2026,
                    'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                // 2. Mesa y Acta
                $mesaId = DB::table('polling_tables')->insertGetId([
                    'election_id' => $electionId, 'code' => 'M1-' . $barrio->code, 'name' => 'Mesa Única', 'capacity' => 500, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                ]);

                $actaId = DB::table('scrutiny_records')->insertGetId([
                    'election_id' => $electionId, 'polling_table_id' => $mesaId, 'created_by_user_id' => $adminId, 'record_number' => 'ACTA-' . $barrio->code, 'source_type' => 'manual', 'status' => 'approved', 'created_at' => $now, 'updated_at' => $now
                ]);

                // 3. Bloque Directivo y Cargos
                $ebDirId = DB::table('election_blocks')->insertGetId([
                    'election_id' => $electionId, 'block_id' => $blocks['DIR'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                ]);

                $ebpPresId = DB::table('election_block_positions')->insertGetId([
                    'election_block_id' => $ebDirId, 'block_id' => $blocks['DIR'], 'position_id' => $positions['DIR_PRES'], 'vacancies' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                ]);

                $ebpViceId = DB::table('election_block_positions')->insertGetId([
                    'election_block_id' => $ebDirId, 'block_id' => $blocks['DIR'], 'position_id' => $positions['DIR_VICE'], 'vacancies' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                ]);

                // 4. Planchas y Votos Aleatorios
                for ($p = 1; $p <= 2; $p++) {
                    $planchaId = DB::table('slates')->insertGetId([
                        'election_id' => $electionId, 'code' => 'PL'.$p.'-'.$barrio->code, 'name' => 'Plancha ' . $p, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                    ]);

                    $slateBlockId = DB::table('slate_blocks')->insertGetId([
                        'election_id' => $electionId, 'slate_id' => $planchaId, 'election_block_id' => $ebDirId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                    ]);

                    // LA MAGIA DE LOS VOTOS: Un número aleatorio entre 10 y 500
                    DB::table('scrutiny_block_results')->insert([
                        'scrutiny_record_id' => $actaId, 'election_id' => $electionId, 'election_block_id' => $ebDirId, 'slate_block_id' => $slateBlockId,
                        'votes' => rand(10, 500),
                        'source_type' => 'manual', 'status' => 'reviewed', 'created_at' => $now, 'updated_at' => $now
                    ]);

                    // Crear Personas (Presidente y Vicepresidente para esta plancha)
                    // Usamos un ID secuencial falso para la cédula para que no colisionen
                    $baseDoc = "1" . str_pad($barrio->id, 3, '0', STR_PAD_LEFT) . $p;

                    $personaPresId = DB::table('persons')->insertGetId([
                        'document_type_id' => $documentTypeId, 'neighborhood_id' => $barrio->id, 'document_number' => $baseDoc . '1', 'first_name' => 'Presi P'.$p, 'last_name' => $barrio->name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                    ]);

                    $personaViceId = DB::table('persons')->insertGetId([
                        'document_type_id' => $documentTypeId, 'neighborhood_id' => $barrio->id, 'document_number' => $baseDoc . '2', 'first_name' => 'Vice P'.$p, 'last_name' => $barrio->name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                    ]);

                    // Postular a las personas como candidatos
                    DB::table('candidates')->insert([
                        ['election_id' => $electionId, 'person_id' => $personaPresId, 'slate_block_id' => $slateBlockId, 'election_block_position_id' => $ebpPresId, 'ballot_number' => '01', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                        ['election_id' => $electionId, 'person_id' => $personaViceId, 'slate_block_id' => $slateBlockId, 'election_block_position_id' => $ebpViceId, 'ballot_number' => '02', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                    ]);
                }
            }
            DB::commit();
            $this->command->info("Simulación masiva completada con éxito.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Error crítico en la simulación: " . $e->getMessage());
        }
    }
}
