<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ElectionMvpSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 1. Apuntamos ESTRICTAMENTE a 10 de Mayo
        $neighborhoodId = DB::table('neighborhoods')
            ->where('code', 'COM02-10-DE-MAYO')
            ->value('id');

        $documentTypeId = DB::table('document_types')->where('code', 'CC')->value('id');

        // 2. Buscamos el admin que realmente existe
        $adminId = DB::table('users')->where('username', 'admin')->value('id');

        if (! $neighborhoodId || ! $documentTypeId) {
            $this->command->error("Falta el barrio 10 de Mayo o el tipo de documento CC.");
            return;
        }

        DB::table('elections')->upsert([
            [
                'code' => 'JAC-10MAYO-2026',
                'neighborhood_id' => $neighborhoodId,
                'name' => 'Eleccion JAC 10 de Mayo 2026',
                'election_date' => '2026-04-26',
                'period_year' => 2026,
                'is_active' => true,
                'description' => 'Escenario demo MVP para flujo completo de votacion en 10 de Mayo.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], ['neighborhood_id', 'name', 'election_date', 'period_year', 'is_active', 'description', 'updated_at']);

        $electionId = DB::table('elections')->where('code', 'JAC-10MAYO-2026')->value('id');

        // Mantenemos tu lógica estricta de bloques y cargos
        $blocks = DB::table('blocks')->whereIn('code', ['DIR', 'DEL', 'FIS'])->pluck('id', 'code');
        $positions = DB::table('positions')->whereIn('code', ['DIR_PRES', 'DIR_VICE', 'DIR_TESO', 'DEL_1', 'DEL_2', 'FIS_PRIN'])->get(['id', 'block_id', 'code']);

        $electionBlocksRows = [];
        foreach (['DIR', 'DEL', 'FIS'] as $blockCode) {
            $blockId = $blocks[$blockCode] ?? null;
            if (! $blockId) continue;

            $electionBlocksRows[] = [
                'election_id' => $electionId,
                'block_id' => $blockId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($electionBlocksRows)) {
            DB::table('election_blocks')->upsert($electionBlocksRows, ['election_id', 'block_id'], ['is_active', 'updated_at']);
        }

        $electionBlocks = DB::table('election_blocks')
            ->where('election_id', $electionId)
            ->whereIn('block_id', $blocks->values())
            ->get(['id', 'block_id']);

        $electionBlockByCode = [];
        foreach ($electionBlocks as $row) {
            $code = $blocks->search($row->block_id);
            if ($code !== false) $electionBlockByCode[$code] = $row->id;
        }

        $electionBlockPositionRows = [];
        foreach ($positions as $position) {
            $blockCode = $blocks->search($position->block_id);
            if ($blockCode === false) continue;

            $electionBlockId = $electionBlockByCode[$blockCode] ?? null;
            if (! $electionBlockId) continue;

            $electionBlockPositionRows[] = [
                'election_block_id' => $electionBlockId,
                'block_id' => $position->block_id,
                'position_id' => $position->id,
                'vacancies' => $position->code === 'DEL_1' || $position->code === 'DEL_2' ? 2 : 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($electionBlockPositionRows)) {
            DB::table('election_block_positions')->upsert($electionBlockPositionRows, ['election_block_id', 'position_id'], ['block_id', 'vacancies', 'is_active', 'updated_at']);
        }

        DB::table('slates')->upsert([
            ['election_id' => $electionId, 'code' => 'P1', 'name' => 'Plancha Unidad Comunal', 'description' => 'Plancha demo 1', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['election_id' => $electionId, 'code' => 'P2', 'name' => 'Plancha Renovacion Barrial', 'description' => 'Plancha demo 2', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['election_id', 'code'], ['name', 'description', 'is_active', 'updated_at']);

        $slates = DB::table('slates')->where('election_id', $electionId)->whereIn('code', ['P1', 'P2'])->pluck('id', 'code');

        $slateBlockRows = [];
        foreach (['P1', 'P2'] as $slateCode) {
            $slateId = $slates[$slateCode] ?? null;
            if (! $slateId) continue;

            foreach (['DIR', 'DEL', 'FIS'] as $blockCode) {
                $electionBlockId = $electionBlockByCode[$blockCode] ?? null;
                if (! $electionBlockId) continue;

                $slateBlockRows[] = [
                    'election_id' => $electionId,
                    'slate_id' => $slateId,
                    'election_block_id' => $electionBlockId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($slateBlockRows)) {
            DB::table('slate_blocks')->upsert($slateBlockRows, ['slate_id', 'election_block_id'], ['election_id', 'is_active', 'updated_at']);
        }

        $peopleSeed = [
            ['doc' => '100000001', 'first' => 'Carlos', 'last' => 'Mejia', 'slate' => 'P1', 'position' => 'DIR_PRES', 'ballot' => 'P1-01'],
            ['doc' => '100000002', 'first' => 'Ana', 'last' => 'Lopez', 'slate' => 'P1', 'position' => 'DIR_VICE', 'ballot' => 'P1-02'],
            ['doc' => '100000003', 'first' => 'Jorge', 'last' => 'Ruiz', 'slate' => 'P1', 'position' => 'DIR_TESO', 'ballot' => 'P1-03'],
            ['doc' => '100000004', 'first' => 'Marta', 'last' => 'Perez', 'slate' => 'P1', 'position' => 'DEL_1', 'ballot' => 'P1-04'],
            ['doc' => '100000005', 'first' => 'Luis', 'last' => 'Diaz', 'slate' => 'P1', 'position' => 'DEL_2', 'ballot' => 'P1-05'],
            ['doc' => '100000006', 'first' => 'Diana', 'last' => 'Gomez', 'slate' => 'P1', 'position' => 'FIS_PRIN', 'ballot' => 'P1-06'],
        ];

        $personRows = [];
        foreach ($peopleSeed as $person) {
            $personRows[] = [
                'document_type_id' => $documentTypeId,
                'neighborhood_id' => $neighborhoodId,
                'document_number' => $person['doc'],
                'first_name' => $person['first'],
                'middle_name' => null,
                'last_name' => $person['last'],
                'second_last_name' => null,
                'birth_date' => null,
                'phone' => null,
                'email' => strtolower($person['first']).'.'.strtolower($person['last']).'@demo.local',
                'address' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('persons')->upsert($personRows, ['document_type_id', 'document_number'], ['neighborhood_id', 'first_name', 'last_name', 'email', 'is_active', 'updated_at']);

        $personsByDoc = DB::table('persons')->where('document_type_id', $documentTypeId)->whereIn('document_number', array_column($peopleSeed, 'doc'))->pluck('id', 'document_number');
        $positionByCode = DB::table('positions')->whereIn('code', ['DIR_PRES', 'DIR_VICE', 'DIR_TESO', 'DEL_1', 'DEL_2', 'FIS_PRIN'])->pluck('id', 'code');
        $ebpByPositionCode = DB::table('election_block_positions')->whereIn('position_id', $positionByCode->values())->pluck('id', 'position_id');

        $slateBlocks = DB::table('slate_blocks')
            ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
            ->join('election_blocks', 'election_blocks.id', '=', 'slate_blocks.election_block_id')
            ->join('blocks', 'blocks.id', '=', 'election_blocks.block_id')
            ->where('slate_blocks.election_id', $electionId)
            ->select('slate_blocks.id', 'slates.code as slate_code', 'blocks.code as block_code')
            ->get();

        $slateBlockMap = [];
        foreach ($slateBlocks as $sb) {
            $slateBlockMap[$sb->slate_code.'|'.$sb->block_code] = $sb->id;
        }

        $positionToBlockCode = [
            'DIR_PRES' => 'DIR', 'DIR_VICE' => 'DIR', 'DIR_TESO' => 'DIR',
            'DEL_1' => 'DEL', 'DEL_2' => 'DEL', 'FIS_PRIN' => 'FIS',
        ];

        $candidateRows = [];
        foreach ($peopleSeed as $seed) {
            $personId = $personsByDoc[$seed['doc']] ?? null;
            $positionId = $positionByCode[$seed['position']] ?? null;
            $electionBlockPositionId = $positionId ? ($ebpByPositionCode[$positionId] ?? null) : null;
            $blockCode = $positionToBlockCode[$seed['position']] ?? null;
            $slateBlockId = $blockCode ? ($slateBlockMap[$seed['slate'].'|'.$blockCode] ?? null) : null;

            if (! $personId || ! $electionBlockPositionId || ! $slateBlockId) continue;

            $candidateRows[] = [
                'election_id' => $electionId,
                'person_id' => $personId,
                'slate_block_id' => $slateBlockId,
                'election_block_position_id' => $electionBlockPositionId,
                'ballot_number' => $seed['ballot'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($candidateRows)) {
            DB::table('candidates')->upsert($candidateRows, ['election_id', 'person_id'], ['slate_block_id', 'election_block_position_id', 'ballot_number', 'is_active', 'updated_at']);
        }

        DB::table('polling_tables')->upsert([
            [
                'election_id' => $electionId,
                'code' => 'MESA-001',
                'name' => 'Mesa 001 10 de Mayo',
                'location' => 'Salon Comunal',
                'capacity' => 500,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['election_id', 'code'], ['name', 'location', 'capacity', 'is_active', 'updated_at']);

        $pollingTableId = DB::table('polling_tables')->where('election_id', $electionId)->where('code', 'MESA-001')->value('id');

        DB::table('scrutiny_records')->upsert([
            [
                'election_id' => $electionId,
                'polling_table_id' => $pollingTableId,
                'created_by_user_id' => $adminId, // Usamos el admin correcto
                'record_number' => 'ACTA-001',
                'record_date' => '2026-04-26',
                'record_time' => '18:30:00',
                'source_type' => 'manual',
                'status' => 'pending_review',
                'quorum_attendees' => 120,
                'total_attendees' => 180,
                'observations' => 'Acta demo 10 de Mayo.',
                'metadata' => json_encode(['demo' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['election_id', 'record_number'], ['polling_table_id', 'created_by_user_id', 'record_date', 'record_time', 'source_type', 'status', 'quorum_attendees', 'total_attendees', 'observations', 'metadata', 'updated_at']);
    }
}
