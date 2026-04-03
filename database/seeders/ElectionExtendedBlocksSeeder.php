<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ElectionExtendedBlocksSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $documentTypeId = DB::table('document_types')->where('code', 'CC')->value('id');
        if (! $documentTypeId) {
            $this->command->error('No existe el tipo de documento CC.');
            return;
        }

        $this->upsertCatalog($now);

        $blockIds = DB::table('blocks')
            ->whereIn('code', ['DIR', 'DEL', 'FIS', 'CYC'])
            ->pluck('id', 'code');

        $positionRows = DB::table('positions')
            ->whereIn('code', [
                'DIR_PRES', 'DIR_VICE', 'DIR_TESO', 'DIR_SECR',
                'DEL_AJ_1', 'DEL_AJ_2', 'DEL_AJ_3',
                'FIS_PRIN',
                'CYC_CONC_1', 'CYC_CONC_2', 'CYC_CONC_3', 'CYC_EMP_COORD',
            ])
            ->get(['id', 'code', 'block_id']);

        $positionByCode = [];
        foreach ($positionRows as $row) {
            $positionByCode[$row->code] = [
                'id' => (int) $row->id,
                'block_id' => (int) $row->block_id,
            ];
        }

        $onlyElectionId = (int) (getenv('EXT_SEED_ONLY_ELECTION') ?: 0);
        $limit = (int) (getenv('EXT_SEED_LIMIT') ?: 0);

        $electionsQuery = DB::table('elections')
            ->where('is_active', true)
            ->orderBy('id');

        if ($onlyElectionId > 0) {
            $electionsQuery->where('id', $onlyElectionId);
        }

        if ($limit > 0) {
            $electionsQuery->limit($limit);
        }

        $activeElections = $electionsQuery->get(['id', 'code', 'name', 'neighborhood_id']);

        if ($activeElections->isEmpty()) {
            $this->command->warn('No hay elecciones activas para extender.');
            return;
        }

        $totalElections = $activeElections->count();
        $processed = 0;

        foreach ($activeElections as $election) {
            $this->seedElection(
                electionId: (int) $election->id,
                electionCode: (string) $election->code,
                neighborhoodId: (int) $election->neighborhood_id,
                blockIds: $blockIds,
                positionByCode: $positionByCode,
                documentTypeId: (int) $documentTypeId,
                now: $now
            );

            $processed++;
            if ($processed === 1 || $processed % 10 === 0 || $processed === $totalElections) {
                $this->command->info("Procesadas {$processed}/{$totalElections} elecciones...");
            }
        }

        $this->command->info('ElectionExtendedBlocksSeeder ejecutado: bloques, cargos, candidatos y resultados extendidos.');
    }

    private function upsertCatalog($now): void
    {
        DB::table('blocks')->upsert([
            [
                'code' => 'DIR',
                'name' => 'Directiva',
                'description' => 'Bloque directivo principal de la JAC',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'DEL',
                'name' => 'Delegados Asojuntas',
                'description' => 'Delegados para representacion ante Asojuntas',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'FIS',
                'name' => 'Fiscal',
                'description' => 'Bloque de fiscalizacion',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'CYC',
                'name' => 'Comision de Convivencia y Conciliacion',
                'description' => 'Conciliadores y coordinacion empresarial',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], ['name', 'description', 'is_active', 'updated_at']);

        $blockByCode = DB::table('blocks')
            ->whereIn('code', ['DIR', 'DEL', 'FIS', 'CYC'])
            ->pluck('id', 'code');

        DB::table('positions')->upsert([
            [
                'block_id' => $blockByCode['DIR'] ?? null,
                'code' => 'DIR_PRES',
                'name' => 'Presidente(a)',
                'order_number' => 1,
                'description' => 'Cargo principal de directiva',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DIR'] ?? null,
                'code' => 'DIR_VICE',
                'name' => 'Vicepresidente(a)',
                'order_number' => 2,
                'description' => 'Cargo de apoyo directivo',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DIR'] ?? null,
                'code' => 'DIR_TESO',
                'name' => 'Tesorero(a)',
                'order_number' => 3,
                'description' => 'Cargo de tesoreria',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DIR'] ?? null,
                'code' => 'DIR_SECR',
                'name' => 'Secretario(a)',
                'order_number' => 4,
                'description' => 'Cargo de secretaria',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DEL'] ?? null,
                'code' => 'DEL_AJ_1',
                'name' => 'Delegado(a) Asojuntas 1',
                'order_number' => 1,
                'description' => 'Delegado titular 1',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DEL'] ?? null,
                'code' => 'DEL_AJ_2',
                'name' => 'Delegado(a) Asojuntas 2',
                'order_number' => 2,
                'description' => 'Delegado titular 2',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DEL'] ?? null,
                'code' => 'DEL_AJ_3',
                'name' => 'Delegado(a) Asojuntas 3',
                'order_number' => 3,
                'description' => 'Delegado titular 3',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['FIS'] ?? null,
                'code' => 'FIS_PRIN',
                'name' => 'Fiscal',
                'order_number' => 1,
                'description' => 'Cargo de fiscal',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['CYC'] ?? null,
                'code' => 'CYC_CONC_1',
                'name' => 'Conciliador(a) 1',
                'order_number' => 1,
                'description' => 'Conciliador titular 1',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['CYC'] ?? null,
                'code' => 'CYC_CONC_2',
                'name' => 'Conciliador(a) 2',
                'order_number' => 2,
                'description' => 'Conciliador titular 2',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['CYC'] ?? null,
                'code' => 'CYC_CONC_3',
                'name' => 'Conciliador(a) 3',
                'order_number' => 3,
                'description' => 'Conciliador titular 3',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['CYC'] ?? null,
                'code' => 'CYC_EMP_COORD',
                'name' => 'Comision Empresarial - Coordinador',
                'order_number' => 4,
                'description' => 'Coordinacion de comision empresarial',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['block_id', 'code'], ['name', 'order_number', 'description', 'is_active', 'updated_at']);
    }

    private function seedElection(
        int $electionId,
        string $electionCode,
        int $neighborhoodId,
        $blockIds,
        array $positionByCode,
        int $documentTypeId,
        $now
    ): void {
        $blockCodes = ['DIR', 'DEL', 'FIS', 'CYC'];

        $electionBlockRows = [];
        foreach ($blockCodes as $blockCode) {
            $blockId = $blockIds[$blockCode] ?? null;
            if (! $blockId) {
                continue;
            }

            $electionBlockRows[] = [
                'election_id' => $electionId,
                'block_id' => $blockId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('election_blocks')->upsert(
            $electionBlockRows,
            ['election_id', 'block_id'],
            ['is_active', 'updated_at']
        );

        $electionBlocks = DB::table('election_blocks')
            ->where('election_id', $electionId)
            ->whereIn('block_id', collect($blockIds)->values())
            ->get(['id', 'block_id']);

        $electionBlockByCode = [];
        foreach ($electionBlocks as $row) {
            $code = $blockIds->search($row->block_id);
            if ($code !== false) {
                $electionBlockByCode[$code] = (int) $row->id;
            }
        }

        $positionToBlockCode = [
            'DIR_PRES' => 'DIR',
            'DIR_VICE' => 'DIR',
            'DIR_TESO' => 'DIR',
            'DIR_SECR' => 'DIR',
            'DEL_AJ_1' => 'DEL',
            'DEL_AJ_2' => 'DEL',
            'DEL_AJ_3' => 'DEL',
            'FIS_PRIN' => 'FIS',
            'CYC_CONC_1' => 'CYC',
            'CYC_CONC_2' => 'CYC',
            'CYC_CONC_3' => 'CYC',
            'CYC_EMP_COORD' => 'CYC',
        ];

        $positionCodeList = array_keys($positionToBlockCode);

        $recordNumber = 'ACTA-EXT-'.$electionId;
        $existingRecordId = DB::table('scrutiny_records')
            ->where('election_id', $electionId)
            ->where('record_number', $recordNumber)
            ->value('id');

        $activeSlates = DB::table('slates')
            ->where('election_id', $electionId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'code', 'name']);

        if ($activeSlates->isEmpty()) {
            $activeSlates = DB::table('slates')
                ->where('election_id', $electionId)
                ->orderBy('id')
                ->get(['id', 'code', 'name']);
        }

        if ($activeSlates->isEmpty()) {
            return;
        }

        $expectedBlocks = count($blockCodes);
        $expectedPositions = count($positionCodeList);
        $expectedCandidates = $activeSlates->count() * $expectedPositions;
        $expectedResults = $activeSlates->count() * $expectedBlocks;

        $existingBlocksCount = DB::table('election_blocks')
            ->where('election_id', $electionId)
            ->whereIn('block_id', collect($blockIds)->values())
            ->count();

        $existingPositionsCount = DB::table('election_block_positions as ebp')
            ->join('election_blocks as eb', 'eb.id', '=', 'ebp.election_block_id')
            ->join('positions as p', 'p.id', '=', 'ebp.position_id')
            ->where('eb.election_id', $electionId)
            ->whereIn('p.code', $positionCodeList)
            ->count();

        $existingCandidatesCount = DB::table('candidates')
            ->where('election_id', $electionId)
            ->count();

        $existingResultsCount = $existingRecordId
            ? DB::table('scrutiny_block_results')->where('scrutiny_record_id', $existingRecordId)->count()
            : 0;

        if (
            $existingBlocksCount >= $expectedBlocks
            && $existingPositionsCount >= $expectedPositions
            && $existingCandidatesCount >= $expectedCandidates
            && $existingResultsCount >= $expectedResults
        ) {
            return;
        }

        $ebpRows = [];
        foreach ($positionToBlockCode as $positionCode => $blockCode) {
            $position = $positionByCode[$positionCode] ?? null;
            $electionBlockId = $electionBlockByCode[$blockCode] ?? null;

            if (! $position || ! $electionBlockId) {
                continue;
            }

            $ebpRows[] = [
                'election_block_id' => $electionBlockId,
                'block_id' => $position['block_id'],
                'position_id' => $position['id'],
                'vacancies' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('election_block_positions')->upsert(
            $ebpRows,
            ['election_block_id', 'position_id'],
            ['block_id', 'vacancies', 'is_active', 'updated_at']
        );

        $slateBlockRows = [];
        foreach ($activeSlates as $slate) {
            foreach ($blockCodes as $blockCode) {
                $electionBlockId = $electionBlockByCode[$blockCode] ?? null;
                if (! $electionBlockId) {
                    continue;
                }

                $slateBlockRows[] = [
                    'election_id' => $electionId,
                    'slate_id' => (int) $slate->id,
                    'election_block_id' => $electionBlockId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('slate_blocks')->upsert(
            $slateBlockRows,
            ['slate_id', 'election_block_id'],
            ['election_id', 'is_active', 'updated_at']
        );

        $slateBlocks = DB::table('slate_blocks')
            ->where('election_id', $electionId)
            ->get(['id', 'slate_id', 'election_block_id']);

        $slateBlockBySlateAndEb = [];
        foreach ($slateBlocks as $sb) {
            $slateBlockBySlateAndEb[$sb->slate_id.'|'.$sb->election_block_id] = (int) $sb->id;
        }

        $ebpByPositionCode = DB::table('election_block_positions as ebp')
            ->join('positions as p', 'p.id', '=', 'ebp.position_id')
            ->whereIn('ebp.election_block_id', array_values($electionBlockByCode))
            ->whereIn('p.code', array_keys($positionToBlockCode))
            ->pluck('ebp.id', 'p.code');

        $personRowsByDoc = [];
        $candidateMeta = [];

        foreach ($activeSlates as $slateIndex => $slate) {
            foreach ($positionCodeList as $positionIndex => $positionCode) {
                $blockCode = $positionToBlockCode[$positionCode];
                $electionBlockId = $electionBlockByCode[$blockCode] ?? null;
                $slateBlockId = $electionBlockId
                    ? ($slateBlockBySlateAndEb[$slate->id.'|'.$electionBlockId] ?? null)
                    : null;
                $ebpId = $ebpByPositionCode[$positionCode] ?? null;

                if (! $slateBlockId || ! $ebpId) {
                    continue;
                }

                $documentNumber = (string) (900000000000
                    + ($electionId * 10000)
                    + ((int) $slate->id * 100)
                    + ($positionIndex + 1));

                $firstName = 'Nom'.$electionId.'_'.$slate->code.'_'.$positionCode;
                $lastName = 'Ape'.($positionIndex + 1);
                $email = strtolower('e'.$electionId.'.'.$slate->code.'.'.$positionCode.'@demo.local');
                $phone = '300'.str_pad((string) (($electionId * 137 + $slate->id * 17 + $positionIndex * 9) % 10000000), 7, '0', STR_PAD_LEFT);

                $personRowsByDoc[$documentNumber] = [
                    'document_type_id' => $documentTypeId,
                    'neighborhood_id' => $neighborhoodId,
                    'document_number' => $documentNumber,
                    'first_name' => $firstName,
                    'middle_name' => null,
                    'last_name' => $lastName,
                    'second_last_name' => null,
                    'birth_date' => null,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $candidateMeta[] = [
                    'document_number' => $documentNumber,
                    'slate_block_id' => $slateBlockId,
                    'election_block_position_id' => (int) $ebpId,
                    'ballot_number' => $this->buildBallotNumber((string) $slate->code, $positionIndex + 1),
                ];
            }
        }

        if (! empty($personRowsByDoc)) {
            DB::table('persons')->upsert(
                array_values($personRowsByDoc),
                ['document_type_id', 'document_number'],
                ['neighborhood_id', 'first_name', 'last_name', 'phone', 'email', 'is_active', 'updated_at']
            );
        }

        $personIdsByDoc = empty($personRowsByDoc)
            ? collect()
            : DB::table('persons')
                ->where('document_type_id', $documentTypeId)
                ->whereIn('document_number', array_keys($personRowsByDoc))
                ->pluck('id', 'document_number');

        $candidateRows = [];
        foreach ($candidateMeta as $meta) {
            $personId = $personIdsByDoc[$meta['document_number']] ?? null;
            if (! $personId) {
                continue;
            }

            $candidateRows[] = [
                'election_id' => $electionId,
                'person_id' => (int) $personId,
                'slate_block_id' => $meta['slate_block_id'],
                'election_block_position_id' => $meta['election_block_position_id'],
                'ballot_number' => $meta['ballot_number'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($candidateRows)) {
            DB::table('candidates')->upsert(
                $candidateRows,
                ['election_id', 'person_id'],
                ['slate_block_id', 'election_block_position_id', 'ballot_number', 'is_active', 'updated_at']
            );
        }

        $pollingTableId = DB::table('polling_tables')
            ->where('election_id', $electionId)
            ->orderBy('id')
            ->value('id');

        if (! $pollingTableId) {
            $pollingTableId = DB::table('polling_tables')->insertGetId([
                'election_id' => $electionId,
                'code' => 'MESA-EXT-'.$electionId,
                'name' => 'Mesa Extendida '.$electionCode,
                'location' => 'Salon Comunal',
                'capacity' => 500,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('scrutiny_records')->upsert([
            [
                'election_id' => $electionId,
                'polling_table_id' => $pollingTableId,
                'created_by_user_id' => null,
                'record_number' => $recordNumber,
                'record_date' => now()->toDateString(),
                'record_time' => '18:30:00',
                'source_type' => 'manual',
                'status' => 'reviewed',
                'quorum_attendees' => 120,
                'total_attendees' => 180,
                'observations' => 'Acta extendida para pruebas de logica por bloques.',
                'metadata' => json_encode(['extended' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['election_id', 'record_number'], ['polling_table_id', 'source_type', 'status', 'record_date', 'record_time', 'quorum_attendees', 'total_attendees', 'observations', 'metadata', 'updated_at']);

        $scrutinyRecordId = DB::table('scrutiny_records')
            ->where('election_id', $electionId)
            ->where('record_number', $recordNumber)
            ->value('id');

        if (! $scrutinyRecordId) {
            return;
        }

        $resultRows = [];
        foreach ($electionBlocks as $blockIndex => $electionBlock) {
            $ebId = (int) $electionBlock->id;

            foreach ($activeSlates as $slateIndex => $slate) {
                $slateBlockId = $slateBlockBySlateAndEb[$slate->id.'|'.$ebId] ?? null;
                if (! $slateBlockId) {
                    continue;
                }

                $base = 90 + (($electionId + $ebId) % 13) * 3;
                $swing = ($blockIndex % 2 === 0)
                    ? (($slateIndex === 0) ? 26 : (($slateIndex === 1) ? 9 : 3))
                    : (($slateIndex === 0) ? 11 : (($slateIndex === 1) ? 24 : 4));
                $noise = (($electionId + $ebId + $slateIndex * 7) % 9);
                $votes = max(5, $base + $swing + $noise);

                $resultRows[] = [
                    'scrutiny_record_id' => $scrutinyRecordId,
                    'election_id' => $electionId,
                    'election_block_id' => $ebId,
                    'slate_block_id' => (int) $slateBlockId,
                    'scrutiny_extraction_id' => null,
                    'votes' => $votes,
                    'source_type' => 'manual',
                    'status' => 'reviewed',
                    'confidence_score' => null,
                    'notes' => 'Semilla extendida para pruebas de cuociente y ganadores por bloque.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($resultRows)) {
            DB::table('scrutiny_block_results')->upsert(
                $resultRows,
                ['scrutiny_record_id', 'election_block_id', 'slate_block_id'],
                ['votes', 'source_type', 'status', 'confidence_score', 'notes', 'updated_at']
            );
        }
    }

    private function buildBallotNumber(string $slateCode, int $positionNumber): string
    {
        // candidates.ballot_number is varchar(30): keep deterministic, readable and short.
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $slateCode) ?? 'PL');
        if ($normalized === '') {
            $normalized = 'PL';
        }

        $prefix = substr($normalized, 0, 20);
        $hash = strtoupper(substr(md5($normalized), 0, 4));
        $pos = str_pad((string) $positionNumber, 2, '0', STR_PAD_LEFT);

        return $prefix.'-'.$hash.'-'.$pos;
    }
}
