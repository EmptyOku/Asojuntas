<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CandidateDraft;
use App\Models\Candidate;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;


class CandidateDraftController extends Controller
{
    /**
     * Lista los borradores. Filtramos manualmente al no tener Scope.
     */
    public function index(Request $request): View
    {
        $query = CandidateDraft::with(['election', 'block', 'position', 'slate']);

        // Filtro manual por defecto: Solo mostrar lo que falta por procesar
        if (!$request->has('show_all')) {
            $query->where('review_status', 'pending')
                  ->where('is_processed', false);
        }

        // Filtro de baja confianza para auditoría de errores del OCR de Python
        if ($request->has('low_confidence')) {
            $query->where('confidence_score', '<', 0.75);
        }

        $drafts = $query->latest()->paginate(20)->withQueryString();

        return view('admin.candidate_drafts.index', compact('drafts'));
    }

    /**
     * Muestra el borrador para corrección humana.
     */
    public function edit(CandidateDraft $candidateDraft): View
    {
        // Auditoría: Bloqueo si el registro ya fue cerrado.
        if ($candidateDraft->is_processed) {
            return redirect()->route('admin.candidate-drafts.index')
                ->with('error', 'Este registro ya fue procesado y no permite edición.');
        }

        return view('admin.candidate_drafts.edit', compact('candidateDraft'));
    }

    /**
     * Proceso de "Promoción": De borrador de IA a Candidato Real.
     */
    public function update(Request $request, CandidateDraft $candidateDraft): RedirectResponse
    {
        // Verificación de seguridad manual
        if ($candidateDraft->is_processed) {
            return back()->with('error', 'Operación denegada: El registro ya está finalizado.');
        }

        $validated = $request->validate([
            'document_number' => 'required|string',
            'first_name'      => 'required|string',
            'last_name'       => 'required|string',
            'review_status'   => 'required|in:approved,rejected',
        ]);

        if ($validated['review_status'] === 'rejected') {
            $candidateDraft->update([
                'review_status' => 'rejected',
                'is_processed'  => true,
                'notes'         => $request->notes ?? 'Rechazado manualmente por el administrador.'
            ]);
            return redirect()->route('admin.candidate-drafts.index')->with('info', 'Borrador descartado.');
        }

        // Transacción Atómica: Evita datos huérfanos si falla la BD
        DB::transaction(function () use ($candidateDraft, $validated) {
            // 1. Buscamos si la persona ya existe o la creamos con los datos corregidos
            $person = Person::firstOrCreate(
                [
                    'document_number'  => $validated['document_number'],
                    'document_type_id' => $candidateDraft->document_type_id
                ],
                [
                    'first_name' => $validated['first_name'],
                    'last_name'  => $validated['last_name'],
                    'email'      => $candidateDraft->email,
                    'phone'      => $candidateDraft->phone,
                    'is_active'  => true
                ]
            );

            // 2. Creamos el registro oficial en la tabla de Candidatos
            Candidate::create([
                'election_id'                => $candidateDraft->election_id,
                'person_id'                  => $person->id,
                'slate_block_id'             => $candidateDraft->slate_block_id,
                'election_block_position_id' => $candidateDraft->position_id,
                'is_active'                  => true,
            ]);

            // 3. Cerramos el borrador para que no vuelva a aparecer en el listado
            $candidateDraft->update([
                'person_id'     => $person->id,
                'review_status' => 'approved',
                'is_processed'  => true,
                'processed_at'  => now()
            ]);
        });

        return redirect()->route('admin.candidate-drafts.index')
            ->with('success', 'El borrador de la IA ha sido validado y el candidato ha sido creado.');
    }
}
