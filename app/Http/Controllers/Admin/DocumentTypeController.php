<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class DocumentTypeController extends Controller
{
    /**
     * Lista los tipos de documento con métricas de uso.
     */
    public function index(Request $request): View
    {
        // Auditoría de Rendimiento: withCount extrae el total de personas asociadas
        // en una sola subconsulta optimizada en PostgreSQL, sin cargar los modelos a memoria.
        $query = DocumentType::withCount('persons');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $documentTypes = $query->orderBy('code')->paginate(20)->withQueryString();

        return view('admin.document_types.index', compact('documentTypes'));
    }

    /**
     * Muestra el formulario de creación.
     */
    public function create(): View
    {
        return view('admin.document_types.create');
    }

    /**
     * Almacena el tipo de documento con normalización estricta.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:20|unique:document_types,code',
            'name'        => 'required|string|max:100|unique:document_types,name',
            'description' => 'nullable|string|max:255',
        ]);

        // NORMALIZACIÓN OBLIGATORIA: El código siempre debe ir en mayúsculas
        // para que el script de Python (OCR) no falle al hacer el cruce.
        $validated['code'] = Str::upper($validated['code']);
        $validated['is_active'] = $request->has('is_active');

        DocumentType::create($validated);

        return redirect()->route('admin.document-types.index')
            ->with('success', 'Tipo de documento creado y normalizado correctamente.');
    }

    /**
     * Muestra el formulario de edición.
     */
    public function edit(DocumentType $documentType): View
    {
        return view('admin.document_types.edit', compact('documentType'));
    }

    /**
     * Actualiza el registro protegiendo las reglas de unicidad.
     */
    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:20|unique:document_types,code,' . $documentType->id,
            'name'        => 'required|string|max:100|unique:document_types,name,' . $documentType->id,
            'description' => 'nullable|string|max:255',
        ]);

        $validated['code'] = Str::upper($validated['code']);
        $validated['is_active'] = $request->has('is_active');

        $documentType->update($validated);

        return redirect()->route('admin.document-types.index')
            ->with('success', 'Tipo de documento actualizado.');
    }

    /**
     * Intenta eliminar el registro bajo estrictas validaciones de integridad.
     */
    public function destroy(DocumentType $documentType): RedirectResponse
    {
        // Capa de Defensa 1: Prevención a nivel de aplicación
        if ($documentType->persons()->exists()) {
            return back()->with('error', 'Auditoría: Riesgo de pérdida de datos. Este tipo de documento está siendo usado por ' . $documentType->persons()->count() . ' personas. Desactívelo en lugar de borrarlo.');
        }

        if ($documentType->candidateDrafts()->exists() || $documentType->scrutinyElectedPeople()->exists()) {
            return back()->with('error', 'Auditoría: Bloqueo legal. Este tipo de documento está atado a borradores del OCR o actas de escrutinio.');
        }

        // Capa de Defensa 2: Prevención a nivel de PostgreSQL
        try {
            $documentType->delete();
            return redirect()->route('admin.document-types.index')
                ->with('success', 'Tipo de documento eliminado.');

        } catch (QueryException $e) {
            if ($e->getCode() == "23503") {
                return back()->with('error', 'Auditoría: Violación de llave foránea detectada por la base de datos.');
            }
            throw $e;
        }
    }
}
