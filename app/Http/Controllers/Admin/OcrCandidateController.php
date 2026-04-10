<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Slate;
use App\Models\Candidate;

class OcrCandidateController extends Controller
{
    public function process(Request $request)
    {
        $request->validate([
            'slate_id'   => 'required|exists:slates,id',
            'imagenes.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120'
        ]);

        $slateId = $request->slate_id;
        
        // 1. Crear directorio temporal para este lote de fotos
        $tempDir = 'temp_ocr_' . uniqid();
        Storage::disk('local')->makeDirectory($tempDir);
        $fullTempPath = storage_path('app/' . $tempDir);

        // 2. Mover las imágenes al directorio temporal
        foreach ($request->file('imagenes') as $imagen) {
            $imagen->move($fullTempPath, $imagen->getClientOriginalName());
        }

        // 3. Ejecutar el script de Python (Ajusta la ruta de Python si usas un entorno virtual .venv)
        $scriptPath = base_path('data_extraction/extraer_candidatos.py');
        $pythonPath = base_path('.venv/Scripts/python'); // Ruta en Windows
        
        $process = new Process([$pythonPath, $scriptPath, $fullTempPath]);
        $process->setTimeout(180); // Dar tiempo suficiente a Bedrock
        $process->run();

        if (!$process->isSuccessful()) {
            Storage::disk('local')->deleteDirectory($tempDir);
            throw new ProcessFailedException($process);
        }

        // 4. Capturar la salida de la consola de Python
        $output = $process->getOutput();
        
        // Extraer solo el JSON (ignorando los logs de [INFO] que imprime el script)
        preg_match('/\{.*\}/s', $output, $matches);
        $jsonString = $matches[0] ?? '{}';
        
        $resultadoOcr = json_decode($jsonString, true);

        // 5. Guardar en Base de Datos
        if (isset($resultadoOcr['status']) && $resultadoOcr['status'] === '200_OK') {
            DB::beginTransaction();
            try {
                // Obtener los bloques de esta plancha para hacer el cruce
                $slate = Slate::with('slateBlocks')->findOrFail($slateId);
                
                foreach ($resultadoOcr['payload'] as $bloquePython) {
                    $numeroBloque = $bloquePython['bloque_num'];
                    
                    // Buscar el SlateBlock de Laravel que corresponde al bloque 1, 2, 3 o 4 de Python
                    // Nota: Aquí asumo que tienes una forma de relacionar el número con el election_block
                    $slateBlock = $slate->slateBlocks->where('internal_block_number', $numeroBloque)->first();
                    
                    if ($slateBlock && !empty($bloquePython['candidatos'])) {
                        foreach ($bloquePython['candidatos'] as $candidatoPython) {
                            Candidate::create([
                                'slate_block_id' => $slateBlock->id,
                                'cargo'          => $candidatoPython['cargo'],
                                'nombre'         => $candidatoPython['nombre'],
                                'identificacion' => $candidatoPython['identificacion'],
                                'celular'        => $candidatoPython['celular'],
                                'correo'         => $candidatoPython['correo'],
                            ]);
                        }
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Storage::disk('local')->deleteDirectory($tempDir);
                return response()->json(['error' => 'Fallo al guardar en BD: ' . $e->getMessage()], 500);
            }
        }

        // 6. Limpiar archivos temporales
        Storage::disk('local')->deleteDirectory($tempDir);

        return response()->json([
            'message' => 'Candidatos procesados exitosamente',
            'data'    => $resultadoOcr
        ]);
    }
}