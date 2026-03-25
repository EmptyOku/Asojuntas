<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyRecord;
use App\Models\Election;
use App\Models\PollingTable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ScrutinyRecordController extends Controller
{
    /**
     * Bandeja principal de Actas. Lista todos los documentos ingresados.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading de la mesa y la elección para optimización.
        $query = ScrutinyRecord::with(['election', 'pollingTable', 'createdByUser.person']);

        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        $records = $query->latest('record_date')->latest('record_time')->paginate(20)->withQueryString();
        $elections = Election::orderBy('name')->get();

        return view('admin.scrutiny_records.index', compact('records', 'elections'));
    }

    /**
     * Formulario para registro manual (Fallback por si la IA de Python falla).
     */
    public function create(): View
    {
        $elections = Election::orderBy('name')->get();
        // En un sistema real, las mesas se filtrarían por AJAX según la elección seleccionada.
        $pollingTables = PollingTable::orderBy('code')->get();

        return view('admin.scrutiny_records.create', compact('elections', 'pollingTables'));
    }

    /**
     * Almacena la cabecera del acta con validación matemática estricta.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'election_id'      => 'required|exists:elections,id',
            'polling_table_id' => 'required|exists:polling_tables,id',
            'record_number'    => 'required|string|max:50',
            'record_date'      => 'required|date|before_or_equal:today',
            'record_time'      => 'required|date_format:H:i',
            'source_type'      => 'required|in:manual,ai,api',
            'status'           => 'required|string|max:50',
            // El quórum no puede superar el total de asistentes lógicamente
            'total_attendees'  => 'required|integer|min:1',
            'quorum_attendees' => 'required|integer|min:0|lte:total_attendees',
            'observations'     => 'nullable|string|max:1000',
        ]);

        $validated['created_by_user_id'] = Auth::id();
        $validated['metadata'] = []; // Inicializamos el JSON vacío

        $record = ScrutinyRecord::create($validated);

        return redirect()->route('admin.scrutiny-records.show', $record)
            ->with('success', 'Cabecera del acta de escrutinio registrada. Proceda a adjuntar las imágenes y resultados.');
    }

    /**
     * Muestra el "Dashboard" completo del acta (Fotos, Resultados, Extracciones).
     */
    public function show(ScrutinyRecord $scrutinyRecord): View
    {
        // Deep Loading masivo para construir la vista 360 del acta
        $scrutinyRecord->load([
            'pollingTable',
            'createdByUser.person',
            'files', // Las fotos del acta
            'extractions.createdByUser', // El historial de lecturas de la IA
            'blockResults.slateBlock.slate', // Los votos registrados
            'electedPeople.person' // Los ganadores directos
        ]);

        return view('admin.scrutiny_records.show', compact('scrutinyRecord'));
    }

    /**
     * Actualiza metadatos y estado. No permite alterar IDs críticos.
     */
    public function update(Request $request, ScrutinyRecord $scrutinyRecord): RedirectResponse
    {
        // Auditoría: Una vez el acta está consolidada, no se toca.
        if ($scrutinyRecord->status === 'consolidated') {
            return back()->with('error', 'Auditoría: El acta ya forma parte de los resultados finales consolidados. Para modificarla, primero debe anular la consolidación general.');
        }

        $validated = $request->validate([
            'status'           => 'required|string|max:50',
            'total_attendees'  => 'required|integer|min:1',
            'quorum_attendees' => 'required|integer|min:0|lte:total_attendees',
            'observations'     => 'nullable|string|max:1000',
        ]);

        $scrutinyRecord->update($validated);

        return redirect()->route('admin.scrutiny-records.show', $scrutinyRecord)
            ->with('success', 'Cabecera del acta actualizada.');
    }

    /**
     * Elimina el acta SÓLO si está vacía.
     */
    public function destroy(ScrutinyRecord $scrutinyRecord): RedirectResponse
    {
        // Escudo Antifraude: Prohibido borrar evidencia.
        if ($scrutinyRecord->files()->exists() || $scrutinyRecord->blockResults()->exists() || $scrutinyRecord->extractions()->exists()) {
            return back()->with('error', 'Auditoría: Bloqueo Forense. Esta acta ya tiene imágenes adjuntas, extracciones de IA o resultados tabulados. Su eliminación está terminantemente prohibida. Cambie el estado a "Anulada" si es inválida.');
        }

        $scrutinyRecord->delete();
        return redirect()->route('admin.scrutiny-records.index')
            ->with('success', 'Borrador de acta eliminado correctamente.');
    }
}
