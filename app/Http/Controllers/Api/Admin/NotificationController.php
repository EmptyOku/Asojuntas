<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Campanita del encabezado: avisos de lo que otros roles hicieron
 * (actas recibidas, planchas registradas). Ver App\Services\AdminNotifications.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly AdminNotifications $notifications) {}

    /** Solo el número de no vistos: es lo que la campana consulta cada minuto. */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['unread' => $this->notifications->unreadCount($request->user())],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:5|max:20',
        ]);

        $page = $this->notifications->page($request->user(), (int) ($validated['per_page'] ?? 8));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $page?->items() ?? [],
                'current_page' => $page?->currentPage() ?? 1,
                'has_more' => $page?->hasMorePages() ?? false,
            ],
        ]);
    }

    public function markSeen(Request $request): JsonResponse
    {
        $validated = $request->validate(['up_to_id' => 'sometimes|integer|min:0']);

        $this->notifications->markSeen($request->user(), (int) ($validated['up_to_id'] ?? 0));

        return response()->json(['success' => true, 'data' => ['unread' => 0]]);
    }
}
