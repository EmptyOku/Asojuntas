<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ScrutinyRecord;
use App\Models\ScrutinyExtraction;
use App\Models\User;

class ScrutinyExtractionSeeder extends Seeder
{
    public function run(): void
    {
        $acta = ScrutinyRecord::first();
        $usuarioApi = User::where('username', 'admin')->first(); // Simulamos que el admin lanzó el proceso

        if (!$acta) {
            $this->command->error("No hay actas para asociar las extracciones OCR.");
            return;
        }

        $extracciones = [
            // CASO 1: Extracción exitosa (Ronda 1)
            [
                'scrutiny_record_id' => $acta->id,
                'scrutiny_record_file_id' => null, // Opcional si no tenemos la tabla de archivos lista
                'based_on_extraction_id' => null,
                'created_by_user_id' => $usuarioApi->id ?? null,
                'source_type' => 'ocr',
                'engine_name' => 'AWS_Textract',
                'engine_version' => 'v2.1',
                'confidence_score' => 95.50,
                'status' => 'processed',
                'round_number' => 1,
                'raw_payload' => json_encode([
                    'Blocks' => [
                        ['BlockType' => 'LINE', 'Text' => 'PRESIDENTE: ANDRES GARCIA', 'Confidence' => 98.1],
                        ['BlockType' => 'LINE', 'Text' => 'CC 10203040', 'Confidence' => 99.0]
                    ]
                ]),
                'normalized_payload' => json_encode([
                    'position' => 'PRES',
                    'first_name' => 'ANDRES',
                    'last_name' => 'GARCIA',
                    'document' => '10203040'
                ]),
                'notes' => 'Primera pasada del OCR. Datos legibles.'
            ],

            // CASO 2: Extracción defectuosa (Ronda 1)
            [
                'scrutiny_record_id' => $acta->id,
                'scrutiny_record_file_id' => null,
                'based_on_extraction_id' => null,
                'created_by_user_id' => $usuarioApi->id ?? null,
                'source_type' => 'ocr',
                'engine_name' => 'AWS_Textract',
                'engine_version' => 'v2.1',
                'confidence_score' => 45.20,
                'status' => 'pending_review', // Requiere que un humano lo mire
                'round_number' => 1,
                'raw_payload' => json_encode([
                    'Blocks' => [
                        ['BlockType' => 'LINE', 'Text' => 'VICEP***: MAR/A R0D...', 'Confidence' => 42.1]
                    ]
                ]),
                'normalized_payload' => null, // No se pudo normalizar automáticamente
                'notes' => 'El OCR detectó manchas de tinta.'
            ],

            // CASO 3: Corrección Humana (Ronda 2, basada en el CASO 2)
            // Se insertará dinámicamente abajo
        ];

        // Insertamos las primeras dos
        $ext1 = ScrutinyExtraction::create($extracciones[0]);
        $ext2 = ScrutinyExtraction::create($extracciones[1]);

        // Insertamos la Ronda 2 (Un humano arregló lo que la IA no pudo leer)
        ScrutinyExtraction::create([
            'scrutiny_record_id' => $acta->id,
            'based_on_extraction_id' => $ext2->id, // Trazabilidad: Esta corrige a la extracción 2
            'created_by_user_id' => $usuarioApi->id ?? null,
            'source_type' => 'manual', // Un humano digitó esto
            'engine_name' => null,
            'engine_version' => null,
            'confidence_score' => 100.00,
            'status' => 'processed',
            'round_number' => 2,
            'raw_payload' => null,
            'normalized_payload' => json_encode([
                'position' => 'VICE',
                'first_name' => 'MARIA',
                'last_name' => 'RODRIGUEZ',
                'document' => '5566'
            ]),
            'notes' => 'Digitador corrigió los datos ilegibles del acta manual.'
        ]);

        $this->command->info('ScrutinyExtractions sembradas: Payloads OCR reales y trazabilidad de rondas.');
    }
}
