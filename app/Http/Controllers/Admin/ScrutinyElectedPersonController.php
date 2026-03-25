<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyElectedPerson;
use App\Models\Person;
use App\Models\SeatAllocation;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ScrutinyElectedPersonController extends Controller
{
    /**
     * Monitor de Proclamaciones: Lista los electos extraídos por la IA.
     */
    public function index(Request $request): View
    {
        $query = ScrutinyElectedPerson::with([
            'scrutinyRecord.pollingTable',
            'election',
            'electionBlockPosition.position',
            'documentType'
        ]);

        // Filtro manual de estado al no tener Scope
        if ($request->filled('status')) {
            $query->where('review_status', $request->status);
        } else {
            $query->where('review_status', 'pending');
        }

        // Alerta de baja confianza (Lectura difícil del OCR)
        if ($request->has('flagged')) {
            $query->where('confidence_score', '<', 0.70);
        }

        $electedPeople = $query->latest()->paginate(25)->withQueryString();

        return view('admin.scrutiny_elected.index', compact('electedPeople'));
    }

    /**
     * Vista de Verificación: Compara la lectura de Python con la firma/foto.
     */
    public function show(ScrutinyElectedPerson $scrutinyElectedPerson): View
    {
        $scrutinyElectedPerson->load(['scrutinyRecord', 'electionBlockPosition.position', 'person']);
        return view('admin.scrutiny_elected.show', compact('scrutinyElectedPerson'));
    }

    /**
     * Proceso de Validación: Cruza el electo del acta con la tabla de Personas.
     */
    public function update(Request $request, ScrutinyElectedPerson $scrutinyElectedPerson): RedirectResponse
    {
        if ($scrutinyElectedPerson->review_status !== 'pending') {
            return back()->with('error', 'Auditoría: Este registro ya fue verificado y no puede reabrirse.');
        }

        $validated = $request->validate([
            'document_number' => 'required|string',
            'review_status'   => 'required|in:verified,rejected,disputed',
            'notes'           => 'nullable|string|max:500'
        ]);

        DB::transaction(function () use ($scrutinyElectedPerson, $validated) {
            // Si se verifica, intentamos vincularlo a una Persona oficial
            if ($validated['review_status'] === 'verified') {
                $person = Person::where('document_number', $validated['document_number'])
                    ->where('document_type_id', $scrutinyElectedPerson->document_type_id)
                    ->first();

                if ($person) {
                    $scrutinyElectedPerson->person_id = $person->id;
                }
            }

            $scrutinyElectedPerson->update([
                'review_status' => $validated['review_status'],
                'notes'         => trim($scrutinyElectedPerson->notes . "\n[" . now() . "]: " . $validated['notes'])
            ]);
        });

        return redirect()->route('admin.scrutiny-elected.index')
            ->with('success', 'Verificación de acta procesada correctamente.');
    }
}
