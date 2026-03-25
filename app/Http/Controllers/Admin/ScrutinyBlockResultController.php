<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyBlockResult;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ScrutinyBlockResultController extends Controller
{
    /**
     * Monitor de Votación: Lista los votos registrados con filtros de seguridad.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading profundo para evitar el problema N+1.
        $query = ScrutinyBlockResult::with([
            'scrutinyRecord.pollingTable',
            'election',
            'electionBlock.block',
            'slateBlock.slate'
        ]);

        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // FILTRO DE SEGURIDAD: Resultados con baja confianza de la IA (OCR de Python).
        // Esto permite al administrador auditar registros donde la IA tuvo dudas.
        if ($request->has('flagged')) {
            $query->where('confidence_score', '<', 0.80)
                  ->where('source_type', 'ai');
        }

        $results = $query->latest()->paginate(50)->withQueryString();
        $elections = Election::orderBy('name')->get();

        return view('admin.scrutiny_results.index', compact('results', 'elections'));
    }

    /**
     * Muestra el detalle forense de un resultado y su extracción original.
     */
    public function show(ScrutinyBlockResult $scrutinyBlockResult): View
    {
        $scrutinyBlockResult->load([
            'scrutinyRecord.pollingTable',
            'scrutinyExtraction',
            'electionBlock.block',
            'slateBlock.slate'
        ]);

        return view('admin.scrutiny_results.show', compact('scrutinyBlockResult'));
    }

    /**
     * Acción de Auditoría: Actualiza el estado o añade notas sin alterar el conteo.
     */
    public function update(Request $request, ScrutinyBlockResult $scrutinyBlockResult): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:validated,disputed,rejected',
            'notes'  => 'nullable|string|max:500',
        ]);

        // Nota Técnica: Se prohíbe la edición del campo 'votes' por seguridad.
        // Si hay un error en el conteo, se debe impugnar el ScrutinyRecord completo.
        $scrutinyBlockResult->update([
            'status' => $validated['status'],
            'notes'  => trim($scrutinyBlockResult->notes . "\nAuditoría (" . now() . "): " . $validated['notes'])
        ]);

        return back()->with('success', 'El estado del resultado ha sido actualizado para auditoría.');
    }
}
