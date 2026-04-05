<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->with([
                'user.person:id,first_name,last_name',
            ]);

        if ($request->filled('action')) {
            $query->where('action', 'ilike', '%'.$request->string('action')->toString().'%');
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->string('auditable_type')->toString());
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', (int) $request->input('auditable_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->date('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->date('to_date'));
        }

        $perPage = max(5, min(100, (int) $request->integer('per_page', 25)));

        $logs = $query
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (AuditLog $log): array {
                $userName = $log->user?->person
                    ? trim(($log->user->person->first_name ?? '').' '.($log->user->person->last_name ?? ''))
                    : null;

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'auditable_type' => $log->auditable_type,
                    'auditable_id' => $log->auditable_id,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'metadata' => $log->metadata,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'created_at_human' => $log->created_at?->diffForHumans(),
                    'user' => [
                        'id' => $log->user?->id,
                        'username' => $log->user?->username,
                        'name' => $userName !== '' ? $userName : ($log->user?->username ?? 'Sin usuario'),
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'records' => $logs,
            ],
        ]);
    }
}
