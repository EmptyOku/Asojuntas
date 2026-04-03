<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Neighborhood;
use App\Models\Commune;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class NeighborhoodController extends Controller
{
    /**
     * Lista los barrios con su jerarquía.
     * Es dual: Responde JSON para Vue, o View para Blade.
     */
    public function index(Request $request): View | JsonResponse
    {
        // 1. Si la petición viene de Vue (Axios)
        if ($request->wantsJson() || $request->ajax()) {
            // Cargamos la relación commune para que tu filtro en Vue funcione
            $neighborhoods = Neighborhood::with('commune')->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $neighborhoods
            ]);
        }

        // 2. Si la petición es normal (Blade web)
        $query = Neighborhood::with(['commune.city']);

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }

        $neighborhoods = $query->orderBy('name')->paginate(20)->withQueryString();
        $communes = Commune::orderBy('name')->get();

        return view('admin.neighborhoods.index', compact('neighborhoods', 'communes'));
    }

    /**
     * Formulario de creación de barrio.
     */
    public function create(): View
    {
        $communes = Commune::with('city')->orderBy('name')->get();
        return view('admin.neighborhoods.create', compact('communes'));
    }

    /**
     * Almacena el barrio con validación de contexto local.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'commune_id' => 'required|exists:communes,id',
            'name'       => 'required|string|max:150',
            // El código solo debe ser único DENTRO de la misma comuna.
            'code'       => [
                'required',
                'string',
                'max:50',
                Rule::unique('neighborhoods')->where(function ($query) use ($request) {
                    return $query->where('commune_id', $request->commune_id);
                }),
            ],
        ]);

        Neighborhood::create($validated);

        return redirect()->route('admin.neighborhoods.index')
            ->with('success', 'Barrio registrado exitosamente.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Neighborhood $neighborhood): View
    {
        $communes = Commune::with('city')->orderBy('name')->get();
        return view('admin.neighborhoods.edit', compact('neighborhood', 'communes'));
    }

    /**
     * Actualiza el barrio validando la unicidad del código.
     */
    public function update(Request $request, Neighborhood $neighborhood): RedirectResponse
    {
        $validated = $request->validate([
            'commune_id' => 'required|exists:communes,id',
            'name'       => 'required|string|max:150',
            'code'       => [
                'required',
                'string',
                'max:50',
                Rule::unique('neighborhoods')->where(function ($query) use ($request) {
                    return $query->where('commune_id', $request->commune_id);
                })->ignore($neighborhood->id),
            ],
        ]);

        $neighborhood->update($validated);

        return redirect()->route('admin.neighborhoods.index')
            ->with('success', 'Datos del barrio actualizados.');
    }

    /**
     * Elimina el barrio solo si no hay personas o elecciones vinculadas.
     */
    public function destroy(Neighborhood $neighborhood): RedirectResponse
    {
        // Capa de Seguridad 1: Personas empadronadas.
        if ($neighborhood->persons()->exists()) {
            return back()->with('error', 'Auditoría: Bloqueo de integridad. No se puede eliminar el barrio porque tiene ' . $neighborhood->persons()->count() . ' personas registradas en él.');
        }

        // Capa de Seguridad 2: Elecciones configuradas.
        if ($neighborhood->elections()->exists()) {
            return back()->with('error', 'Auditoría: Riesgo legal. Este barrio tiene procesos electorales históricos o activos vinculados.');
        }

        try {
            $neighborhood->delete();
            return redirect()->route('admin.neighborhoods.index')
                ->with('success', 'Barrio eliminado correctamente.');
        } catch (QueryException $e) {
            return back()->with('error', 'Error técnico de base de datos (Llave foránea).');
        }
    }

    /**
     * =========================================================================
     * EL MÉTODO QUE FALTABA: Muestra los resultados matemáticos del escrutinio
     * consumidos por tu vista NeighborhoodResultsView.vue
     * =========================================================================
     */
    public function show($id, Request $request)
    {

        // EL CHIVATO:
        dd("¡SÍ ESTOY ENTRANDO AL CONTROLADOR CORRECTO! El ID es: " . $id);

        $neighborhood = Neighborhood::findOrFail($id);

        // 1. Buscamos la elección activa
        $election = $neighborhood->elections()->where('is_active', true)->latest('election_date')->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'No hay elecciones activas para este barrio.',
                'data' => null
            ]);
        }

        // 2. Extraemos los bloques electorales que participaron
        $electionBlocks = \App\Models\ElectionBlock::with('block')
            ->where('election_id', $election->id)
            ->get();

        $resultadosFormateados = [];

        // 3. Iteramos por cada bloque (Directivos, Delegados, Fiscal)
        foreach ($electionBlocks as $eb) {

            // Buscamos las actas para este bloque
            $resultadosBloque = \App\Models\ScrutinyBlockResult::with('slateBlock.slate')
                ->where('election_id', $election->id)
                ->where('election_block_id', $eb->id)
                ->get();

            $votosPlanchas = [];
            $totalValidos = 0;

            // Agrupamos los votos por plancha
            foreach ($resultadosBloque as $resultado) {
                // Aceptamos votos 'approved' o 'reviewed'
                if ($resultado->status !== 'approved' && $resultado->status !== 'reviewed') continue;

                $nombrePlancha = $resultado->slateBlock->slate->name ?? 'Plancha Desconocida';

                $votosPlanchas[] = [
                    'plancha' => $nombrePlancha,
                    'votos' => $resultado->votes
                ];

                $totalValidos += $resultado->votes;
            }

            // Ordenamos para que la plancha ganadora salga de primera
            usort($votosPlanchas, function($a, $b) {
                return $b['votos'] <=> $a['votos'];
            });

            // 4. Armamos la estructura estricta que Vue está esperando
            if (count($votosPlanchas) > 0) {
                $resultadosFormateados[] = [
                    'nombre_bloque' => $eb->block->name,
                    'votos_planchas' => $votosPlanchas,
                    'estadisticas' => [
                        'validos' => $totalValidos,
                        'blancos' => 0,
                        'nulos' => 0
                    ]
                ];
            }
        }

        // 5. Retornamos el JSON con 'success' => true
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $neighborhood->id,
                'name' => $neighborhood->name,
                'resultados' => $resultadosFormateados
            ]
        ]);
    }
}
