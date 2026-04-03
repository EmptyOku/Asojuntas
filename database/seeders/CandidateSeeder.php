<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Election;
use App\Models\DocumentType;
use App\Models\Person;
use App\Models\Candidate;
use App\Models\SlateBlock;
use App\Models\ElectionBlockPosition;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $eleccion = Election::first();
        $tipoDoc = DocumentType::where('code', 'CC')->first();
        $slateBlock = SlateBlock::where('election_id', $eleccion->id)->first();

        if (!$eleccion || !$slateBlock) {
            $this->command->error("Faltan datos de Elección o Plancha.");
            return;
        }

        // Consultas estrictas de cargos
        $ebpPresidente = ElectionBlockPosition::where('election_block_id', $slateBlock->election_block_id)
            ->whereHas('position', fn($q) => $q->where('code', 'PRES'))->first();

        $ebpVice = ElectionBlockPosition::where('election_block_id', $slateBlock->election_block_id)
            ->whereHas('position', fn($q) => $q->where('code', 'VICE'))->first();

        $ebpTesorero = ElectionBlockPosition::where('election_block_id', $slateBlock->election_block_id)
            ->whereHas('position', fn($q) => $q->where('code', 'TESO'))->first();

        $candidatosData = [];

        if ($ebpPresidente) {
            $candidatosData[] = [
                'pos_id' => $ebpPresidente->id,
                'doc' => '10203040', 'name1' => 'ANDRES', 'name2' => 'FELIPE', 'last1' => 'GARCIA', 'last2' => 'LOPEZ', 'ballot' => '1'
            ];
        }

        if ($ebpVice) {
            $candidatosData[] = [
                'pos_id' => $ebpVice->id,
                'doc' => '55667788', 'name1' => 'MARIA', 'name2' => 'ANTONIA', 'last1' => 'RODRIGUEZ', 'last2' => 'PENA', 'ballot' => '1'
            ];
        }

        if ($ebpTesorero) {
            $candidatosData[] = [
                'pos_id' => $ebpTesorero->id,
                'doc' => '11223344', 'name1' => 'DIANA', 'name2' => 'MARCELA', 'last1' => 'RUIZ', 'last2' => 'DIAZ', 'ballot' => '1'
            ];
        }

        foreach ($candidatosData as $data) {
            // 1. Creación exhaustiva de la persona (Mismas columnas que permitimos en Drafts)
            $persona = Person::firstOrCreate(
                ['document_number' => $data['doc']],
                [
                    'document_type_id' => $tipoDoc->id,
                    'neighborhood_id' => $eleccion->neighborhood_id,
                    'first_name' => $data['name1'],
                    'middle_name' => $data['name2'],
                    'last_name' => $data['last1'],
                    'second_last_name' => $data['last2'],
                    'phone' => '300' . rand(1111111, 9999999),
                    'is_active' => true,
                ]
            );

            // 2. Vinculación estricta a la elección
            Candidate::firstOrCreate(
                [
                    'election_id' => $eleccion->id,
                    'person_id' => $persona->id,
                ],
                [
                    'slate_block_id' => $slateBlock->id,
                    'election_block_position_id' => $data['pos_id'],
                    'ballot_number' => $data['ballot'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Candidatos oficiales sembrados con datos biográficos completos.');
    }
}
