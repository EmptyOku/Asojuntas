<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsolidationRun;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsolidationRunController extends Controller
{
    /**
     * Lista el historial de cálculos electorales (Runs).
     */
    public function index(Request $request): View
    {
        $query = ConsolidationRun::with(['election', 'createdByUser']);

        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Ordenamos por los más recientes primero
        $runs = $query->latest()->paginate(20)->withQueryString();
        $elections = Election::orderBy('name')->get();

        return view('admin.consolidation_runs.index', compact('runs', 'elections'));
    }

    /**
     * DISPARADOR: Inicia un nuevo cálculo de resultados.
     * No recibe datos de votos, solo la orden de calcular.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'election_id' => 'required|exists:elections,id',
            'run_type'    => 'required|string|in:preliminary,official,recount',
        ]);

        // Auditoría Preventiva: Evitar colisiones en la base de datos
        // Si ya hay un cálculo corriendo para esta elección, bloqueamos el intento.
        $isRunning = ConsolidationRun::where('election_id', $request->election_id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($isRunning) {
            return back()->with('error', 'Auditoría: Operación denegada. Ya existe un cálculo en proceso para esta elección. Espere a que termine para evitar duplicación de datos.');
        }

        // Creamos el registro dejando el rastro forense inmutable
        $run = ConsolidationRun::create([
            'election_id'        => $request->election_id,
            'created_by_user_id' => Auth::id(), // Registra quién apretó el botón
            'run_type'           => $request->run_type,
            'status'             => 'pending',
            'started_at'         => now(),
        ]);

        // =========================================================================
        // NOTA DEL AUDITOR: Aquí es donde, en el futuro, despacharás un Job (Cola).
        // Ej: CalculateElectionResultsJob::dispatch($run);
        // Por ahora, solo creamos el registro en estado 'pending'.
        // =========================================================================

        return redirect()->route('admin.consolidation-runs.show', $run)
            ->with('success', 'Motor de consolidación iniciado. El sistema está sumando los votos aprobados...');
    }

    /**
     * Muestra el detalle de una ejecución específica y sus resultados matemáticos.
     */
    public function show(ConsolidationRun $consolidationRun): View
    {
        // Deep Eager Loading para traer toda la jerarquía de ganadores sin ahogar el servidor
        $consolidationRun->load([
            'election',
            'createdByUser',
            'consolidatedBlockResults.electionBlock.block',
            'seatAllocations.candidate.person'
        ]);

        return view('admin.consolidation_runs.show', compact('consolidationRun'));
    }

    /**
     * Anula un cálculo mal hecho o de prueba.
     */
    public function destroy(ConsolidationRun $consolidationRun): RedirectResponse
    {
        // Regla de Negocio Estricta: Lo oficial no se borra.
        if ($consolidationRun->status === 'official') {
            return back()->with('error', 'Auditoría: Violación de protocolo. No se puede eliminar un escrutinio marcado como oficial. Debe realizar un nuevo cálculo de tipo "recount" (Recuento) o anular la elección.');
        }

        // Transacción de Base de Datos: Si falla borrar una curul, no se borra nada.
        DB::transaction(function () use ($consolidationRun) {
            // Borramos los hijos primero (Cascada manual para mayor seguridad en Laravel)
            $consolidationRun->seatAllocations()->delete();
            $consolidationRun->consolidatedBlockResults()->delete();
            // Borramos el padre
            $consolidationRun->delete();
        });

        return redirect()->route('admin.consolidation-runs.index')
            ->with('success', 'Ejecución de consolidación anulada y eliminada del historial.');
    }

    // AUDITORÍA: Se omiten 'create', 'edit' y 'update'.
    // Un cálculo no se edita a mano. Si está mal, se borra y se vuelve a correr.
}
