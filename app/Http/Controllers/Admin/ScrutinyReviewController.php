<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyReview;
use App\Models\ScrutinyExtraction;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScrutinyReviewController extends Controller
{
    /**
     * Bandeja de Auditoría: Lista todas las revisiones humanas realizadas.
     */
    public function index(Request $request): View
    {
        // Eager loading para ver quién revisó y a qué acta pertenece
        $query = ScrutinyReview::with([
            'scrutinyRecord.pollingTable',
            'scrutinyExtraction',
            'reviewedByUser.person'
        ]);

        if ($request->filled('decision')) {
            $query->where('decision', $request->decision);
        }

        $reviews = $query->latest('reviewed_at')->paginate(25)->withQueryString();

        return view('admin.reviews.index', compact('reviews'));
    }

    /**
     * Muestra el detalle forense de la revisión (El "Diff" entre la IA y el Humano).
     */
    public function show(ScrutinyReview $scrutinyReview): View
    {
        $scrutinyReview->load(['scrutinyRecord', 'scrutinyExtraction', 'reviewedByUser.person']);
        return view('admin.reviews.show', compact('scrutinyReview'));
    }

    /**
     * Almacena el veredicto humano. Se ejecuta dentro de una transacción para
     * actualizar el estado de la extracción al mismo tiempo.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scrutiny_record_id'     => 'required|exists:scrutiny_records,id',
            'scrutiny_extraction_id' => 'required|exists:scrutiny_extractions,id',
            'decision'               => 'required|in:approved,rejected,modified',
            'comments'               => 'required|string|max:1000', // Justificación obligatoria
            'changes_payload'        => 'nullable|array',
        ]);

        // Transacción Atómica: Guardamos la revisión y actualizamos la extracción.
        DB::transaction(function () use ($validated, $request) {

            // 1. Guardar el registro de auditoría
            $review = ScrutinyReview::create(array_merge($validated, [
                'reviewed_by_user_id' => Auth::id(),
                'reviewed_at'         => now(),
            ]));

            // 2. Actualizar el estado del trabajo de la IA
            $extraction = ScrutinyExtraction::findOrFail($validated['scrutiny_extraction_id']);

            $newExtractionStatus = match($validated['decision']) {
                'approved' => 'verified',
                'rejected' => 'discarded',
                'modified' => 'corrected',
                default    => 'pending_review',
            };

            $extraction->update([
                'status' => $newExtractionStatus,
                'notes'  => trim($extraction->notes . "\n[Revisión " . $review->id . "]: " . $validated['decision'])
            ]);
        });

        return back()->with('success', 'La revisión ha sido registrada y el estado de la extracción ha sido actualizado.');
    }

    /**
     * ACCIÓN BLOQUEADA: Destrucción de evidencia penalizada.
     */
    public function destroy(ScrutinyReview $scrutinyReview)
    {
        // Auditoría Extrema: NUNCA se borra una revisión.
        abort(403, 'AUDITORÍA: Violación de protocolo. La destrucción de registros de revisión humana está estrictamente prohibida por reglas de cadena de custodia.');
    }
}
