<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyExtraction;
use App\Models\ScrutinyRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ScrutinyExtractionController extends Controller
{
    /**
     * Lista las extracciones realizadas, permitiendo filtrar por calidad.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading de las relaciones clave
        $query = ScrutinyExtraction::with(['scrutinyRecord.pollingTable', 'createdByUser.person']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro de "Duda": Buscar lecturas con confianza menor al 85%
        if ($request->has('uncertain')) {
            $query->where('confidence_score', '<', 0.85)
                  ->where('source_type', 'ai');
        }

        $extractions = $query->latest()->paginate(20)->withQueryString();

        return view('admin.extractions.index', compact('extractions'));
    }

    /**
     * Muestra el detalle técnico de la extracción (JSON de la IA).
     */
    public function show(ScrutinyExtraction $scrutinyExtraction): View
    {
        // Cargamos el árbol de derivación (de dónde viene y qué generó después)
        $scrutinyExtraction->load([
            'scrutinyRecord',
            'scrutinyRecordFile',
            'basedOnExtraction',
            'derivedExtractions',
            'blockResults'
        ]);

        return view('admin.extractions.show', compact('scrutinyExtraction'));
    }

    /**
     * Almacena una nueva extracción (usualmente disparado por la API de Python o corrección manual).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scrutiny_record_id'      => 'required|exists:scrutiny_records,id',
            'scrutiny_record_file_id' => 'required|exists:scrutiny_record_files,id',
            'source_type'             => 'required|in:ai,manual,api',
            'raw_payload'             => 'required|array',
            'normalized_payload'      => 'required|array',
            'confidence_score'        => 'required|numeric|min:0|max:1',
            'based_on_extraction_id'  => 'nullable|exists:scrutiny_extractions,id',
        ]);

        // Auditoría: Autogestionar la ronda y el usuario
        $lastRound = ScrutinyExtraction::where('scrutiny_record_id', $request->scrutiny_record_id)->max('round_number') ?? 0;

        $extraction = ScrutinyExtraction::create(array_merge($validated, [
            'created_by_user_id' => Auth::id(),
            'round_number'       => $lastRound + 1,
            'status'             => 'pending_review',
            'engine_name'        => $request->engine_name ?? 'Generic-OCR',
            'engine_version'     => $request->engine_version ?? '1.0',
        ]));

        return redirect()->route('admin.extractions.show', $extraction)
            ->with('success', "Extracción (Ronda {$extraction->round_number}) registrada correctamente.");
    }

    /**
     * No permitimos 'edit' ni 'update' tradicional.
     * Si un dato está mal, se crea una NUEVA extracción basada en la anterior.
     */
    public function destroy(ScrutinyExtraction $scrutinyExtraction): RedirectResponse
    {
        // Protección: Si ya generó resultados de bloques, no se borra.
        if ($scrutinyExtraction->blockResults()->exists()) {
            return back()->with('error', 'Auditoría: Imposible eliminar. Esta extracción ya ha consolidado resultados en los bloques electorales.');
        }

        $scrutinyExtraction->delete();
        return redirect()->route('admin.extractions.index')
            ->with('success', 'Registro de extracción eliminado.');
    }
}
