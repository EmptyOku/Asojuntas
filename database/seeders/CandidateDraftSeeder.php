<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\CandidateDraft;
use App\Models\Election;
use App\Models\DocumentType;
use App\Models\Block;
use App\Models\Position;
use App\Models\Slate;
use App\Models\SlateBlock;
use App\Models\Person;

class CandidateDraftSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Obtener el contexto relacional
        $eleccion = Election::first();
        $docType = DocumentType::where('code', 'CC')->first();
        $bloque = Block::first();
        $posicion = Position::first();
        $plancha = Slate::first();
        $planchaBloque = SlateBlock::first();
        $personaExistente = Person::first(); // Para simular que la IA reconoció a alguien de la DB

        if (!$eleccion || !$docType) {
            $this->command->error("Faltan datos maestros (Elección o Tipo de Documento).");
            return;
        }

        $drafts = [
            // CASO 1: OCR Perfecto. Leyó todo claro y lo mapeó completo.
            [
                'election_id' => $eleccion->id,
                'block_id' => $bloque->id ?? null,
                'position_id' => $posicion->id ?? null,
                'slate_id' => $plancha->id ?? null,
                'slate_block_id' => $planchaBloque->id ?? null,
                'document_type_id' => $docType->id,
                'person_id' => null,
                'document_number' => '10203040',
                'first_name' => 'ANDRES',
                'middle_name' => 'FELIPE',
                'last_name' => 'GARCIA',
                'second_last_name' => 'LOPEZ',
                'phone' => '3001234567',
                'email' => 'andres@test.com',
                'source_type' => 'ocr',
                'confidence_score' => 98.50,
                'review_status' => 'pending',
                'is_processed' => false,
                'processed_at' => null,
                'notes' => 'Lectura OCR limpia y de alta confianza.'
            ],

            // CASO 2: OCR Borroso. Faltan datos (sin plancha, sin bloque) y baja confianza.
            [
                'election_id' => $eleccion->id,
                'block_id' => null, // No supo leer el bloque
                'position_id' => null,
                'slate_id' => null,
                'slate_block_id' => null,
                'document_type_id' => $docType->id,
                'person_id' => null,
                'document_number' => '5566', // Cédula incompleta
                'first_name' => 'MARIA',
                'middle_name' => null,
                'last_name' => 'ROD...', // Mancha en el papel
                'second_last_name' => null,
                'phone' => null,
                'email' => null,
                'source_type' => 'ocr',
                'confidence_score' => 45.20, // IA con dudas
                'review_status' => 'pending',
                'is_processed' => false,
                'processed_at' => null,
                'notes' => 'ADVERTENCIA: Imagen de baja calidad. Faltan dígitos en cédula.'
            ],

            // CASO 3: Match con BD. El OCR leyó una cédula que ya existe en `persons`.
            [
                'election_id' => $eleccion->id,
                'block_id' => $bloque->id ?? null,
                'position_id' => $posicion->id ?? null,
                'slate_id' => $plancha->id ?? null,
                'slate_block_id' => $planchaBloque->id ?? null,
                'document_type_id' => $docType->id,
                'person_id' => $personaExistente->id ?? null, // Match relacional
                'document_number' => $personaExistente->document_number ?? '999999',
                'first_name' => $personaExistente->first_name ?? 'CARLOS',
                'middle_name' => $personaExistente->middle_name,
                'last_name' => $personaExistente->last_name ?? 'PEREZ',
                'second_last_name' => $personaExistente->second_last_name,
                'phone' => $personaExistente->phone,
                'email' => null,
                'source_type' => 'ocr',
                'confidence_score' => 92.00,
                'review_status' => 'pending',
                'is_processed' => false,
                'processed_at' => null,
                'notes' => 'Match automático por número de documento con persona existente.'
            ],

            // CASO 4: Ingreso Manual. Alguien transcribió el acta a mano porque el OCR falló.
            [
                'election_id' => $eleccion->id,
                'block_id' => $bloque->id ?? null,
                'position_id' => $posicion->id ?? null,
                'slate_id' => $plancha->id ?? null,
                'slate_block_id' => $planchaBloque->id ?? null,
                'document_type_id' => $docType->id,
                'person_id' => null,
                'document_number' => '11223344',
                'first_name' => 'DIANA',
                'middle_name' => 'MARCELA',
                'last_name' => 'RUIZ',
                'second_last_name' => 'DIAZ',
                'phone' => '3159876543',
                'email' => 'diana.r@test.com',
                'source_type' => 'manual',
                'confidence_score' => 100.00, // Manual siempre es 100%
                'review_status' => 'approved', // Un humano ya lo validó
                'is_processed' => false,
                'processed_at' => null,
                'notes' => 'Transcripción manual realizada por digitador.'
            ],

            // CASO 5: Rechazado (Ruido). El OCR leyó una firma o sello como si fuera texto.
            [
                'election_id' => $eleccion->id,
                'block_id' => null,
                'position_id' => null,
                'slate_id' => null,
                'slate_block_id' => null,
                'document_type_id' => null,
                'person_id' => null,
                'document_number' => null,
                'first_name' => '///XX__', // Ruido del OCR
                'middle_name' => null,
                'last_name' => 'PRESID',
                'second_last_name' => null,
                'phone' => null,
                'email' => null,
                'source_type' => 'ocr',
                'confidence_score' => 12.50, // IA casi segura de que es basura
                'review_status' => 'rejected', // Descartado
                'is_processed' => true, // Ya se tomó una decisión
                'processed_at' => Carbon::now()->subDays(1),
                'notes' => 'Falso positivo del OCR. Se leyó un sello húmedo.'
            ],

            // CASO 6: Ya procesado y convertido en Candidato oficial.
            [
                'election_id' => $eleccion->id,
                'block_id' => $bloque->id ?? null,
                'position_id' => $posicion->id ?? null,
                'slate_id' => $plancha->id ?? null,
                'slate_block_id' => $planchaBloque->id ?? null,
                'document_type_id' => $docType->id,
                'person_id' => null,
                'document_number' => '88776655',
                'first_name' => 'HECTOR',
                'middle_name' => null,
                'last_name' => 'SALAZAR',
                'second_last_name' => null,
                'phone' => null,
                'email' => null,
                'source_type' => 'ocr',
                'confidence_score' => 89.00,
                'review_status' => 'approved',
                'is_processed' => true, // Ya pasó a la tabla candidates
                'processed_at' => Carbon::now(),
                'notes' => 'Revisado y exportado exitosamente a la tabla de candidatos.'
            ],
        ];

        foreach ($drafts as $draft) {
            CandidateDraft::create($draft);
        }

        $this->command->info('CandidateDrafts sembrados: 6 casos de prueba (OCR, Manual, Ruido, Match).');
    }
}
