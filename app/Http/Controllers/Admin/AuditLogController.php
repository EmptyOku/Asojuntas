<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * Muestra el historial de movimientos del sistema.
     * Diseñado para manejar grandes volúmenes de datos en tu VPS.
     */
    public function index(Request $request): View
    {
        // Aplicamos Eager Loading para evitar el problema de N+1 consultas.
        // Consultamos la relación 'user' y su 'person' para saber quién es el responsable.
        $query = AuditLog::with(['user.person'])->latest();

        // FILTRO: Por tipo de acción (Created, Updated, Deleted, Login, etc.)
        if ($request->filled('action')) {
            // Usamos ILIKE para PostgreSQL para que la búsqueda no distinga mayúsculas.
            $query->where('action', 'ilike', "%{$request->action}%");
        }

        // FILTRO: Por entidad afectada (Ej: App\Models\Candidate o App\Models\Person)
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        // FILTRO: Por ID específico del objeto (Ej: Buscar todo lo que le pasó al Candidato #5)
        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->auditable_id);
        }

        // FILTRO: Por rango de fechas (Indispensable para auditoría el día de la elección)
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Paginación obligatoria: No podemos cargar 10,000 logs de golpe en memoria RAM.
        $logs = $query->paginate(40)->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }

    /**
     * Detalle profundo de un cambio específico.
     * Muestra el "Antes" y "Después" procesando los JSON del modelo.
     */
    public function show(AuditLog $auditLog): View
    {
        // Cargamos la relación polimórfica para identificar el objeto afectado
        $auditLog->load('auditable');

        return view('admin.audit.show', compact('auditLog'));
    }
}
