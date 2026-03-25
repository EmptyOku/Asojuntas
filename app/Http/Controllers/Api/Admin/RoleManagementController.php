<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with(['permissions:id,name,display_name'])
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }
}