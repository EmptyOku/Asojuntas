<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Genera datos de demostracion para TODAS las elecciones sembradas.
 *
 * Los seeders demo previos solo montan una eleccion de ejemplo, asi que
 * las otras ~117 quedaban sin planchas, candidatos ni votos. Este seeder
 * completa la cadena por cada eleccion:
 *
 *   election_block_positions -> slates -> slate_blocks -> persons
 *   -> candidates -> scrutiny_records -> scrutiny_block_results
 *
 * DemoConsolidationSeeder toma esos votos y produce la consolidacion.
 *
 * Es idempotente: solo crea lo que falta en cada eleccion.
 */
class DemoElectionDataSeeder extends Seeder
{
    /** Planchas que se crean por eleccion. */
    private const SLATES = [
        ['code' => 'P1', 'name' => 'Plancha Unidad Comunal'],
        ['code' => 'P2', 'name' => 'Plancha Progreso Vecinal'],
        ['code' => 'P3', 'name' => 'Plancha Renovacion Barrial'],
    ];

    private const FIRST_NAMES = [
        'Ana', 'Carlos', 'Beatriz', 'Diego', 'Elena', 'Fabian', 'Gloria', 'Hector',
        'Isabel', 'Javier', 'Karina', 'Luis', 'Marta', 'Nestor', 'Olga', 'Pedro',
        'Rocio', 'Samuel', 'Teresa', 'Ulises', 'Valeria', 'Wilson', 'Ximena', 'Yolanda',
    ];

    private const LAST_NAMES = [
        'Gomez', 'Rodriguez', 'Martinez', 'Lopez', 'Garcia', 'Perez', 'Sanchez',
        'Ramirez', 'Torres', 'Vargas', 'Castro', 'Rojas', 'Moreno', 'Jimenez',
        'Ruiz', 'Alvarez', 'Romero', 'Suarez', 'Herrera', 'Medina',
    ];

    public function run(): void
    {
        $now = now();

        $documentTypeId = DB::table('document_types')->where('code', 'CC')->value('id')
            ?? DB::table('document_types')->value('id');
        $juradoId = DB::table('users')->where('username', 'jurado')->value('id')
            ?? DB::table('users')->value('id');

        if (! $documentTypeId || ! $juradoId) {
            $this->command?->warn('Faltan catalogos base; ejecuta los seeders principales primero.');

            return;
        }

        // Posiciones disponibles por bloque.
        $positionsByBlock = DB::table('positions')
            ->where('is_active', true)
            ->get(['id', 'block_id'])
            ->groupBy('block_id');

        if ($positionsByBlock->isEmpty()) {
            $positionsByBlock = DB::table('positions')->get(['id', 'block_id'])->groupBy('block_id');
        }

        $elections = DB::table('elections')->orderBy('id')->get(['id', 'code']);
        $this->command?->info("Generando datos demo para {$elections->count()} elecciones...");

        // Secuencia global para documentos unicos.
        $docSeq = (int) (DB::table('persons')->max('id') ?? 0) + 1_000_000;

        $done = 0;
        foreach ($elections as $election) {
            DB::transaction(function () use (
                $election, $positionsByBlock, $documentTypeId, $juradoId, $now, &$docSeq
            ) {
                $this->seedElection(
                    (int) $election->id,
                    (string) $election->code,
                    $positionsByBlock,
                    (int) $documentTypeId,
                    (int) $juradoId,
                    $now,
                    $docSeq
                );
            });

            $done++;
            if ($done % 25 === 0 || $done === $elections->count()) {
                $this->command?->info("  {$done}/{$elections->count()} elecciones procesadas");
            }
        }

        $this->command?->info('Datos demo por eleccion finalizados.');
    }

    private function seedElection(
        int $electionId,
        string $electionCode,
        $positionsByBlock,
        int $documentTypeId,
        int $juradoId,
        $now,
        int &$docSeq
    ): void {
        $electionBlocks = DB::table('election_blocks')
            ->where('election_id', $electionId)
            ->where('is_active', true)
            ->get(['id', 'block_id']);

        if ($electionBlocks->isEmpty()) {
            return;
        }

        $ebPositions = $this->ensureBlockPositions($electionBlocks, $positionsByBlock, $now);
        $slateBlocks = $this->ensureSlates($electionId, $electionBlocks, $now);

        if (empty($ebPositions) || empty($slateBlocks)) {
            return;
        }

        $this->ensureCandidates(
            $electionId, $electionBlocks, $ebPositions, $slateBlocks, $documentTypeId, $now, $docSeq
        );

        $this->ensureVotes($electionId, $electionCode, $electionBlocks, $slateBlocks, $juradoId, $now);
    }

    /**
     * Cada bloque de la eleccion recibe las posiciones de su bloque base.
     *
     * @return array<int,list<int>> election_block_id => [election_block_position_id]
     */
    private function ensureBlockPositions($electionBlocks, $positionsByBlock, $now): array
    {
        $existing = DB::table('election_block_positions')
            ->whereIn('election_block_id', $electionBlocks->pluck('id'))
            ->get(['id', 'election_block_id', 'position_id']);

        $byBlock = $existing->groupBy('election_block_id');
        $insert = [];

        foreach ($electionBlocks as $eb) {
            $wanted = $positionsByBlock[$eb->block_id] ?? collect();
            $have = ($byBlock[$eb->id] ?? collect())->pluck('position_id')->flip();

            foreach ($wanted as $position) {
                if ($have->has($position->id)) {
                    continue;
                }
                $insert[] = [
                    'election_block_id' => $eb->id,
                    'block_id' => $eb->block_id,
                    'position_id' => $position->id,
                    'vacancies' => 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($insert) {
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('election_block_positions')->insert($chunk);
            }
        }

        return DB::table('election_block_positions')
            ->whereIn('election_block_id', $electionBlocks->pluck('id'))
            ->get(['id', 'election_block_id'])
            ->groupBy('election_block_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();
    }

    /**
     * @return array<int,list<int>> election_block_id => [slate_block_id]
     */
    private function ensureSlates(int $electionId, $electionBlocks, $now): array
    {
        $slateIds = [];
        foreach (self::SLATES as $slate) {
            // slates es unico tanto por (election_id, code) como por
            // (election_id, name), y otros seeders ya pudieron crear la
            // plancha con uno de los dos valores.
            $id = DB::table('slates')
                ->where('election_id', $electionId)
                ->where(function ($q) use ($slate) {
                    $q->where('code', $slate['code'])->orWhere('name', $slate['name']);
                })
                ->value('id');

            $slateIds[] = $id ?: DB::table('slates')->insertGetId([
                'election_id' => $electionId,
                'code' => $slate['code'],
                'name' => $slate['name'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Si dos entradas resolvieron a la misma plancha existente, se evita
        // duplicar sus slate_blocks.
        $slateIds = array_values(array_unique($slateIds));

        $existing = DB::table('slate_blocks')
            ->where('election_id', $electionId)
            ->get(['id', 'slate_id', 'election_block_id']);

        $pairs = $existing->map(fn ($r) => $r->slate_id.'-'.$r->election_block_id)->flip();
        $insert = [];

        foreach ($electionBlocks as $eb) {
            foreach ($slateIds as $slateId) {
                if ($pairs->has($slateId.'-'.$eb->id)) {
                    continue;
                }
                $insert[] = [
                    'election_id' => $electionId,
                    'slate_id' => $slateId,
                    'election_block_id' => $eb->id,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($insert) {
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('slate_blocks')->insert($chunk);
            }
        }

        return DB::table('slate_blocks')
            ->where('election_id', $electionId)
            ->get(['id', 'election_block_id'])
            ->groupBy('election_block_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();
    }

    /**
     * Un candidato por cada combinacion plancha/posicion.
     */
    private function ensureCandidates(
        int $electionId,
        $electionBlocks,
        array $ebPositions,
        array $slateBlocks,
        int $documentTypeId,
        $now,
        int &$docSeq
    ): void {
        if (DB::table('candidates')->where('election_id', $electionId)->exists()) {
            return;
        }

        $persons = [];
        $plan = [];
        $ballot = 0;

        foreach ($electionBlocks as $eb) {
            $positions = $ebPositions[$eb->id] ?? [];
            $blocks = $slateBlocks[$eb->id] ?? [];

            foreach ($blocks as $slateBlockId) {
                foreach ($positions as $positionId) {
                    $docSeq++;
                    $ballot++;

                    $persons[] = [
                        'document_type_id' => $documentTypeId,
                        'document_number' => (string) $docSeq,
                        'first_name' => self::FIRST_NAMES[$docSeq % count(self::FIRST_NAMES)],
                        'middle_name' => null,
                        'last_name' => self::LAST_NAMES[$docSeq % count(self::LAST_NAMES)],
                        'second_last_name' => self::LAST_NAMES[($docSeq + 7) % count(self::LAST_NAMES)],
                        'birth_date' => null,
                        'phone' => '30'.str_pad((string) ($docSeq % 100_000_000), 8, '0', STR_PAD_LEFT),
                        'email' => 'candidato'.$docSeq.'@demo.local',
                        'address' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $plan[] = [
                        'document_number' => (string) $docSeq,
                        'slate_block_id' => $slateBlockId,
                        'election_block_position_id' => $positionId,
                        'ballot_number' => (string) $ballot,
                    ];
                }
            }
        }

        if (! $persons) {
            return;
        }

        foreach (array_chunk($persons, 500) as $chunk) {
            DB::table('persons')->insert($chunk);
        }

        $personIds = DB::table('persons')
            ->where('document_type_id', $documentTypeId)
            ->whereIn('document_number', array_column($plan, 'document_number'))
            ->pluck('id', 'document_number');

        $candidates = [];
        foreach ($plan as $row) {
            $personId = $personIds[$row['document_number']] ?? null;
            if (! $personId) {
                continue;
            }
            $candidates[] = [
                'election_id' => $electionId,
                'person_id' => $personId,
                'slate_block_id' => $row['slate_block_id'],
                'election_block_position_id' => $row['election_block_position_id'],
                'ballot_number' => $row['ballot_number'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($candidates, 500) as $chunk) {
            DB::table('candidates')->insert($chunk);
        }
    }

    /**
     * Un acta por mesa con la votacion de cada plancha en cada bloque.
     */
    private function ensureVotes(
        int $electionId,
        string $electionCode,
        $electionBlocks,
        array $slateBlocks,
        int $juradoId,
        $now
    ): void {
        $pollingTables = DB::table('polling_tables')
            ->where('election_id', $electionId)
            ->get(['id', 'code']);

        if ($pollingTables->isEmpty()) {
            return;
        }

        foreach ($pollingTables as $mesa) {
            $recordNumber = 'ACTA-'.$electionCode.'-'.$mesa->code;

            $recordId = DB::table('scrutiny_records')
                ->where('election_id', $electionId)
                ->where('record_number', $recordNumber)
                ->value('id');

            if ($recordId) {
                continue;
            }

            $attendees = random_int(45, 320);

            $recordId = DB::table('scrutiny_records')->insertGetId([
                'election_id' => $electionId,
                'polling_table_id' => $mesa->id,
                'created_by_user_id' => $juradoId,
                'record_number' => $recordNumber,
                'record_date' => $now->toDateString(),
                'record_time' => $now->format('H:i:s'),
                'source_type' => 'manual',
                'status' => 'closed',
                'quorum_attendees' => (int) ceil($attendees / 2),
                'total_attendees' => $attendees,
                'observations' => 'Acta de demostracion generada por DemoElectionDataSeeder.',
                'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $results = [];
            foreach ($electionBlocks as $eb) {
                $blocks = $slateBlocks[$eb->id] ?? [];
                if (! $blocks) {
                    continue;
                }

                // Se reparten los asistentes entre las planchas del bloque.
                $remaining = $attendees;
                $lastIndex = count($blocks) - 1;

                foreach ($blocks as $i => $slateBlockId) {
                    $votes = $i === $lastIndex
                        ? max(0, $remaining)
                        : random_int(0, max(0, (int) floor($remaining * 0.6)));
                    $remaining -= $votes;

                    $results[] = [
                        'scrutiny_record_id' => $recordId,
                        'election_id' => $electionId,
                        'election_block_id' => $eb->id,
                        'slate_block_id' => $slateBlockId,
                        'scrutiny_extraction_id' => null,
                        'votes' => $votes,
                        'source_type' => 'manual',
                        'status' => 'confirmed',
                        'confidence_score' => null,
                        'notes' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($results, 500) as $chunk) {
                DB::table('scrutiny_block_results')->insert($chunk);
            }
        }
    }
}
